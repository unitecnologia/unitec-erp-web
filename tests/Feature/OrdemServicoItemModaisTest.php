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
use App\Support\Erp\Nfse\NfseOsDiscriminacao;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class OrdemServicoItemModaisTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_modal_de_desconto_mostra_preco_do_item_selecionado_e_foca_o_percentual(): void
    {
        $this->contexto();

        Livewire::test(CreateOrdemServico::class)
            ->set('itens', [$this->servico('new-1', 'SERVIÇO DE RETÍFICA CABEÇOTE', '2,00')])
            ->set('selectedItemIndex', 0)
            ->call('abrirModalDescontoItem')
            ->assertSet('descontoModalOpen', true)
            ->assertSet('itemAjusteAlvo', 'grid')
            ->set('itemAjusteValor', '10')
            ->assertSee('SERVIÇO DE RETÍFICA CABEÇOTE')
            ->assertSee('R$ 1,80')
            ->assertSeeHtml('id="erp-os-desconto-preco"')
            ->assertSeeHtml('$el.focus()');
    }

    public function test_cada_servico_tem_o_proprio_servico_prestado(): void
    {
        $cliente = $this->contexto();

        $tecnico = Vendedor::query()->create([
            'codigo' => '1',
            'nome' => 'TECNICO OS',
            'ativo' => true,
            'empresa_id' => Empresa::query()->value('id'),
        ]);

        $lw = Livewire::test(CreateOrdemServico::class)
            ->set('clienteId', $cliente->id)
            ->set('clienteSearch', $cliente->nome_razao)
            ->set('atendenteId', $tecnico->id)
            ->set('itens', [
                $this->servico('new-1', 'RETÍFICA CABEÇOTE', '1,00'),
                $this->servico('new-2', 'TROCA DE JUNTA', '2,00'),
            ]);

        $lw->call('abrirModalServicoPrestado', 0)
            ->assertSet('servicoPrestadoTexto', '')
            ->assertSee('RETÍFICA CABEÇOTE')
            ->set('servicoPrestadoTexto', 'plaina e teste de trinca')
            ->call('confirmarModalServicoPrestado');

        $lw->call('abrirModalServicoPrestado', 1)
            ->assertSet('servicoPrestadoTexto', '')
            ->set('servicoPrestadoTexto', 'junta nova original')
            ->call('confirmarModalServicoPrestado');

        $lw->call('abrirModalServicoPrestado', 0)
            ->assertSet('servicoPrestadoTexto', 'PLAINA E TESTE DE TRINCA')
            ->call('cancelarModalServicoPrestado')
            ->call('gravarOs')
            ->assertHasNoErrors();

        $ordem = OrdemServico::query()->with('itens')->sole();
        $textos = $ordem->itens->pluck('servico_prestado', 'discriminacao')->all();

        $this->assertSame('PLAINA E TESTE DE TRINCA', $textos['RETÍFICA CABEÇOTE']);
        $this->assertSame('JUNTA NOVA ORIGINAL', $textos['TROCA DE JUNTA']);

        Livewire::test(EditOrdemServico::class, ['record' => $ordem->id])
            ->call('abrirModalServicoPrestado', 1)
            ->assertSet('servicoPrestadoTexto', $textos[$ordem->itens[1]->discriminacao]);

        // O texto vai na linha da NFS-e, não no bloco da OS na discriminação.
        $this->assertStringNotContainsString('PLAINA E TESTE DE TRINCA', NfseOsDiscriminacao::texto($ordem));
    }

    /**
     * @return array<string, mixed>
     */
    private function servico(string $key, string $descricao, string $preco): array
    {
        return [
            'id' => null,
            'key' => $key,
            'tipo' => 'S',
            'product_id' => null,
            'product_codigo' => '1',
            'discriminacao' => $descricao,
            'servico_prestado' => '',
            'qtd' => '1,000',
            'preco' => $preco,
            'acrescimo' => '0,00',
            'desconto' => '0,00',
            'total' => $preco,
            'funcionario_id' => null,
            'concluido_em' => '',
        ];
    }

    private function contexto(): Person
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

        return Person::query()->create([
            'codigo' => 'C-OS-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS',
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }
}
