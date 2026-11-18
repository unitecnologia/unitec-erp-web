<?php

namespace Tests\Feature;

use App\Filament\Resources\ExtratoLancamentoResource\Pages\ListExtratoLancamentos;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoConciliacao;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoCandidatosQuery;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualException;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualService;
use App\Support\Erp\Financeiro\Conciliacao\PixIdentificadorOfx;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ConciliacaoManualC3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_concilia_1_para_1_sem_alterar_livro_caixa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '150.00', '2026-10-10', 'FIT-C3-1');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 150.00, saida: 0, emissao: '2026-10-10', historico: 'Recebimento conta a receber #99');

        $attrsAntes = $caixa->fresh()->getAttributes();

        $result = app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);

        $extrato->refresh();
        $caixa->refresh();
        $conc = ExtratoConciliacao::query()->findOrFail($result['conciliacao_id']);

        $this->assertSame(ExtratoLancamento::SITUACAO_CONCILIADO, $extrato->situacao);
        $this->assertSame((int) $caixa->id, (int) $extrato->caixa_lancamento_id);
        $this->assertSame('caixa_lancamento', $extrato->origem_tipo);
        $this->assertSame(ExtratoConciliacao::STATUS_ATIVA, $conc->status);
        $this->assertSame($attrsAntes, $caixa->getAttributes());
        $this->assertSame(1, ExtratoConciliacao::query()->count());
        $this->assertSame(1, CaixaLancamento::query()->count());
    }

    public function test_rejeita_valor_diferente(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '100.00', '2026-10-10', 'FIT-V');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 99.00, saida: 0, emissao: '2026-10-10');

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('Valor divergente');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);
    }

    public function test_rejeita_conta_diferente(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $outro = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9202,
            'nome' => 'OUTRO BANCO',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);

        $extrato = $this->criarExtratoPendente($empresa, $banco, 'debito', '40.00', '2026-10-11', 'FIT-C');
        $caixa = $this->criarCaixa($empresa, $outro, entrada: 0, saida: 40.00, emissao: '2026-10-11');

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('Conta bancária diferente');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);
    }

    public function test_rejeita_outra_empresa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $outra = Empresa::query()->create([
            'codigo' => 'OUT',
            'nome' => 'OUTRA',
            'fantasia' => 'OUT',
            'ativo' => true,
        ]);

        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '10.00', '2026-10-12', 'FIT-E');
        $caixa = $this->criarCaixa($outra, $banco, entrada: 10.00, saida: 0, emissao: '2026-10-12');

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('outra empresa');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);
    }

    public function test_rejeita_ja_conciliado(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '25.00', '2026-10-13', 'FIT-J');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 25.00, saida: 0, emissao: '2026-10-13');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);

        $caixa2 = $this->criarCaixa($empresa, $banco, entrada: 25.00, saida: 0, emissao: '2026-10-13', historico: 'outro');

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('já conciliado');

        app(ConciliacaoManualService::class)->conciliar($extrato->fresh(), $caixa2, (int) $admin->id, (int) $empresa->id);
    }

    public function test_concorrencia_segundo_usuario_falha(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $user2 = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '70.00', '2026-10-14', 'FIT-X');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 70.00, saida: 0, emissao: '2026-10-14');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);

        $extratoB = $this->criarExtratoPendente($empresa, $banco, 'credito', '70.00', '2026-10-14', 'FIT-Y');

        try {
            app(ConciliacaoManualService::class)->conciliar($extratoB, $caixa->fresh(), (int) $user2->id, (int) $empresa->id);
            $this->fail('Esperava falha de concorrência');
        } catch (ConciliacaoManualException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'já conciliado')
                || str_contains($e->getMessage(), 'concorrente')
                || str_contains($e->getMessage(), 'já vinculado')
            );
        }

        $this->assertSame(1, ExtratoConciliacao::query()->where('status', ExtratoConciliacao::STATUS_ATIVA)->count());
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extratoB->fresh()->situacao);
    }

    public function test_sem_candidato_extrato_permanece_pendente(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '33.33', '2026-10-15', 'FIT-N');
        $this->criarCaixa($empresa, $banco, entrada: 10.00, saida: 0, emissao: '2026-10-15');

        $page = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, 1);
        $this->assertSame(0, $page->total());
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(0, ExtratoConciliacao::query()->count());
    }

    public function test_varios_candidatos_aparecem_para_escolha(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'debito', '12.00', '2026-10-16', 'FIT-M');
        $this->criarCaixa($empresa, $banco, entrada: 0, saida: 12.00, emissao: '2026-10-16', historico: 'A');
        $this->criarCaixa($empresa, $banco, entrada: 0, saida: 12.00, emissao: '2026-10-15', historico: 'B');

        $page = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, 1);
        $this->assertSame(2, $page->total());
    }

    public function test_reversao_preserva_auditoria_e_livro_caixa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '80.00', '2026-10-17', 'FIT-R');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 80.00, saida: 0, emissao: '2026-10-17', historico: 'PIX OK');
        $attrsAntes = $caixa->fresh()->getAttributes();

        $ok = app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);
        app(ConciliacaoManualService::class)->reverter(
            $extrato->fresh(),
            (int) $admin->id,
            'Teste reversão',
            (int) $empresa->id,
        );

        $extrato->refresh();
        $caixa->refresh();
        $conc = ExtratoConciliacao::query()->findOrFail($ok['conciliacao_id']);

        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->situacao);
        $this->assertNull($extrato->caixa_lancamento_id);
        $this->assertSame(ExtratoConciliacao::STATUS_REVERTIDA, $conc->status);
        $this->assertNotNull($conc->revertido_em);
        $this->assertSame('Teste reversão', $conc->motivo_reversao);
        $this->assertNull($conc->lock_extrato);
        $this->assertSame($attrsAntes, $caixa->getAttributes());
    }

    public function test_confirmar_sem_candidato_selecionado_nao_vincula(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '15.00', '2026-10-21', 'FIT-NOSSEL');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 15.00, saida: 0, emissao: '2026-10-21');

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-10-01')
            ->set('periodoAte', '2026-10-31')
            ->set('situacao', 'pendente')
            ->call('highlightRecord', $extrato->id)
            ->call('openConciliarModal')
            ->assertSet('conciliarModalOpen', true)
            ->assertSet('conciliarCandidatosTotal', 1)
            ->assertSet('conciliarCandidatoId', null)
            ->call('confirmarConciliacaoManual')
            ->assertSet('conciliarModalOpen', true);

        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(0, ExtratoConciliacao::query()->count());
        $this->assertSame(1, CaixaLancamento::query()->count());
        $this->assertSame((int) $caixa->id, (int) CaixaLancamento::query()->value('id'));
    }

    public function test_confirmar_sem_correspondencia_mantem_pendente(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'debito', '1084.90', '2026-10-30', 'FIT-EMPTY');

        $lw = Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-10-01')
            ->set('periodoAte', '2026-10-31')
            ->set('situacao', 'pendente')
            ->call('highlightRecord', $extrato->id)
            ->call('openConciliarModal')
            ->assertSet('conciliarCandidatosTotal', 0)
            ->assertSet('conciliarModalOpen', true);

        $this->assertFalse($lw->instance()->podeConfirmarConciliacaoManual());
        $lw->call('confirmarConciliacaoManual');

        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(0, ExtratoConciliacao::query()->count());
    }

    public function test_usuario_sem_permissao_nao_concilia_pela_ui(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $operador = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        ErpAccess::syncUserPermissions($operador, ['conciliacao_bancaria.access']);
        $this->actingAs($operador);

        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '5.00', '2026-10-18', 'FIT-P');
        $this->criarCaixa($empresa, $banco, entrada: 5.00, saida: 0, emissao: '2026-10-18');

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-10-01')
            ->set('periodoAte', '2026-10-31')
            ->set('situacao', 'pendente')
            ->call('highlightRecord', $extrato->id)
            ->call('openConciliarModal')
            ->assertSet('conciliarModalOpen', false);

        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
    }

    public function test_livewire_concilia_e_desfaz(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '9.50', '2026-10-19', 'FIT-LW');
        $caixa = $this->criarCaixa($empresa, $banco, entrada: 9.50, saida: 0, emissao: '2026-10-19', historico: 'Recebimento conta a receber #1');

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-10-01')
            ->set('periodoAte', '2026-10-31')
            ->set('situacao', 'todos')
            ->call('highlightRecord', $extrato->id)
            ->call('openConciliarModal')
            ->assertSet('conciliarModalOpen', true)
            ->assertSet('conciliarCandidatosTotal', 1)
            ->set('conciliarCandidatoId', (int) $caixa->id)
            ->call('confirmarConciliacaoManual')
            ->assertSet('conciliarModalOpen', false);

        $this->assertSame(ExtratoLancamento::SITUACAO_CONCILIADO, $extrato->fresh()->situacao);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-10-01')
            ->set('periodoAte', '2026-10-31')
            ->set('situacao', 'conciliado')
            ->call('highlightRecord', $extrato->id)
            ->call('openDesfazerConfirm')
            ->assertSet('desfazerConfirmOpen', true)
            ->call('confirmDesfazerConciliacao')
            ->assertSet('desfazerConfirmOpen', false);

        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(1, ExtratoConciliacao::query()->where('status', ExtratoConciliacao::STATUS_REVERTIDA)->count());
        $this->assertSame(1, CaixaLancamento::query()->count());
    }

    public function test_pix_sem_txid_nao_confirma_mesmo_valor_e_data(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            '96E259774A94383687F1632F6FB09ACB',
            'CREDITO PIX - ALENCAR DE OLIVEIRA',
        );
        $caixaA = $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #8 PIX AILOSPDV501M75EF9ADEA666C5D1EE13B',
        );
        $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #9 PIX AILOSPDV501M7877BE5CC530F9D5A1EAE',
        );

        $page = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, 1);
        $this->assertSame(2, $page->total());
        $this->assertSame(
            PixIdentificadorOfx::MATCH_FRACA,
            PixIdentificadorOfx::classificarMatch($extrato, $caixaA),
        );

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('Confirme a identificação não comprovada');

        app(ConciliacaoManualService::class)->conciliar($extrato, $caixaA, (int) $admin->id, (int) $empresa->id);
    }

    public function test_pix_fraco_com_aceite_grava_auditoria_sem_duplicar(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            '96E259774A94383687F1632F6FB09ACB',
            'CREDITO PIX - ALENCAR DE OLIVEIRA',
        );
        $caixa = $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #8 PIX AILOSPDV501M75EF9ADEA666C5D1EE13B',
        );
        $attrsAntes = $caixa->fresh()->getAttributes();

        $result = app(ConciliacaoManualService::class)->conciliar(
            $extrato,
            $caixa,
            (int) $admin->id,
            (int) $empresa->id,
            true,
        );

        $this->assertSame(PixIdentificadorOfx::MATCH_FRACA, $result['identificacao']);
        $this->assertSame(ExtratoLancamento::SITUACAO_CONCILIADO, $extrato->fresh()->situacao);
        $this->assertSame($attrsAntes, $caixa->fresh()->getAttributes());

        $conc = ExtratoConciliacao::query()->findOrFail($result['conciliacao_id']);
        $this->assertSame(ExtratoConciliacao::IDENTIFICACAO_FRACA, $conc->identificacao);
        $this->assertSame((int) $admin->id, (int) $conc->conciliado_por_user_id);
        $this->assertNotNull($conc->conciliado_em);
        $this->assertNotEmpty($conc->observacao);
        $this->assertStringContainsString('Identificação não comprovada', (string) $conc->observacao);

        $outro = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            '36B1918BD9D6483DF470A309F67DD20B',
            'CREDITO PIX - OUTRO',
        );

        $this->expectException(ConciliacaoManualException::class);
        app(ConciliacaoManualService::class)->conciliar($outro, $caixa->fresh(), (int) $admin->id, (int) $empresa->id, true);
    }

    public function test_pix_com_txid_comum_confirma_e_nao_duplica_caixa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $txid = 'AILOSPDV501M75EF9ADEA666C5D1EE13B';
        $extrato = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            '36B1918BD9D6483DF470A309F67DD20B',
            'CREDITO PIX '.$txid,
        );
        $caixaOk = $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #8 PIX '.$txid,
        );
        $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #9 PIX AILOSPDV501M7877BE5CC530F9D5A1EAE',
        );

        $page = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, 1);
        $this->assertSame(1, $page->total());
        $this->assertSame((int) $caixaOk->id, (int) $page->getCollection()->first()->id);

        $attrsAntes = $caixaOk->fresh()->getAttributes();
        app(ConciliacaoManualService::class)->conciliar($extrato, $caixaOk, (int) $admin->id, (int) $empresa->id);

        $this->assertSame(ExtratoLancamento::SITUACAO_CONCILIADO, $extrato->fresh()->situacao);
        $this->assertSame($attrsAntes, $caixaOk->fresh()->getAttributes());
        $this->assertSame(2, CaixaLancamento::query()->count());
        $this->assertSame(1, ExtratoConciliacao::query()->where('status', ExtratoConciliacao::STATUS_ATIVA)->count());
    }

    public function test_livewire_pix_fraco_exige_segunda_confirmacao(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            '96E259774A94383687F1632F6FB09ACB',
            'CREDITO PIX - ALENCAR DE OLIVEIRA',
        );
        $caixa = $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #8 PIX AILOSPDV501M75EF9ADEA666C5D1EE13B',
        );

        $lw = Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->set('situacao', 'pendente')
            ->call('highlightRecord', $extrato->id)
            ->call('openConciliarModal')
            ->assertSet('conciliarModalOpen', true)
            ->assertSet('conciliarCandidatosTotal', 1)
            ->assertSet('conciliarFracaConfirmOpen', false)
            ->set('conciliarCandidatoId', (int) $caixa->id)
            ->call('confirmarConciliacaoManual')
            ->assertSet('conciliarModalOpen', true)
            ->assertSet('conciliarFracaConfirmOpen', true);

        $this->assertTrue($lw->instance()->podeConfirmarConciliacaoManual());
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(0, ExtratoConciliacao::query()->count());

        $lw->call('confirmarConciliacaoFracaAssinada')
            ->assertSet('conciliarModalOpen', false)
            ->assertSet('conciliarFracaConfirmOpen', false);

        $this->assertSame(ExtratoLancamento::SITUACAO_CONCILIADO, $extrato->fresh()->situacao);
        $conc = ExtratoConciliacao::query()->first();
        $this->assertNotNull($conc);
        $this->assertSame(ExtratoConciliacao::IDENTIFICACAO_FRACA, $conc->identificacao);
        $this->assertSame((int) $admin->id, (int) $conc->conciliado_por_user_id);
        $this->assertSame(1, CaixaLancamento::query()->count());
    }

    public function test_fitid_nao_e_usado_como_txid(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $fitid = '96E259774A94383687F1632F6FB09ACB';
        $extrato = $this->criarExtratoPendente(
            $empresa,
            $banco,
            'credito',
            '1.00',
            '2026-11-16',
            $fitid,
            'CREDITO PIX - ALENCAR DE OLIVEIRA',
        );
        $caixa = $this->criarCaixa(
            $empresa,
            $banco,
            entrada: 1.00,
            saida: 0,
            emissao: '2026-11-16',
            historico: 'VENDA PDV #8 PIX '.$fitid,
        );

        $this->assertSame([], PixIdentificadorOfx::extrairIdentificadoresDoExtrato($extrato));
        $this->assertSame(PixIdentificadorOfx::MATCH_FRACA, PixIdentificadorOfx::classificarMatch($extrato, $caixa));

        $this->expectException(ConciliacaoManualException::class);
        app(ConciliacaoManualService::class)->conciliar($extrato, $caixa, (int) $admin->id, (int) $empresa->id);
    }

    public function test_candidato_nao_lista_titulo_aberto_sem_caixa(): void
    {
        // Garantia de escopo: candidatos vêm só de caixa_lancamentos (não de CR aberto).
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $extrato = $this->criarExtratoPendente($empresa, $banco, 'credito', '200.00', '2026-10-20', 'FIT-CR');

        $page = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, 1);
        $this->assertSame(0, $page->total());
        $this->assertFalse(Schema::hasTable('contas_receber'));
    }

    /**
     * @return array{0: Empresa, 1: User, 2: CaixaConta}
     */
    private function seedEmpresaAdminEContaBanco(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'C3X',
            'nome' => 'EMPRESA C3',
            'fantasia' => 'C3',
            'ativo' => true,
        ]);

        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $banco = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9301,
            'nome' => 'BANCO C3',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
            'banco_codigo' => '085',
            'agencia' => '0109',
            'conta_numero' => '48346',
        ]);

        return [$empresa, $admin, $banco];
    }

    private function criarExtratoPendente(
        Empresa $empresa,
        CaixaConta $banco,
        string $tipo,
        string $valor,
        string $data,
        string $fitid,
        ?string $historico = null,
    ): ExtratoLancamento {
        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'c3.ofx',
            'arquivo_sha256' => hash('sha256', $fitid.microtime(true)),
            'formato' => 'ofx',
            'status' => 'concluida',
            'total_linhas' => 1,
            'total_creditos' => $tipo === 'credito' ? $valor : 0,
            'total_debitos' => $tipo === 'debito' ? $valor : 0,
            'imported_at' => now(),
        ]);

        return ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => $data,
            'data_posted' => $data,
            'tipo' => $tipo,
            'valor' => $valor,
            'historico' => $historico ?? ('OFX '.$fitid),
            'fitid' => $fitid,
            'fingerprint' => ExtratoLancamento::buildFingerprint(
                $fitid,
                $data,
                $tipo,
                $valor,
                $historico ?? ('OFX '.$fitid),
                null,
                1,
                null,
                null,
                null,
                (int) $empresa->id,
                (int) $banco->id,
            ),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
            'origem_tipo' => 'ofx',
        ]);
    }

    private function criarCaixa(
        Empresa $empresa,
        CaixaConta $conta,
        float $entrada,
        float $saida,
        string $emissao,
        string $historico = 'LANC C3',
    ): CaixaLancamento {
        return CaixaLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $emissao,
            'documento' => 'MANUAL-C3',
            'historico' => $historico,
            'caixa_conta_id' => $conta->id,
            'entrada' => $entrada,
            'saida' => $saida,
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('extrato_conciliacoes');
        Schema::dropIfExists('extrato_lancamentos');
        Schema::dropIfExists('extrato_importacoes');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->string('fantasia', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission_key', 120);
            $table->timestamps();
            $table->unique(['user_id', 'permission_key']);
        });

        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->string('banco_codigo', 10)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('agencia_dv', 4)->nullable();
            $table->string('conta_numero', 30)->nullable();
            $table->string('conta_dv', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 500)->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('extrato_importacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_sha256', 64);
            $table->string('formato', 16)->default('ofx');
            $table->string('status', 20)->default('pendente');
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->decimal('total_creditos', 14, 2)->default(0);
            $table->decimal('total_debitos', 14, 2)->default(0);
            $table->unsignedBigInteger('imported_by_user_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('extrato_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('extrato_importacao_id');
            $table->date('data_movimento');
            $table->date('data_posted')->nullable();
            $table->string('tipo', 16)->default('outro');
            $table->decimal('valor', 14, 2);
            $table->string('historico', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('payee', 255)->nullable();
            $table->string('check_num', 40)->nullable();
            $table->string('ref_num', 80)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->char('fingerprint', 64);
            $table->string('situacao', 20)->default('pendente');
            $table->boolean('ambiguo')->default(false);
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'fingerprint'], 'extrato_lanc_emp_conta_fp_uq');
        });

        Schema::create('extrato_conciliacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('extrato_lancamento_id');
            $table->unsignedBigInteger('caixa_lancamento_id');
            $table->string('status', 20)->default('ativa');
            $table->string('identificacao', 20)->nullable();
            $table->string('tipo_extrato', 16);
            $table->decimal('valor_extrato', 14, 2);
            $table->decimal('valor_caixa', 14, 2);
            $table->date('data_movimento_extrato');
            $table->date('data_emissao_caixa')->nullable();
            $table->string('historico_extrato', 500)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->string('documento_caixa', 40)->nullable();
            $table->string('historico_caixa', 500)->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('revertido_por_user_id')->nullable();
            $table->timestamp('revertido_em')->nullable();
            $table->string('motivo_reversao', 255)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->string('lock_extrato', 32)->nullable()->unique();
            $table->string('lock_caixa', 32)->nullable()->unique();
            $table->timestamps();
        });
    }
}
