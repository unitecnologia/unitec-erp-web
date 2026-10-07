<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Support\Erp\Nfse\NfseDpsXmlGerador;
use App\Support\Erp\Nfse\NfseOsDiscriminacao;
use DOMDocument;
use Tests\TestCase;

class NfseOsDiscriminacaoTest extends TestCase
{
    public function test_monta_uma_linha_por_informacao_sem_vazios(): void
    {
        $os = new OrdemServico([
            'numero' => '6',
            'descricao' => 'FIAT FIORINO',
            'modelo' => '1.4 FLEX',
            'placa' => 'lzk-6311',
            'numero_serie' => '',
            'problema' => "Câmbio   raspando\na 2ª marcha",
            'laudo' => 'Embreagem gasta; trocado kit completo',
            'observacoes' => '   ',
        ]);

        $this->assertSame(
            "OS nº 6\n"
            ."Equipamento/Veículo: FIAT FIORINO | Modelo: 1.4 FLEX | Placa: LZK-6311\n"
            ."Problema: Câmbio raspando a 2ª marcha\n"
            .'Laudo: Embreagem gasta; trocado kit completo',
            NfseOsDiscriminacao::texto($os),
        );
    }

    public function test_nao_repete_informacao(): void
    {
        $os = new OrdemServico([
            'numero' => '7',
            'descricao' => 'IPHONE 13 PRO SERIE ABC123',
            'modelo' => 'iPhone 13 Pro',
            'numero_serie' => 'abc-123',
            'problema' => 'Tela quebrada',
            'laudo' => 'tela quebrada',
            'observacoes' => 'TELA QUEBRADA.',
        ]);

        $this->assertSame(
            "OS nº 7\nEquipamento/Veículo: IPHONE 13 PRO SERIE ABC123\nProblema: Tela quebrada",
            NfseOsDiscriminacao::texto($os),
        );
    }

    public function test_os_sem_equipamento_e_textos_traz_so_o_numero(): void
    {
        $this->assertSame('OS nº 8', NfseOsDiscriminacao::texto(new OrdemServico(['numero' => '8'])));
    }

    public function test_anexar_nao_duplica_a_mesma_os_e_separa_outra(): void
    {
        $primeira = new OrdemServico(['numero' => '6', 'placa' => 'LZK6311']);
        $segunda = new OrdemServico(['numero' => '9', 'laudo' => 'Revisão feita']);

        $texto = NfseOsDiscriminacao::anexar('', $primeira);
        $this->assertSame($texto, NfseOsDiscriminacao::anexar($texto, $primeira));

        $this->assertSame(
            "OS nº 6\nPlaca: LZK6311\n\nOS nº 9\nLaudo: Revisão feita",
            NfseOsDiscriminacao::anexar($texto, $segunda),
        );
        $this->assertSame("OS nº 60\n\nOS nº 6\nPlaca: LZK6311", NfseOsDiscriminacao::anexar('OS nº 60', $primeira));
    }

    public function test_texto_longo_fica_no_limite_do_campo(): void
    {
        $os = new OrdemServico([
            'numero' => '6',
            'problema' => str_repeat('A', 900),
            'laudo' => str_repeat('B', 900),
            'observacoes' => str_repeat('C', 900),
        ]);

        $texto = NfseOsDiscriminacao::texto($os);

        $this->assertLessThanOrEqual(NfseOsDiscriminacao::LIMITE, mb_strlen($texto));
        $this->assertStringContainsString('Problema: '.str_repeat('A', 397).'...', $texto);
    }

    public function test_nacional_leva_o_bloco_no_xdescserv_sem_quebra_de_linha(): void
    {
        $xml = (new NfseDpsXmlGerador)->gerar([
            'dps' => [],
            'prestador' => [],
            'tomador' => [],
            'municipio_prestacao' => ['codigo' => '4203204'],
            'servicos' => [[
                'descricao' => 'MAO DE OBRA',
                'cTribNac' => '140101',
                'cNBS' => '120013110',
            ]],
            'discriminacao' => "OS nº 6\nEquipamento/Veículo: FIAT FIORINO | Placa: LZK6311\nLaudo: Trocado — kit “completo”",
            'valores' => [],
        ]);

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $descricao = $doc->getElementsByTagName('xDescServ')->item(0)?->textContent;

        $this->assertSame(
            'MAO DE OBRA | OS nº 6 | Equipamento/Veículo: FIAT FIORINO | Placa: LZK6311 | Laudo: Trocado - kit "completo"',
            $descricao,
        );
        $this->assertMatchesRegularExpression('/^[\x{20}-\x{FF}]+$/u', (string) $descricao);
    }
}
