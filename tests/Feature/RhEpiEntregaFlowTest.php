<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhEpi;
use App\Models\RhEpiEntrega;
use App\Models\RhEpiEntregaLog;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhEpiEntregaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fluxo EPIs sem migrate:fresh — schema mínimo em SQLite testing.
 */
class RhEpiEntregaFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_epi_entrega_logs');
        Schema::dropIfExists('rh_epi_entregas');
        Schema::dropIfExists('rh_epis');
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

        Schema::create('rh_epis', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 160);
            $table->string('ca_numero', 40)->nullable();
            $table->date('ca_valido_ate')->nullable();
            $table->string('fabricante', 120)->nullable();
            $table->date('validade_equipamento')->nullable();
            $table->unsignedSmallInteger('intervalo_troca_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_epi_entregas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedBigInteger('epi_id');
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->date('entregue_em');
            $table->date('previsao_troca')->nullable();
            $table->string('situacao', 20)->default('entregue');
            $table->text('observacao')->nullable();
            $table->string('motivo_substituicao', 255)->nullable();
            $table->date('devolvido_em')->nullable();
            $table->unsignedBigInteger('substitui_id')->nullable();
            $table->unsignedBigInteger('substituido_por_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_epi_entrega_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entrega_id');
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

    public function test_cadastro_entrega_devolucao_substituicao_e_auditoria(): void
    {
        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-epi@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO EPI',
            'ativo' => true,
        ]);

        $epi = RhEpi::query()->create([
            'codigo' => '1',
            'descricao' => 'LUVA NITRILICA',
            'ca_numero' => '12345',
            'intervalo_troca_dias' => 30,
            'ativo' => true,
        ]);

        $service = app(RhEpiEntregaService::class);
        $entrega = $service->entregar([
            'funcionario_id' => (int) $func->id,
            'epi_id' => (int) $epi->id,
            'quantidade' => 2,
            'entregue_em' => '2026-11-12',
        ]);

        $this->assertSame(RhEpiEntrega::SIT_ENTREGUE, $entrega->situacao);
        $this->assertSame('2026-12-12', $entrega->previsao_troca?->toDateString());
        $this->assertDatabaseHas('rh_epi_entrega_logs', [
            'entrega_id' => $entrega->id,
            'acao' => 'entregar',
        ]);

        $service->devolver($entrega, '2026-11-13', 'Troca programada');
        $entrega->refresh();
        $this->assertSame(RhEpiEntrega::SIT_DEVOLVIDO, $entrega->situacao);
        $this->assertSame('2026-11-13', $entrega->devolvido_em?->toDateString());

        $aberta = $service->entregar([
            'funcionario_id' => (int) $func->id,
            'epi_id' => (int) $epi->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-14',
            'previsao_troca' => '2026-12-14',
        ]);

        $nova = $service->entregar([
            'funcionario_id' => (int) $func->id,
            'epi_id' => (int) $epi->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-15',
            'substitui_id' => (int) $aberta->id,
            'motivo_substituicao' => 'DESGASTE',
        ]);

        $aberta->refresh();
        $this->assertSame(RhEpiEntrega::SIT_SUBSTITUIDO, $aberta->situacao);
        $this->assertSame((int) $nova->id, (int) $aberta->substituido_por_id);
        $this->assertSame(RhEpiEntrega::SIT_ENTREGUE, $nova->situacao);

        $service->cancelar($nova, 'Erro de digitação');
        $nova->refresh();
        $this->assertSame(RhEpiEntrega::SIT_CANCELADO, $nova->situacao);
        $this->assertFalse((bool) $nova->ativo);
        $this->assertDatabaseHas('rh_epi_entregas', ['id' => $nova->id]);
        $this->assertGreaterThanOrEqual(4, RhEpiEntregaLog::query()->count());
    }

    public function test_anexo_comprovante_exige_permissao_epis(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $func = RhFuncionario::query()->create([
            'codigo' => '2',
            'nome' => 'MARIA EPI',
            'ativo' => true,
        ]);
        $epi = RhEpi::query()->create([
            'codigo' => '2',
            'descricao' => 'OCULOS',
            'ativo' => true,
        ]);
        $entrega = RhEpiEntrega::query()->create([
            'codigo' => '10',
            'funcionario_id' => $func->id,
            'epi_id' => $epi->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-12',
            'situacao' => RhEpiEntrega::SIT_ENTREGUE,
            'ativo' => true,
        ]);

        $file = UploadedFile::fake()->create('comprovante.pdf', 80, 'application/pdf');
        $anexo = (new RhAnexoService)->store($entrega, $file, [
            'titulo' => 'COMPROVANTE EPI 10',
            'categoria' => RhAnexo::CAT_EPI,
            'entregue_em' => '2026-11-12',
        ]);

        $semPerm = User::query()->create([
            'name' => 'Sem',
            'email' => 'sem-epi@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($semPerm);
        ErpAccess::storeInSession($semPerm, []);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertForbidden();

        $comPerm = User::query()->create([
            'name' => 'Com',
            'email' => 'com-epi@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($comPerm);
        ErpAccess::storeInSession($comPerm, ['rh.epis.access']);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertOk();
    }

    public function test_nao_apaga_historico_ao_cancelar(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-epi@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => 'PEDRO', 'ativo' => true]);
        $epi = RhEpi::query()->create(['codigo' => '3', 'descricao' => 'CAPACETE', 'ativo' => true]);

        $entrega = app(RhEpiEntregaService::class)->entregar([
            'funcionario_id' => (int) $func->id,
            'epi_id' => (int) $epi->id,
        ]);

        app(RhEpiEntregaService::class)->cancelar($entrega, 'teste');
        $this->assertSame(1, RhEpiEntrega::query()->count());
        $this->assertSame(RhEpiEntrega::SIT_CANCELADO, $entrega->fresh()->situacao);
    }
}
