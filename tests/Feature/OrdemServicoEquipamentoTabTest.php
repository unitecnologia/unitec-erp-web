<?php

namespace Tests\Feature;

use App\Filament\Resources\OrdemServicoResource\Pages\CreateOrdemServico;
use App\Filament\Resources\OrdemServicoResource\Pages\EditOrdemServico;
use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class OrdemServicoEquipamentoTabTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_cria_salva_reabre_e_edita_os_com_campos_da_aba_equipamento(): void
    {
        [$empresa, $cliente, $tecnico] = $this->contexto();

        Livewire::test(CreateOrdemServico::class)
            ->set('clienteId', $cliente->id)
            ->set('clienteSearch', $cliente->nome_razao)
            ->set('atendenteId', $tecnico->id)
            ->set('descricao', 'samsung')
            ->set('modelo', 'galaxy s23')
            ->set('ano', '2023')
            ->set('numeroSerie', '356789012345678')
            ->set('placa', 'abc1d23')
            ->set('km', '1500')
            ->set('corVeiculo', 'preto')
            ->set('chassiVeiculo', '9bwzzz377vt004251')
            ->set('descricao2', 'tela trincada')
            ->call('gravarOs')
            ->assertHasNoErrors();

        $ordem = OrdemServico::query()->sole();
        $this->assertSame('SAMSUNG', $ordem->descricao);
        $this->assertSame('GALAXY S23', $ordem->modelo);
        $this->assertSame('2023', $ordem->ano);
        $this->assertSame('356789012345678', $ordem->numero_serie);
        $this->assertSame('ABC1D23', $ordem->placa);
        $this->assertSame(1500, (int) $ordem->km);
        $this->assertSame('PRETO', $ordem->cor_veiculo);
        $this->assertSame('9BWZZZ377VT004251', $ordem->chassi_veiculo);
        $this->assertSame('TELA TRINCADA', $ordem->descricao2);
        $this->assertNull($ordem->marca);
        $this->assertNull($ordem->marca_veiculo);
        $this->assertNull($ordem->modelo_veiculo);
        $this->assertNull($ordem->placa_veiculo);

        Livewire::test(EditOrdemServico::class, ['record' => $ordem->id])
            ->assertSet('descricao', 'SAMSUNG')
            ->assertSet('modelo', 'GALAXY S23')
            ->assertSet('ano', '2023')
            ->assertSet('numeroSerie', '356789012345678')
            ->assertSet('placa', 'ABC1D23')
            ->assertSet('km', '1500')
            ->assertSet('corVeiculo', 'PRETO')
            ->assertSet('chassiVeiculo', '9BWZZZ377VT004251')
            ->assertSet('descricao2', 'TELA TRINCADA')
            ->call('setActiveFormTab', 'equipamento')
            ->assertSee('Equipamento / Marca')
            ->assertSee('Nº Série / IMEI')
            ->assertSee('Descrição / Complemento')
            ->assertDontSee('Veículo (opcional)')
            ->set('descricao', 'dell')
            ->set('modelo', 'inspiron 15')
            ->set('descricao2', 'não liga')
            ->call('gravarOs')
            ->assertHasNoErrors();

        $ordem->refresh();
        $this->assertSame('DELL', $ordem->descricao);
        $this->assertSame('INSPIRON 15', $ordem->modelo);
        $this->assertSame('NÃO LIGA', $ordem->descricao2);
        $this->assertSame('ABC1D23', $ordem->placa);

        Livewire::test(EditOrdemServico::class, ['record' => $ordem->id])
            ->assertSet('descricao', 'DELL')
            ->assertSet('modelo', 'INSPIRON 15')
            ->assertSet('descricao2', 'NÃO LIGA')
            ->assertSet('corVeiculo', 'PRETO');
    }

    public function test_os_antiga_usa_campos_legados_quando_principal_vazio_e_preserva_colunas(): void
    {
        [$empresa, $cliente, $tecnico] = $this->contexto();

        $ordem = OrdemServico::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '77',
            'situacao' => OrdemServico::SITUACAO_ABERTA,
            'cliente_id' => $cliente->id,
            'atendente_id' => $tecnico->id,
            'nome' => $cliente->nome_razao,
            'descricao' => null,
            'marca' => 'EPSON',
            'marca_veiculo' => 'TOYOTA',
            'modelo' => null,
            'modelo_veiculo' => 'COROLLA',
            'ano' => null,
            'ano_veiculo' => '2020',
            'placa' => null,
            'placa_veiculo' => 'QTL6G28',
            'cor_veiculo' => 'PRATA',
            'chassi_veiculo' => 'CH123',
            'descricao2' => 'OBS ANTIGA',
        ]);

        Livewire::test(EditOrdemServico::class, ['record' => $ordem->id])
            ->assertSet('descricao', 'EPSON')
            ->assertSet('modelo', 'COROLLA')
            ->assertSet('ano', '2020')
            ->assertSet('placa', 'QTL6G28')
            ->assertSet('corVeiculo', 'PRATA')
            ->assertSet('chassiVeiculo', 'CH123')
            ->assertSet('descricao2', 'OBS ANTIGA')
            ->call('gravarOs')
            ->assertHasNoErrors();

        $ordem->refresh();
        $this->assertSame('EPSON', $ordem->descricao);
        $this->assertSame('COROLLA', $ordem->modelo);
        $this->assertSame('2020', $ordem->ano);
        $this->assertSame('QTL6G28', $ordem->placa);
        $this->assertSame('EPSON', $ordem->marca);
        $this->assertSame('TOYOTA', $ordem->marca_veiculo);
        $this->assertSame('COROLLA', $ordem->modelo_veiculo);
        $this->assertSame('QTL6G28', $ordem->placa_veiculo);
    }

    public function test_campo_principal_tem_prioridade_sobre_legado(): void
    {
        [$empresa, $cliente, $tecnico] = $this->contexto();

        $ordem = OrdemServico::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '78',
            'situacao' => OrdemServico::SITUACAO_ABERTA,
            'cliente_id' => $cliente->id,
            'atendente_id' => $tecnico->id,
            'nome' => $cliente->nome_razao,
            'descricao' => 'NOTEBOOK',
            'marca' => 'DELL',
            'placa' => 'AAA1A11',
            'placa_veiculo' => 'BBB2B22',
        ]);

        Livewire::test(EditOrdemServico::class, ['record' => $ordem->id])
            ->assertSet('descricao', 'NOTEBOOK')
            ->assertSet('placa', 'AAA1A11');
    }

    public function test_overlay_cadastro_nao_redesenha_os_e_salvar_ainda_atualiza(): void
    {
        $this->contexto();

        $lw = Livewire::test(CreateOrdemServico::class);

        $lw->call('openProdutosCadastro')
            ->assertSet('overlayProductOpen', true)
            ->assertSet('overlayPersonOpen', false);

        $this->assertStringNotContainsString('data-erp-form-overlay-iframe', $lw->html());

        $lw->call('openProdutosCadastro')
            ->assertSet('overlayProductOpen', true);

        $lw->call('applyOverlayProdutoSaved', 'abc-99')
            ->assertSet('overlayProductOpen', false)
            ->assertSet('itemCodigoInput', 'ABC-99');

        $lw->call('openPessoasCadastro')
            ->assertSet('overlayPersonOpen', true)
            ->assertSet('overlayProductOpen', false);

        $this->assertStringNotContainsString('data-erp-form-overlay-iframe', $lw->html());

        $lw->call('closePersonOverlay')
            ->assertSet('overlayPersonOpen', false);

        $lw->call('openProdutosCadastro');
        $lw->call('handleOsFormEscape')
            ->assertSet('overlayProductOpen', false);
    }

    /**
     * @return array{0: Empresa, 1: Person, 2: Vendedor}
     */
    private function contexto(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA OS',
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);

        $tecnico = Vendedor::query()->create([
            'codigo' => '1',
            'nome' => 'TECNICO OS',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-OS-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador OS',
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        return [$empresa, $cliente, $tecnico];
    }
}
