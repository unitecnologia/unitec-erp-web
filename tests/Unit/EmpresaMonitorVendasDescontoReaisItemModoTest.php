<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaMonitorVendasDescontoReaisItemModoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_unitario_grupo_monitor(): void
    {
        $field = EmpresaParametros::monitorVendasDescontoReaisItemModoField();

        $this->assertSame('Desconto em R$ nos itens', $field['label']);
        $this->assertSame(
            'Define se o desconto informado em R$ no item será aplicado em cada unidade ou uma única vez sobre o total da linha.',
            $field['hint'],
        );
        $this->assertSame('unitario', $field['default']);
        $this->assertSame([
            'unitario' => 'Por unidade',
            'linha' => 'Total da linha',
        ], $field['options']);
        $this->assertSame(
            'unitario',
            EmpresaParametros::defaultFormValues()['param_monitor_vendas_desconto_reais_item_modo'],
        );
        $this->assertNotContains(
            'param_monitor_vendas_desconto_reais_item_modo',
            array_keys(EmpresaParametros::permissionFields()),
        );
    }

    public function test_empresa_nova_grava_unitario_e_aceita_linha(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', 'param_monitor_vendas_desconto_reais_item_modo'));

        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ DESC REAIS',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertSame('unitario', $empresa->param_monitor_vendas_desconto_reais_item_modo);

        $empresa->update(['param_monitor_vendas_desconto_reais_item_modo' => 'linha']);
        $this->assertSame('linha', $empresa->fresh()->param_monitor_vendas_desconto_reais_item_modo);

        $this->assertSame('unitario', EmpresaParametros::normalizarDescontoReaisItemModo('outro'));
        $this->assertSame('unitario', EmpresaParametros::normalizarDescontoReaisItemModo(null));
        $this->assertSame('linha', EmpresaParametros::normalizarDescontoReaisItemModo('linha'));
    }

    public function test_pull_entrega_o_modo_no_meta_sem_chamada_extra(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMP DESC REAIS PULL',
            'ativo' => true,
            'param_monitor_vendas_desconto_reais_item_modo' => 'linha',
        ]);

        $payload = app(ForcaVendasSyncService::class)->buildPull(null, null, $empresa->id);

        $this->assertSame('linha', $payload['meta']['desconto_reais_item_modo']);

        $empresa->update(['param_monitor_vendas_desconto_reais_item_modo' => 'unitario']);

        $payloadUnitario = app(ForcaVendasSyncService::class)->buildPull(null, null, $empresa->id);

        $this->assertSame('unitario', $payloadUnitario['meta']['desconto_reais_item_modo']);
    }
}
