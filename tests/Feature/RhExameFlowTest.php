<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhExame;
use App\Models\RhExameLog;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhExameService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RhExameFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_exame_logs');
        Schema::dropIfExists('rh_exames');
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
            $table->boolean('ativo')->default(true);
            $table->date('data_demissao')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_exames', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->string('tipo', 40);
            $table->date('realizado_em');
            $table->date('proximo_em')->nullable();
            $table->string('medico_nome', 120)->nullable();
            $table->string('medico_crm', 40)->nullable();
            $table->string('conclusao', 40)->default('nao_informada');
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_exame_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('exame_id');
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

    public function test_crud_datas_anexo_e_historico(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-exame@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO EXAME',
            'ativo' => true,
        ]);

        $service = app(RhExameService::class);
        $exame = $service->criar([
            'funcionario_id' => (int) $func->id,
            'tipo' => RhExame::TIPO_PERIODICO,
            'realizado_em' => '2026-11-01',
            'proximo_em' => '2027-11-01',
            'medico_nome' => 'Dr Silva',
            'medico_crm' => 'CRM/PR 123',
            'conclusao' => RhExame::CONCL_APTO,
            'observacao' => 'Controle PCMSO',
        ], UploadedFile::fake()->create('aso.pdf', 100, 'application/pdf'));

        $this->assertSame('2026-11-01', $exame->realizado_em?->toDateString());
        $this->assertSame('2027-11-01', $exame->proximo_em?->toDateString());
        $this->assertSame(RhExame::SIT_OK, $exame->situacaoPrazo(30));
        $this->assertDatabaseHas('rh_anexos', [
            'anexavel_id' => $exame->id,
            'categoria' => RhAnexo::CAT_EXAME,
        ]);

        $semPrazo = $service->criar([
            'funcionario_id' => (int) $func->id,
            'tipo' => RhExame::TIPO_ADMISSIONAL,
            'realizado_em' => '2026-11-10',
            'conclusao' => RhExame::CONCL_APTO,
        ]);
        $this->assertNull($semPrazo->proximo_em);
        $this->assertSame(RhExame::SIT_SEM, $semPrazo->situacaoPrazo());

        $pendente = $service->criar([
            'funcionario_id' => (int) $func->id,
            'tipo' => RhExame::TIPO_PERIODICO,
            'realizado_em' => '2025-01-01',
            'proximo_em' => '2025-12-01',
            'conclusao' => RhExame::CONCL_APTO,
        ]);
        $this->assertSame(RhExame::SIT_PENDENTE, $pendente->situacaoPrazo());

        $service->alterar($exame, [
            'conclusao' => RhExame::CONCL_APTO_RESTRICAO,
            'observacao' => 'Atualizado',
        ], null, 'correção administrativa');

        $service->inativar($exame, 'arquivo antigo');
        $this->assertFalse((bool) $exame->fresh()->ativo);
        $this->assertSame(3, RhExame::query()->count());
        $this->assertGreaterThanOrEqual(4, RhExameLog::query()->count());
    }

    public function test_download_aso_exige_permissao_exames_e_audita(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $func = RhFuncionario::query()->create(['codigo' => '2', 'nome' => 'MARIA', 'ativo' => true]);
        $exame = RhExame::query()->create([
            'codigo' => '9',
            'funcionario_id' => $func->id,
            'tipo' => RhExame::TIPO_PERIODICO,
            'realizado_em' => '2026-11-01',
            'conclusao' => RhExame::CONCL_APTO,
            'ativo' => true,
        ]);

        $anexo = (new RhAnexoService)->store($exame, UploadedFile::fake()->create('aso.pdf', 50, 'application/pdf'), [
            'titulo' => 'ASO 9',
            'categoria' => RhAnexo::CAT_EXAME,
        ]);

        $sem = User::query()->create([
            'name' => 'Sem',
            'email' => 'sem-exame@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($sem);
        ErpAccess::storeInSession($sem, []);
        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertForbidden();

        $com = User::query()->create([
            'name' => 'Com',
            'email' => 'com-exame@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($com);
        ErpAccess::storeInSession($com, ['rh.exames.access']);
        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertOk();

        $this->assertDatabaseHas('rh_exame_logs', [
            'exame_id' => $exame->id,
            'acao' => 'acessar_anexo',
            'user_id' => $com->id,
        ]);
    }
}
