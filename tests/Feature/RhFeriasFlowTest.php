<?php

namespace Tests\Feature;

use App\Models\RhFeriasGozo;
use App\Models\RhFeriasPeriodo;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpPermissionCatalog;
use App\Support\Rh\RhFeriasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class RhFeriasFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_ferias_gozo_logs');
        Schema::dropIfExists('rh_ferias_gozos');
        Schema::dropIfExists('rh_ferias_periodos');
        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_funcionarios');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->date('data_admissao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->date('data_demissao')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_ferias_periodos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->date('aquisitivo_inicio');
            $table->date('aquisitivo_fim');
            $table->date('concessivo_inicio');
            $table->date('concessivo_fim');
            $table->unsignedSmallInteger('dias_direito')->default(30);
            $table->unsignedSmallInteger('dias_saldo')->default(30);
            $table->string('situacao', 20)->default('aberto');
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_ferias_gozos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('periodo_id');
            $table->unsignedBigInteger('funcionario_id');
            $table->date('gozo_inicio');
            $table->date('gozo_fim');
            $table->unsignedSmallInteger('dias');
            $table->unsignedTinyInteger('fracao')->default(1);
            $table->date('aviso_em')->nullable();
            $table->string('situacao', 20)->default('programada');
            $table->boolean('ajuste_admin')->default(false);
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_ferias_gozo_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gozo_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_gerar_periodo_e_programar_gozo_atualiza_saldo(): void
    {
        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-ferias@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'MARIA FERIAS',
            'data_admissao' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'ativo' => true,
        ]);

        $service = app(RhFeriasService::class);
        $periodo = $service->gerarPeriodoAquisitivo($func, 20);

        $this->assertSame(20, $periodo->dias_saldo);

        $gozo = $service->programarGozo($periodo, [
            'gozo_inicio' => $periodo->concessivo_inicio->toDateString(),
            'gozo_fim' => $periodo->concessivo_inicio->copy()->addDays(13)->toDateString(),
        ]);

        $periodo->refresh();
        $this->assertSame(6, $periodo->dias_saldo);
        $this->assertSame(14, $gozo->dias);
        $this->assertContains($periodo->situacao, [RhFeriasPeriodo::SIT_PARCIAL, RhFeriasPeriodo::SIT_ABERTO]);
    }

    public function test_fracionamento_rejeita_quarta_fracao(): void
    {
        $this->actingAs(User::query()->create(['name' => 'U', 'email' => 'u@test', 'password' => 'x', 'is_admin' => true]));

        $func = RhFuncionario::query()->create([
            'codigo' => '2',
            'nome' => 'JOAO',
            'data_admissao' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'ativo' => true,
        ]);

        $service = app(RhFeriasService::class);
        $periodo = $service->gerarPeriodoAquisitivo($func, 30);
        $base = $periodo->concessivo_inicio->toDateString();

        $service->programarGozo($periodo->fresh(), [
            'gozo_inicio' => $base,
            'gozo_fim' => date('Y-m-d', strtotime($base.' +13 days')),
        ]);
        $periodo->refresh();

        $service->programarGozo($periodo, [
            'gozo_inicio' => date('Y-m-d', strtotime($base.' +20 days')),
            'gozo_fim' => date('Y-m-d', strtotime($base.' +24 days')),
        ]);
        $periodo->refresh();

        $service->programarGozo($periodo, [
            'gozo_inicio' => date('Y-m-d', strtotime($base.' +30 days')),
            'gozo_fim' => date('Y-m-d', strtotime($base.' +34 days')),
        ]);
        $periodo->refresh();

        $this->expectException(InvalidArgumentException::class);
        $service->programarGozo($periodo, [
            'gozo_inicio' => date('Y-m-d', strtotime($base.' +40 days')),
            'gozo_fim' => date('Y-m-d', strtotime($base.' +44 days')),
        ]);
    }

    public function test_conflito_de_datas_entre_gozos(): void
    {
        $this->actingAs(User::query()->create(['name' => 'U', 'email' => 'u2@test', 'password' => 'x', 'is_admin' => true]));

        $func = RhFuncionario::query()->create([
            'codigo' => '3',
            'nome' => 'ANA',
            'data_admissao' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'ativo' => true,
        ]);

        $service = app(RhFeriasService::class);
        $p1 = $service->gerarPeriodoAquisitivo($func, 30);
        $p2 = $service->gerarPeriodoAquisitivo($func, 30);

        $ini = $p1->concessivo_inicio->toDateString();
        $service->programarGozo($p1, [
            'gozo_inicio' => $ini,
            'gozo_fim' => date('Y-m-d', strtotime($ini.' +13 days')),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $service->programarGozo($p2, [
            'gozo_inicio' => date('Y-m-d', strtotime($ini.' +5 days')),
            'gozo_fim' => date('Y-m-d', strtotime($ini.' +14 days')),
        ]);
    }

    public function test_cancelar_restitui_saldo(): void
    {
        $this->actingAs(User::query()->create(['name' => 'U', 'email' => 'u3@test', 'password' => 'x', 'is_admin' => true]));

        $func = RhFuncionario::query()->create([
            'codigo' => '4',
            'nome' => 'PEDRO',
            'data_admissao' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'ativo' => true,
        ]);

        $service = app(RhFeriasService::class);
        $periodo = $service->gerarPeriodoAquisitivo($func, 15);
        $gozo = $service->programarGozo($periodo, [
            'gozo_inicio' => $periodo->concessivo_inicio->toDateString(),
            'gozo_fim' => $periodo->concessivo_inicio->copy()->addDays(13)->toDateString(),
        ]);

        $periodo->refresh();
        $this->assertSame(1, $periodo->dias_saldo);

        $service->cancelar($gozo, 'Desistência');
        $periodo->refresh();

        $this->assertSame(15, $periodo->dias_saldo);
        $this->assertSame(RhFeriasGozo::SIT_CANCELADA, $gozo->fresh()->situacao);
    }

    public function test_em_ferias_hoje_consulta_gozo_em_andamento(): void
    {
        $func = RhFuncionario::query()->create([
            'codigo' => '5',
            'nome' => 'CARLA',
            'data_admissao' => '2020-01-01',
            'ativo' => true,
        ]);

        $periodo = RhFeriasPeriodo::query()->create([
            'codigo' => '1',
            'funcionario_id' => $func->id,
            'aquisitivo_inicio' => '2024-01-01',
            'aquisitivo_fim' => '2024-12-31',
            'concessivo_inicio' => '2025-01-01',
            'concessivo_fim' => '2025-12-31',
            'dias_direito' => 30,
            'dias_saldo' => 20,
            'situacao' => RhFeriasPeriodo::SIT_PARCIAL,
            'ativo' => true,
        ]);

        RhFeriasGozo::query()->create([
            'codigo' => '1',
            'periodo_id' => $periodo->id,
            'funcionario_id' => $func->id,
            'gozo_inicio' => now()->subDay()->toDateString(),
            'gozo_fim' => now()->addDays(5)->toDateString(),
            'dias' => 7,
            'fracao' => 1,
            'situacao' => RhFeriasGozo::SIT_EM_ANDAMENTO,
            'ativo' => true,
        ]);

        $this->assertTrue($func->fresh()->emFeriasHoje());
    }

    public function test_permissoes_ferias_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.ferias.'.$action, $keys);
        }
    }

    public function test_acesso_listagem_sem_permissao(): void
    {
        $user = User::query()->create(['name' => 'Sem', 'email' => 'sem@test', 'password' => 'x', 'ativo' => true]);
        $this->actingAs($user);
        ErpAccess::storeInSession($user, []);

        $this->assertFalse(ErpAccess::currentCan('rh.ferias.access'));
    }
}
