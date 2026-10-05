<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\Nfse\Ipm\NfseIpmImpressaoViewData;
use App\Support\Erp\Nfse\Ipm\NfseLc116;
use App\Support\Erp\Nfse\NfseImpressao;
use Tests\TestCase;

class NfseIpmImpressaoTest extends TestCase
{
    public function test_layout_ipm_usa_dados_da_nota_e_nao_o_pdf_de_referencia(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('status', Nfse::STATUS_AUTORIZADA);
        $nota->setAttribute('numero_nfse', '15');
        $nota->setAttribute('chave', 'ABC123XYZ');
        $nota->setAttribute('data_hora_processamento', '2026-09-28T17:41:00');

        $this->assertTrue(NfseIpmImpressaoViewData::aplica($nota));
        $this->assertSame(NfseImpressao::VIEW_IPM, NfseImpressao::dados($nota)['impressao_view']);

        $dados = NfseIpmImpressaoViewData::for($nota);
        $ipm = $dados['ipm'];

        $this->assertSame('15', $ipm['numero']);
        $this->assertSame('Emitida', $ipm['situacao']);
        $this->assertSame('RPS', $ipm['tipo']);
        $this->assertSame('ABC1 23XY Z', $ipm['identificador']);
        $this->assertSame('-', $ipm['chave_acesso']);
        $this->assertSame('140101', $ipm['servico_codigo']);
        $this->assertSame('4203204', $ipm['local_codigo']);
        $this->assertSame('SIMPLES NACIONAL', $ipm['aliquota']);
        $this->assertSame('SIMPLES NACIONAL', $ipm['valor_iss']);
        $this->assertSame('100,00', $ipm['valor_servico']);
        $this->assertSame('0,00', $ipm['desconto_incondicional']);
        $this->assertSame('Exigível', $ipm['natureza']);
        $this->assertSame('1.2001.31.10', $ipm['nbs']);
        $this->assertStringContainsString('Lubrificação, limpeza, lustração', $ipm['lc116']);
        $this->assertSame('4520-0/01', $ipm['atividade']);
        $this->assertStringContainsString('4203204 - Itajaí', $ipm['legenda_local']);
        $this->assertStringContainsString('Simples Nacional', $ipm['outras']);
        $this->assertStringNotContainsString('8061', $ipm['legenda_local'].$ipm['local_codigo'].$ipm['outras']);

        $html = view(NfseImpressao::VIEW_IPM, $dados)->render();

        $this->assertStringContainsString('OFICINA TESTE LTDA', $html);
        $this->assertStringContainsString('CLIENTE TESTE', $html);
        $this->assertStringContainsString('Número da NFS-e', $html);
        $this->assertStringContainsString('TOMADOR DO SERVIÇO', $html);
        $this->assertStringContainsString('DESCRIÇÃO DOS SERVIÇOS PRESTADOS', $html);
        $this->assertStringContainsString('Chave de Acesso NFS-e Nacional', $html);
        $this->assertStringContainsString('Série NE', $html);
        $this->assertStringContainsString('Outras Informações', $html);
        $this->assertStringContainsString('Valor do PIS Devido: R$0,00', $html);
        $this->assertStringNotContainsString('2.180,00', $html);
        $this->assertStringNotContainsString('PATRICIA', $html);
        $this->assertStringNotContainsString('42032041254644503000129', $html);
        $this->assertStringNotContainsString('SECRETARIA MUNICIPAL DE FINANÇAS', $html);
        $this->assertStringNotContainsString('DANFSe v2.0', $html);
    }

    public function test_nota_nacional_continua_no_danfse(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('xml_nfse', '<?xml version="1.0"?><NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"></NFSe>');

        $this->assertFalse(NfseIpmImpressaoViewData::aplica($nota));
        $this->assertSame(NfseImpressao::VIEW_NACIONAL, NfseImpressao::dados($nota)['impressao_view']);
    }

    public function test_fora_do_simples_mostra_aliquota_calculada(): void
    {
        $nota = $this->nota();
        $nota->empresa->setAttribute('regime_tributario', 'lucro_presumido');
        $nota->setAttribute('iss', '5.00');

        $ipm = NfseIpmImpressaoViewData::for($nota)['ipm'];

        $this->assertSame('5.00%', $ipm['aliquota']);
        $this->assertSame('5,00', $ipm['valor_iss']);
        $this->assertSame('5,00', $ipm['issqn']);
        $this->assertStringNotContainsString('SIMPLES NACIONAL', $ipm['aliquota'].$ipm['valor_iss'].$ipm['issqn'].$ipm['base_calculo']);
    }

    public function test_retorno_ipm_de_araucaria_usa_valores_oficiais_e_codigo_siafi(): void
    {
        $nota = $this->nota();
        $nota->empresa->setAttribute('cidade_codigo', '4101804');
        $nota->setAttribute('municipio_prestacao_codigo', '4101804');
        $nota->setAttribute('status', Nfse::STATUS_AUTORIZADA);
        $nota->setAttribute('xml_nfse', '<GerarNfseResposta><ListaNfse><CompNfse><Nfse><InfNfse><Numero>73</Numero>'
            .'<CodigoVerificacao>7435051026171551660536920682026107398905</CodigoVerificacao>'
            .'<OutrasInformacoes>https://araucaria.atende.net/consulta/7435051026171551660536920682026107398905 Chave de Acesso NFS-e Nacional: 41018041253692068000145000000000007326100000000019</OutrasInformacoes>'
            .'<ValoresNfse><BaseCalculo>100.00</BaseCalculo><Aliquota>4.17</Aliquota><ValorIss>4.17</ValorIss></ValoresNfse>'
            .'</InfNfse></Nfse></CompNfse></ListaNfse></GerarNfseResposta>');

        $ipm = NfseIpmImpressaoViewData::for($nota)['ipm'];

        $this->assertSame('41018041253692068000145000000000007326100000000019', $ipm['chave_acesso']);
        $this->assertSame('4.17%', $ipm['aliquota']);
        $this->assertSame('4,17', $ipm['valor_iss']);
        $this->assertSame('7435', $ipm['local_codigo']);
        $this->assertSame('7435 - Araucária', $ipm['legenda_local']);
        $this->assertSame('SECRETARIA MUNICIPAL DE FINANÇAS', $ipm['prestador']['secretaria']);
        $this->assertNotNull($ipm['codigo_barras']);
    }

    public function test_lc116_desconhece_codigo_sem_inventar_texto(): void
    {
        $this->assertNull(NfseLc116::descricao('999999'));
        $this->assertNotNull(NfseLc116::descricao('140101'));
    }

    private function nota(): Nfse
    {
        $empresa = new Empresa([
            'cnpj' => '11.222.333/0001-81',
            'razao_social' => 'OFICINA TESTE LTDA',
            'im' => '100',
            'ie' => '123456',
            'cnae' => '4520001',
            'cidade' => 'Itajaí',
            'cidade_codigo' => '4203204',
            'uf' => 'SC',
            'endereco' => 'RUA DAS OFICINAS',
            'numero' => '10',
            'bairro' => 'CENTRO',
            'cep' => '88301000',
            'email' => 'oficina@example.com',
            'telefone' => '4733334444',
            'regime_tributario' => 'simples',
        ]);
        $empresa->setAttribute('nfse_provedor', 'ipm');
        $empresa->setAttribute('nfse_tipo_rps', '1');
        $empresa->id = 1;

        $nota = new Nfse([
            'status' => Nfse::STATUS_ABERTA,
            'serie_dps' => 'NE',
            'numero_dps' => 1,
            'competencia' => '2026-09-01',
            'data_emissao' => '2026-09-28',
            'tomador_nome' => 'CLIENTE TESTE',
            'tomador_cpf_cnpj' => '123.456.789-09',
            'tomador_endereco' => 'RUA TESTE',
            'tomador_numero' => '10',
            'tomador_bairro' => 'CENTRO',
            'tomador_cep' => '88340000',
            'tomador_cidade' => 'Penha',
            'tomador_uf' => 'SC',
            'tomador_cidade_codigo' => '4212502',
            'municipio_prestacao_codigo' => '4203204',
            'municipio_prestacao_nome' => 'Itajaí',
            'municipio_prestacao_uf' => 'SC',
            'trib_issqn' => '1',
            'tp_ret_issqn' => '1',
            'valor_servicos' => '100.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '100.00',
        ]);
        $nota->setRelation('empresa', $empresa);
        $nota->setRelation('itens', collect([
            new NfseItem([
                'descricao' => 'SERVICO DE TESTE',
                'c_trib_nac' => '140101',
                'c_nbs' => '120013110',
            ]),
        ]));

        return $nota;
    }
}
