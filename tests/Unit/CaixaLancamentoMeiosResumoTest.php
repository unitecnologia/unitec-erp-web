<?php

namespace Tests\Unit;

use App\Support\Erp\Queries\CaixaLancamentoMeiosResumo;
use Tests\TestCase;

class CaixaLancamentoMeiosResumoTest extends TestCase
{
    public function test_estrutura_vazia_padrao(): void
    {
        $ref = new \ReflectionClass(CaixaLancamentoMeiosResumo::class);
        $method = $ref->getMethod('vazio');
        $method->setAccessible(true);

        $out = $method->invoke(new CaixaLancamentoMeiosResumo, 10.5);

        $this->assertSame(10.5, $out['total_entradas']);
        $this->assertTrue($out['visivel']);
        $this->assertSame([], $out['linhas']);
        $this->assertSame(0.0, $out['total_estorno']);
        $this->assertSame(0.0, $out['outras_saidas']);
        $this->assertSame(0.0, $out['saldo_liquido']);
    }

    public function test_eh_estorno_recebimento_so_com_prefixo_estorno(): void
    {
        $ref = new \ReflectionClass(CaixaLancamentoMeiosResumo::class);
        $method = $ref->getMethod('ehEstornoRecebimento');
        $method->setAccessible(true);
        $svc = new CaixaLancamentoMeiosResumo;

        $this->assertTrue($method->invoke($svc, 'ESTORNO PIX', 10.0));
        $this->assertFalse($method->invoke($svc, 'PAGAMENTO FORNECEDOR', 10.0));
        $this->assertFalse($method->invoke($svc, 'ESTORNO PIX', 0.0));
    }

    public function test_label_sem_meio_constante(): void
    {
        $this->assertSame('SEM MEIO', CaixaLancamentoMeiosResumo::LABEL_SEM_MEIO);
    }
}
