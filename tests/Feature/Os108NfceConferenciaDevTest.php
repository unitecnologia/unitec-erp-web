<?php

namespace Tests\Feature;

use App\Models\OrdemServico;
use App\Support\Erp\Nfce\NfceOrdemServicoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Auditoria DEV da OS 108 × conferência F11 (sem SEFAZ, rollback).
 * Rodar: tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=Os108NfceConferenciaDevTest
 */
class Os108NfceConferenciaDevTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => env('DB_DATABASE', 'unitec_erp'),
            ]);
            DB::purge();
            DB::reconnect('mysql');
        }

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped('Exige MySQL DEV.');
        }
    }

    public function test_os_108_pecas_batem_com_conferencia_f11(): void
    {
        $os = OrdemServico::query()
            ->with(['itens.product'])
            ->whereKey(108)
            ->first();

        if ($os === null) {
            $this->markTestSkipped('OS id=108 não encontrada na base DEV.');
        }

        $this->assertSame('108', ltrim((string) $os->numero, '0') ?: (string) $os->numero);
        $this->assertEqualsWithDelta(2484.90, (float) $os->total_produtos, 0.001);
        $this->assertEqualsWithDelta(980.00, (float) $os->total_servicos, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $os->vl_desc_pecas, 0.001);
        $this->assertEqualsWithDelta(0.0, $os->descontoTotalLista(), 0.001);

        $pecas = $os->itens->filter(
            static fn ($i): bool => mb_strtoupper(trim((string) ($i->tipo ?? '')), 'UTF-8') === 'P'
        )->values();
        $servicos = $os->itens->filter(
            static fn ($i): bool => mb_strtoupper(trim((string) ($i->tipo ?? '')), 'UTF-8') === 'S'
        )->values();

        $this->assertCount(8, $pecas);
        $this->assertCount(2, $servicos);
        $this->assertEqualsWithDelta(2484.90, round((float) $pecas->sum('total'), 2), 0.001);
        $this->assertEqualsWithDelta(980.00, round((float) $servicos->sum('total'), 2), 0.001);

        $conf = (new NfceOrdemServicoService)->conferirPecas($os);

        $this->assertCount(8, $conf['itens']);
        $this->assertEqualsWithDelta(2484.90, $conf['total'], 0.001);
        $this->assertEqualsWithDelta((float) $os->total_produtos, $conf['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto_pecas'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(
            round($conf['subtotal'] - $conf['desconto'] + $conf['acrescimo'], 2),
            $conf['total'],
            0.001,
        );

        foreach ($pecas as $idx => $peca) {
            $linha = $conf['itens'][$idx];
            $this->assertEqualsWithDelta((float) $peca->qtd, $linha['quantidade'], 0.001);
            $this->assertEqualsWithDelta((float) $peca->total, $linha['total'], 0.001);
            $this->assertEqualsWithDelta((float) $peca->desconto, $linha['desconto'], 0.001);
            $this->assertEqualsWithDelta(
                round((float) $peca->total / (float) $peca->qtd, 2),
                $linha['valor_unitario'],
                0.001,
            );
        }
    }
}
