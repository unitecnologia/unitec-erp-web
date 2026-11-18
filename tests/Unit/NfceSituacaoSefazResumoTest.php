<?php

namespace Tests\Unit;

use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceListAvisoRodape;
use App\Support\Erp\Nfce\NfceListRowFormatter;
use PHPUnit\Framework\TestCase;

class NfceSituacaoSefazResumoTest extends TestCase
{
    public function test_resumo_rejeitada_mantem_cstat_sem_motivo_completo(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'motivo_rejeicao' => 'cStat 392: Rejeicao: Nao e permitido o preenchimento de grupo de cartoes...',
        ]);

        $this->assertSame('392', $nfce->cStatDoMotivoRejeicao());
        $this->assertSame('Rejeitada — cStat 392', $nfce->situacaoSefazResumo());
        $this->assertStringNotContainsString('Nao e permitido', $nfce->situacaoSefazResumo());
    }

    public function test_resumo_denegada_com_cstat(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_DENEGADA,
            'motivo_rejeicao' => 'cStat 301: Uso Denegado: Irregularidade fiscal do emitente',
        ]);

        $this->assertSame('Uso denegado — cStat 301', $nfce->situacaoSefazResumo());
    }

    public function test_resumo_sem_motivo_fica_somente_status(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'motivo_rejeicao' => null,
        ]);

        $this->assertNull($nfce->cStatDoMotivoRejeicao());
        $this->assertSame('Rejeitada', $nfce->situacaoSefazResumo());
    }

    public function test_duplicidade_nao_repete_539_no_resumo(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_DUPLICIDADE,
            'motivo_rejeicao' => 'cStat 539: número já utilizado na SEFAZ com outra chave',
        ]);

        $this->assertSame('Duplicidade (539)', $nfce->situacaoSefazResumo());
    }

    public function test_formatter_coluna_situacao_usa_resumo_e_preserva_motivo_no_title(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'serie' => '1',
            'numero' => 52,
            'operacao' => 'pdv',
            'motivo_rejeicao' => 'cStat 392: Rejeicao: Nao e permitido o preenchimento de grupo de cartoes na NFC-e',
        ]);
        $nfce->setRelation('pdvVenda', null);

        $cells = (new NfceListRowFormatter)->format($nfce, showSituacao: true);

        $this->assertArrayHasKey('situacao', $cells);
        $this->assertStringContainsString('Rejeitada — cStat 392', $cells['situacao']);
        $this->assertStringNotContainsString('Nao e permitido', strip_tags($cells['situacao']));
        $this->assertStringContainsString(
            'title="cStat 392: Rejeicao: Nao e permitido o preenchimento de grupo de cartoes na NFC-e"',
            $cells['situacao'],
        );
    }

    public function test_aviso_rodape_denegado_preserva_cstat_e_motivo_completo(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'numero' => 52,
            'motivo_rejeicao' => 'cStat 392: Rejeicao: Nao e permitido o preenchimento de grupo de cartoes na NFC-e',
        ]);

        $aviso = NfceListAvisoRodape::fromRecord($nfce, PdvVendaNfce::TAB_DENEGADO);

        $this->assertNotNull($aviso);
        $this->assertSame('NFC-e 000052 rejeitada (cStat 392)', $aviso['titulo']);
        $this->assertSame(
            'cStat 392: Rejeicao: Nao e permitido o preenchimento de grupo de cartoes na NFC-e',
            $aviso['motivo'],
        );
        $this->assertStringContainsString('F5', $aviso['ajuda']);
    }

    public function test_aviso_rodape_denegado_sem_motivo_oculta(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'numero' => 51,
            'motivo_rejeicao' => null,
        ]);

        $this->assertNull(NfceListAvisoRodape::fromRecord($nfce, PdvVendaNfce::TAB_DENEGADO));
    }

    public function test_aviso_rodape_contingencia_permanece_no_padrao_amarelo(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'numero' => 10,
            'motivo_contingencia' => 'SEFAZ indisponivel no momento da emissao',
        ]);

        $aviso = NfceListAvisoRodape::fromRecord($nfce, PdvVendaNfce::TAB_CONTINGENCIA);

        $this->assertNotNull($aviso);
        $this->assertSame('NFC-e 000010 em contingência', $aviso['titulo']);
        $this->assertSame('SEFAZ indisponivel no momento da emissao', $aviso['motivo']);
    }
}
