<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\ForcaVendasVisitaSemVenda;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\Tabular\Definitions\VisitasRealizadasSemVendaReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class VisitasRealizadasSemVendaReportTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_registry_e_permissao(): void
    {
        $this->assertTrue(ReportRegistry::has('visitas-realizadas-sem-venda'));
        $report = ReportRegistry::make('visitas-realizadas-sem-venda');
        $this->assertSame('vendas.print', $report->permission());
        $this->assertSame('VISITAS REALIZADAS SEM VENDA', $report->title());
    }

    public function test_build_filtra_periodo_vendedor_cliente_motivo_e_gps(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => 91001,
            'nome' => 'EMPRESA VISITAS',
            'razao_social' => 'EMPRESA VISITAS LTDA',
            'ativo' => true,
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id]);
        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $vendedor = Vendedor::query()->create([
            'codigo' => '9',
            'nome' => 'VENDEDOR TESTE',
            'ativo' => true,
        ]);
        $outroVendedor = Vendedor::query()->create([
            'codigo' => '8',
            'nome' => 'OUTRO',
            'ativo' => true,
        ]);

        $cliente = Person::query()->create([
            'codigo' => '100',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE VISITA',
            'cidade_nome' => 'BLUMENAU',
            'uf' => 'SC',
            'celular1' => '47999990000',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $outroCliente = Person::query()->create([
            'codigo' => '101',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'OUTRO CLIENTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        ForcaVendasVisitaSemVenda::query()->create([
            'uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeee1',
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'motivo' => 'Cliente fechado no horário',
            'latitude' => -26.9150000,
            'longitude' => -49.0700000,
            'status' => ForcaVendasVisitaSemVenda::STATUS_IMPORTADO,
            'client_created_at' => now()->startOfMonth()->addDays(2)->setTime(14, 30),
            'received_at' => now(),
        ]);

        ForcaVendasVisitaSemVenda::query()->create([
            'uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeee2',
            'empresa_id' => $empresa->id,
            'cliente_id' => $outroCliente->id,
            'vendedor_id' => $outroVendedor->id,
            'motivo' => 'Sem estoque do produto',
            'latitude' => null,
            'longitude' => null,
            'status' => ForcaVendasVisitaSemVenda::STATUS_IMPORTADO,
            'client_created_at' => now()->startOfMonth()->addDays(3)->setTime(10, 0),
            'received_at' => now(),
        ]);

        // Outra empresa — não pode aparecer.
        ForcaVendasVisitaSemVenda::query()->create([
            'uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeee3',
            'empresa_id' => $empresa->id + 999,
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'motivo' => 'Cliente fechado no horário',
            'latitude' => -26.9,
            'longitude' => -49.0,
            'status' => ForcaVendasVisitaSemVenda::STATUS_IMPORTADO,
            'client_created_at' => now()->startOfMonth()->addDays(2)->setTime(15, 0),
            'received_at' => now(),
        ]);

        $report = new VisitasRealizadasSemVendaReport;

        $preview = $report->build(Request::create('/admin/reports/r/visitas-realizadas-sem-venda', 'GET', [
            'vendedor' => (string) $vendedor->id,
            'cliente' => 'CLIENTE VISITA',
            'motivo' => 'fechado',
        ]));

        $this->assertCount(1, $preview['rows']);
        $row = $preview['rows'][0];
        $this->assertStringContainsString('CLIENTE VISITA', $row['cliente']);
        $this->assertStringContainsString('BLUMENAU', $row['cidade']);
        $this->assertSame('47999990000', $row['telefone']);
        $this->assertStringContainsString('fechado', mb_strtolower($row['motivo']));
        $this->assertInstanceOf(HtmlString::class, $row['local']);
        $this->assertStringContainsString('google.com/maps?q=', $row['local']->toHtml());
        $this->assertStringContainsString('Total de visitas sem venda: 1', $preview['totals']['data_hora']);

        $pdf = $report->build(Request::create('/admin/reports/r/visitas-realizadas-sem-venda', 'GET', [
            'pdf' => 1,
            'vendedor' => (string) $vendedor->id,
        ]));
        $this->assertIsString($pdf['rows'][0]['local']);
        $this->assertStringContainsString('-26.91500', $pdf['rows'][0]['local']);
        $this->assertStringNotContainsString('google.com', $pdf['rows'][0]['local']);

        $semGps = $report->build(Request::create('/admin/reports/r/visitas-realizadas-sem-venda', 'GET', [
            'vendedor' => (string) $outroVendedor->id,
        ]));
        $this->assertSame('—', $semGps['rows'][0]['local']);
    }
}
