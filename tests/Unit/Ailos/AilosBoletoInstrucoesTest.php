<?php

declare(strict_types=1);

namespace Tests\Unit\Ailos;

use App\Models\Empresa;
use App\Services\Ailos\AilosBoletoEmissionService;
use App\Services\Ailos\AilosCobrancaAuth;
use App\Services\Ailos\AilosCobrancaClient;
use App\Support\Erp\EmpresaParametros;
use Mockery;
use Tests\TestCase;

final class AilosBoletoInstrucoesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_instrucoes_percentuais_e_xor_pos_vencimento(): void
    {
        $service = new AilosBoletoEmissionService(
            new AilosCobrancaClient(Mockery::mock(AilosCobrancaAuth::class))
        );

        $protesto = $service->buildInstrucoesForTests($this->empresa([
            'param_boleto_juros_pct' => '2,5',
            'param_boleto_multa_pct' => '3',
            'param_boleto_desconto_pct' => '1',
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_PROTESTO,
            'param_boleto_protesto_dias' => '10',
        ]));

        self::assertSame(2, $protesto['tipoJurosMora']);
        self::assertSame(2.5, $protesto['valorJurosMora']);
        self::assertSame(2, $protesto['tipoMulta']);
        self::assertSame(3.0, $protesto['valorMulta']);
        self::assertSame(2, $protesto['tipoDesconto']);
        self::assertSame(10, $protesto['diasProtesto']);
        self::assertSame(0, $protesto['diasNegativacao']);

        $neg = $service->buildInstrucoesForTests($this->empresa([
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_NEGATIVACAO,
            'param_boleto_protesto_dias' => '5',
        ]));
        self::assertSame(0, $neg['diasProtesto']);
        self::assertSame(5, $neg['diasNegativacao']);

        $nenhuma = $service->buildInstrucoesForTests($this->empresa([
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA,
            'param_boleto_protesto_dias' => '5',
            'param_boleto_protestar_automatico' => true,
        ]));
        self::assertSame(0, $nenhuma['diasProtesto']);
        self::assertSame(0, $nenhuma['diasNegativacao']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function empresa(array $extra = []): Empresa
    {
        return new Empresa(array_merge([
            'param_boleto_banco' => EmpresaParametros::BOLETO_BANCO_AILOS,
            'param_boleto_pos_vencimento' => 'nenhuma',
        ], $extra));
    }
}
