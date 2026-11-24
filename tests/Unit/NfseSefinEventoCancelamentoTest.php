<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinCancelamentoConsulta;
use App\Support\Erp\Nfse\NfseSefinCancelarService;
use App\Support\Erp\Nfse\NfseSefinEndpoints;
use App\Support\Erp\Nfse\NfseSefinEventoResposta;
use App\Support\Erp\Nfse\NfseSefinEventoXmlGerador;
use App\Support\Erp\Nfse\NfseSefinXmlLeitor;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_modal_nacional_expoe_tres_motivos_oficiais(): void
    {
        $this->assertSame([
            '1' => 'Erro na emissão',
            '2' => 'Serviço não prestado',
            '9' => 'Outros',
        ], NfseSefinEventoXmlGerador::motivos());
    }

    #[DataProvider('provedorMotivosNacionais')]
    public function test_cada_motivo_do_modal_gera_e101101_com_cmotivo_e_xmotivo(
        string $codigoUi,
        string $cMotivoEsperado,
        ?string $justificativa,
    ): void {
        $chave = '42032042254644503000129000000000004826110170089842';
        $empresa = new Empresa;
        $empresa->setAttribute('cnpj', '54644503000129');
        $empresa->setAttribute('nfse_ambiente', NfseSefinAmbiente::Producao->value);
        $empresa->setAttribute('nfse_provedor', 'nacional');

        $nfse = new Nfse;
        $nfse->setAttribute('chave_acesso', $chave);
        $nfse->setAttribute('status', Nfse::STATUS_AUTORIZADA);
        $nfse->setRelation('empresa', $empresa);

        $mapeado = NfseSefinEventoXmlGerador::mapearMotivo($codigoUi, $justificativa);
        $this->assertSame($cMotivoEsperado, $mapeado['codigo']);

        $xml = (new NfseSefinEventoXmlGerador)->gerarCancelamento($nfse, $codigoUi, $justificativa);

        $this->assertStringContainsString('<e101101>', $xml);
        $this->assertStringContainsString('<cMotivo>'.$cMotivoEsperado.'</cMotivo>', $xml);
        $this->assertMatchesRegularExpression('/<xMotivo>.{15,255}<\/xMotivo>/u', $xml);
        $this->assertStringContainsString('Id="PRE'.$chave.'101101"', $xml);
        if ($justificativa !== null) {
            $this->assertStringContainsString('<xMotivo>'.$justificativa.'</xMotivo>', $xml);
        }
        $this->assertSame(
            NfseSefinEndpoints::NFSE_PRODUCAO.'/'.$chave.'/eventos/101101/001',
            NfseSefinEndpoints::evento(
                NfseSefinAmbiente::Producao,
                $chave,
                NfseSefinEventoXmlGerador::TIPO_CANCELAMENTO,
                NfseSefinEventoXmlGerador::SEQ_CANCELAMENTO,
            ),
        );
    }

    public static function provedorMotivosNacionais(): array
    {
        return [
            'erro_na_emissao' => ['1', '1', null],
            'servico_nao_prestado' => ['2', '2', null],
            'outros' => ['9', '9', 'Cancelamento solicitado por divergencia cadastral.'],
        ];
    }

    public function test_outros_exige_justificativa_do_operador_no_post(): void
    {
        $this->expectException(NfseNaoTransmitida::class);
        $this->expectExceptionMessage('15 a 255');

        $chave = '42032042254644503000129000000000004826110170089842';
        $empresa = new Empresa;
        $empresa->setAttribute('cnpj', '54644503000129');
        $empresa->setAttribute('nfse_ambiente', NfseSefinAmbiente::Producao->value);
        $nfse = new Nfse;
        $nfse->setAttribute('chave_acesso', $chave);
        $nfse->setRelation('empresa', $empresa);

        (new NfseSefinEventoXmlGerador)->gerarCancelamento($nfse, '9', 'curto');
    }

    public function test_outros_normaliza_justificativa_valida(): void
    {
        $texto = 'Servico faturado em duplicidade na competencia.';
        $this->assertSame($texto, NfseSefinEventoXmlGerador::normalizarXMotivo($texto, exigir: true));
        $this->assertGreaterThanOrEqual(15, mb_strlen($texto));
        $this->assertLessThanOrEqual(255, mb_strlen($texto));
    }

    public function test_rejeicao_e_evento_ja_registrado_nao_dependem_do_motivo_ui(): void
    {
        foreach (['1', '2', '9'] as $codigoUi) {
            $this->assertSame($codigoUi === '9' ? '9' : $codigoUi, NfseSefinEventoXmlGerador::mapearMotivo($codigoUi)['codigo']);
        }

        $rejeicao = NfseSefinEventoResposta::interpretar(400, json_encode([
            'erro' => [['Codigo' => 'E0822', 'Descricao' => 'Prazo para cancelamento da NFS-e expirado.']],
        ], JSON_THROW_ON_ERROR));
        $this->assertFalse($rejeicao->registrado);
        $this->assertSame('E0822', $rejeicao->erros[0]['codigo']);

        $e0840 = NfseSefinEventoResposta::interpretar(400, json_encode([
            'erro' => [[
                'Codigo' => 'E0840',
                'Descricao' => 'O evento de Cancelamento de NFS-e já está vinculado à NFS-e indicada.',
            ]],
        ], JSON_THROW_ON_ERROR));
        $this->assertFalse($e0840->registrado);
        $this->assertFalse($e0840->jaCancelada);
        $this->assertTrue(NfseSefinEventoResposta::exigeConsultaConfirmacao($e0840->erros));

        $xml = '<evento><infEvento Id="EVTREG"><e101101><xDesc>Cancelamento de NFS-e</xDesc></e101101></infEvento></evento>';
        $registrado = NfseSefinCancelamentoConsulta::classificar(200, json_encode([
            'eventos' => [[
                'tipoEvento' => '101101',
                'arquivoXml' => base64_encode(base64_encode(gzencode($xml))),
            ]],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(NfseSefinCancelamentoConsulta::REGISTRADO, $registrado['status']);
        $this->assertFalse(NfseSefinCancelamentoConsulta::podeEnviarNovoPost($registrado['status']));
    }

    public function test_situacao_local_so_confirma_com_evento_registrado(): void
    {
        $src = (string) file_get_contents((new \ReflectionClass(NfseSefinCancelarService::class))->getFileName());

        $this->assertMatchesRegularExpression(
            '/if \(\$consultaPrevia\[\'status\'\] === NfseSefinCancelamentoConsulta::REGISTRADO\).*confirmarLocalmente/s',
            $src,
        );
        $this->assertStringContainsString("'status' => Nfse::STATUS_CANCELADA", $src);
        // Falha de POST/GET não chama confirmarLocalmente sem registrado.
        $this->assertStringContainsString("'cancelada' => false", $src);
        $this->assertStringContainsString('protocoloParaGravar', $src);
        $this->assertStringContainsString('xml_cancelamento', $src);
    }

    public function test_protocolo_emissao_nao_e_substituido_pelo_id_evt(): void
    {
        $idEvt = 'EVT42032042254644503000129000000000004926116821242379101101001';
        $this->assertSame(62, strlen($idEvt));

        $this->assertSame(
            'PROT-EMISSAO-123',
            NfseSefinCancelarService::protocoloParaGravar($idEvt, 'PROT-EMISSAO-123'),
        );
        $this->assertNull(NfseSefinCancelarService::protocoloParaGravar($idEvt, null));
        $this->assertNull(NfseSefinCancelarService::protocoloParaGravar($idEvt, ''));
        $this->assertSame(
            'ABC123',
            NfseSefinCancelarService::protocoloParaGravar('ABC123', null),
        );
    }

    public function test_reconciliacao_get_idempotente_sem_novo_post(): void
    {
        $xml = '<evento><infEvento Id="EVT42032042254644503000129000000000004826110170089842101101001"><e101101><xDesc>Cancelamento de NFS-e</xDesc></e101101></infEvento></evento>';
        $primeiro = NfseSefinCancelamentoConsulta::classificar(200, json_encode([
            'eventos' => [[
                'tipoEvento' => '101101',
                'arquivoXml' => base64_encode(base64_encode(gzencode($xml))),
            ]],
        ], JSON_THROW_ON_ERROR));
        $segundo = NfseSefinCancelamentoConsulta::classificar(200, json_encode([
            'eventos' => [[
                'tipoEvento' => '101101',
                'arquivoXml' => base64_encode(base64_encode(gzencode($xml))),
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(NfseSefinCancelamentoConsulta::REGISTRADO, $primeiro['status']);
        $this->assertSame(NfseSefinCancelamentoConsulta::REGISTRADO, $segundo['status']);
        $this->assertFalse(NfseSefinCancelamentoConsulta::podeEnviarNovoPost($primeiro['status']));
        $this->assertFalse(NfseSefinCancelamentoConsulta::podeEnviarNovoPost($segundo['status']));
        $this->assertSame(
            'PROT-ORIGINAL',
            NfseSefinCancelarService::protocoloParaGravar($primeiro['resposta']?->protocolo, 'PROT-ORIGINAL'),
        );
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

        // E1102 indica já cancelada, mas a confirmação local exige XML via GET.
        $this->assertTrue($dup->jaCancelada);
        $this->assertFalse($dup->registrado);
    }

    public function test_http_400_de_evento_le_erro_singular_objeto_sem_descartar_motivo(): void
    {
        $resposta = NfseSefinEventoResposta::interpretar(400, json_encode([
            'tipoAmbiente' => 1,
            'versaoAplicativo' => 'SefinNacional_1.6.0',
            'dataHoraProcessamento' => '2026-11-23T17:00:00-03:00',
            'erro' => [
                'Codigo' => 'E0822',
                'Descricao' => 'Prazo para cancelamento da NFS-e expirado.',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse($resposta->registrado);
        $this->assertFalse($resposta->jaCancelada);
        $this->assertSame(400, $resposta->http);
        $this->assertSame('E0822', $resposta->erros[0]['codigo']);
        $this->assertSame('Prazo para cancelamento da NFS-e expirado.', $resposta->erros[0]['descricao']);
        $this->assertSame('E0822 — Prazo para cancelamento da NFS-e expirado.', $resposta->mensagemErros());
        $this->assertStringNotContainsString('não confirmou o cancelamento', $resposta->mensagemErros());
    }

    public function test_e0840_sozinho_nunca_confirma_cancelamento(): void
    {
        $oficial = 'O Sistema Nacional NFS-e não pode recepcionar o EVENTO DE CANCELAMENTO DE NFS-e pois, o evento de Cancelamento de NFS-e já está vinculado à NFS-e indicada no evento enviado, impedindo sua recepção.';

        $resposta = NfseSefinEventoResposta::interpretar(400, json_encode([
            'tipoAmbiente' => 1,
            'erro' => [[
                'Codigo' => 'E0840',
                'Descricao' => $oficial,
                'Complemento' => null,
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse($resposta->registrado);
        $this->assertFalse($resposta->jaCancelada);
        $this->assertSame('E0840', $resposta->erros[0]['codigo']);
        $this->assertTrue(NfseSefinEventoResposta::exigeConsultaConfirmacao($resposta->erros));
        $this->assertStringContainsString('E0840', $resposta->mensagemErros());
    }

    public function test_get_consulta_le_eventos_arquivo_xml_duplo_base64(): void
    {
        $xml = '<evento versao="1.01"><infEvento Id="EVT48"><nSeqEvento>001</nSeqEvento><e101101><xDesc>Cancelamento de NFS-e</xDesc></e101101></infEvento></evento>';
        $gzipB64 = base64_encode(gzencode($xml));
        $arquivoXml = base64_encode($gzipB64); // formato observado na SEFIN real

        $this->assertStringStartsWith('SDRz', $arquivoXml);

        $resposta = NfseSefinEventoResposta::interpretar(200, json_encode([
            'eventos' => [[
                'chaveAcesso' => '42032042254644503000129000000000004826110170089842',
                'tipoEvento' => '101101',
                'numeroPedidoRegistroEvento' => '001',
                'arquivoXml' => $arquivoXml,
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue($resposta->registrado);
        $this->assertSame($xml, $resposta->xmlEvento);
        $this->assertSame('EVT48', $resposta->protocolo);
        $this->assertSame([], $resposta->erros);
    }

    public function test_get_200_sem_e101101_nao_confirma(): void
    {
        $xml = '<evento><infEvento Id="EVT9"><e202101><xDesc>Confirmacao</xDesc></e202101></infEvento></evento>';

        $resposta = NfseSefinEventoResposta::interpretar(200, json_encode([
            'eventos' => [[
                'tipoEvento' => '202101',
                'arquivoXml' => base64_encode(base64_encode(gzencode($xml))),
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse($resposta->registrado);
        $this->assertStringContainsString('não confirmou o cancelamento', $resposta->mensagemErros());
    }

    public function test_http_400_com_erro_vazio_mantem_mensagem_generica(): void
    {
        $resposta = NfseSefinEventoResposta::interpretar(400, json_encode([
            'tipoAmbiente' => 1,
            'erro' => [],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse($resposta->registrado);
        $this->assertStringContainsString('não confirmou o cancelamento', $resposta->mensagemErros());
    }

    public function test_duplicidade_via_campo_erro_singular_preserva_protecao(): void
    {
        $resposta = NfseSefinEventoResposta::interpretar(400, json_encode([
            'erro' => [
                'Codigo' => 'E1102',
                'Descricao' => 'NFS-e já se encontra cancelada.',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue($resposta->jaCancelada);
        $this->assertFalse($resposta->registrado);
        $this->assertTrue(NfseSefinEventoResposta::exigeConsultaConfirmacao([
            ['codigo' => 'E0840', 'descricao' => 'evento de Confirmacao ja esta vinculado'],
        ]));
    }

    public function test_cancelar_service_consulta_antes_do_post_e_nao_forca_sem_xml(): void
    {
        $src = (string) file_get_contents((new \ReflectionClass(\App\Support\Erp\Nfse\NfseSefinCancelarService::class))->getFileName());

        $this->assertStringContainsString('exigeConsultaConfirmacao', $src);
        $this->assertStringContainsString('consultarEventoCancelamento', $src);
        $this->assertStringContainsString('NfseSefinCancelamentoConsulta::REGISTRADO', $src);
        $this->assertStringContainsString('NfseSefinCancelamentoConsulta::ERRO_COMUNICACAO', $src);
        $this->assertStringContainsString('confirmado_por_consulta_previa', $src);
        $this->assertStringContainsString('TIPO_CANCELAMENTO', $src);
        $this->assertStringContainsString('SEQ_CANCELAMENTO', $src);
        $this->assertStringNotContainsString('registrado: true,', $src);
        // POST só depois da consulta prévia.
        $posConsulta = strpos($src, 'consultarEventoCancelamento');
        $posPost = strpos($src, 'prepararEvento');
        $this->assertNotFalse($posConsulta);
        $this->assertNotFalse($posPost);
        $this->assertLessThan($posPost, $posConsulta);
    }

    public function test_consulta_previa_so_libera_post_quando_evento_ausente(): void
    {
        $xml = '<evento><infEvento Id="EVT1"><e101101><xDesc>Cancelamento de NFS-e</xDesc></e101101></infEvento></evento>';
        $registrado = NfseSefinCancelamentoConsulta::classificar(200, json_encode([
            'eventos' => [[
                'tipoEvento' => '101101',
                'arquivoXml' => base64_encode(base64_encode(gzencode($xml))),
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(NfseSefinCancelamentoConsulta::REGISTRADO, $registrado['status']);
        $this->assertFalse(NfseSefinCancelamentoConsulta::podeEnviarNovoPost($registrado['status']));

        $ausente = NfseSefinCancelamentoConsulta::classificar(404, '{"message":"not found"}');
        $this->assertSame(NfseSefinCancelamentoConsulta::AUSENTE, $ausente['status']);
        $this->assertTrue(NfseSefinCancelamentoConsulta::podeEnviarNovoPost($ausente['status']));

        $this->assertFalse(NfseSefinCancelamentoConsulta::podeEnviarNovoPost(
            NfseSefinCancelamentoConsulta::ERRO_COMUNICACAO
        ));
    }

    public function test_le_nnfse_do_xml_nacional_sem_alterar_ndps(): void
    {
        $xml = '<NFSe><infNFSe><nNFSe>48</nNFSe><nDPS>26</nDPS></infNFSe></NFSe>';

        $this->assertSame('48', NfseSefinXmlLeitor::numeroNfse($xml));
    }
}
