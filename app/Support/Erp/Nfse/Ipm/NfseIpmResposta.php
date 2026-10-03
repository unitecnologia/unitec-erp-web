<?php

namespace App\Support\Erp\Nfse\Ipm;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class NfseIpmResposta
{
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
    ) {}

    public static function interpretar(string $corpo): self
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
            ]], [], null, null, null, null, $texto);
        }

        $erros = self::mensagens($documento);
        $numero = self::primeiro($documento, 'Numero', ['InfNfse', 'IdentificacaoNfse']);
        $codigo = self::primeiro($documento, 'CodigoVerificacao');
        $protocolo = self::primeiro($documento, 'Protocolo');
        $data = self::primeiro($documento, 'DataEmissao', ['InfNfse']);
        $falha = self::falhaSoap($documento);

        if ($falha !== null) {
            $erros[] = ['codigo' => '', 'descricao' => $falha];
        }

        $autorizada = $numero !== null && $falha === null;

        return new self(
            $autorizada,
            $autorizada ? [] : ($erros !== [] ? $erros : [[
                'codigo' => '',
                'descricao' => 'O provedor IPM rejeitou a NFS-e.',
            ]]),
            $autorizada ? array_values(array_filter(array_map(
                fn (array $erro): string => trim($erro['codigo'].' '.$erro['descricao']),
                $erros,
            ))) : [],
            $numero,
            $codigo,
            $protocolo,
            $data,
            $interno ?? $decodificado,
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
    private static function mensagens(DOMDocument $documento): array
    {
        $xpath = new DOMXPath($documento);
        $nos = $xpath->query('//*[local-name()="MensagemRetorno"]');
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
