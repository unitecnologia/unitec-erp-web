<?php

namespace Tests\Unit;

use App\Support\Erp\Nfce\NfceRegularizacaoPagamentos;
use App\Support\Erp\Nfce\NfceRegularizacaoQuery;
use App\Support\Erp\Nfce\NfceRegularizacaoService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NfceRegularizacaoPagamentosWiringTest extends TestCase
{
    public function test_query_usa_helper_e_nao_so_cabecalho(): void
    {
        $src = file_get_contents((new ReflectionClass(NfceRegularizacaoQuery::class))->getFileName());

        $this->assertStringContainsString('NfceRegularizacaoPagamentos::resolverLinhas', $src);
        $this->assertStringContainsString('pdv_venda_pagamentos', $src);
        $this->assertStringContainsString('forma_title', $src);
        $this->assertStringNotContainsString(
            "'forma' => filled(\$record->forma_pagamento) ? (string) \$record->forma_pagamento : '—'",
            $src,
        );
    }

    public function test_service_protege_emissao_e_criar_venda_fiscal(): void
    {
        $src = file_get_contents((new ReflectionClass(NfceRegularizacaoService::class))->getFileName());

        $this->assertStringContainsString('garantirPagamentosEmissao', $src);
        $this->assertStringContainsString('NfceRegularizacaoPagamentos::garantirParaEmissao', $src);
        $this->assertStringContainsString('carregarPagamentosVinculadosVenda', $src);
        $this->assertStringContainsString('pagamentosPayloadForcaVendas', $src);
        $this->assertStringContainsString('ehRotuloInutilizavel', $src);
    }

    public function test_blade_usa_forma_title(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/erp/nfce-regularizacao.blade.php');

        $this->assertStringContainsString('forma_title', $blade);
    }

    public function test_helper_existe(): void
    {
        $this->assertTrue(class_exists(NfceRegularizacaoPagamentos::class));
    }
}
