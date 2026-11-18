<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\User;
use App\Support\Erp\Pdv\PdvNfceReimpressao;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): migrate :memory: quebra em migration legada.
 */
class PdvNfceReimpressaoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
    }

    public function test_lista_ultima_autorizada_e_ignora_rejeitada_pendente_e_sem_nfce(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();

        $autorizada = $this->vendaComNfce($sessao, $user, $empresa, 10, PdvVendaNfce::STATUS_AUTORIZADA, [
            'chave' => str_repeat('1', 44),
            'protocolo' => '123456789012345',
        ]);
        $this->vendaComNfce($sessao, $user, $empresa, 11, PdvVendaNfce::STATUS_REJEITADA);
        $pendente = $this->vendaComNfce($sessao, $user, $empresa, 12, PdvVendaNfce::STATUS_PENDENTE);
        $this->vendaSemNfce($sessao, $user, 13);

        $lista = PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $empresa->id);

        $this->assertCount(1, $lista);
        $this->assertSame((int) $autorizada->id, (int) $lista[0]['venda_id']);
        $this->assertFalse(PdvNfceReimpressao::podeReimprimir($pendente->nfce));

        $ultima = PdvNfceReimpressao::ultimaVendaImprimivel((int) $sessao->id, (int) $empresa->id);
        $this->assertNotNull($ultima);
        $this->assertSame((int) $autorizada->id, (int) $ultima->id);
    }

    public function test_autorizada_sem_chave_protocolo_nao_entra_na_lista(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $this->vendaComNfce($sessao, $user, $empresa, 20, PdvVendaNfce::STATUS_AUTORIZADA, [
            'chave' => null,
            'protocolo' => null,
        ]);

        $this->assertSame([], PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $empresa->id));
        $this->assertFalse(PdvNfceReimpressao::podeReimprimir(
            PdvVendaNfce::query()->where('numero', 20)->first()
        ));
    }

    public function test_contingencia_inconsistente_nao_e_imprimivel(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $venda = $this->vendaComNfce($sessao, $user, $empresa, 30, PdvVendaNfce::STATUS_CONTINGENCIA, [
            'chave' => str_repeat('1', 44), // pos 34 != 9
            'protocolo' => null,
        ]);

        $this->assertFalse(PdvNfceReimpressao::podeReimprimir($venda->nfce));
        $this->assertSame([], PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $empresa->id));
        $this->assertStringContainsString(
            'contingência inconsistente',
            PdvNfceReimpressao::motivoIndisponivel($venda->nfce),
        );
    }

    public function test_reimpressao_repetida_nao_duplica_nfce(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $venda = $this->vendaComNfce($sessao, $user, $empresa, 40, PdvVendaNfce::STATUS_AUTORIZADA, [
            'chave' => str_repeat('2', 44),
            'protocolo' => '999',
        ]);

        $antes = PdvVendaNfce::query()->count();
        $lista1 = PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $empresa->id);
        $lista2 = PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $empresa->id);
        $depois = PdvVendaNfce::query()->count();

        $this->assertSame($antes, $depois);
        $this->assertCount(1, $lista1);
        $this->assertCount(1, $lista2);
        $this->assertSame((int) $venda->id, (int) $lista1[0]['venda_id']);
        $this->assertSame((int) $venda->id, (int) $lista2[0]['venda_id']);
        $this->assertTrue(PdvNfceReimpressao::podeReimprimir($venda->fresh()->nfce));
    }

    public function test_empresa_e_caixa_diferentes_nao_listam(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $outraEmpresa = Empresa::query()->create([
            'nome' => 'OUTRA EMPRESA',
            'ativo' => true,
        ]);
        $this->vendaComNfce($sessao, $user, $empresa, 50, PdvVendaNfce::STATUS_AUTORIZADA, [
            'chave' => str_repeat('3', 44),
            'protocolo' => '1',
        ]);

        $this->assertSame([], PdvNfceReimpressao::listarParaModal((int) $sessao->id, (int) $outraEmpresa->id));
        $this->assertSame([], PdvNfceReimpressao::listarParaModal((int) $sessao->id + 999, (int) $empresa->id));
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('pdv_venda_nfce');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('pdv_caixa_sessoes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->decimal('valor_abertura', 12, 2)->default(0);
            $table->timestamp('aberto_em')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('forma_pagamento')->nullable();
            $table->boolean('fiscal')->default(false);
            $table->string('situacao', 1)->default('F');
            $table->string('vendedor_nome')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_venda_nfce', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('operacao')->nullable();
            $table->string('modelo', 2)->nullable();
            $table->string('serie')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('cnf')->nullable();
            $table->string('chave', 44)->nullable();
            $table->string('protocolo')->nullable();
            $table->string('status')->nullable();
            $table->boolean('simulada')->default(false);
            $table->longText('xml')->nullable();
            $table->text('qr_code_conteudo')->nullable();
            $table->timestamp('autorizada_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{0:Empresa,1:PdvCaixaSessao,2:User}
     */
    private function caixaPronto(): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA NFCE REIMP',
            'ativo' => true,
        ]);
        $user = User::query()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador',
            'email' => 'op-'.uniqid('', true).'@test.local',
            'password' => 'secret',
        ]);
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        return [$empresa, $sessao, $user];
    }

    /**
     * @param  array<string, mixed>  $nfceExtra
     */
    private function vendaComNfce(
        PdvCaixaSessao $sessao,
        User $user,
        Empresa $empresa,
        int $numero,
        string $status,
        array $nfceExtra = [],
    ): PdvVenda {
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => $numero,
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'fiscal' => true,
            'situacao' => 'F',
            'vendedor_nome' => 'OPERADOR',
        ]);

        PdvVendaNfce::query()->create(array_merge([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'operacao' => 'nfce_transmitir',
            'modelo' => '65',
            'serie' => '1',
            'numero' => $numero,
            'status' => $status,
            'simulada' => false,
            'autorizada_em' => $status === PdvVendaNfce::STATUS_AUTORIZADA ? now() : null,
        ], $nfceExtra));

        return $venda->fresh(['nfce']);
    }

    private function vendaSemNfce(PdvCaixaSessao $sessao, User $user, int $numero): PdvVenda
    {
        return PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => $numero,
            'subtotal' => 5,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 5,
            'forma_pagamento' => 'DINHEIRO',
            'fiscal' => false,
            'situacao' => 'F',
        ]);
    }
}
