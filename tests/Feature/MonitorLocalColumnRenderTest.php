<?php

namespace Tests\Feature;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class MonitorLocalColumnRenderTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_grade_mostra_pin_com_gps_e_traco_sem_gps(): void
    {
        [$empresa, $user] = $this->seedUsuarioMonitor();

        $comGps = $this->seedPedido($empresa, $user, [
            'latitude' => '-27.0099811',
            'longitude' => '-48.6290570',
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
        ]);
        $semGps = $this->seedPedido($empresa, $user, [
            'latitude' => null,
            'longitude' => null,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
        ]);

        $html = Livewire::actingAs($user)
            ->test(ListForcaVendasMonitor::class)
            ->set('situacaoFilter', ForcaVendasOrder::SITUACAO_FATURADO)
            ->html();

        $this->assertStringContainsString('erp-fv-mon-local', $html);
        $this->assertStringContainsString('Ver local da venda', $html);
        $this->assertStringContainsString(
            'https://www.google.com/maps?q='.rawurlencode('-27.0099811,-48.6290570'),
            $html,
        );
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('event.stopPropagation()', $html);
        $this->assertStringContainsString('erp-fv-mon-local--vazio', $html);

        // Pedido faturado com GPS continua com link (não some após faturar).
        $this->assertSame(ForcaVendasOrder::SITUACAO_FATURADO, $comGps->fresh()->situacao);
        $this->assertNotNull($comGps->fresh()->latitude);
        $this->assertNull($semGps->fresh()->latitude);
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedUsuarioMonitor(): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMP LOCAL GPS',
            'ativo' => true,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $empresa->id]);

        ErpContext::clearMemo();
        ErpAccess::forgetSession();
        $request = Request::create('/admin/forca-vendas-monitor', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('erp_empresa_id', (int) $empresa->id);
        app()->instance('request', $request);
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());

        return [$empresa, $user];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function seedPedido(Empresa $empresa, User $user, array $extra = []): ForcaVendasOrder
    {
        $cliente = Person::query()->create([
            'codigo' => 'C-GPS-'.random_int(1000, 9999),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente GPS',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(10000, 99999),
            'data' => now()->toDateString(),
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        return ForcaVendasOrder::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'venda_id' => $venda->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [],
            'client_created_at' => now(),
            'received_at' => now(),
            'faturado_at' => now(),
        ], $extra));
    }
}
