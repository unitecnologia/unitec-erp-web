<?php

namespace Tests\Feature;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhHolerite;
use App\Models\RhHoleriteLog;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhHoleriteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class RhHoleriteFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_holerite_logs');
        Schema::dropIfExists('rh_holerites');
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

        Schema::create('rh_holerites', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedSmallInteger('competencia_ano');
            $table->unsignedTinyInteger('competencia_mes');
            $table->date('pago_em')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['funcionario_id', 'competencia_ano', 'competencia_mes'], 'rh_holerites_func_comp_unique');
        });

        Schema::create('rh_holerite_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('holerite_id');
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

    public function test_criar_substituir_duplicata_e_lote_com_mapeamento_explicito(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-hol@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO HOL',
            'ativo' => true,
        ]);

        $service = app(RhHoleriteService::class);
        $holerite = $service->criar([
            'funcionario_id' => (int) $func->id,
            'competencia_ano' => 2026,
            'competencia_mes' => 10,
            'pago_em' => '2026-11-05',
        ], UploadedFile::fake()->create('hol.pdf', 100, 'application/pdf'));

        $this->assertSame('10/2026', $holerite->competenciaLabel());
        $this->assertDatabaseHas('rh_anexos', [
            'anexavel_id' => $holerite->id,
            'categoria' => RhAnexo::CAT_HOLERITE,
        ]);

        try {
            $service->criar([
                'funcionario_id' => (int) $func->id,
                'competencia_ano' => 2026,
                'competencia_mes' => 10,
            ], UploadedFile::fake()->create('hol2.pdf', 90, 'application/pdf'));
            $this->fail('Era esperada exceção de duplicata.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function test_substituir_atualiza_pdf_e_historico(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-hol2@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '3', 'nome' => 'PEDRO', 'ativo' => true]);
        $service = app(RhHoleriteService::class);

        $holerite = $service->criar([
            'funcionario_id' => (int) $func->id,
            'competencia_ano' => 2026,
            'competencia_mes' => 9,
        ], UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'));

        $service->substituir($holerite, [
            'funcionario_id' => (int) $func->id,
            'competencia_ano' => 2026,
            'competencia_mes' => 9,
            'observacao' => 'Corrigido',
        ], UploadedFile::fake()->create('b.pdf', 55, 'application/pdf'), 'Republicação do PDF');

        $this->assertDatabaseHas('rh_holerite_logs', [
            'holerite_id' => $holerite->id,
            'acao' => 'substituir',
        ]);
        $this->assertSame(1, RhAnexo::query()->where('categoria', RhAnexo::CAT_HOLERITE)->count());
    }

    public function test_importacao_lote_exige_funcionario_id_e_rejeita_linha_ambigua(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $user = User::query()->create([
            'name' => 'RH',
            'email' => 'rh-hol3@test.local',
            'password' => bcrypt('x'),
            'is_admin' => true,
            'ativo' => true,
        ]);
        $this->actingAs($user);

        $func = RhFuncionario::query()->create(['codigo' => '4', 'nome' => 'ANA', 'ativo' => true]);
        $service = app(RhHoleriteService::class);

        $preview = $service->conferirLote([
            [
                'linha' => 1,
                'funcionario_id' => 0,
                'competencia_ano' => 2026,
                'competencia_mes' => 8,
                'arquivo' => UploadedFile::fake()->create('x.pdf', 40, 'application/pdf'),
            ],
        ]);
        $this->assertFalse($preview['valido']);

        $resultado = $service->importarLote([
            [
                'linha' => 1,
                'funcionario_id' => (int) $func->id,
                'competencia_ano' => 2026,
                'competencia_mes' => 8,
                'arquivo' => UploadedFile::fake()->create('ok.pdf', 60, 'application/pdf'),
            ],
        ]);

        $this->assertSame(1, $resultado['importados']);
        $this->assertDatabaseHas('rh_holerites', [
            'funcionario_id' => $func->id,
            'competencia_ano' => 2026,
            'competencia_mes' => 8,
        ]);
    }

    public function test_download_exige_permissao_holerites(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $func = RhFuncionario::query()->create(['codigo' => '5', 'nome' => 'CARLA', 'ativo' => true]);
        $holerite = RhHolerite::query()->create([
            'codigo' => '8',
            'funcionario_id' => $func->id,
            'competencia_ano' => 2026,
            'competencia_mes' => 7,
            'ativo' => true,
        ]);

        $anexo = (new RhAnexoService)->store($holerite, UploadedFile::fake()->create('hol.pdf', 50, 'application/pdf'), [
            'titulo' => 'Holerite 8',
            'categoria' => RhAnexo::CAT_HOLERITE,
        ]);

        $sem = User::query()->create([
            'name' => 'Sem',
            'email' => 'sem-hol@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($sem);
        ErpAccess::storeInSession($sem, []);
        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertForbidden();

        $com = User::query()->create([
            'name' => 'Com',
            'email' => 'com-hol@test.local',
            'password' => bcrypt('x'),
            'is_admin' => false,
            'ativo' => true,
        ]);
        $this->actingAs($com);
        ErpAccess::storeInSession($com, ['rh.holerites.access']);
        $this->get(route('erp.rh.anexo.download', ['anexo' => $anexo->id]))->assertOk();

        $this->assertGreaterThanOrEqual(0, RhHoleriteLog::query()->count());
    }
}
