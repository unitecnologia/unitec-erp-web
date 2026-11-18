<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\PlanoConta;
use Database\Seeders\UnitecInitialSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnitecInitialSeederPlanoContaSanitizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::dropIfExists('empresas');
        Schema::dropIfExists('planos_contas');

        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo');
            $table->string('conta_completa')->nullable();
            $table->string('descricao');
            $table->string('dc', 1);
            $table->unsignedTinyInteger('nivel')->default(2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('pessoa_tipo')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('cnpj')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('configuracao_inicial_concluida')->default(false);
            $table->foreignId('param_plano_conta_venda_id')->nullable()->constrained('planos_contas')->nullOnDelete();
            $table->foreignId('param_plano_conta_compra_id')->nullable()->constrained('planos_contas')->nullOnDelete();
            $table->foreignId('param_plano_conta_taxa_cartao_id')->nullable()->constrained('planos_contas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function test_sanitize_sem_planos_zera_fks_para_evitar_erro_de_foreign_key(): void
    {
        $sanitized = UnitecInitialSeeder::sanitizeEmpresaPlanoContaIds([
            'param_plano_conta_venda_id' => 1,
            'param_plano_conta_compra_id' => 6,
            'param_plano_conta_taxa_cartao_id' => 13,
            'razao_social' => null,
        ]);

        $this->assertNull($sanitized['param_plano_conta_venda_id']);
        $this->assertNull($sanitized['param_plano_conta_compra_id']);
        $this->assertNull($sanitized['param_plano_conta_taxa_cartao_id']);
        $this->assertArrayHasKey('razao_social', $sanitized);
    }

    public function test_sanitize_resolve_taxa_pelo_codigo_209_mesmo_quando_snapshot_aponta_id_errado(): void
    {
        $venda = PlanoConta::query()->create([
            'codigo' => 101,
            'conta_completa' => '1.01',
            'descricao' => 'VENDAS DE MERCADORIAS',
            'dc' => 'C',
            'nivel' => 2,
            'ativo' => true,
        ]);
        $compra = PlanoConta::query()->create([
            'codigo' => 201,
            'conta_completa' => '2.01',
            'descricao' => 'COMPRA DE MERCADORIAS',
            'dc' => 'D',
            'nivel' => 2,
            'ativo' => true,
        ]);
        $impostos = PlanoConta::query()->create([
            'codigo' => 208,
            'conta_completa' => '2.08',
            'descricao' => 'IMPOSTOS / TAXAS',
            'dc' => 'D',
            'nivel' => 2,
            'ativo' => true,
        ]);
        $tarifas = PlanoConta::query()->create([
            'codigo' => 209,
            'conta_completa' => '2.09',
            'descricao' => 'TARIFAS BANCARIAS',
            'dc' => 'D',
            'nivel' => 2,
            'ativo' => true,
        ]);

        $sanitized = UnitecInitialSeeder::sanitizeEmpresaPlanoContaIds([
            'param_plano_conta_venda_id' => 999,
            'param_plano_conta_compra_id' => 999,
            'param_plano_conta_taxa_cartao_id' => $impostos->id,
        ]);

        $this->assertSame((int) $venda->id, (int) $sanitized['param_plano_conta_venda_id']);
        $this->assertSame((int) $compra->id, (int) $sanitized['param_plano_conta_compra_id']);
        $this->assertSame((int) $tarifas->id, (int) $sanitized['param_plano_conta_taxa_cartao_id']);
        $this->assertNotSame((int) $impostos->id, (int) $sanitized['param_plano_conta_taxa_cartao_id']);
    }

    public function test_fallback_grava_empresa_provisoria_sem_fk_invalida_quando_nao_ha_planos(): void
    {
        $payload = UnitecInitialSeeder::sanitizeEmpresaPlanoContaIds([
            'codigo' => 1,
            'pessoa_tipo' => 'juridica',
            'ativo' => true,
            'configuracao_inicial_concluida' => false,
            'param_plano_conta_venda_id' => 1,
            'param_plano_conta_compra_id' => 6,
            'param_plano_conta_taxa_cartao_id' => 13,
        ]);

        $empresa = Empresa::query()->updateOrCreate(['id' => 1], $payload);

        $this->assertSame(1, (int) $empresa->id);
        $this->assertTrue(Empresa::configuracaoInicialPendente());
        $this->assertNull($empresa->fresh()->param_plano_conta_venda_id);
        $this->assertNull($empresa->fresh()->param_plano_conta_compra_id);
        $this->assertNull($empresa->fresh()->param_plano_conta_taxa_cartao_id);
    }

    public function test_migration_corrige_taxa_cartao_para_codigo_209(): void
    {
        $impostos = PlanoConta::query()->create([
            'codigo' => 208,
            'conta_completa' => '2.08',
            'descricao' => 'IMPOSTOS / TAXAS',
            'dc' => 'D',
            'nivel' => 2,
            'ativo' => true,
        ]);
        $tarifas = PlanoConta::query()->create([
            'codigo' => 209,
            'conta_completa' => '2.09',
            'descricao' => 'TARIFAS BANCARIAS',
            'dc' => 'D',
            'nivel' => 2,
            'ativo' => true,
        ]);

        DB::table('empresas')->insert([
            'id' => 1,
            'codigo' => 1,
            'pessoa_tipo' => 'juridica',
            'ativo' => 1,
            'configuracao_inicial_concluida' => 0,
            'param_plano_conta_taxa_cartao_id' => $impostos->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_10_120000_fix_empresa_param_plano_conta_taxa_cartao_codigo_209.php');
        $migration->up();

        $taxaId = (int) DB::table('empresas')->where('id', 1)->value('param_plano_conta_taxa_cartao_id');
        $this->assertSame((int) $tarifas->id, $taxaId);
    }
}
