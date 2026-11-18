<?php

namespace Tests\Unit;

use App\Models\ExtratoLancamento;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class ConciliacaoBancariaPermissionCatalogTest extends TestCase
{
    public function test_permissoes_conciliacao_existem_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'import', 'conciliar', 'desfazer'] as $action) {
            $this->assertContains('conciliacao_bancaria.'.$action, $keys);
        }
    }

    public function test_modulo_no_grupo_financeiro(): void
    {
        $modules = ErpPermissionCatalog::modules();

        $this->assertArrayHasKey('conciliacao_bancaria', $modules);
        $this->assertSame('Financeiro', $modules['conciliacao_bancaria']['group']);
        $this->assertArrayHasKey('access', $modules['conciliacao_bancaria']['actions']);
        $this->assertArrayHasKey('import', $modules['conciliacao_bancaria']['actions']);
        $this->assertArrayHasKey('conciliar', $modules['conciliacao_bancaria']['actions']);
        $this->assertArrayHasKey('desfazer', $modules['conciliacao_bancaria']['actions']);
    }

    public function test_fingerprint_prefers_fitid(): void
    {
        $a = ExtratoLancamento::buildFingerprint('ABC-123', '2026-11-01', 'credito', '10.00', 'PIX', null, 1, null, null, null, 1, 2);
        $b = ExtratoLancamento::buildFingerprint('abc-123', '2026-11-02', 'debito', '99.00', 'OUTRO', null, 9, null, null, null, 1, 2);

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function test_fingerprint_sem_fitid_usa_ocorrencia(): void
    {
        $a = ExtratoLancamento::buildFingerprint(null, '2026-11-01', 'credito', '10.00', 'PIX', 'm1', 1, null, null, null, 1, 2);
        $b = ExtratoLancamento::buildFingerprint('', '2026-11-01', 'credito', '10.00', 'PIX', 'm1', 2, null, null, null, 1, 2);

        $this->assertNotSame($a, $b);
    }

    public function test_fingerprint_fitid_isola_por_conta(): void
    {
        $a = ExtratoLancamento::buildFingerprint('SAME', '2026-11-01', 'credito', '1', 'X', null, 1, null, null, null, 1, 10);
        $b = ExtratoLancamento::buildFingerprint('SAME', '2026-11-01', 'credito', '1', 'X', null, 1, null, null, null, 1, 11);

        $this->assertNotSame($a, $b);
    }
}
