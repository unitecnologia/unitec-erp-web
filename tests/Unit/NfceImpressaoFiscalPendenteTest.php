<?php

namespace Tests\Unit;

use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceDanfeA4Data;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
use Tests\TestCase;

class NfceImpressaoFiscalPendenteTest extends TestCase
{
    public function test_pendente_e_contingencia_podem_imprimir(): void
    {
        $pendente = new PdvVendaNfce([
            'numero' => 1,
            'status' => PdvVendaNfce::STATUS_PENDENTE,
            'simulada' => false,
        ]);
        $contingencia = new PdvVendaNfce([
            'numero' => 2,
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'simulada' => false,
            'chave' => str_repeat('1', 44),
        ]);
        $rejeitada = new PdvVendaNfce([
            'numero' => 3,
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'simulada' => false,
        ]);

        $this->assertNull(NfceImpressaoFiscal::motivoBloqueio($pendente));
        $this->assertNull(NfceImpressaoFiscal::motivoBloqueio($contingencia));
        $this->assertNotNull(NfceImpressaoFiscal::motivoBloqueio($rejeitada));
        $this->assertSame('EMITIDA EM CONTINGÊNCIA', NfceDanfeA4Data::MSG_CONTINGENCIA);
    }
}
