<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Venda;
use App\Support\Fiscal\VendaFiscalLock;
use Tests\TestCase;

/**
 * Monitor / NF-e: NFC-e autorizada ou em contingência bloqueia Emitir NF-e.
 * Rejeitada/cancelada/simulada não bloqueiam (regra alinhada à transmissão).
 */
class VendaFiscalLockNfceBloqueiaNfeTest extends TestCase
{
    public function test_nfce_autorizada_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_AUTORIZADA, simulada: false);

        $motivo = VendaFiscalLock::motivoBloqueioNfePorNfce($nfce);

        $this->assertSame(VendaFiscalLock::MENSAGEM_NFCE_BLOQUEIA_NFE, $motivo);
    }

    public function test_nfce_contingencia_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_CONTINGENCIA, simulada: false);

        $this->assertSame(
            VendaFiscalLock::MENSAGEM_NFCE_BLOQUEIA_NFE,
            VendaFiscalLock::motivoBloqueioNfePorNfce($nfce),
        );
    }

    public function test_nfce_rejeitada_nao_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_REJEITADA, simulada: false);

        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_nfce_cancelada_nao_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_CANCELADA, simulada: false);

        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_nfce_pendente_nao_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_PENDENTE, simulada: false);

        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_nfce_denegada_nao_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_DENEGADA, simulada: false);

        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_nfce_simulada_autorizada_nao_bloqueia_nfe(): void
    {
        $nfce = $this->nfce(PdvVendaNfce::STATUS_AUTORIZADA, simulada: true);

        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_sem_nfce_nao_bloqueia_nfe(): void
    {
        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce(null));
    }

    public function test_numero_nc_sozinho_nao_prova_autorizacao(): void
    {
        // Mesmo com número preenchido (ex.: NC-16951 na grade), status rejeitada libera NF-e.
        $nfce = $this->nfce(PdvVendaNfce::STATUS_REJEITADA, simulada: false, numero: '16951');

        $this->assertSame('16951', (string) $nfce->numero);
        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorNfce($nfce));
    }

    public function test_venda_com_relacao_eager_respeita_status(): void
    {
        $venda = new Venda(['id' => 10]);
        $pdv = new PdvVenda(['id' => 20, 'venda_id' => 10]);
        $pdv->setRelation('nfce', $this->nfce(PdvVendaNfce::STATUS_AUTORIZADA, false, '16951'));
        $venda->setRelation('pdvVenda', $pdv);

        $this->assertSame(
            VendaFiscalLock::MENSAGEM_NFCE_BLOQUEIA_NFE,
            VendaFiscalLock::motivoBloqueioNfePorVenda($venda),
        );

        $pdv->setRelation('nfce', $this->nfce(PdvVendaNfce::STATUS_CANCELADA, false, '16951'));
        $this->assertNull(VendaFiscalLock::motivoBloqueioNfePorVenda($venda));
    }

    private function nfce(string $status, bool $simulada, string $numero = '100'): PdvVendaNfce
    {
        $nfce = new PdvVendaNfce([
            'pdv_venda_id' => 1,
            'numero' => $numero,
            'status' => $status,
            'simulada' => $simulada,
        ]);
        $nfce->syncOriginal();

        return $nfce;
    }
}
