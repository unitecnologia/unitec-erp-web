<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvCaixaPadraoResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
    }

    public function test_sem_vinculo_usa_caixa_geral_como_padrao(): void
    {
        $empresa = $this->empresa();
        $this->caixaPdv('CAIXA PDV 1', 10);
        $this->caixaPdv('CAIXA PDV 2', 20);
        $geral = CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        $this->assertSame((int) $geral->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
        $this->assertSame(CaixaConta::NOME_CAIXA_GERAL, $user->defaultCaixaContaNome((int) $empresa->id));
    }

    public function test_com_vinculo_respeita_caixa_padrao(): void
    {
        $empresa = $this->empresa();
        $caixaA = $this->caixaPdv('CAIXA A', 10);
        $caixaB = $this->caixaPdv('CAIXA B', 20);
        CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        DB::table('caixa_conta_user')->insert([
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaA->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaB->id,
                'is_padrao' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame((int) $caixaB->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
        $this->assertSame((int) $caixaB->id, (int) $user->caixaPdvVinculadoId((int) $empresa->id));
    }

    public function test_vinculo_sem_is_padrao_usa_primeiro_explicito_nao_outro_pdv(): void
    {
        $empresa = $this->empresa();
        $caixaA = $this->caixaPdv('CAIXA A', 10);
        $caixaB = $this->caixaPdv('CAIXA B', 20);
        $this->caixaPdv('CAIXA C NAO VINCULADO', 30);
        CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        DB::table('caixa_conta_user')->insert([
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaB->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaA->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame((int) $caixaA->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
        $this->assertSame((int) $caixaA->id, (int) $user->caixaPdvVinculadoId((int) $empresa->id));
    }

    public function test_sem_vinculo_nao_pode_operar_pdv_sem_caixa_vinculado(): void
    {
        $empresa = $this->empresa();
        $this->caixaPdv('CAIXA PDV 1', 10);
        CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        $this->assertFalse($user->podeOperarComCaixaPdv((int) $empresa->id));
        $this->assertNull($user->caixaPdvVinculadoId((int) $empresa->id));
        $this->assertNotEmpty($user->accessibleCaixaContaIds((int) $empresa->id));
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'nome' => 'EMPRESA PDV CAIXA '.Str::random(4),
            'ativo' => true,
        ]);
    }

    private function usuario(Empresa $empresa): User
    {
        return User::query()->create([
            'name' => 'OP '.Str::random(4),
            'password' => Hash::make('secret'),
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
    }

    private function caixaPdv(string $nome, int $codigo): CaixaConta
    {
        return CaixaConta::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => CaixaConta::TIPO_PDV,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    private function criarSchema(): void
    {
        foreach (['caixa_conta_user', 'users', 'caixa_contas', 'empresas'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('PDV');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('password');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('caixa_conta_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->boolean('is_padrao')->default(false);
            $table->timestamps();
        });
    }
}
