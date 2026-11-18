<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhOcorrencia;
use App\Models\RhOcorrenciaLog;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhOcorrenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RhOcorrenciaFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_ocorrencia_logs');
        Schema::dropIfExists('rh_ocorrencias');
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

        Schema::create('rh_ocorrencias', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->string('tipo', 40);
            $table->date('ocorrido_em');
            $table->string('descricao', 1000);
            $table->string('responsavel', 120)->nullable();
            $table->string('situacao', 20)->default('registrada');
            $table->boolean('acesso_restrito')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_ocorrencia_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ocorrencia_id');
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

    public function test_crud_cancelar_anexo_e_logs(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-ocorrencia@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);
        ErpAccess::storeInSession($user, ['rh.ocorrencias.access', 'rh.ocorrencias.create', 'rh.ocorrencias.update', 'rh.ocorrencias.delete']);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO OC',
            'ativo' => true,
        ]);

        $service = app(RhOcorrenciaService::class);
        $registro = $service->criar([
            'funcionario_id' => (int) $func->id,
            'tipo' => RhOcorrencia::TIPO_ATRASO,
            'ocorrido_em' => '2026-11-10',
            'descricao' => 'Atraso na entrada',
            'responsavel' => 'SUPERVISOR',
            'acesso_restrito' => false,
        ], UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'));

        $this->assertSame(RhOcorrencia::SIT_REGISTRADA, $registro->situacao);
        $this->assertDatabaseHas('rh_anexos', [
            'anexavel_id' => $registro->id,
            'categoria' => RhAnexo::CAT_OCORRENCIA,
        ]);

        $service->alterar($registro, [
            'descricao' => 'Atraso justificado administrativamente',
        ], null, 'ajuste de texto');
        $this->assertSame(RhOcorrencia::SIT_CORRIGIDA, $registro->fresh()->situacao);
        $this->assertDatabaseHas('rh_ocorrencia_logs', [
            'ocorrencia_id' => $registro->id,
            'acao' => 'corrigir',
        ]);

        $outro = $service->criar([
            'funcionario_id' => (int) $func->id,
            'tipo' => RhOcorrencia::TIPO_ELOGIO,
            'ocorrido_em' => '2026-11-11',
            'descricao' => 'Bom desempenho',
        ]);
        $service->cancelar($outro, 'duplicidade');
        $this->assertSame(RhOcorrencia::SIT_CANCELADA, $outro->fresh()->situacao);
        $this->assertFalse((bool) $outro->fresh()->ativo);

        $this->assertGreaterThanOrEqual(4, RhOcorrenciaLog::query()->count());
    }

    public function test_snapshot_restrito_sem_access_na_sessao(): void
    {
        $user = User::query()->create([
            'name' => 'Print',
            'email' => 'print-oc@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($user);
        ErpAccess::storeInSession($user, ['rh.ocorrencias.print']);

        $func = RhFuncionario::query()->create(['codigo' => '2', 'nome' => 'MARIA', 'ativo' => true]);
        $oc = RhOcorrencia::query()->create([
            'codigo' => '5',
            'funcionario_id' => $func->id,
            'tipo' => RhOcorrencia::TIPO_OUTROS,
            'ocorrido_em' => '2026-11-01',
            'descricao' => 'Detalhe sigiloso',
            'situacao' => RhOcorrencia::SIT_REGISTRADA,
            'acesso_restrito' => true,
            'ativo' => true,
        ]);

        $snap = app(RhOcorrenciaService::class)->snapshot($oc);
        $this->assertNull($snap['descricao']);
        $this->assertFalse(RhOcorrenciaService::podeVerConteudoRestrito());

        ErpAccess::storeInSession($user, ['rh.ocorrencias.access', 'rh.ocorrencias.print']);
        $this->assertTrue(RhOcorrenciaService::podeVerConteudoRestrito());
        $snap2 = app(RhOcorrenciaService::class)->snapshot($oc);
        $this->assertSame('Detalhe sigiloso', $snap2['descricao']);
    }

    public function test_anexo_ocorrencia_via_rh_anexo_service(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'anexo-oc@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => 'PEDRO', 'ativo' => true]);
        $oc = RhOcorrencia::query()->create([
            'codigo' => '8',
            'funcionario_id' => $func->id,
            'tipo' => RhOcorrencia::TIPO_JUSTIFICATIVA,
            'ocorrido_em' => '2026-11-02',
            'descricao' => 'Falta justificada',
            'situacao' => RhOcorrencia::SIT_REGISTRADA,
            'ativo' => true,
        ]);

        $anexo = app(RhAnexoService::class)->store($oc, UploadedFile::fake()->create('doc.pdf', 40, 'application/pdf'), [
            'titulo' => 'OC 8',
            'categoria' => RhAnexo::CAT_OCORRENCIA,
        ]);

        $this->assertSame(RhAnexo::CAT_OCORRENCIA, $anexo->categoria);
        $this->assertTrue(Storage::disk(RhAnexoService::DISK)->exists($anexo->caminho));
    }
}
