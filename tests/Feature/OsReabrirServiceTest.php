<?php

namespace Tests\Feature;

use App\Filament\Resources\OrdemServicoResource\Pages\ListOrdensServico;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OsFaturamentoService;
use App\Support\Erp\Os\OsReabrirService;
use DomainException;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class OsReabrirServiceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_reabrir_estorna_caixa_contas_e_estoque_do_faturamento(): void
    {
        [$os, $peca] = $this->osFaturavel();
        $estoqueAntes = (float) $peca->fresh()->estoque;

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '40.00'],
            ['id' => $this->forma('PRAZO OS', 'crediario', 'contas_receber')->id, 'valor' => '60.00'],
        ]);

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);
        $this->assertSame(1, ContaReceber::query()->count());
        $this->assertEqualsWithDelta($estoqueAntes - 2, (float) $peca->fresh()->estoque, 0.001);

        app(OsReabrirService::class)->reabrir($os);

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_ABERTA, $os->situacao);
        $this->assertNull($os->data_termino);
        $this->assertSame(0, ContaReceber::query()->count());
        $this->assertEqualsWithDelta($estoqueAntes, (float) $peca->fresh()->estoque, 0.001);

        $caixa = CaixaLancamento::query()->where('documento', 'OS-'.$os->numero)->get();
        $this->assertEqualsWithDelta(0.0, (float) $caixa->sum('entrada') - (float) $caixa->sum('saida'), 0.001);

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => FormaPagamento::query()->where('descricao', 'DINHEIRO OS')->value('id'), 'valor' => '100.00'],
        ]);
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
    }

    public function test_bloqueia_reabrir_com_nfse_autorizada_e_libera_quando_cancelada(): void
    {
        [$os] = $this->osFaturavel();
        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '100.00'],
        ]);
        $os->refresh();

        $nfse = $this->nfse($os, Nfse::STATUS_AUTORIZADA);

        try {
            app(OsReabrirService::class)->reabrir($os);
            $this->fail('Deveria bloquear a reabertura com NFS-e autorizada.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('NFS-e emitida (nº 15)', $exception->getMessage());
        }

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);

        $nfse->forceFill(['status' => Nfse::STATUS_CANCELADA])->save();
        app(OsReabrirService::class)->reabrir($os);

        $this->assertSame(OrdemServico::SITUACAO_ABERTA, $os->fresh()->situacao);
    }

    public function test_os_aberta_nao_pode_ser_reaberta(): void
    {
        [$os] = $this->osFaturavel();

        $this->assertSame('Só é possível reabrir OS finalizada.', app(OsReabrirService::class)->motivoBloqueio($os));
    }

    public function test_cancelar_os_aberta_so_muda_a_situacao(): void
    {
        [$os, $peca] = $this->osFaturavel();

        app(OsReabrirService::class)->cancelar($os);

        $this->assertSame(OrdemServico::SITUACAO_CANCELADA, $os->fresh()->situacao);
        $this->assertEqualsWithDelta(10.0, (float) $peca->fresh()->estoque, 0.001);
        $this->assertSame(0, CaixaLancamento::query()->count());
        $this->assertDatabaseHas('erp_operacao_logs', [
            'operacao' => OsReabrirService::OPERACAO_CANCELAR,
            'documento_id' => $os->id,
        ]);
    }

    public function test_cancelar_os_faturada_estorna_uma_vez_e_fica_cancelada(): void
    {
        [$os, $peca] = $this->osFaturavel();

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '40.00'],
            ['id' => $this->forma('PRAZO OS', 'crediario', 'contas_receber')->id, 'valor' => '60.00'],
        ]);
        $os->refresh();

        app(OsReabrirService::class)->cancelar($os);

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_CANCELADA, $os->situacao);
        $this->assertNull($os->data_termino);
        $this->assertNull($os->faturamento_pagamentos);
        $this->assertSame(0, ContaReceber::query()->count());
        $this->assertEqualsWithDelta(10.0, (float) $peca->fresh()->estoque, 0.001);

        $caixa = CaixaLancamento::query()->where('documento', 'OS-'.$os->numero)->get();
        $saidas = $caixa->where('saida', '>', 0)->count();
        $this->assertGreaterThan(0, $saidas);
        $this->assertEqualsWithDelta(0.0, (float) $caixa->sum('entrada') - (float) $caixa->sum('saida'), 0.001);

        try {
            app(OsReabrirService::class)->cancelar($os);
            $this->fail('Segundo cancelamento não deveria passar.');
        } catch (DomainException $exception) {
            $this->assertSame('OS já está cancelada.', $exception->getMessage());
        }

        $this->assertSame($saidas, CaixaLancamento::query()->where('documento', 'OS-'.$os->numero)->where('saida', '>', 0)->count());
        $this->assertEqualsWithDelta(10.0, (float) $peca->fresh()->estoque, 0.001);
    }

    public function test_cancelar_bloqueia_com_nfse_autorizada(): void
    {
        [$os] = $this->osFaturavel();
        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '100.00'],
        ]);
        $os->refresh();
        $this->nfse($os, Nfse::STATUS_AUTORIZADA);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cancele a NFS-e antes de cancelar a OS.');

        try {
            app(OsReabrirService::class)->cancelar($os);
        } finally {
            $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
        }
    }

    public function test_f4_so_cancela_depois_da_confirmacao(): void
    {
        [$os, $peca] = $this->osFaturavel();
        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '100.00'],
        ]);

        $lw = Livewire::test(ListOrdensServico::class)
            ->set('highlightedRecordId', $os->id)
            ->call('confirmarAcaoOs');

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);

        $lw->call('cancelOrdem')
            ->assertSet('osConfirmAcao', 'cancelar')
            ->assertSet('osConfirmId', $os->id)
            ->assertSee('Confirmação')
            ->assertSee('Esta OS já foi faturada. Ao cancelar, serão estornados financeiro, caixa e estoque. Deseja continuar?');

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);

        $lw->call('fecharConfirmacaoOs')
            ->assertSet('osConfirmAcao', null)
            ->call('confirmarAcaoOs');

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);

        $lw->call('cancelOrdem')
            ->call('confirmarAcaoOs')
            ->assertSet('osConfirmAcao', null);

        $this->assertSame(OrdemServico::SITUACAO_CANCELADA, $os->fresh()->situacao);
        $this->assertEqualsWithDelta(10.0, (float) $peca->fresh()->estoque, 0.001);
    }

    public function test_f4_em_os_aberta_pede_confirmacao_simples(): void
    {
        [$os] = $this->osFaturavel();

        Livewire::test(ListOrdensServico::class)
            ->set('highlightedRecordId', $os->id)
            ->call('cancelOrdem')
            ->assertSee('Deseja realmente cancelar esta OS?');

        $this->assertSame(OrdemServico::SITUACAO_ABERTA, $os->fresh()->situacao);
    }

    public function test_f8_reabre_somente_depois_do_sim_no_modal(): void
    {
        [$os] = $this->osFaturavel();
        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '100.00'],
        ]);

        $lw = Livewire::test(ListOrdensServico::class)
            ->set('highlightedRecordId', $os->id)
            ->call('reabrirOrdem')
            ->assertSet('osConfirmAcao', 'reabrir')
            ->assertSee('Reabrir esta OS?');

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);

        $lw->call('confirmarAcaoOs');

        $this->assertSame(OrdemServico::SITUACAO_ABERTA, $os->fresh()->situacao);
    }

    public function test_alterar_fica_bloqueado_para_os_finalizada(): void
    {
        [$os] = $this->osFaturavel();

        $aberta = Livewire::test(ListOrdensServico::class)
            ->set('highlightedRecordId', $os->id)
            ->assertSeeHtml('data-erp-disable-on-row="erp-os-row--faturada"');

        $this->assertMatchesRegularExpression('/data-erp-key="F7"[^>]*disabled/s', $aberta->html());
        $this->assertMatchesRegularExpression('/data-erp-key="F8"[^>]*disabled/s', $aberta->html());
        $this->assertDoesNotMatchRegularExpression('/data-erp-key="F3"[^>]*disabled/s', $aberta->html());

        $aberta->call('editOrdem')->assertRedirect();

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $this->forma('DINHEIRO OS', 'dinheiro', 'caixa')->id, 'valor' => '100.00'],
        ]);

        $lw = Livewire::test(ListOrdensServico::class)
            ->set('highlightedRecordId', $os->id)
            ->call('editOrdem')
            ->assertNoRedirect()
            ->assertNotified('OS finalizada não pode ser alterada.');

        $this->assertFalse($lw->instance()->podeAlterarOsSelecionada());
        $this->assertMatchesRegularExpression('/data-erp-key="F3"[^>]*disabled/s', $lw->html());
        $this->assertDoesNotMatchRegularExpression('/data-erp-key="F7"[^>]*disabled/s', $lw->html());
        $this->assertDoesNotMatchRegularExpression('/data-erp-key="F8"[^>]*disabled/s', $lw->html());
    }

    public function test_confirmacao_nao_aceita_acao_vinda_do_navegador(): void
    {
        [$os] = $this->osFaturavel();

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(ListOrdensServico::class)
            ->set('osConfirmId', $os->id);
    }

    private function nfse(OrdemServico $os, string $status): Nfse
    {
        $nfse = new Nfse();
        $nfse->forceFill([
            'empresa_id' => $os->empresa_id,
            'ordem_servico_id' => $os->id,
            'status' => $status,
            'numero_dps' => 1,
            'numero_nfse' => '15',
            'tomador_nome' => 'CLIENTE OS',
            'competencia' => '2026-10-06',
            'data_emissao' => '2026-10-06',
            'serie_dps' => '1',
        ])->save();

        return $nfse;
    }

    private function forma(string $descricao, string $tipo, string $movimento): FormaPagamento
    {
        $forma = new FormaPagamento();
        $forma->forceFill([
            'codigo' => (int) FormaPagamento::query()->max('codigo') + 1,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'tipo_movimento' => $movimento,
            'ativo' => true,
        ])->save();

        return $forma;
    }

    /**
     * @return array{0: OrdemServico, 1: Product}
     */
    private function osFaturavel(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA OS',
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'C-OS-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $peca = Product::query()->create([
            'codigo' => 'PECA1',
            'descricao' => 'PECA OS',
            'unidade' => 'UN',
            'preco_venda' => 20,
            'estoque' => 10,
            'ativo' => true,
        ]);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => 321,
            'situacao' => OrdemServico::SITUACAO_ABERTA,
            'cliente_id' => $cliente->id,
            'total_geral' => 100,
        ])->save();

        foreach ([['P', $peca->id, 2, 20], ['S', null, 1, 60]] as [$tipo, $productId, $qtd, $preco]) {
            $item = new OrdemServicoItem();
            $item->forceFill([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $empresa->id,
                'tipo' => $tipo,
                'product_id' => $productId,
                'qtd' => $qtd,
                'preco' => $preco,
                'total' => $qtd * $preco,
            ])->save();
        }

        return [$os->fresh(), $peca];
    }
}
