<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhTreinamento;
use App\Models\RhTreinamentoParticipante;
use App\Models\RhTreinamentoRealizacao;
use App\Models\RhTreinamentoRealizacaoLog;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhTreinamentoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fluxo Treinamentos sem migrate:fresh — schema mínimo em SQLite testing.
 */
class RhTreinamentoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_treinamento_realizacao_logs');
        Schema::dropIfExists('rh_treinamento_participantes');
        Schema::dropIfExists('rh_treinamento_realizacoes');
        Schema::dropIfExists('rh_treinamentos');
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
            $table->string('cpf', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->date('data_demissao')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_treinamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome', 160);
            $table->text('descricao')->nullable();
            $table->string('tipo', 60)->nullable();
            $table->decimal('carga_horaria', 6, 2)->nullable();
            $table->string('instrutor', 120)->nullable();
            $table->unsignedSmallInteger('periodicidade_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_treinamento_realizacoes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('treinamento_id');
            $table->date('realizado_em');
            $table->date('vencimento_em')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_treinamento_participantes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('realizacao_id');
            $table->unsignedBigInteger('funcionario_id');
            $table->date('vencimento_em')->nullable();
            $table->string('situacao', 20)->default('ativo');
            $table->string('observacao', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['realizacao_id', 'funcionario_id']);
        });

        Schema::create('rh_treinamento_realizacao_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('realizacao_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('rh_anexos', function (Blueprint $table): void {
            $table->id();
            $table->string('anexavel_type');
            $table->unsignedBigInteger('anexavel_id');
            $table->string('categoria', 40);
            $table->string('titulo', 160);
            $table->string('caminho', 255);
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->date('emitido_em')->nullable();
            $table->date('valido_ate')->nullable();
            $table->date('entregue_em')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function test_catalogo_realizar_com_dois_funcionarios_e_vencimento_por_periodicidade(): void
    {
        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-trein@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $f1 = RhFuncionario::query()->create(['codigo' => '1', 'nome' => 'JOAO TREIN', 'ativo' => true]);
        $f2 = RhFuncionario::query()->create(['codigo' => '2', 'nome' => 'MARIA TREIN', 'ativo' => true]);

        $catalogo = RhTreinamento::query()->create([
            'codigo' => RhTreinamento::nextCodigo(),
            'nome' => 'NR-12 MAQUINAS',
            'tipo' => 'SEGURANCA',
            'carga_horaria' => 8,
            'periodicidade_dias' => 365,
            'ativo' => true,
        ]);

        $realizacao = app(RhTreinamentoService::class)->realizar([
            'treinamento_id' => (int) $catalogo->id,
            'realizado_em' => '2026-11-12',
            'funcionario_ids' => [(int) $f1->id, (int) $f2->id],
        ]);

        $this->assertTrue((bool) $realizacao->ativo);
        $this->assertSame('2027-11-12', $realizacao->vencimento_em?->toDateString());
        $this->assertCount(2, $realizacao->participantes);
        $this->assertSame('2027-11-12', $realizacao->participantes->first()->vencimento_em?->toDateString());
        $this->assertDatabaseHas('rh_treinamento_realizacao_logs', [
            'realizacao_id' => $realizacao->id,
            'acao' => 'realizar',
        ]);
    }

    public function test_cancelar_preserva_linha_e_historico(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-trein@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => 'PEDRO', 'ativo' => true]);
        $trein = RhTreinamento::query()->create([
            'codigo' => '10',
            'nome' => 'CIPA',
            'ativo' => true,
        ]);

        $realizacao = app(RhTreinamentoService::class)->realizar([
            'treinamento_id' => (int) $trein->id,
            'funcionario_ids' => [(int) $func->id],
            'realizado_em' => '2026-11-12',
        ]);

        $participante = $realizacao->participantes->first();
        $this->assertNotNull($participante);

        app(RhTreinamentoService::class)->cancelarParticipante($participante, 'Erro de digitação');
        $participante->refresh();

        $this->assertSame(RhTreinamentoParticipante::SIT_CANCELADO, $participante->situacao);
        $this->assertFalse((bool) $participante->ativo);
        $this->assertSame(1, RhTreinamentoParticipante::query()->count());
        $this->assertSame(1, RhTreinamentoRealizacao::query()->count());
        $this->assertGreaterThanOrEqual(2, RhTreinamentoRealizacaoLog::query()->count());
    }

    public function test_anexo_certificado_exige_permissao_treinamentos(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $func = RhFuncionario::query()->create(['codigo' => '4', 'nome' => 'ANA', 'ativo' => true]);
        $trein = RhTreinamento::query()->create(['codigo' => '20', 'nome' => 'PRIMEIROS SOCORROS', 'ativo' => true]);
        $realizacao = RhTreinamentoRealizacao::query()->create([
            'codigo' => '5',
            'treinamento_id' => $trein->id,
            'realizado_em' => '2026-11-12',
            'ativo' => true,
        ]);
        $participante = RhTreinamentoParticipante::query()->create([
            'codigo' => '8',
            'realizacao_id' => $realizacao->id,
            'funcionario_id' => $func->id,
            'situacao' => RhTreinamentoParticipante::SIT_ATIVO,
            'ativo' => true,
        ]);

        $file = UploadedFile::fake()->create('certificado.pdf', 80, 'application/pdf');
        $anexo = (new RhAnexoService)->store($participante, $file, [
            'titulo' => 'CERTIFICADO 8',
            'categoria' => RhAnexo::CAT_TREINAMENTO,
        ]);

        $semPerm = User::query()->create([
            'name' => 'Sem',
            'email' => 'sem-trein@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($semPerm);
        ErpAccess::storeInSession($semPerm, []);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertForbidden();

        $comPerm = User::query()->create([
            'name' => 'Com',
            'email' => 'com-trein@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($comPerm);
        ErpAccess::storeInSession($comPerm, ['rh.treinamentos.access']);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertOk();
    }
}
