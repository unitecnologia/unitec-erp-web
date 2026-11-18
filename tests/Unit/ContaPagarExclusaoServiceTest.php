<?php

namespace Tests\Unit;

use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\ContaPagarExclusaoService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ContaPagarExclusaoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
    }

    protected function tearDown(): void
    {
        ErpContext::clearMemo();
        parent::tearDown();
    }

    public function test_permite_excluir_conta_avulsa_sem_baixa(): void
    {
        $conta = $this->conta([
            'valor_pago' => 0,
            'compra_id' => null,
            'empresa_id' => null,
        ]);

        $service = new ContaPagarExclusaoService();

        $this->assertTrue($service->podeExcluir($conta));
        $this->assertNull($service->motivoBloqueio($conta));
    }

    public function test_bloqueia_quando_possui_valor_pago(): void
    {
        $conta = $this->conta([
            'valor_pago' => 10.5,
            'compra_id' => null,
        ]);

        $service = new ContaPagarExclusaoService();

        $this->assertFalse($service->podeExcluir($conta));
        $this->assertSame(
            'Conta já possui baixa. Estorne a parcela paga antes de excluir.',
            $service->motivoBloqueio($conta),
        );
    }

    public function test_bloqueia_quando_possui_pagamentos(): void
    {
        $conta = $this->conta([
            'valor_pago' => 0,
            'compra_id' => null,
        ], pagamentos: collect([new ContaPagarPagamento(['id' => 1])]));

        $service = new ContaPagarExclusaoService();

        $this->assertFalse($service->podeExcluir($conta));
        $this->assertSame(
            'Conta possui histórico de pagamentos. Estorne antes de excluir.',
            $service->motivoBloqueio($conta),
        );
    }

    public function test_bloqueia_quando_vinculada_a_compra(): void
    {
        $conta = $this->conta([
            'valor_pago' => 0,
            'compra_id' => 88,
        ]);

        $service = new ContaPagarExclusaoService();

        $this->assertFalse($service->podeExcluir($conta));
        $this->assertSame(
            'Conta vinculada à compra #88. Exclua ou reabra pelo módulo de Compras.',
            $service->motivoBloqueio($conta),
        );
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  Collection<int, ContaPagarPagamento>|null  $pagamentos
     */
    private function conta(array $attrs, ?Collection $pagamentos = null): ContaPagar
    {
        $conta = new ContaPagar($attrs);
        $conta->setRelation('pagamentos', $pagamentos ?? collect());

        return $conta;
    }
}
