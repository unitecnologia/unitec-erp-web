<?php

namespace Tests\Unit;

use App\Models\RhEscala;
use App\Models\RhEscalaAjuste;
use App\Models\RhEscalaVinculo;
use App\Models\RhFuncionario;
use App\Support\Rh\RhEscalaService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RhEscalaServiceTest extends TestCase
{
    private RhEscalaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_escala_logs');
        Schema::dropIfExists('rh_escala_ajustes');
        Schema::dropIfExists('rh_escala_vinculos');
        Schema::dropIfExists('rh_escala_itens');
        Schema::dropIfExists('rh_escalas');
        Schema::dropIfExists('rh_funcionarios');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_escalas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->string('tipo', 40)->default('personalizada');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_saida')->nullable();
            $table->unsignedSmallInteger('intervalo_minutos')->nullable();
            $table->json('dias_trabalho')->nullable();
            $table->json('dias_folga')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_escala_vinculos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->unsignedBigInteger('escala_id');
            $table->unsignedBigInteger('funcionario_id');
            $table->date('inicio_em');
            $table->date('termino_em')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_escala_ajustes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vinculo_id')->nullable();
            $table->unsignedBigInteger('funcionario_id');
            $table->date('data_ref');
            $table->string('tipo', 20);
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['funcionario_id', 'data_ref']);
        });

        Schema::create('rh_escala_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('escala_id')->nullable();
            $table->unsignedBigInteger('vinculo_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->service = new RhEscalaService;
    }

    public function test_dias_padrao_5x2(): void
    {
        $dias = $this->service->diasPadraoParaTipo(RhEscala::TIPO_5X2);
        $this->assertSame([1, 2, 3, 4, 5], $dias['dias_trabalho']);
        $this->assertSame([0, 6], $dias['dias_folga']);
    }

    public function test_conflito_vinculo_mesmo_funcionario(): void
    {
        $func = RhFuncionario::query()->create(['codigo' => '1', 'nome' => 'TESTE']);
        $escala = RhEscala::query()->create([
            'codigo' => '1',
            'nome' => 'COMERCIAL',
            'tipo' => RhEscala::TIPO_5X2,
            'dias_trabalho' => [1, 2, 3, 4, 5],
            'dias_folga' => [0, 6],
            'ativo' => true,
        ]);

        $this->service->vincular([
            'escala_id' => $escala->getKey(),
            'funcionario_id' => $func->getKey(),
            'inicio_em' => '2026-01-01',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->vincular([
            'escala_id' => $escala->getKey(),
            'funcionario_id' => $func->getKey(),
            'inicio_em' => '2026-02-01',
        ]);
    }

    public function test_ajuste_sobrescreve_dia(): void
    {
        $func = RhFuncionario::query()->create(['codigo' => '2', 'nome' => 'AJUSTE']);
        $escala = RhEscala::query()->create([
            'codigo' => '2',
            'nome' => 'PADRAO',
            'tipo' => RhEscala::TIPO_5X2,
            'dias_trabalho' => [1, 2, 3, 4, 5],
            'dias_folga' => [0, 6],
            'ativo' => true,
        ]);

        $vinculo = $this->service->vincular([
            'escala_id' => $escala->getKey(),
            'funcionario_id' => $func->getKey(),
            'inicio_em' => '2026-03-01',
        ]);

        $this->service->ajustarDia([
            'funcionario_id' => $func->getKey(),
            'vinculo_id' => $vinculo->getKey(),
            'data_ref' => '2026-03-02',
            'tipo' => RhEscalaAjuste::TIPO_FOLGA,
            'motivo' => 'Troca',
        ]);

        $tipo = $this->service->tipoDoDia((int) $func->getKey(), '2026-03-02');
        $this->assertSame(RhEscalaAjuste::TIPO_FOLGA, $tipo);
    }

    public function test_calendario_12x36_alterna_a_partir_inicio_vinculo(): void
    {
        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => '12X36']);
        $escala = RhEscala::query()->create([
            'codigo' => '3',
            'nome' => 'PLANTÃO 12X36',
            'tipo' => RhEscala::TIPO_12X36,
            'hora_entrada' => '07:00:00',
            'hora_saida' => '19:00:00',
            'ativo' => true,
        ]);

        $inicio = '2026-06-10';
        $this->service->vincular([
            'escala_id' => $escala->getKey(),
            'funcionario_id' => $func->getKey(),
            'inicio_em' => $inicio,
        ]);

        $dias = $this->service->calendarioMensal((int) $func->getKey(), 2026, 6);
        $map = collect($dias)->keyBy('data');

        $this->assertSame(RhEscalaAjuste::TIPO_TRABALHO, $map[$inicio]['tipo']);
        $this->assertSame(RhEscalaAjuste::TIPO_FOLGA, $map['2026-06-11']['tipo']);
        $this->assertSame(RhEscalaAjuste::TIPO_TRABALHO, $map['2026-06-12']['tipo']);

        $cicloDia = Carbon::parse($inicio)->diffInDays(Carbon::parse('2026-06-15')) + 1;
        $esperado = ($cicloDia % 2) === 1 ? RhEscalaAjuste::TIPO_TRABALHO : RhEscalaAjuste::TIPO_FOLGA;
        $this->assertSame($esperado, $map['2026-06-15']['tipo']);
    }
}
