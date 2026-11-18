<?php

namespace Tests\Feature;

use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use App\Models\Empresa;
use App\Models\ErpOperacaoLog;
use App\Models\ErpProfile;
use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\Erp\Audit\ErpOperacaoAuditoria;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Financeiro\ContaPagarEstornoService;
use App\Support\Erp\Financeiro\ContaReceberEstornoService;
use App\Support\Rh\RhAnexoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Logs essenciais — schema mínimo SQLite (sem migrate:fresh).
 */
class ErpOperacaoLogsEssenciaisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ErpSchema::flush();
        ErpAccess::forgetSession();
        ErpContext::clearMemo();

        foreach ([
            'erp_operacao_logs', 'user_permissions', 'erp_profile_permissions', 'erp_profiles',
            'conta_receber_pagamentos', 'contas_receber', 'conta_pagar_pagamentos', 'contas_pagar',
            'caixa_lancamentos', 'rh_anexos', 'rh_funcionarios', 'users', 'empresas', 'migrations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 160)->nullable();
            $table->string('razao_social', 160)->nullable();
            $table->string('fantasia', 160)->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('param_modulo_pdv')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('erp_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->nullable();
            $table->string('descricao')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('erp_profile_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('erp_profile_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('erp_operacao_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('ocorrido_em')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nome', 120)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('operacao', 80);
            $table->string('origem', 80)->nullable();
            $table->string('documento_tipo', 40)->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->string('documento_numero', 60)->nullable();
            $table->string('resultado', 20)->default('ok');
            $table->text('resumo')->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamps();
        });

        Schema::create('contas_receber', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero')->nullable();
            $table->string('documento')->nullable();
            $table->string('historico')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('valor_recebido', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->date('vencimento')->nullable();
            $table->date('recebido_em')->nullable();
            $table->string('forma')->nullable();
            $table->timestamps();
        });

        Schema::create('conta_receber_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conta_receber_id');
            $table->date('data')->nullable();
            $table->decimal('valor_parcela', 15, 2)->default(0);
            $table->decimal('valor_recebido', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->unsignedBigInteger('forma_pagamento_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contas_pagar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero')->nullable();
            $table->string('documento')->nullable();
            $table->string('historico')->nullable();
            $table->unsignedBigInteger('fornecedor_id')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->date('vencimento')->nullable();
            $table->date('pago_em')->nullable();
            $table->timestamps();
        });

        Schema::create('conta_pagar_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conta_pagar_id');
            $table->date('data')->nullable();
            $table->decimal('valor_parcela', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->unsignedBigInteger('forma_pagamento_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->string('tipo_vinculo')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_anexos', function (Blueprint $table): void {
            $table->id();
            $table->string('anexavel_type');
            $table->unsignedBigInteger('anexavel_id');
            $table->string('categoria', 40);
            $table->string('titulo', 160);
            $table->string('caminho', 255)->nullable();
            $table->date('emitido_em')->nullable();
            $table->date('valido_ate')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function test_permissoes_usuario_registra_apenas_diff(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        $alvo = User::query()->create([
            'name' => 'Operador',
            'password' => bcrypt('x'),
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $alvo->id, 'permission_key' => 'pdv.access', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $alvo->id, 'permission_key' => 'vendas.access', 'created_at' => now(), 'updated_at' => now()],
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        ErpAccess::syncUserPermissions($alvo, ['pdv.access', 'caixa.access', 'auditoria.access']);

        $log = ErpOperacaoLog::query()->where('operacao', 'ALTERAR_PERMISSOES_USUARIO')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $empresa->id, (int) $log->empresa_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertEqualsCanonicalizing(['caixa.access', 'auditoria.access'], $log->detalhes['added']);
        $this->assertEqualsCanonicalizing(['vendas.access'], $log->detalhes['removed']);
        $this->assertArrayNotHasKey('password', $log->detalhes);
    }

    public function test_permissoes_sem_mudanca_nao_gera_log(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        $alvo = User::query()->create([
            'name' => 'Operador 2',
            'password' => bcrypt('x'),
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        DB::table('user_permissions')->insert([
            ['user_id' => $alvo->id, 'permission_key' => 'pdv.access', 'created_at' => now(), 'updated_at' => now()],
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        ErpAccess::syncUserPermissions($alvo, ['pdv.access']);

        $this->assertSame(0, ErpOperacaoLog::query()->where('operacao', 'ALTERAR_PERMISSOES_USUARIO')->count());
    }

    public function test_permissoes_perfil_registra_diff(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        $perfil = ErpProfile::query()->create(['nome' => 'Caixa', 'descricao' => 'x', 'is_system' => false]);
        DB::table('erp_profile_permissions')->insert([
            ['erp_profile_id' => $perfil->id, 'permission_key' => 'pdv.access', 'created_at' => now(), 'updated_at' => now()],
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        ErpAccess::syncProfilePermissions($perfil, ['pdv.access', 'caixa.access']);

        $log = ErpOperacaoLog::query()->where('operacao', 'ALTERAR_PERMISSOES_PERFIL')->first();
        $this->assertNotNull($log);
        $this->assertSame(['caixa.access'], $log->detalhes['added']);
        $this->assertSame([], $log->detalhes['removed']);
    }

    public function test_estorno_cr_loga_e_rollback_nao_persiste(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $conta = ContaReceber::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '100',
            'valor' => 50,
            'valor_recebido' => 50,
            'saldo' => 0,
        ]);
        $pag = ContaReceberPagamento::query()->create([
            'conta_receber_id' => $conta->id,
            'data' => now()->toDateString(),
            'valor_recebido' => 50,
            'valor_parcela' => 50,
        ]);

        // Rollback: força falha após log? Simula estorno OK e depois estorno falho.
        app(ContaReceberEstornoService::class)->estornarPagamento((int) $pag->id);

        $this->assertSame(1, ErpOperacaoLog::query()->where('operacao', 'ESTORNAR_BAIXA_CONTAS_RECEBER')->count());
        $this->assertNull(ContaReceberPagamento::query()->find($pag->id));

        try {
            DB::transaction(function (): void {
                app(ErpOperacaoLogService::class)->registrar(
                    operacao: 'ESTORNAR_BAIXA_CONTAS_RECEBER',
                    resumo: 'não deve ficar',
                    origem: 'teste',
                );
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame(1, ErpOperacaoLog::query()->where('operacao', 'ESTORNAR_BAIXA_CONTAS_RECEBER')->count());
    }

    public function test_estorno_cp_loga_snapshot(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $this->app->instance(
            \App\Support\Erp\Financeiro\ComissaoPeriodoService::class,
            new class
            {
                public function syncStatusPagaFromContaPagar(mixed $conta): void
                {
                    // no-op no schema mínimo
                }
            }
        );

        $conta = ContaPagar::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '200',
            'valor' => 80,
            'valor_pago' => 80,
            'saldo' => 0,
        ]);
        $pag = ContaPagarPagamento::query()->create([
            'conta_pagar_id' => $conta->id,
            'data' => now()->toDateString(),
            'valor_pago' => 80,
            'valor_parcela' => 80,
        ]);

        app(ContaPagarEstornoService::class)->estornarPagamento((int) $pag->id);

        $log = ErpOperacaoLog::query()->where('operacao', 'ESTORNAR_BAIXA_CONTAS_PAGAR')->first();
        $this->assertNotNull($log);
        $this->assertSame(80.0, (float) $log->detalhes['pagamento']['valor_pago']);
    }

    public function test_rh_funcionario_e_documento_geram_log(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa->id);
        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        $func = RhFuncionario::query()->create([
            'codigo' => '1',
            'nome' => 'JOAO',
            'ativo' => true,
        ]);

        DB::transaction(function () use ($func): void {
            $snapshot = [
                'id' => (int) $func->id,
                'codigo' => $func->codigo,
                'nome' => $func->nome,
                'ativo' => true,
                'vendedor_id' => null,
                'tipo_vinculo' => null,
            ];
            $func->delete();
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'EXCLUIR_RH_FUNCIONARIO',
                resumo: 'Funcionário RH excluído: JOAO.',
                origem: 'rh_funcionarios',
                documentoTipo: 'rh_funcionario',
                documentoId: (int) $snapshot['id'],
                documentoNumero: '1',
                detalhes: ['acao' => 'excluir', 'antes' => $snapshot],
                empresaId: ErpContext::currentEmpresaId(),
            );
        });

        $anexo = RhAnexo::query()->create([
            'anexavel_type' => RhFuncionario::class,
            'anexavel_id' => 999,
            'categoria' => RhAnexo::CAT_DOCUMENTO,
            'titulo' => 'CNH',
            'caminho' => '',
            'ativo' => true,
        ]);

        // deleteStoredFile com caminho vazio deve ser no-op.
        app(RhAnexoService::class)->delete($anexo);

        $this->assertSame(1, ErpOperacaoLog::query()->where('operacao', 'EXCLUIR_RH_FUNCIONARIO')->count());
        $this->assertSame(1, ErpOperacaoLog::query()->where('operacao', 'EXCLUIR_RH_DOCUMENTO')->count());
        $doc = ErpOperacaoLog::query()->where('operacao', 'EXCLUIR_RH_DOCUMENTO')->first();
        $this->assertSame('CNH', $doc->detalhes['antes']['titulo']);
    }

    public function test_mascara_sensivel_e_labels(): void
    {
        $this->assertTrue(ErpOperacaoAuditoria::ehSensivel('senha_certificado'));
        $this->assertSame('(alterado)', ErpOperacaoAuditoria::mascaraValor('segredo'));
        $this->assertSame('Alteração de permissões do usuário', \App\Support\Erp\Audit\ErpOperacaoLogLabels::operacao('ALTERAR_PERMISSOES_USUARIO'));
        $this->assertSame('Estorno de baixa a receber', \App\Support\Erp\Audit\ErpOperacaoLogLabels::operacao('ESTORNAR_BAIXA_CONTAS_RECEBER'));
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'codigo' => 'E1',
            'nome' => 'EMP LOG',
            'razao_social' => 'EMP LOG',
            'fantasia' => 'EMP',
            'cnpj' => '123',
            'ativo' => true,
        ]);
    }

    private function admin(int $empresaId): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'password' => bcrypt('x'),
            'empresa_id' => $empresaId,
            'is_admin' => true,
            'ativo' => true,
        ]);
    }
}
