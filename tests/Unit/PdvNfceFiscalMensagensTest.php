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

    public function test_mensagens_pdv_apos_venda_sao_curtas_sem_cstat(): void
    {
        $ok = PdvNfceFiscalMensagens::mensagemPdvAposVenda('autorizada');
        $this->assertSame('Venda concluída', $ok['titulo']);
        $this->assertNull($ok['corpo']);

        $pendente = PdvNfceFiscalMensagens::mensagemPdvAposVenda('pendente');
        $this->assertSame('Venda registrada. NFC-e pendente', $pendente['titulo']);
        $this->assertNull($pendente['corpo']);
        $this->assertStringNotContainsString('cStat', $pendente['titulo']);
        $this->assertStringNotContainsString('392', $pendente['titulo']);
        $this->assertStringNotContainsString('cartão', mb_strtolower($pendente['titulo'], 'UTF-8'));
    }
}
