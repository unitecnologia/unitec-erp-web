<?php

namespace Tests\Unit;

use App\Support\Erp\Tef\SuperTefFormasPagamento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Testa o helper sem MigratesSqliteMemory (suíte completa de migrations falha em SQLite).
 */
class SuperTefFormasPagamentoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('formas_pagamento', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('descricao', 120);
            $table->string('tipo', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('usa_tef')->default(false);
            $table->boolean('usa_super_tef')->default(false);
            $table->boolean('aparece_venda')->default(true);
            $table->boolean('gerar_qrcode_pdv')->default(false);
            $table->boolean('aparece_contas_receber')->default(false);
            $table->string('tipo_movimento', 30)->nullable();
            $table->string('modo_prazo', 20)->nullable();
            $table->unsignedInteger('max_parcelas')->default(1);
            $table->unsignedInteger('prazo_cartao')->default(0);
            $table->unsignedInteger('intervalo_parcelas')->default(0);
            $table->string('atalho', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->json('tef_extra')->nullable();
            $table->timestamps();
        });
    }

    public function test_ensure_ativas_cria_debito_e_credito(): void
    {
        SuperTefFormasPagamento::ensureAtivas();

        $descricoes = DB::table('formas_pagamento')->orderBy('codigo')->pluck('descricao')->all();
        $this->assertSame(['SUPERTEF DEBITO', 'SUPERTEF CREDITO'], $descricoes);
        $this->assertTrue((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF DEBITO')->value('ativo'));
        $this->assertTrue((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF CREDITO')->value('aparece_venda'));
    }

    public function test_desativar_sem_terminal_supertef(): void
    {
        SuperTefFormasPagamento::ensureAtivas();
        $n = SuperTefFormasPagamento::desativarSeSemSuperTef();

        $this->assertSame(2, $n);
        $this->assertFalse((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF DEBITO')->value('ativo'));
        $this->assertFalse((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF CREDITO')->value('aparece_venda'));
    }

    public function test_nao_desativa_com_terminal_supertef_habilitado(): void
    {
        SuperTefFormasPagamento::ensureAtivas();

        DB::table('terminais')->insert([
            'tef_extra' => json_encode([
                'v' => 1,
                'ativo' => true,
                'provedor' => 'supertef',
                'pos_id' => 10,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, SuperTefFormasPagamento::desativarSeSemSuperTef());
        $this->assertTrue((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF DEBITO')->value('ativo'));
    }

    public function test_ensure_reativa_formas_desativadas(): void
    {
        SuperTefFormasPagamento::ensureAtivas();
        SuperTefFormasPagamento::desativarSeSemSuperTef();
        SuperTefFormasPagamento::ensureAtivas();

        $this->assertCount(2, DB::table('formas_pagamento')->get());
        $this->assertTrue((bool) DB::table('formas_pagamento')->where('descricao', 'SUPERTEF CREDITO')->value('ativo'));
    }
}
