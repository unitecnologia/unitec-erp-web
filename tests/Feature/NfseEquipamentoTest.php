<?php

namespace Tests\Feature;

use App\Filament\Pages\NfsePage;
use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OsVeiculo;
use App\Models\Person;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfse\NfseGravarService;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfseEquipamentoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_gravar_salva_equipamento_e_regravar_sem_equipamento_preserva(): void
    {
        [$empresa, $tomador] = $this->contexto();
        $service = app(NfseGravarService::class);

        $nfse = $service->gravar((int) $empresa->id, null, $this->cabecalho($tomador, [
            'placa' => 'ABC1D23',
            'descricao' => 'FIAT',
            'modelo' => 'FIORINO',
            'km' => 15000,
            'cor_veiculo' => 'BRANCO',
        ]), $this->itens());

        $this->assertSame('ABC1D23', $nfse->placa);
        $this->assertSame('FIAT', $nfse->descricao);
        $this->assertSame('FIORINO', $nfse->modelo);
        $this->assertSame(15000, (int) $nfse->km);
        $this->assertSame('BRANCO', $nfse->cor_veiculo);

        $service->gravar((int) $empresa->id, (int) $nfse->id, $this->cabecalho($tomador), $this->itens());

        $this->assertSame('ABC1D23', $nfse->fresh()->placa);
    }

    public function test_tela_abre_modal_de_equipamento_e_esc_fecha_so_o_modal(): void
    {
        $this->contexto();

        Livewire::test(NfsePage::class)
            ->set('nfseModalOpen', true)
            ->call('abrirNfseEquipamento')
            ->assertSet('nfseEquipamentoModalOpen', true)
            ->assertSee('Consultar placa')
            ->assertSee('Equipamento / Marca')
            ->call('closeNfseModal')
            ->assertSet('nfseEquipamentoModalOpen', false)
            ->assertSet('nfseModalOpen', true);
    }

    public function test_equipamento_vai_para_os_veiculos_igual_ao_orcamento(): void
    {
        [$empresa] = $this->contexto();

        $lw = Livewire::test(NfsePage::class)
            ->set('placa', 'abc1d23')
            ->set('descricao', 'FIAT')
            ->set('modelo', 'fiorino')
            ->set('km', '12.500');

        $atributos = $this->chamar($lw->instance(), 'atributosEquipamentoNfse');

        $veiculo = OsVeiculo::query()->where('empresa_id', $empresa->id)->where('placa', 'ABC1D23')->sole();
        $this->assertSame((int) $veiculo->id, $atributos['os_veiculo_id']);
        $this->assertSame('ABC1D23', $atributos['placa']);
        $this->assertSame('FIORINO', $atributos['modelo']);
        $this->assertSame(12500, $atributos['km']);
    }

    public function test_nota_da_os_sem_equipamento_proprio_mostra_o_da_os(): void
    {
        [$empresa, $tomador] = $this->contexto();

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => 77,
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'cliente_id' => $tomador->id,
            'placa' => 'LZK6311',
            'descricao' => 'FIAT FIORINO',
            'km' => 98000,
        ])->save();

        $nfse = app(NfseGravarService::class)->gravar(
            (int) $empresa->id,
            null,
            [...$this->cabecalho($tomador), 'ordem_servico_id' => $os->id],
            $this->itens(),
        );

        $lw = Livewire::test(NfsePage::class);
        $this->chamar($lw->instance(), 'aplicarNfseGravada', $nfse);

        $this->assertSame('LZK6311', $lw->instance()->placa);
        $this->assertSame('FIAT FIORINO', $lw->instance()->descricao);
        $this->assertSame('98000', $lw->instance()->km);
        $this->assertTrue($lw->instance()->nfseTemEquipamento());
    }

    private function chamar(object $objeto, string $metodo, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($objeto, $metodo);

        return $ref->invoke($objeto, ...$args);
    }

    /**
     * @param  array<string, mixed>|null  $equipamento
     * @return array<string, mixed>
     */
    private function cabecalho(Person $tomador, ?array $equipamento = null): array
    {
        return [
            'tomador_id' => (int) $tomador->id,
            'tomador_nome' => 'CLIENTE NFSE',
            'tomador_cpf_cnpj' => null,
            'tomador_telefone' => null,
            'tomador_endereco' => null,
            'tomador_numero' => null,
            'tomador_bairro' => null,
            'tomador_cep' => null,
            'tomador_cidade' => null,
            'tomador_uf' => null,
            'tomador_cidade_codigo' => null,
            'tomador_email' => null,
            'competencia' => '2026-10-01',
            'data_emissao' => '2026-10-06',
            'municipio_incidencia' => 'CAMBORIU',
            'municipio_prestacao_codigo' => '4203204',
            'municipio_prestacao_nome' => 'CAMBORIU',
            'municipio_prestacao_uf' => 'SC',
            'trib_issqn' => Nfse::TRIB_ISSQN_TRIBUTAVEL,
            'tp_ret_issqn' => Nfse::TP_RET_ISSQN_NAO_RETIDO,
            'valor_servicos' => '10.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '10.00',
            'equipamento' => $equipamento,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itens(): array
    {
        return [[
            'product_id' => null,
            'codigo' => 'SRV1',
            'descricao' => 'MAO DE OBRA',
            'unidade' => 'UN',
            'quantidade' => '1.000',
            'valor' => '10.00',
            'total' => '10.00',
        ]];
    }

    /**
     * @return array{0: Empresa, 1: Person}
     */
    private function contexto(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA NFSE',
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

        $tomador = Person::query()->create([
            'codigo' => 'C-NFSE-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE NFSE',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        return [$empresa, $tomador];
    }
}
