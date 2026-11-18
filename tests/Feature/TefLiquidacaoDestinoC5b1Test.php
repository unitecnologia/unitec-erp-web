<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\TefLiquidacaoDestino;
use App\Models\Terminal;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefLiquidacaoDestinoResolveResult;
use App\Support\Erp\Tef\TefLiquidacaoDestinoResolver;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * C5B.1 — Destino previsto SuperTEF (configuração isolada; sem liquidação).
 */
class TefLiquidacaoDestinoC5b1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_resolve_por_empresa_e_terminal_padrao(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $this->criarDestino($empresa, $terminal, $banco, '');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);

        $this->assertTrue($r->isOk());
        $this->assertSame((int) $banco->id, (int) $r->destino->caixa_conta_id);
    }

    public function test_resolve_adquirente_com_cnpj(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $bancoAilos = $this->criarBanco($empresa, 9202, 'AILOS');
        $this->criarDestino($empresa, $terminal, $banco, '');
        $this->criarDestino($empresa, $terminal, $bancoAilos, '09616768000101');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve(
            (int) $empresa->id,
            (int) $terminal->id,
            '09616768000101'
        );

        $this->assertTrue($r->isOk());
        $this->assertSame((int) $bancoAilos->id, (int) $r->destino->caixa_conta_id);
    }

    public function test_resolve_adquirente_sem_cnpj_usa_padrao(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $bancoAilos = $this->criarBanco($empresa, 9203, 'AILOS 2');
        $this->criarDestino($empresa, $terminal, $banco, '');
        $this->criarDestino($empresa, $terminal, $bancoAilos, '09616768000101');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, '');

        $this->assertTrue($r->isOk());
        $this->assertSame((int) $banco->id, (int) $r->destino->caixa_conta_id);
    }

    public function test_cnpj_desconhecido_cai_no_padrao(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $this->criarDestino($empresa, $terminal, $banco, '');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve(
            (int) $empresa->id,
            (int) $terminal->id,
            '00000000000191'
        );

        $this->assertTrue($r->isOk());
        $this->assertSame((int) $banco->id, (int) $r->destino->caixa_conta_id);
    }

    public function test_conta_outra_empresa_nao_elegivel(): void
    {
        [$empresa, $terminal] = $this->seedBase();
        $outra = Empresa::query()->create(['razao_social' => 'OUTRA LTDA', 'nome_fantasia' => 'OUTRA']);
        $bancoOutra = $this->criarBanco($outra, 9301, 'BANCO OUTRA');

        // Config aponta para conta de outra empresa (dado inconsistente).
        TefLiquidacaoDestino::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'acquirer_cnpj' => '',
            'caixa_conta_id' => $bancoOutra->id,
            'ativo' => true,
        ]);

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);

        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_CONTA_INVALIDA, $r->status);
        $this->assertFalse($r->isOk());
    }

    public function test_conta_inativa_nao_elegivel(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $banco->ativo = false;
        $banco->save();
        $this->criarDestino($empresa, $terminal, $banco, '');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);

        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_CONTA_INVALIDA, $r->status);
    }

    public function test_ambiguo_nao_escolhe_automaticamente(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $banco2 = $this->criarBanco($empresa, 9204, 'BANCO 2');

        // Sem unique no schema de teste — força dois ativos no mesmo nível.
        Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->dropUnique('tef_liq_dest_emp_term_prov_acq_uq');
        });

        $this->criarDestino($empresa, $terminal, $banco, '');
        $this->criarDestino($empresa, $terminal, $banco2, '');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);

        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_AMBIGUO, $r->status);
        $this->assertNull($r->destino);
        $this->assertCount(2, $r->candidatos);
    }

    public function test_empresas_nao_compartilham_config(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $outra = Empresa::query()->create(['razao_social' => 'EMP 2', 'nome_fantasia' => 'EMP 2']);
        $term2 = Terminal::query()->create([
            'empresa_id' => $outra->id,
            'nome' => 'PDV 2',
            'ativo' => true,
            'pdv' => true,
        ]);
        $banco2 = $this->criarBanco($outra, 9401, 'BANCO EMP2');
        $this->criarDestino($outra, $term2, $banco2, '');

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);
        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO, $r->status);

        // Mesmo terminal_id de outra empresa não deve vazar.
        $r2 = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $term2->id, null);
        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO, $r2->status);
    }

    public function test_alterar_destino_nao_mexe_intencoes_historicas(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $destino = $this->criarDestino($empresa, $terminal, $banco, '');

        Schema::create('tef_intencoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->string('status', 30)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('acquirer_cnpj', 14)->nullable();
            $table->timestamps();
        });

        $intentId = DB::table('tef_intencoes')->insertGetId([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'status' => 'paid',
            'valor' => 50.00,
            'acquirer_cnpj' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = (array) DB::table('tef_intencoes')->where('id', $intentId)->first();

        $novoBanco = $this->criarBanco($empresa, 9205, 'NOVO');
        $destino->caixa_conta_id = $novoBanco->id;
        $destino->save();

        $after = (array) DB::table('tef_intencoes')->where('id', $intentId)->first();
        $this->assertSame($before, $after);
        $this->assertSame(0, DB::table('caixa_lancamentos')->count());
        $this->assertSame(0, DB::table('contas_receber')->count());
    }

    public function test_orfas_nao_sao_incluidas_por_config_de_destino(): void
    {
        // Orfãs (intenção paga sem venda) permanecem só para análise — destino não as "liquida".
        [$empresa, $terminal, $banco] = $this->seedBase();
        $this->criarDestino($empresa, $terminal, $banco, '');

        Schema::create('tef_intencoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamps();
        });
        DB::table('tef_intencoes')->insert([
            'empresa_id' => $empresa->id,
            'pdv_venda_id' => null,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);
        $this->assertTrue($r->isOk());
        $this->assertSame(1, DB::table('tef_intencoes')->whereNull('pdv_venda_id')->where('status', 'paid')->count());
        $this->assertSame(0, DB::table('caixa_lancamentos')->count());
    }

    public function test_nenhuma_movimentacao_financeira_ao_resolver(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $this->criarDestino($empresa, $terminal, $banco, '');

        app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, '09616768000101');

        $this->assertSame(0, DB::table('caixa_lancamentos')->count());
        $this->assertSame(0, DB::table('contas_receber')->count());
        $this->assertSame(0, DB::table('extrato_conciliacoes')->count());
    }

    public function test_regressao_supertef_terminal_config_inalterado(): void
    {
        $terminal = new Terminal;
        $terminal->forceFill([
            'tef_extra' => [
                'ativo' => true,
                'provedor' => 'supertef',
                'pos_id' => 1,
            ],
        ]);

        $cfg = SuperTefTerminalConfig::read($terminal, false);
        $this->assertTrue($cfg['ativo']);
        $this->assertSame(1, $cfg['pos_id']);
        $this->assertArrayNotHasKey('caixa_conta_id', $cfg);
        $this->assertArrayNotHasKey('liquidacao_destino', $cfg);
    }

    public function test_registro_sem_provedor_vira_supertef(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $destino = $this->criarDestino($empresa, $terminal, $banco, '');

        $this->assertSame(TefProviderId::SUPERTEF, (string) $destino->fresh()->provedor);
    }

    public function test_mesmo_terminal_adquirente_separado_por_provedor(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $bancoScope = $this->criarBanco($empresa, 9301, 'BANCO SCOPE');
        $this->criarDestino($empresa, $terminal, $banco, '', TefProviderId::SUPERTEF);
        $this->criarDestino($empresa, $terminal, $bancoScope, '', TefProviderId::SCOPE);

        $rSuper = app(TefLiquidacaoDestinoResolver::class)->resolve(
            (int) $empresa->id,
            (int) $terminal->id,
            null,
            TefProviderId::SUPERTEF
        );
        $rScope = app(TefLiquidacaoDestinoResolver::class)->resolve(
            (int) $empresa->id,
            (int) $terminal->id,
            null,
            TefProviderId::SCOPE
        );

        $this->assertTrue($rSuper->isOk());
        $this->assertTrue($rScope->isOk());
        $this->assertSame((int) $banco->id, (int) $rSuper->destino->caixa_conta_id);
        $this->assertSame((int) $bancoScope->id, (int) $rScope->destino->caixa_conta_id);
    }

    public function test_resolve_scope_nao_usa_destino_supertef(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $this->criarDestino($empresa, $terminal, $banco, '', TefProviderId::SUPERTEF);

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve(
            (int) $empresa->id,
            (int) $terminal->id,
            null,
            TefProviderId::SCOPE
        );

        $this->assertSame(TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO, $r->status);
        $this->assertFalse($r->isOk());
    }

    public function test_ui_autorizada_nao_lista_scope(): void
    {
        $opts = TefLiquidacaoDestino::provedoresAutorizadosParaUi();
        $this->assertArrayHasKey(TefProviderId::SUPERTEF, $opts);
        $this->assertArrayNotHasKey(TefProviderId::SCOPE, $opts);
    }

    public function test_performance_resolver_com_volume(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        for ($i = 0; $i < 80; $i++) {
            $t = Terminal::query()->create([
                'empresa_id' => $empresa->id,
                'nome' => 'T'.$i,
                'ativo' => true,
                'pdv' => true,
            ]);
            $cnpj = $i === 0 ? '' : str_pad((string) $i, 14, '0', STR_PAD_LEFT);
            $this->criarDestino($empresa, $t, $banco, $cnpj);
        }
        $this->criarDestino($empresa, $terminal, $banco, '');

        $start = hrtime(true);
        for ($n = 0; $n < 50; $n++) {
            app(TefLiquidacaoDestinoResolver::class)->resolve((int) $empresa->id, (int) $terminal->id, null);
        }
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        $this->assertLessThan(500, $elapsedMs, "Resolver 50x demorou {$elapsedMs}ms");
    }

    /**
     * @return array{0: Empresa, 1: Terminal, 2: CaixaConta}
     */
    private function seedBase(): array
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'EMP TESTE',
            'nome_fantasia' => 'EMP',
        ]);
        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV 1',
            'ativo' => true,
            'pdv' => true,
        ]);
        $banco = $this->criarBanco($empresa, 9201, 'BANCO PRINCIPAL');

        return [$empresa, $terminal, $banco];
    }

    private function criarBanco(Empresa $empresa, int $codigo, string $nome): CaixaConta
    {
        return CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    private function criarDestino(
        Empresa $empresa,
        Terminal $terminal,
        CaixaConta $banco,
        string $cnpj,
        string $provedor = TefProviderId::SUPERTEF,
    ): TefLiquidacaoDestino {
        return TefLiquidacaoDestino::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'provedor' => $provedor,
            'acquirer_cnpj' => TefLiquidacaoDestino::normalizeAcquirerCnpj($cnpj),
            'caixa_conta_id' => $banco->id,
            'ativo' => true,
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('tef_liquidacao_destinos');
        Schema::dropIfExists('tef_intencoes');
        Schema::dropIfExists('extrato_conciliacoes');
        Schema::dropIfExists('contas_receber');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('terminais');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->timestamps();
        });

        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('nome')->nullable();
            $table->string('numero_logico_terminal')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('pdv')->default(false);
            $table->text('tef_extra')->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome');
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('contas_receber', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('extrato_conciliacoes', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            $table->string('provedor', 20)->default(TefProviderId::SUPERTEF);
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(
                ['empresa_id', 'terminal_id', 'provedor', 'acquirer_cnpj'],
                'tef_liq_dest_emp_term_prov_acq_uq'
            );
        });
    }
}
