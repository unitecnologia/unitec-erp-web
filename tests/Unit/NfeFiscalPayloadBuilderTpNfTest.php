<?php

namespace Tests\Unit;

use App\Models\Nfe;
use App\Support\Fiscal\NfeFiscalPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Garante o mapeamento movimento → tpNF sem montar certificado/XML nem gravar no banco.
 */
class NfeFiscalPayloadBuilderTpNfTest extends TestCase
{
    #[DataProvider('movimentoProvider')]
    public function test_map_tp_nf_respeita_movimento(mixed $movimento, int $esperado): void
    {
        $nfe = new Nfe();
        $nfe->movimento = $movimento;

        $this->assertSame($esperado, $this->invokeMapTpNf($nfe));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function movimentoProvider(): array
    {
        return [
            'banco entrada 0 string' => ['0', 0],
            'banco entrada 0 int' => [0, 0],
            'ui entrada' => ['entrada', 0],
            'banco saida 1 string' => ['1', 1],
            'banco saida 1 int' => [1, 1],
            'legado S' => ['S', 1],
            'ui saida' => ['saida', 1],
            'null default saida' => [null, 1],
            'vazio default saida' => ['', 1],
        ];
    }

    private function invokeMapTpNf(Nfe $nfe): int
    {
        $method = new ReflectionMethod(NfeFiscalPayloadBuilder::class, 'mapTpNf');

        return (int) $method->invoke(new NfeFiscalPayloadBuilder(), $nfe);
    }
}
