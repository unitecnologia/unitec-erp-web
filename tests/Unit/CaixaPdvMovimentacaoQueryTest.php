<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\User;
use App\Support\Erp\Queries\CaixaPdvMovimentacaoQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaixaPdvMovimentacaoQueryTest extends TestCase
{
    private Empresa $empresa;

    private User $user;

    private CaixaConta $pdv1;

    private CaixaConta $pdv2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();

        $this->empresa = Empresa::query()->create(['nome' => 'EMP TEST', 'ativo' => true]);
        $this->user = User::query()->create([
            'name' => 'OP',
            'password' => Hash::make('x'),
            'empresa_id' => $this->empresa->id,
            'ativo' => true,
            'is_admin' => false,
        ]);
        $this->pdv1 = CaixaConta::query()->create([
            'codigo' => 2,
            'nome' => 'CAIXA PDV 1',
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
            'sistema' => false,
        ]);
        $this->pdv2 = CaixaConta::query()->create([
            'codigo' => 3,
            'nome' => 'CAIXA PDV 2',
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    public function test_filtro_pdv_so_sessao_com_caixa_explicito(): void
    {
        $sPdv1 = $this->sessao($this->pdv1->id, venda: 10);
        $sNull = $this->sessao(null, venda: 99);
        $this->vincularPadrao($this->pdv1->id);

        $linhas = DB::query()->fromSub((new CaixaPdvMovimentacaoQuery((int) $this->pdv1->id))->build(), 'x')->get();
        $sessoes = $linhas->pluck('pdv_sessao_id')->filter()->unique()->values()->all();

        $this->assertContains($sPdv1, $sessoes);
        $this->assertNotContains($sNull, $sessoes);
        $this->assertTrue($linhas->contains(fn ($r) => (float) $r->entrada === 10.0));
        $this->assertFalse($linhas->contains(fn ($r) => (float) $r->entrada === 99.0));
    }

    public function test_mudar_padrao_nao_move_historico_entre_pdvs(): void
    {
        $this->sessao($this->pdv1->id, venda: 10);
        $this->sessao(null, venda: 50);
        $this->vincularPadrao($this->pdv1->id);

        $antes = $this->agg((int) $this->pdv1->id);

        DB::table('caixa_conta_user')->where('user_id', $this->user->id)->update([
            'caixa_conta_id' => $this->pdv2->id,
            'is_padrao' => true,
        ]);

        $depois = $this->agg((int) $this->pdv1->id);
        $pdv2 = $this->agg((int) $this->pdv2->id);

        $this->assertSame($antes, $depois);
        $this->assertSame(0, $pdv2['vendas']);
    }

    public function test_filtro_sessoes_sem_conta_isola_legado(): void
    {
        $this->sessao($this->pdv1->id, venda: 10);
        $sNull = $this->sessao(null, venda: 50);
        $this->vincularPadrao($this->pdv1->id);

        $linhas = DB::query()
            ->fromSub((new CaixaPdvMovimentacaoQuery(CaixaPdvMovimentacaoQuery::FILTRO_SESSOES_SEM_CONTA))->build(), 'x')
            ->get();

        $sessoes = $linhas->pluck('pdv_sessao_id')->filter()->unique()->values()->all();
        $this->assertSame([$sNull], $sessoes);
        $this->assertTrue($linhas->contains(fn ($r) => (float) $r->entrada === 50.0));
        $this->assertFalse($linhas->contains(fn ($r) => (float) $r->entrada === 10.0));
    }

    /**
     * @return array{linhas: int, vendas: int, entrada: float}
     */
    private function agg(int $contaId): array
    {
        $row = DB::query()->fromSub((new CaixaPdvMovimentacaoQuery($contaId))->build(), 'x')
            ->selectRaw('COUNT(*) linhas, SUM(CASE WHEN historico LIKE ? THEN 1 ELSE 0 END) vendas, ROUND(COALESCE(SUM(entrada),0),2) entrada', ['VENDA PDV%'])
            ->first();

        return [
            'linhas' => (int) ($row->linhas ?? 0),
            'vendas' => (int) ($row->vendas ?? 0),
            'entrada' => (float) ($row->entrada ?? 0),
        ];
    }

    private function vincularPadrao(int $caixaId): void
    {
        DB::table('caixa_conta_user')->insert([
            'user_id' => $this->user->id,
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $caixaId,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sessao(?int $caixaContaId, float $venda): int
    {
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $this->user->id,
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $caixaContaId,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #'.$sessao->id,
            'forma_pagamento' => 'DINHEIRO',
            'entrada' => $venda,
            'saida' => 0,
        ]);

        return (int) $sessao->id;
    }

    private function criarSchema(): void
    {
        foreach ([
            'pdv_caixa_movimentos', 'pdv_vendas', 'pdv_caixa_sessoes', 'terminais',
            'caixa_lancamentos', 'caixa_conta_user', 'users', 'caixa_contas', 'empresas', 'planos_contas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 80)->nullable();
            $table->timestamps();
        });
        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao', 120)->nullable();
            $table->string('dc', 1)->nullable();
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
        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 180)->nullable();
            $table->string('plano_contas', 120)->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('valor_abertura', 14, 2)->default(0);
            $table->decimal('valor_fechamento', 14, 2)->nullable();
            $table->timestamp('aberto_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_caixa_movimentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->string('tipo', 30);
            $table->string('historico', 180)->nullable();
            $table->string('forma_pagamento', 60)->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->string('plano_conta_codigo', 40)->nullable();
            $table->timestamps();
        });
    }
}
