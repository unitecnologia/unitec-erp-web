<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhUniforme;
use App\Models\RhUniformeEntrega;
use App\Models\RhUniformeEntregaLog;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhUniformeEntregaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fluxo Uniformes sem migrate:fresh — schema mínimo em SQLite testing.
 */
class RhUniformeEntregaFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_uniforme_entrega_logs');
        Schema::dropIfExists('rh_uniforme_entregas');
        Schema::dropIfExists('rh_uniformes');
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

        Schema::create('rh_uniformes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 160);
            $table->string('tipo', 40)->nullable();
            $table->string('tamanho', 20)->nullable();
            $table->string('cor', 40)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_uniforme_entregas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedBigInteger('uniforme_id');
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->date('entregue_em');
            $table->string('situacao', 20)->default('entregue');
            $table->text('observacao')->nullable();
            $table->string('motivo_movimentacao', 255)->nullable();
            $table->date('devolvido_em')->nullable();
            $table->unsignedBigInteger('substitui_id')->nullable();
            $table->unsignedBigInteger('substituido_por_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_uniforme_entrega_logs', function (Blueprint $table): void {
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

    public function test_catalogo_entrega_lote_devolver_trocar_substituir_e_cancelar(): void
    {
        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-unif@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO UNIFORME',
            'ativo' => true,
        ]);

        $camisaM = RhUniforme::query()->create([
            'codigo' => RhUniforme::nextCodigo(),
            'descricao' => 'CAMISA POLO',
            'tipo' => 'camisa',
            'tamanho' => 'M',
            'cor' => 'AZUL',
            'ativo' => true,
        ]);

        $camisaG = RhUniforme::query()->create([
            'codigo' => RhUniforme::nextCodigo(),
            'descricao' => 'CAMISA POLO',
            'tipo' => 'camisa',
            'tamanho' => 'G',
            'cor' => 'AZUL',
            'ativo' => true,
        ]);

        $calca = RhUniforme::query()->create([
            'codigo' => RhUniforme::nextCodigo(),
            'descricao' => 'CALCA SOCIAL',
            'tipo' => 'calça',
            'tamanho' => '42',
            'cor' => 'PRETA',
            'ativo' => true,
        ]);

        $this->assertSame('1 — CAMISA POLO | camisa | Tam. M | AZUL', $camisaM->labelSelect());

        $service = app(RhUniformeEntregaService::class);

        $lote = $service->entregarLote([
            'funcionario_id' => (int) $func->id,
            'entregue_em' => '2026-11-13',
            'observacao' => 'Kit inicial',
            'itens' => [
                ['uniforme_id' => (int) $camisaM->id, 'quantidade' => 1],
                ['uniforme_id' => (int) $calca->id, 'quantidade' => 2],
            ],
        ]);

        $this->assertCount(2, $lote);
        $this->assertSame(RhUniformeEntrega::SIT_ENTREGUE, $lote[0]->situacao);
        $this->assertSame(2, (int) $lote[1]->quantidade);
        $this->assertDatabaseHas('rh_uniforme_entrega_logs', [
            'entrega_id' => $lote[0]->id,
            'acao' => 'entregar',
        ]);

        $service->devolver($lote[1], '2026-11-14', 'Não usou');
        $lote[1]->refresh();
        $this->assertSame(RhUniformeEntrega::SIT_DEVOLVIDO, $lote[1]->situacao);
        $this->assertSame('2026-11-14', $lote[1]->devolvido_em?->toDateString());

        $trocada = $service->trocar($lote[0], [
            'uniforme_id' => (int) $camisaG->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-15',
            'motivo_movimentacao' => 'TAMANHO ERRADO',
        ]);

        $lote[0]->refresh();
        $this->assertSame(RhUniformeEntrega::SIT_TROCADO, $lote[0]->situacao);
        $this->assertSame((int) $trocada->id, (int) $lote[0]->substituido_por_id);
        $this->assertSame(RhUniformeEntrega::SIT_ENTREGUE, $trocada->situacao);
        $this->assertSame((int) $camisaG->id, (int) $trocada->uniforme_id);
        $this->assertDatabaseHas('rh_uniforme_entrega_logs', [
            'entrega_id' => $lote[0]->id,
            'acao' => 'trocar',
        ]);

        $nova = $service->substituir($trocada, [
            'uniforme_id' => (int) $camisaG->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-16',
            'motivo_movimentacao' => 'DESGASTE',
        ]);

        $trocada->refresh();
        $this->assertSame(RhUniformeEntrega::SIT_SUBSTITUIDO, $trocada->situacao);
        $this->assertSame((int) $nova->id, (int) $trocada->substituido_por_id);
        $this->assertSame(RhUniformeEntrega::SIT_ENTREGUE, $nova->situacao);
        $this->assertDatabaseHas('rh_uniforme_entrega_logs', [
            'entrega_id' => $trocada->id,
            'acao' => 'substituir',
        ]);

        $service->cancelar($nova, 'Erro de digitação');
        $nova->refresh();
        $this->assertSame(RhUniformeEntrega::SIT_CANCELADO, $nova->situacao);
        $this->assertFalse((bool) $nova->ativo);
        $this->assertDatabaseHas('rh_uniforme_entregas', ['id' => $nova->id]);
        $this->assertGreaterThanOrEqual(5, RhUniformeEntregaLog::query()->count());
    }

    public function test_anexo_comprovante_exige_permissao_uniformes(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $func = RhFuncionario::query()->create([
            'codigo' => '2',
            'nome' => 'MARIA UNIFORME',
            'ativo' => true,
        ]);
        $uniforme = RhUniforme::query()->create([
            'codigo' => '2',
            'descricao' => 'JALECO',
            'tipo' => 'jaleco',
            'tamanho' => 'G',
            'ativo' => true,
        ]);
        $entrega = RhUniformeEntrega::query()->create([
            'codigo' => '10',
            'funcionario_id' => $func->id,
            'uniforme_id' => $uniforme->id,
            'quantidade' => 1,
            'entregue_em' => '2026-11-13',
            'situacao' => RhUniformeEntrega::SIT_ENTREGUE,
            'ativo' => true,
        ]);

        $file = UploadedFile::fake()->create('comprovante.pdf', 80, 'application/pdf');
        $anexo = (new RhAnexoService)->store($entrega, $file, [
            'titulo' => 'COMPROVANTE UNIFORME 10',
            'categoria' => RhAnexo::CAT_UNIFORME,
            'entregue_em' => '2026-11-13',
        ]);

        $semPerm = User::query()->create([
            'name' => 'Sem',
            'email' => 'sem-unif@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($semPerm);
        ErpAccess::storeInSession($semPerm, []);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertForbidden();

        $comPerm = User::query()->create([
            'name' => 'Com',
            'email' => 'com-unif@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($comPerm);
        ErpAccess::storeInSession($comPerm, ['rh.uniformes.access']);

        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertOk();
    }

    public function test_nao_apaga_historico_ao_cancelar(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-unif@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => 'PEDRO', 'ativo' => true]);
        $uniforme = RhUniforme::query()->create([
            'codigo' => '3',
            'descricao' => 'CAMISA',
            'tamanho' => 'P',
            'ativo' => true,
        ]);

        $entrega = app(RhUniformeEntregaService::class)->entregar([
            'funcionario_id' => (int) $func->id,
            'uniforme_id' => (int) $uniforme->id,
        ]);

        app(RhUniformeEntregaService::class)->cancelar($entrega, 'teste');
        $this->assertSame(1, RhUniformeEntrega::query()->count());
        $this->assertSame(RhUniformeEntrega::SIT_CANCELADO, $entrega->fresh()->situacao);
    }
}
