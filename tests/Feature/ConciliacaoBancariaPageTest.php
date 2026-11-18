<?php

namespace Tests\Feature;

use App\Filament\Resources\ExtratoLancamentoResource\Pages\ListExtratoLancamentos;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Schema mínimo em SQLite :memory: (migrate completo da suíte quebra em migration
 * legada information_schema — fora do escopo C1).
 */
class ConciliacaoBancariaPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_migration_equivalente_cria_tabelas_e_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('extrato_importacoes'));
        $this->assertTrue(Schema::hasTable('extrato_lancamentos'));

        $this->assertTrue(Schema::hasColumns('extrato_importacoes', [
            'empresa_id',
            'caixa_conta_id',
            'arquivo_nome',
            'arquivo_sha256',
            'formato',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('extrato_lancamentos', [
            'empresa_id',
            'caixa_conta_id',
            'extrato_importacao_id',
            'data_movimento',
            'tipo',
            'valor',
            'fitid',
            'fingerprint',
            'situacao',
            'origem_tipo',
            'origem_id',
        ]));
    }

    public function test_admin_abre_tela_sem_gerar_movimentacao(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $lancamentosAntes = Schema::hasTable('caixa_lancamentos')
            ? CaixaLancamento::query()->count()
            : 0;
        $extratoAntes = ExtratoLancamento::query()->count();

        Livewire::test(ListExtratoLancamentos::class)
            ->assertOk()
            ->assertSet('caixaContaId', '')
            ->set('caixaContaId', (string) $banco->id)
            ->assertSee('Nenhum lançamento de extrato')
            ->assertSee('Importar OFX');

        $this->assertSame($extratoAntes, ExtratoLancamento::query()->count());
        if (Schema::hasTable('caixa_lancamentos')) {
            $this->assertSame($lancamentosAntes, CaixaLancamento::query()->count());
        }

        $options = Livewire::test(ListExtratoLancamentos::class)
            ->instance()
            ->contaBancariaOptions();

        $ids = collect($options)->pluck('id')->all();
        $this->assertContains((int) $banco->id, $ids);
    }

    public function test_lista_somente_contas_banco_da_empresa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $outraEmpresa = Empresa::query()->create([
            'codigo' => 'CB2',
            'nome' => 'OUTRA EMPRESA CB',
            'fantasia' => 'OE',
            'ativo' => true,
        ]);

        $bancoOutra = CaixaConta::query()->create([
            'empresa_id' => $outraEmpresa->id,
            'codigo' => 9102,
            'nome' => 'BANCO OUTRA',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);

        $pdv = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9103,
            'nome' => 'PDV TESTE CB',
            'tipo' => CaixaConta::TIPO_PDV,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);

        $options = Livewire::test(ListExtratoLancamentos::class)
            ->instance()
            ->contaBancariaOptions();

        $ids = collect($options)->pluck('id')->all();

        $this->assertContains((int) $banco->id, $ids);
        $this->assertNotContains((int) $bancoOutra->id, $ids);
        $this->assertNotContains((int) $pdv->id, $ids);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'CB3',
            'nome' => 'EMPRESA SEM PERM',
            'fantasia' => 'SP',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        ErpAccess::storeInSession($user, ['caixa.access']);
        $this->actingAs($user);

        Livewire::test(ListExtratoLancamentos::class)
            ->assertForbidden();
    }

    public function test_paginacao_filtra_por_empresa_conta_e_situacao(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'teste.ofx',
            'arquivo_sha256' => str_repeat('a', 64),
            'formato' => ExtratoImportacao::FORMATO_OFX,
            'status' => ExtratoImportacao::STATUS_CONCLUIDA,
            'total_linhas' => 2,
            'imported_at' => now(),
        ]);

        ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => now()->toDateString(),
            'tipo' => ExtratoLancamento::TIPO_CREDITO,
            'valor' => 100,
            'historico' => 'PIX TESTE',
            'fitid' => 'FIT-1',
            'fingerprint' => ExtratoLancamento::buildFingerprint('FIT-1', now()->toDateString(), 'credito', '100', 'PIX TESTE', null, 1),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
        ]);

        ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => now()->toDateString(),
            'tipo' => ExtratoLancamento::TIPO_DEBITO,
            'valor' => 20,
            'historico' => 'TARIFA',
            'fitid' => 'FIT-2',
            'fingerprint' => ExtratoLancamento::buildFingerprint('FIT-2', now()->toDateString(), 'debito', '20', 'TARIFA', null, 2),
            'situacao' => ExtratoLancamento::SITUACAO_CONCILIADO,
            'conciliado_em' => now(),
        ]);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('situacao', 'pendente')
            ->assertSee('PIX TESTE')
            ->assertDontSee('TARIFA');
    }

    public function test_coluna_data_mostra_hora_somente_quando_existe(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'hora.ofx',
            'arquivo_sha256' => str_repeat('c', 64),
            'formato' => ExtratoImportacao::FORMATO_OFX,
            'status' => ExtratoImportacao::STATUS_CONCLUIDA,
            'total_linhas' => 2,
            'imported_at' => now(),
        ]);

        ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => '2026-11-10 14:30:00',
            'tipo' => ExtratoLancamento::TIPO_CREDITO,
            'valor' => 10,
            'historico' => 'COM HORA',
            'fitid' => 'FIT-H',
            'fingerprint' => ExtratoLancamento::buildFingerprint('FIT-H', '2026-11-10', 'credito', '10', 'COM HORA', null, 1),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
        ]);

        ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => '2026-11-10',
            'tipo' => ExtratoLancamento::TIPO_DEBITO,
            'valor' => 5,
            'historico' => 'SO DATA',
            'fitid' => 'FIT-D',
            'fingerprint' => ExtratoLancamento::buildFingerprint('FIT-D', '2026-11-10', 'debito', '5', 'SO DATA', null, 1),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
        ]);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->assertSee('COM HORA')
            ->assertSee('10/11/2026 14:30')
            ->assertSee('SO DATA')
            ->assertSee('10/11/2026')
            ->assertDontSee('10/11/2026 00:00');
    }

    public function test_unique_arquivo_sha(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'a.ofx',
            'arquivo_sha256' => str_repeat('b', 64),
            'formato' => 'ofx',
            'status' => 'concluida',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'a-copy.ofx',
            'arquivo_sha256' => str_repeat('b', 64),
            'formato' => 'ofx',
            'status' => 'concluida',
        ]);
    }

    public function test_abertura_nao_dispara_consulta_extrato_sem_conta(): void
    {
        $this->seedEmpresaAdminEContaBanco();

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListExtratoLancamentos::class)->assertOk();

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->implode("\n");

        $this->assertStringNotContainsString('extrato_lancamentos', strtolower($queries));

        DB::disableQueryLog();
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('extrato_lancamentos');
        Schema::dropIfExists('extrato_importacoes');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->string('fantasia', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->string('banco_codigo', 10)->nullable();
            $table->string('banco_nome', 80)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('agencia_dv', 4)->nullable();
            $table->string('conta_numero', 30)->nullable();
            $table->string('conta_dv', 4)->nullable();
            $table->string('conta_tipo_bancaria', 20)->nullable();
            $table->string('titular_nome', 120)->nullable();
            $table->string('titular_documento', 20)->nullable();
            $table->unsignedBigInteger('ultimo_usuario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->string('historico', 255)->nullable();
            $table->timestamps();
        });

        // Espelha a migration C1 (unicidade + índices essenciais).
        Schema::create('extrato_importacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_sha256', 64);
            $table->string('formato', 16)->default('ofx');
            $table->string('status', 20)->default('pendente')->index();
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->decimal('total_creditos', 14, 2)->default(0);
            $table->decimal('total_debitos', 14, 2)->default(0);
            $table->text('mensagem_erro')->nullable();
            $table->unsignedBigInteger('imported_by_user_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'arquivo_sha256'], 'extrato_imp_emp_conta_sha_uq');
        });

        Schema::create('extrato_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->unsignedBigInteger('extrato_importacao_id')->index();
            $table->dateTime('data_movimento')->index();
            $table->dateTime('data_posted')->nullable();
            $table->string('tipo', 16)->default('outro');
            $table->decimal('valor', 14, 2);
            $table->string('historico', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('payee', 255)->nullable();
            $table->string('check_num', 40)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->char('fingerprint', 64);
            $table->string('situacao', 20)->default('pendente')->index();
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'fingerprint'], 'extrato_lanc_emp_conta_fp_uq');
        });
    }

    /**
     * @return array{0: Empresa, 1: User, 2: CaixaConta}
     */
    private function seedEmpresaAdminEContaBanco(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'CB1',
            'nome' => 'EMPRESA CONCILIACAO',
            'fantasia' => 'CB',
            'ativo' => true,
        ]);

        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $banco = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9101,
            'nome' => 'BANCO AILOS TESTE',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
            'banco_codigo' => '085',
            'agencia' => '0001',
            'conta_numero' => '12345',
        ]);

        return [$empresa, $admin, $banco];
    }
}
