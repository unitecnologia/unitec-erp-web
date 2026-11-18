<?php

namespace Tests\Feature;

use App\Models\RhBeneficio;
use App\Models\RhBeneficioVinculo;
use App\Models\RhBeneficioVinculoLog;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Rh\RhBeneficioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fluxo benefícios sem migrate:fresh — schema mínimo em SQLite testing.
 */
class RhBeneficioVinculoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_beneficio_vinculo_logs');
        Schema::dropIfExists('rh_beneficio_vinculos');
        Schema::dropIfExists('rh_beneficios');
        Schema::dropIfExists('rh_funcionarios');
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

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->date('data_demissao')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_beneficios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 160);
            $table->string('tipo', 40);
            $table->decimal('valor_padrao', 12, 2)->nullable();
            $table->string('periodicidade', 40)->default('mensal');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_beneficio_vinculos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedBigInteger('beneficio_id');
            $table->date('inicio_em');
            $table->date('termino_em')->nullable();
            $table->decimal('valor', 12, 2);
            $table->decimal('participacao_funcionario', 12, 2)->nullable();
            $table->string('situacao', 20)->default('ativo');
            $table->text('observacao')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_beneficio_vinculo_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vinculo_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_vincular_alterar_valor_com_log_e_cancelar(): void
    {
        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-ben@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO BEN',
            'ativo' => true,
        ]);

        $beneficio = RhBeneficio::query()->create([
            'codigo' => '1',
            'descricao' => 'VALE TRANSPORTE',
            'tipo' => RhBeneficio::TIPO_TRANSPORTE,
            'valor_padrao' => 200,
            'periodicidade' => RhBeneficio::PER_MENSAL,
            'ativo' => true,
        ]);

        $service = app(RhBeneficioService::class);

        $vinculo = $service->vincular([
            'funcionario_id' => $func->getKey(),
            'beneficio_id' => $beneficio->getKey(),
            'inicio_em' => '2026-01-01',
            'valor' => 200,
        ]);

        $this->assertSame(RhBeneficioVinculo::SIT_ATIVO, $vinculo->situacao);
        $this->assertSame(1, RhBeneficioVinculoLog::query()->where('vinculo_id', $vinculo->getKey())->where('acao', 'vincular')->count());

        $service->alterarVinculo($vinculo, ['valor' => 280], 'reajuste sindical');
        $vinculo->refresh();
        $this->assertSame('280.00', number_format((float) $vinculo->valor, 2, '.', ''));

        $logValor = RhBeneficioVinculoLog::query()
            ->where('vinculo_id', $vinculo->getKey())
            ->where('acao', 'alterar_valor')
            ->first();
        $this->assertNotNull($logValor);
        $this->assertSame('200.00', $logValor->antes['valor'] ?? null);
        $this->assertSame('280.00', $logValor->depois['valor'] ?? null);

        $service->cancelarVinculo($vinculo->fresh(), 'desligamento');
        $vinculo->refresh();
        $this->assertSame(RhBeneficioVinculo::SIT_CANCELADO, $vinculo->situacao);
        $this->assertFalse($vinculo->ativo);
        $this->assertSame(1, RhBeneficioVinculoLog::query()->where('vinculo_id', $vinculo->getKey())->where('acao', 'cancelar')->count());
    }
}
