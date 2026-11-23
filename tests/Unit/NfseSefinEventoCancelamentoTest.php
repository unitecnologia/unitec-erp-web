<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\NfseSefinEndpoints;
use App\Support\Erp\Nfse\NfseSefinEventoResposta;
use App\Support\Erp\Nfse\NfseSefinEventoXmlGerador;
use App\Support\Erp\Nfse\NfseSefinXmlLeitor;
use Tests\TestCase;

class NfseSefinEventoCancelamentoTest extends TestCase
{
    public function test_gera_ped_reg_evento_e101101_com_id_oficial(): void
    {
        $chave = '42032042254644503000129000000000004826110170089842';
        $empresa = new Empresa;
        $empresa->setAttribute('cnpj', '54644503000129');
        $empresa->setAttribute('nfse_ambiente', NfseSefinAmbiente::Producao->value);
        $empresa->setAttribute('nfse_provedor', 'nacional');

        $nfse = new Nfse;
        $nfse->setAttribute('chave_acesso', $chave);
        $nfse->setAttribute('numero_dps', 26);
        $nfse->setAttribute('numero_nfse', '48');
        $nfse->setAttribute('status', Nfse::STATUS_AUTORIZADA);
        $nfse->setRelation('empresa', $empresa);

        $xml = (new NfseSefinEventoXmlGerador)->gerarCancelamento($nfse, '1');

        $this->assertStringContainsString('<pedRegEvento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01"', $xml);
        $this->assertStringContainsString('Id="PRE'.$chave.'101101"', $xml);
        $this->assertStringContainsString('<tpAmb>1</tpAmb>', $xml);
        $this->assertStringContainsString('<CNPJAutor>54644503000129</CNPJAutor>', $xml);
        $this->assertStringContainsString('<chNFSe>'.$chave.'</chNFSe>', $xml);
        $this->assertStringContainsString('<e101101>', $xml);
        $this->assertStringContainsString('<xDesc>Cancelamento de NFS-e</xDesc>', $xml);
        $this->assertStringContainsString('<cMotivo>1</cMotivo>', $xml);
        $this->assertMatchesRegularExpression('/<xMotivo>.{15,}<\/xMotivo>/', $xml);
    }

    public function test_mapeia_motivos_abrasf_para_nacional(): void
    {
        $this->assertSame('9', NfseSefinEventoXmlGerador::mapearMotivo('4')['codigo']);
        $this->assertSame('1', NfseSefinEventoXmlGerador::mapearMotivo('1')['codigo']);
        $this->assertSame('2', NfseSefinEventoXmlGerador::mapearMotivo('2')['codigo']);
    }

    public function test_endpoints_de_evento_ficam_sob_a_base_oficial(): void
    {
        $chave = '42032042254644503000129000000000004826110170089842';
        $url = NfseSefinEndpoints::eventos(NfseSefinAmbiente::Producao, $chave);

        $this->assertSame(
            NfseSefinEndpoints::NFSE_PRODUCAO.'/'.$chave.'/eventos',
            $url,
        );
        $this->assertTrue(NfseSefinEndpoints::urlOficial($url));
        $this->assertSame(
            $url.'/101101/001',
            NfseSefinEndpoints::evento(NfseSefinAmbiente::Producao, $chave, '101101', '001'),
        );
    }

    public function test_interpreta_evento_autorizado_e_duplicidade(): void
    {
        $xml = '<evento><infEvento Id="EVT1"><e101101><xDesc>Cancelamento de NFS-e</xDesc></e101101></infEvento></evento>';
        $ok = NfseSefinEventoResposta::interpretar(201, json_encode([
            'tipoAmbiente' => 1,
            'dataHoraProcessamento' => '2026-11-23T15:00:00-03:00',
            'eventoXmlGZipB64' => base64_encode(gzencode($xml)),
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue($ok->registrado);
        $this->assertSame($xml, $ok->xmlEvento);
        $this->assertSame('EVT1', $ok->protocolo);

        $dup = NfseSefinEventoResposta::interpretar(400, json_encode([
            'erros' => [[
                'Codigo' => 'E1102',
                'Descricao' => 'NFS-e já cancelada.',
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue($dup->registrado);
        $this->assertTrue($dup->jaCancelada);
    }

    public function test_le_nnfse_do_xml_nacional_sem_alterar_ndps(): void
    {
        $xml = '<NFSe><infNFSe><nNFSe>48</nNFSe><nDPS>26</nDPS></infNFSe></NFSe>';

        $this->assertSame('48', NfseSefinXmlLeitor::numeroNfse($xml));
    }
}
