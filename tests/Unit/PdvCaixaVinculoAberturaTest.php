<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\Financeiro\CaixaLivroMovimentoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvCaixaVinculoAberturaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
    }

    public function test_sem_vinculo_nao_opera_pdv_nem_usa_geral(): void
    {
        $empresa = $this->empresa();
        $this->caixaPdv('CAIXA PDV 1', 10);
        CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        $this->assertNull($user->caixaPdvVinculadoId((int) $empresa->id));
        $this->assertFalse($user->podeOperarComCaixaPdv((int) $empresa->id));
        $this->assertSame(
            (int) CaixaConta::ensureCaixaGeral()->id,
            (int) $user->defaultCaixaContaId((int) $empresa->id),
        );
    }

    public function test_vinculo_pdv_resolve_conta_correta(): void
    {
        $empresa = $this->empresa();
        $caixa = $this->caixaPdv('CAIXA PDV 1', 10);
        CaixaConta::ensureCaixaGeral();

        $user = $this->usuario($empresa);

        DB::table('caixa_conta_user')->insert([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $caixa->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame((int) $caixa->id, (int) $user->caixaPdvVinculadoId((int) $empresa->id));
        $this->assertTrue($user->podeOperarComCaixaPdv((int) $empresa->id));
    }

    public function test_banco_nao_e_elegivel_f8_e_pdv_bloqueia_mutacao_retaguarda(): void
    {
        $svc = app(CaixaLivroMovimentoService::class);

        $this->assertNotContains(CaixaConta::TIPO_BANCO, $svc->tiposElegiveis());
        $this->assertContains(CaixaConta::TIPO_SUBCAIXA, $svc->tiposElegiveis());

        $pdv = $this->caixaPdv('CAIXA PDV X', 99);
        $this->expectException(\InvalidArgumentException::class);
        $svc->assertContaRetaguardaMutavel((int) $pdv->id);
    }

    public function test_banco_assert_conta_elegivel_bloqueia_abertura(): void
    {
        $empresa = $this->empresa();
        $banco = CaixaConta::query()->create([
            'codigo' => 50,
            'nome' => 'CONTA BANCO TESTE',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
            'empresa_id' => $empresa->id,
        ]);

        $svc = app(CaixaLivroMovimentoService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contas bancárias não possuem abertura ou fechamento');
        $svc->assertContaElegivel((int) $banco->id, (int) $empresa->id);
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'nome' => 'EMPRESA PDV VINC '.Str::random(4),
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
