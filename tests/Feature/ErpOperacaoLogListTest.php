<?php

namespace Tests\Feature;

use App\Filament\Resources\ErpOperacaoLogResource;
use App\Filament\Resources\ErpOperacaoLogResource\Pages\ListErpOperacaoLogs;
use App\Models\Empresa;
use App\Models\ErpOperacaoLog;
use App\Models\User;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\Compra\FinalizarCompraLancamentoService;
use App\Support\Erp\Compra\ReabrirCompraLancamentoService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Log de Operações — schema mínimo em SQLite testing (sem migrate:fresh).
 */
class ErpOperacaoLogListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ErpSchema::flush();
        ErpAccess::forgetSession();
        ErpContext::clearMemo();

        foreach (['erp_operacao_logs', 'user_permissions', 'users', 'empresas', 'migrations'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 160)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('erp_operacao_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('ocorrido_em')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nome', 120)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('operacao', 80);
            $table->string('origem', 80)->nullable();
            $table->string('documento_tipo', 40)->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->string('documento_numero', 60)->nullable();
            $table->string('resultado', 20)->default('ok');
            $table->text('resumo')->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamps();
            $table->index(['ocorrido_em']);
            $table->index(['operacao', 'ocorrido_em']);
            $table->index(['documento_tipo', 'documento_id']);
        });
    }

    public function test_admin_ve_somente_logs_da_empresa_ativa(): void
    {
        [$empresaA, $empresaB] = $this->duasEmpresas();
        $admin = $this->usuario($empresaA->id, admin: true);

        $this->log($empresaA->id, $admin, 'ESTORNAR_VENDA', 'Estorno A', '10');
        $this->log($empresaB->id, $admin, 'ESTORNAR_VENDA', 'Estorno B', '20');

        session(['erp_empresa_id' => $empresaA->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        $resumos = ErpOperacaoLogResource::getEloquentQuery()->pluck('resumo')->all();

        $this->assertSame(['Estorno A'], $resumos);

        $attrs = ErpOperacaoLogResource::getEloquentQuery()->first()?->getAttributes() ?? [];
        $this->assertArrayNotHasKey('detalhes', $attrs);
    }

    public function test_usuario_sem_permissao_nao_acessa(): void
    {
        $empresa = $this->empresa('EMP SEM PERM');
        $user = $this->usuario($empresa->id, admin: false);

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($user);
        ErpAccess::forgetSession();

        $this->assertFalse(ErpAccess::currentCan('auditoria.access'));
        $this->assertFalse(ErpOperacaoLogResource::canAccess());

        Livewire::test(ListErpOperacaoLogs::class)
            ->assertForbidden();
    }

    public function test_usuario_com_auditoria_access_lista_e_abre_detalhe(): void
    {
        $empresa = $this->empresa('EMP AUD');
        $user = $this->usuario($empresa->id, admin: false, nome: 'Operador Aud');

        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission_key' => 'auditoria.access',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $log = $this->log($empresa->id, $user, 'NFCE_INUTILIZACAO', 'Série 1 inutilizada', '1-10', [
            'serie' => 1,
            'protocolo' => 'ABC',
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($user);
        ErpAccess::forgetSession();

        $this->assertTrue(ErpAccess::currentCan('auditoria.access'));

        Livewire::test(ListErpOperacaoLogs::class)
            ->assertOk()
            ->assertSee('Fechar')
            ->assertCanSeeTableRecords([$log])
            ->call('abrirDetalhe', (int) $log->id)
            ->assertSet('detalheModalOpen', true)
            ->assertSet('detalheRegistro.operacao', 'Inutilização de NFC-e')
            ->assertSet('detalheRegistro.operacao_codigo', 'NFCE_INUTILIZACAO')
            ->call('closeScreen')
            ->assertRedirect(filament()->getUrl());
    }

    public function test_detalhe_de_outra_empresa_nao_abre(): void
    {
        [$empresaA, $empresaB] = $this->duasEmpresas();
        $admin = $this->usuario($empresaA->id, admin: true);
        $logB = $this->log($empresaB->id, $admin, 'ESTORNAR_VENDA', 'Segredo B', '99');

        session(['erp_empresa_id' => $empresaA->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        Livewire::test(ListErpOperacaoLogs::class)
            ->call('abrirDetalhe', (int) $logB->id)
            ->assertSet('detalheModalOpen', false)
            ->assertSet('detalheRegistro', null);
    }

    public function test_filtro_periodo_usa_intervalo_e_operacao(): void
    {
        $empresa = $this->empresa('EMP FILTRO');
        $admin = $this->usuario($empresa->id, admin: true);

        $noMes = $this->log($empresa->id, $admin, 'ESTORNAR_VENDA', 'No mês', '1');
        $noMes->forceFill(['ocorrido_em' => now()->startOfMonth()->addDay()])->save();

        $fora = $this->log($empresa->id, $admin, 'CANCELAR_COMPRA', 'Fora', '2');
        $fora->forceFill(['ocorrido_em' => now()->startOfMonth()->subMonth()])->save();

        $outraOp = $this->log($empresa->id, $admin, 'CANCELAR_COMPRA', 'Outra op', '3');
        $outraOp->forceFill(['ocorrido_em' => now()->startOfMonth()->addDays(2)])->save();

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        Livewire::test(ListErpOperacaoLogs::class)
            ->set('operacaoFilter', 'ESTORNAR_VENDA')
            ->call('search')
            ->assertCanSeeTableRecords([$noMes])
            ->assertCanNotSeeTableRecords([$fora, $outraOp]);
    }

    public function test_registrar_usa_erp_schema_e_preserva_json_para_reabrir_compra(): void
    {
        ErpSchema::flush();

        $empresa = $this->empresa('EMP COMPRA LOG');
        $admin = $this->usuario($empresa->id, admin: true);
        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        $detalhes = [
            'gera_estoque' => true,
            'ajusta_preco' => false,
            'gerar_financeiro' => true,
            'lotes' => [
                ['item_id' => 7, 'lote' => 'L1', 'qtd' => 2],
            ],
            'caixa_lancamentos' => [101, 102],
            'total' => 50.5,
        ];

        app(ErpOperacaoLogService::class)->registrar(
            operacao: FinalizarCompraLancamentoService::OPERACAO,
            resumo: 'Compra #9 finalizada no lançamento.',
            origem: 'compra',
            documentoTipo: 'compra',
            documentoId: 9,
            documentoNumero: '9',
            detalhes: $detalhes,
            empresaId: (int) $empresa->id,
        );

        app(ErpOperacaoLogService::class)->registrar(
            operacao: ReabrirCompraLancamentoService::OPERACAO,
            resumo: 'Compra #9 reaberta para edição.',
            origem: 'lista_compras',
            documentoTipo: 'compra',
            documentoId: 9,
            documentoNumero: '9',
            detalhes: $detalhes,
            empresaId: (int) $empresa->id,
        );

        $finalizar = ErpOperacaoLog::query()
            ->where('documento_tipo', 'compra')
            ->where('documento_id', 9)
            ->where('operacao', FinalizarCompraLancamentoService::OPERACAO)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($finalizar);
        $this->assertSame($detalhes['lotes'], $finalizar->detalhes['lotes']);
        $this->assertSame([101, 102], $finalizar->detalhes['caixa_lancamentos']);
        $this->assertFalse($finalizar->detalhes['ajusta_preco']);
        $this->assertSame(2, ErpOperacaoLog::query()->where('documento_id', 9)->count());
    }

    public function test_can_create_edit_delete_desabilitados(): void
    {
        $this->assertFalse(ErpOperacaoLogResource::canCreate());
        $this->assertFalse(ErpOperacaoLogResource::canDeleteAny());
    }

    /**
     * @return array{0: Empresa, 1: Empresa}
     */
    private function duasEmpresas(): array
    {
        return [
            $this->empresa('EMP A LOG'),
            $this->empresa('EMP B LOG'),
        ];
    }

    private function empresa(string $nome): Empresa
    {
        return Empresa::query()->create([
            'codigo' => 'L'.substr((string) microtime(true), -5),
            'nome' => $nome,
            'ativo' => true,
        ]);
    }

    private function usuario(int $empresaId, bool $admin, string $nome = 'Admin Log'): User
    {
        return User::query()->create([
            'name' => $nome,
            'password' => bcrypt('x'),
            'empresa_id' => $empresaId,
            'is_admin' => $admin,
            'ativo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $detalhes
     */
    private function log(
        int $empresaId,
        User $user,
        string $operacao,
        string $resumo,
        string $documentoNumero,
        ?array $detalhes = null,
    ): ErpOperacaoLog {
        return ErpOperacaoLog::query()->create([
            'ocorrido_em' => now(),
            'user_id' => $user->id,
            'user_nome' => $user->name,
            'empresa_id' => $empresaId,
            'operacao' => mb_strtoupper($operacao, 'UTF-8'),
            'origem' => 'teste',
            'documento_tipo' => 'venda',
            'documento_id' => (int) preg_replace('/\D+/', '', $documentoNumero) ?: null,
            'documento_numero' => $documentoNumero,
            'resultado' => 'ok',
            'resumo' => $resumo,
            'detalhes' => $detalhes ?? ['motivo' => 'teste'],
        ]);
    }
}
