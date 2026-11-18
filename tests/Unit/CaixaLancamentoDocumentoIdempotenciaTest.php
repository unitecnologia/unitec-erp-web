<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Support\Erp\ErpSchema;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Usa DatabaseTransactions (MySQL DEV com rollback).
 * A suíte SQLite :memory: está bloqueada por migration legada alheia a este escopo.
 */
class CaixaLancamentoDocumentoIdempotenciaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite' && DB::getDatabaseName() === ':memory:') {
            $this->markTestSkipped('SQLite :memory: migrate quebrado por migration legada (entregas_devices).');
        }
    }

    public function test_indice_documento_existe_apos_migrate(): void
    {
        $names = collect(Schema::getIndexes('caixa_lancamentos'))->pluck('name')->all();

        $this->assertContains('caixa_lancamentos_documento_index', $names);
    }

    public function test_saldo_liquido_documento_soma_exato_e_prefixo(): void
    {
        $caixa = CaixaConta::ensureCaixaGeral();
        $suffix = (string) (time() % 100000);
        $doc = 'FV-T'.$suffix;

        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => now()->toDateString(),
            'documento' => $doc,
            'historico' => 'VENDA ERP TESTE',
            'caixa_conta_id' => $caixa->id,
            'entrada' => 10.00,
            'saida' => 0,
        ]);
        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => now()->toDateString(),
            'documento' => $doc.'/P1',
            'historico' => 'VENDA ERP TESTE P1',
            'caixa_conta_id' => $caixa->id,
            'entrada' => 5.50,
            'saida' => 0,
        ]);
        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => now()->toDateString(),
            'documento' => $doc.'/P1',
            'historico' => 'ESTORNO',
            'caixa_conta_id' => $caixa->id,
            'entrada' => 0,
            'saida' => 2.00,
        ]);
        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => now()->toDateString(),
            'documento' => $doc.'9',
            'historico' => 'OUTRO',
            'caixa_conta_id' => $caixa->id,
            'entrada' => 99.00,
            'saida' => 0,
        ]);

        $svc = new ForcaVendasFaturamentoService();
        $method = new ReflectionMethod($svc, 'saldoLiquidoLivroDocumento');
        $method->setAccessible(true);

        $this->assertSame(13.50, $method->invoke($svc, $doc));
        $this->assertSame(0.0, $method->invoke($svc, 'FV-ZZ-'.$suffix));
    }

    public function test_erp_schema_memoiza_has_table_caixa(): void
    {
        ErpSchema::flush();
        $this->assertTrue(ErpSchema::hasTable('caixa_lancamentos'));
        $this->assertTrue(ErpSchema::hasTable('caixa_lancamentos'));
    }
}
