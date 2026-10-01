<?php

namespace Tests\Feature;

use App\Filament\Resources\CargaResource\Pages\ListCargas;
use App\Models\Carga;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\CargaNumeroService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class CargaNumeroServiceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_primeira_carga_apos_historico_10_vira_11_e_proxima_12(): void
    {
        [$empresa] = $this->seedEmpresa();
        $this->seedCargasNumeroAte($empresa->id, 10);

        $this->assertNull(
            DB::table('venda_numero_sequencias')->where('chave', CargaNumeroService::chave((int) $empresa->id))->first()
        );

        $service = app(CargaNumeroService::class);

        $n1 = DB::transaction(fn (): string => $service->proximo((int) $empresa->id));
        $n2 = DB::transaction(fn (): string => $service->proximo((int) $empresa->id));

        $this->assertSame('11', $n1);
        $this->assertSame('12', $n2);
        $this->assertSame(12, (int) DB::table('venda_numero_sequencias')
            ->where('chave', CargaNumeroService::chave((int) $empresa->id))
            ->value('ultimo_numero'));
    }

    public function test_empresas_diferentes_tem_sequencias_independentes(): void
    {
        [$empresaA] = $this->seedEmpresa('A');
        [$empresaB] = $this->seedEmpresa('B');
        $this->seedCargasNumeroAte($empresaA->id, 3);
        $this->seedCargasNumeroAte($empresaB->id, 20);

        $service = app(CargaNumeroService::class);

        $na = DB::transaction(fn (): string => $service->proximo((int) $empresaA->id));
        $nb = DB::transaction(fn (): string => $service->proximo((int) $empresaB->id));

        $this->assertSame('4', $na);
        $this->assertSame('21', $nb);
    }

    public function test_carga_cancelada_continua_contando(): void
    {
        [$empresa] = $this->seedEmpresa();
        $this->seedCargasNumeroAte($empresa->id, 5);
        Carga::query()
            ->where('empresa_id', $empresa->id)
            ->where('numero', '5')
            ->update(['status' => Carga::STATUS_CANCELADA]);

        $this->assertSame(5, app(CargaNumeroService::class)->maxNumeroExistente((int) $empresa->id));
        $this->assertSame('6', Carga::nextNumero((int) $empresa->id));

        $proximo = DB::transaction(
            fn (): string => app(CargaNumeroService::class)->proximo((int) $empresa->id)
        );

        $this->assertSame('6', $proximo);
    }

    public function test_preview_next_numero_nao_reserva_contador(): void
    {
        [$empresa] = $this->seedEmpresa();
        $this->seedCargasNumeroAte($empresa->id, 2);

        $this->assertSame('3', Carga::nextNumero((int) $empresa->id));
        $this->assertSame('3', Carga::nextNumero((int) $empresa->id));
        $this->assertNull(
            DB::table('venda_numero_sequencias')->where('chave', CargaNumeroService::chave((int) $empresa->id))->first()
        );
    }

    public function test_editar_carga_nao_avanca_sequencia(): void
    {
        [$user, $empresa, $carga] = $this->seedCargaAbertaComPedido();

        DB::table('venda_numero_sequencias')->insert([
            'chave' => CargaNumeroService::chave((int) $empresa->id),
            'ultimo_numero' => (int) $carga->numero,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('editCarga')
            ->set('cargaForm.observacao', 'edit sem novo numero')
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', false);

        $this->assertSame((string) $carga->numero, (string) $carga->fresh()->numero);
        $this->assertSame((int) $carga->numero, (int) DB::table('venda_numero_sequencias')
            ->where('chave', CargaNumeroService::chave((int) $empresa->id))
            ->value('ultimo_numero'));
    }

    public function test_rollback_do_create_nao_consome_numero(): void
    {
        [$empresa] = $this->seedEmpresa();
        $this->seedCargasNumeroAte($empresa->id, 7);
        $chave = CargaNumeroService::chave((int) $empresa->id);
        $service = app(CargaNumeroService::class);

        try {
            DB::transaction(function () use ($service, $empresa): void {
                $numero = $service->proximo((int) $empresa->id);
                $this->assertSame('8', $numero);
                $this->assertSame(8, (int) DB::table('venda_numero_sequencias')
                    ->where('chave', CargaNumeroService::chave((int) $empresa->id))
                    ->value('ultimo_numero'));

                throw new \RuntimeException('falha simulada no create');
            });
            $this->fail('Deveria ter lançado RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('falha simulada no create', $e->getMessage());
        }

        $this->assertNull(DB::table('venda_numero_sequencias')->where('chave', $chave)->first());
        $this->assertSame('8', DB::transaction(fn (): string => $service->proximo((int) $empresa->id)));
    }

    public function test_unique_empresa_numero_continua_como_protecao(): void
    {
        [$empresa] = $this->seedEmpresa();
        Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);

        $this->expectException(QueryException::class);

        Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);
    }

    public function test_save_carga_nova_usa_numero_definitivo_do_servico(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoBasico();
        $this->seedCargasNumeroAte($empresa->id, 4);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('cargaForm.numero', '5')
            ->set('cargaForm.data', now()->toDateString())
            ->set('cargaPedidoIds', [(int) $pedido->id])
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', false);

        $criada = Carga::query()->where('empresa_id', $empresa->id)->orderByDesc('id')->first();
        $this->assertNotNull($criada);
        $this->assertSame('5', (string) $criada->numero);
        $this->assertSame(5, (int) DB::table('venda_numero_sequencias')
            ->where('chave', CargaNumeroService::chave((int) $empresa->id))
            ->value('ultimo_numero'));
    }

    /**
     * @return array{0: Empresa}
     */
    private function seedEmpresa(string $sufixo = '1'): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'CN'.$sufixo,
            'nome' => 'EMPRESA CARGA NUM '.$sufixo,
            'fantasia' => 'CN'.$sufixo,
            'ativo' => true,
        ]);

        return [$empresa];
    }

    private function seedCargasNumeroAte(int $empresaId, int $ate): void
    {
        for ($n = 1; $n <= $ate; $n++) {
            Carga::query()->create([
                'empresa_id' => $empresaId,
                'numero' => (string) $n,
                'data' => now()->toDateString(),
                'status' => Carga::STATUS_ABERTA,
            ]);
        }
    }

    /**
     * @return array{0: User, 1: Empresa, 2: Carga}
     */
    private function seedCargaAbertaComPedido(): array
    {
        [$user, $empresa, $pedido] = $this->seedPedidoBasico();

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);
        $carga->pedidos()->attach([$pedido->id]);

        return [$user, $empresa, $carga];
    }

    /**
     * @return array{0: User, 1: Empresa, 2: Venda}
     */
    private function seedPedidoBasico(): array
    {
        [$empresa] = $this->seedEmpresa('P');

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador Num Carga',
            'is_admin' => true,
            'ativo' => true,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-CN-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Num',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9301',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        return [$user, $empresa, $pedido];
    }
}
