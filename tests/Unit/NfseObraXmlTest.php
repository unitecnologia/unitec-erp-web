<?php

namespace Tests\Unit;

use App\Support\Erp\Nfse\NfseDpsXmlGerador;
use App\Support\Erp\Nfse\NfseObra;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class NfseObraXmlTest extends TestCase
{
    public function test_codigo_fora_da_lista_nao_gera_grupo(): void
    {
        $this->assertFalse(NfseObra::exige('010701'));
        $this->assertNull(NfseObra::paraXml('010701', [
            'obra_tipo' => 'cObra',
            'obra_c_obra' => '123',
        ]));
        $this->assertNull(NfseObra::pendencia('010701', []));

        $xml = $this->xml([
            'cTribNac' => '010701',
            'descricao' => 'Servico comum',
        ]);

        $this->assertNotNull($xml);
        $this->assertSame(['locPrest', 'cServ'], $this->filhos($xml, 'serv'));
        $this->assertStringNotContainsString('<obra', $xml);
    }

    public function test_cobra_fica_imediatamente_apos_cserv(): void
    {
        $grupo = NfseObra::paraXml('07.02.02', [
            'obra_tipo' => 'cObra',
            'obra_c_obra' => '123456789012',
            'obra_insc_imob_fisc' => ' 99 ',
        ]);

        $xml = $this->xml([
            'cTribNac' => '070202',
            'descricao' => 'Execucao de obra',
            'cNBS' => '123456789',
            'obra' => $grupo,
        ]);

        $this->assertNotNull($xml);
        $this->assertSame(['locPrest', 'cServ', 'obra'], $this->filhos($xml, 'serv'));
        $this->assertSame(['inscImobFisc', 'cObra'], $this->filhos($xml, 'obra'));
        $this->assertStringNotContainsString('<cCIB', $xml);
        $this->assertStringNotContainsString('<end', $xml);
    }

    public function test_endereco_respeita_a_ordem_do_xsd(): void
    {
        $grupo = NfseObra::paraXml('141403', [
            'obra_tipo' => 'end',
            'obra_cep' => '80010-000',
            'obra_logradouro' => 'Rua da Obra',
            'obra_numero' => '100',
            'obra_complemento' => 'Sala 2',
            'obra_bairro' => 'Centro',
        ]);

        $xml = $this->xml([
            'cTribNac' => '141403',
            'descricao' => 'Obra',
            'obra' => $grupo,
        ]);

        $this->assertNotNull($xml);
        $this->assertSame(['CEP', 'xLgr', 'nro', 'xCpl', 'xBairro'], $this->filhos($xml, 'end'));
    }

    public function test_sem_identificacao_bloqueia_e_nao_monta_xml(): void
    {
        $linha = ['obra_tipo' => ''];

        $this->assertNotNull(NfseObra::pendencia('070202', $linha, 'Pintura'));
        $this->assertNull(NfseObra::paraXml('070202', $linha));
        $this->assertNull(NfseObra::pendencia('010101', $linha));
    }

    public function test_ccib_exige_oito_caracteres(): void
    {
        $this->assertNotNull(NfseObra::pendencia('070202', [
            'obra_tipo' => 'cCIB',
            'obra_c_cib' => '1234567',
        ]));
        $this->assertSame('ABCD1234', NfseObra::paraXml('070202', [
            'obra_tipo' => 'cCIB',
            'obra_c_cib' => 'ABCD1234',
        ])['cCIB']);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function xml(array $item): ?string
    {
        $gerado = (new NfseDpsXmlGerador)->gerar([
            'dps' => [],
            'prestador' => [],
            'tomador' => [],
            'municipio_prestacao' => ['codigo' => '4106902'],
            'servicos' => [$item],
            'valores' => [],
        ]);

        return $gerado === '' ? null : $gerado;
    }

    /**
     * @return list<string>
     */
    private function filhos(string $xml, string $pai): array
    {
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', NfseDpsXmlGerador::NS);
        $nos = $xpath->query('//n:'.$pai.'/*');
        $nomes = [];

        foreach ($nos ?: [] as $no) {
            $nomes[] = $no->localName;
        }

        return $nomes;
    }
}
