<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceListCaixaLabel;
use App\Support\Erp\Nfce\NfceListDavNumero;
use App\Support\Erp\Nfce\NfceListRowFormatter;
use PHPUnit\Framework\TestCase;

class NfceListCaixaLabelTest extends TestCase
{
    protected function tearDown(): void
    {
        NfceListCaixaLabel::limparCache();
        parent::tearDown();
    }

    public function test_pdv_usa_terminal_da_sessao(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => 'nfce_transmitir',
            'numero' => 1,
            'serie' => '1',
        ]);

        $sessao = new class
        {
            public object $terminal;

            public function __construct()
            {
                $this->terminal = (object) ['nome' => 'CAIXA-1'];
            }
        };

        $venda = new PdvVenda(['origem' => null]);
        $venda->setRelation('sessao', $sessao);
        $nfce->setRelation('pdvVenda', $venda);

        $this->assertSame('CAIXA-1', NfceListCaixaLabel::texto($nfce));
    }

    public function test_scv_hist_retorna_caixa_geral(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => NfceListDavNumero::OPERACAO_SCV_HIST,
            'numero' => 1,
            'serie' => '1',
        ]);
        $nfce->setRelation('pdvVenda', null);

        $this->assertSame('CAIXA GERAL', NfceListCaixaLabel::texto($nfce));
    }

    public function test_regularizacao_usa_mapa_financeiro_por_venda_id(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => 'nfce_transmitir',
            'numero' => 8855,
            'serie' => '1',
        ]);
        $venda = new PdvVenda([
            'origem' => PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
            'venda_id' => 59007,
        ]);
        $venda->setRelation('sessao', null);
        $nfce->setRelation('pdvVenda', $venda);

        $this->assertSame(
            'CAIXA GERAL',
            NfceListCaixaLabel::texto($nfce, [59007 => 'CAIXA GERAL']),
        );
    }

    public function test_formatter_exibe_caixa_financeiro_da_regularizacao(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => 'nfce_transmitir',
            'numero' => 8853,
            'serie' => '1',
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
        ]);
        $venda = new PdvVenda([
            'origem' => PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
            'venda_id' => 59002,
            'subtotal' => 100,
            'acrescimo' => 0,
            'desconto' => 0,
            'total' => 100,
        ]);
        $venda->setRelation('sessao', null);
        $venda->setRelation('vendedor', null);
        $venda->setRelation('venda', null);
        $venda->setRelation('person', null);
        $nfce->setRelation('pdvVenda', $venda);

        $cells = (new NfceListRowFormatter)->format(
            $nfce,
            false,
            null,
            [59002 => 'CAIXA GERAL'],
        );

        $this->assertSame('CAIXA GERAL', html_entity_decode(strip_tags($cells['caixa'])));
    }
}
