<?php

namespace Tests\Unit;

use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

class PdvNfceFiscalMensagensTest extends TestCase
{
    public function test_mapeia_cstat_501_para_modal_de_prazo_expirado(): void
    {
        $exception = new FiscalEngineException(
            'Rejeicao: Prazo de Cancelamento Superior ao Previsto na Legislacao [cStat 501]',
            '501',
            'Rejeicao: Prazo de Cancelamento Superior ao Previsto na Legislacao',
        );

        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        $this->assertTrue($resolvido['modal']);
        $this->assertSame('Não é possível cancelar esta NFC-e', $resolvido['titulo']);
        $this->assertStringContainsString('30 minutos', (string) $resolvido['corpo']);
        $this->assertStringContainsString('modelo 55', (string) $resolvido['corpo']);
    }

    public function test_remove_cstat_do_titulo_padrao(): void
    {
        $exception = new FiscalEngineException(
            'Duplicidade de NF-e [cStat 204]',
            '204',
            'Duplicidade de NF-e',
        );

        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        $this->assertFalse($resolvido['modal']);
        $this->assertSame('Duplicidade de NF-e', $resolvido['titulo']);
    }
}
