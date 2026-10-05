<?php

namespace App\Support\Erp\Nfse\Ipm;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class NfseIpmResposta
{
    /**
     * Alerta devolvido pela IPM quando o envio foi feito com EnvioTeste=1.
     */
    public const CODIGO_MODO_TESTE = 'L1079';

    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     * @param  list<string>  $alertas
     */
    public function __construct(
        public readonly bool $autorizada,
        public readonly array $erros,
        public readonly array $alertas,
        public readonly ?string $numero,
        public readonly ?string $codigoVerificacao,
        public readonly ?string $protocolo,
        public readonly ?string $dataHora,
        public readonly string $xmlRetorno,
        public readonly bool $modoTeste = false,
        public readonly bool $aceitaEmTeste = false,
    ) {}

    /**
     * Com EnvioTeste=1 (ou alerta L1079 no retorno) o resultado nunca é uma NFS-e autorizada.
     */
    public static function interpretar(string $corpo, bool $envioTeste = false): self
    {
        $texto = self::paraUtf8($corpo);
        $decodificado = str_contains($texto, '&lt;')
            ? html_entity_decode($texto, ENT_QUOTES | ENT_XML1, 'UTF-8')
            : $texto;
        $interno = self::xmlInterno($decodificado);
        $documento = self::carregar($interno ?? $decodificado);

        if ($documento instanceof DOMDocument && $interno === null) {
            $embutido = self::xmlEmbutido($documento);
            $carregado = $embutido !== null ? self::carregar($embutido) : null;

            if ($carregado instanceof DOMDocument) {
                $documento = $carregado;
                $interno = $embutido;
            }
        }

        if (! $documento instanceof DOMDocument) {
            return new self(false, [[
                'codigo' => '',
                'descricao' => 'O WebService IPM não retornou um XML de NFS-e.',
            ]], [], null, null, null, null, $texto, $envioTeste);
        }

        $erros = self::mensagens($documento, false);
        $alertas = self::mensagens($documento, true);
        $numero = self::primeiro($documento, 'Numero', ['InfNfse', 'IdentificacaoNfse']);
        $codigo = self::primeiro($documento, 'CodigoVerificacao');
        $protocolo = self::primeiro($documento, 'Protocolo');
        $data = self::primeiro($documento, 'DataEmissao', ['InfNfse']);
        $aviso = self::primeiro($documento, 'Aviso');
        $falha = self::falhaSoap($documento);

        if ($falha !== null) {
            $erros[] = ['codigo' => '', 'descricao' => $falha];
        }

        if ($aviso !== null) {
            $alertas[] = ['codigo' => '', 'descricao' => $aviso];
        }

        $modoTeste = $envioTeste;

        foreach ($erros as $indice => $erro) {
            if ($erro['codigo'] === self::CODIGO_MODO_TESTE) {
                $modoTeste = true;
                $alertas[] = $erro;
                unset($erros[$indice]);
            }
        }

        $erros = array_values($erros);
        $modoTeste = $modoTeste || in_array(self::CODIGO_MODO_TESTE, array_column($alertas, 'codigo'), true);
        $semErros = $erros === [] && $falha === null;
        $autorizada = ! $modoTeste && $numero !== null && $semErros;
        $aceita = $modoTeste && $semErros && ($numero !== null || in_array(self::CODIGO_MODO_TESTE, array_column($alertas, 'codigo'), true));

        return new self(
            $autorizada,
            $autorizada || $aceita ? [] : ($erros !== [] ? $erros : [[
                'codigo' => '',
                'descricao' => 'O provedor IPM rejeitou a NFS-e.',
            ]]),
            array_values(array_filter(array_map(
                fn (array $alerta): string => trim($alerta['codigo'].' '.$alerta['descricao']),
                $alertas,
            ))),
            $autorizada ? $numero : null,
            $autorizada ? $codigo : null,
            $protocolo,
            $data,
            $interno ?? $decodificado,
            $modoTeste,
            $aceita,
        );
    }

    private static function xmlEmbutido(DOMDocument $documento): ?string
    {
        $xpath = new DOMXPath($documento);
        $nos = $xpath->query('//text()');

        if ($nos === false) {
            return null;
        }

        foreach ($nos as $no) {
            $conteudo = trim($no->textContent);

            if ($conteudo === '' || ! str_contains($conteudo, '<')) {
                continue;
            }

            $extraido = self::xmlInterno($conteudo);

            if ($extraido !== null) {
                return $extraido;
            }
        }

        return null;
    }

    private static function paraUtf8(string $corpo): string
    {
        if (preg_match('/encoding=["\']ISO-8859-1["\']/i', $corpo) === 1) {
            return mb_convert_encoding($corpo, 'UTF-8', 'ISO-8859-1');
        }

        return $corpo;
    }

    private static function xmlInterno(string $corpo): ?string
    {
        $decodificado = str_contains($corpo, '&lt;')
            ? html_entity_decode($corpo, ENT_QUOTES | ENT_XML1, 'UTF-8')
            : $corpo;

        if (preg_match('/<(GerarNfseResposta|CompNfse|ListaMensagemRetorno)\b[^>]*>.*<\/\1>/s', $decodificado, $encontrado) === 1) {
            return $encontrado[0];
        }

        return null;
    }

    private static function carregar(string $xml): ?DOMDocument
    {
        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $ok = $documento->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $ok ? $documento : null;
    }

    /**
     * @return list<array{codigo: string, descricao: string}>
     */
    private static function mensagens(DOMDocument $documento, bool $alertas): array
    {
        $xpath = new DOMXPath($documento);
        $nos = $xpath->query($alertas
            ? '//*[local-name()="ListaMensagemAlertaRetorno"]/*[local-name()="MensagemRetorno"]'
            : '//*[local-name()="MensagemRetorno"][not(parent::*[local-name()="ListaMensagemAlertaRetorno"])]');
        $erros = [];

        if ($nos === false) {
            return [];
        }

        foreach ($nos as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }

            $codigo = self::filho($no, 'Codigo');
            $mensagem = self::filho($no, 'Mensagem');
            $correcao = self::filho($no, 'Correcao');
            $descricao = trim($mensagem.($correcao !== '' ? ' '.$correcao : ''));

            if ($codigo === '' && $descricao === '') {
                continue;
            }

            $erros[] = ['codigo' => $codigo, 'descricao' => $descricao];
        }

        return $erros;
    }

    /**
     * @param  list<string>  $pais
     */
    private static function primeiro(DOMDocument $documento, string $nome, array $pais = []): ?string
    {
        $xpath = new DOMXPath($documento);
        $nos = $xpath->query('//*[local-name()="'.$nome.'"]');

        if ($nos === false) {
            return null;
        }

        foreach ($nos as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }

            if ($pais !== []) {
                $pai = $no->parentNode instanceof DOMElement ? $no->parentNode->localName : '';

                if (! in_array($pai, $pais, true)) {
                    continue;
                }
            }

            $texto = trim($no->textContent);

            if ($texto !== '') {
                return $texto;
            }
        }

        return null;
    }

    private static function filho(DOMElement $pai, string $nome): string
    {
        foreach ($pai->childNodes as $filho) {
            if ($filho instanceof DOMElement && $filho->localName === $nome) {
                return trim($filho->textContent);
            }
        }

        return '';
    }

    private static function falhaSoap(DOMDocument $documento): ?string
    {
        $xpath = new DOMXPath($documento);
        $nos = $xpath->query('//*[local-name()="faultstring"]');

        if ($nos === false || $nos->length === 0) {
            return null;
        }

        $texto = trim($nos->item(0)?->textContent ?? '');

        return $texto !== '' ? $texto : 'O WebService IPM recusou a transmissão.';
    }
}
