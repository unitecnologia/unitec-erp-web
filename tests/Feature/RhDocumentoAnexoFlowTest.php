<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fluxo de anexos RH sem migrate:fresh — schema mínimo em SQLite testing.
 */
class RhDocumentoAnexoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
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

        Schema::dropIfExists('user_permissions');
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
            $table->timestamps();
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
            $table->index(['anexavel_type', 'anexavel_id']);
        });
    }

    public function test_grava_anexo_no_disco_local_e_faz_download_autenticado(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $funcionario = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO TESTE',
            'ativo' => true,
        ]);

        $file = UploadedFile::fake()->create('cnh.pdf', 120, 'application/pdf');
        $anexo = (new RhAnexoService)->store($funcionario, $file, [
            'titulo' => 'CNH',
            'categoria' => RhAnexo::CAT_DOCUMENTO,
            'emitido_em' => '2026-01-10',
            'valido_ate' => '2027-01-10',
        ]);

        $this->assertDatabaseHas('rh_anexos', [
            'id' => $anexo->id,
            'categoria' => RhAnexo::CAT_DOCUMENTO,
            'titulo' => 'CNH',
            'anexavel_id' => $funcionario->id,
        ]);

        Storage::disk(RhAnexoService::DISK)->assertExists((string) $anexo->caminho);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-rh-doc@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($admin);

        $response = $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_download_sem_permissao_retorna_403(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $funcionario = RhFuncionario::query()->create([
            'codigo' => '2',
            'nome' => 'MARIA',
            'ativo' => true,
        ]);

        $file = UploadedFile::fake()->create('rg.pdf', 80, 'application/pdf');
        $anexo = (new RhAnexoService)->store($funcionario, $file, [
            'titulo' => 'RG',
            'categoria' => RhAnexo::CAT_DOCUMENTO,
        ]);

        $user = User::query()->create([
            'name' => 'Sem Perm',
            'email' => 'user-rh-doc@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($user);
        ErpAccess::storeInSession($user, []);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))
            ->assertForbidden();
    }

    public function test_scope_documentos_filtra_categoria(): void
    {
        $funcionario = RhFuncionario::query()->create([
            'codigo' => '3',
            'nome' => 'PEDRO',
            'ativo' => true,
        ]);

        $doc = $funcionario->anexos()->create([
            'categoria' => RhAnexo::CAT_DOCUMENTO,
            'titulo' => 'DOC',
            'caminho' => 'rh-anexos/documento/x.pdf',
            'ativo' => true,
        ]);

        $funcionario->anexos()->create([
            'categoria' => RhAnexo::CAT_EPI,
            'titulo' => 'EPI',
            'caminho' => 'rh-anexos/epi/y.pdf',
            'ativo' => true,
        ]);

        $ids = RhAnexo::query()->documentos()->pluck('id')->all();

        $this->assertContains($doc->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_update_meta_substitui_arquivo_e_exclui_antigo(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $funcionario = RhFuncionario::query()->create([
            'codigo' => '4',
            'nome' => 'ANA',
            'ativo' => true,
        ]);

        $service = new RhAnexoService;
        $anexo = $service->store($funcionario, UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'), [
            'titulo' => 'DOC A',
            'categoria' => RhAnexo::CAT_DOCUMENTO,
        ]);
        $oldPath = (string) $anexo->caminho;

        $service->updateMeta($anexo, [
            'titulo' => 'DOC B',
            'valido_ate' => '2027-05-01',
        ], UploadedFile::fake()->create('b.pdf', 60, 'application/pdf'));

        $anexo->refresh();
        $this->assertSame('DOC B', $anexo->titulo);
        $this->assertNotSame($oldPath, $anexo->caminho);
        Storage::disk(RhAnexoService::DISK)->assertMissing($oldPath);
        Storage::disk(RhAnexoService::DISK)->assertExists((string) $anexo->caminho);

        $service->delete($anexo);
        $this->assertDatabaseMissing('rh_anexos', ['id' => $anexo->id]);
    }
}
