<?php

namespace App\Support\Erp\Nfse\Ipm;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Retorno do CancelarNfseEnvio IPM: RetCancelamento (confirmado) ou ListaMensagemRetorno (recusado).
 */
final class NfseIpmCancelamentoResposta
{
    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     */
    public function __construct(
        public readonly bool $cancelada,
        public readonly array $erros,
        public readonly ?string $dataHora,
        public readonly string $xmlRetorno,
        public readonly bool $jaCancelada = false,
    ) {}

    public static function interpretar(string $corpo): self
    {
        $texto = preg_match('/encoding=["\']ISO-8859-1["\']/i', $corpo) === 1
            ? mb_convert_encoding($corpo, 'UTF-8', 'ISO-8859-1')
            : $corpo;
        $decodificado = str_contains($texto, '&lt;')
            ? html_entity_decode($texto, ENT_QUOTES | ENT_XML1, 'UTF-8')
            : $texto;
        $interno = preg_match('/<(CancelarNfseResposta|RetCancelamento|ListaMensagemRetorno)\b[^>]*>.*<\/\1>/s', $decodificado, $encontrado) === 1
            ? $encontrado[0]
            : null;
        $documento = self::carregar($interno ?? $decodificado);

        if (! $documento instanceof DOMDocument) {
            return new self(false, [[
                'codigo' => '',
                'descricao' => 'O WebService IPM não retornou um XML de cancelamento.',
            ]], null, $texto);
        }

        $xpath = new DOMXPath($documento);
        $erros = [];

        foreach ($xpath->query('//*[local-name()="MensagemRetorno"]') ?: [] as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }

            $codigo = self::filho($no, 'Codigo');
            $descricao = trim(self::filho($no, 'Mensagem').' '.self::filho($no, 'Correcao'));

            if ($codigo !== '' || $descricao !== '') {
                $erros[] = ['codigo' => $codigo, 'descricao' => $descricao];
            }
        }

        $falha = trim($xpath->query('//*[local-name()="faultstring"]')?->item(0)?->textContent ?? '');

        if ($falha !== '') {
            $erros[] = ['codigo' => '', 'descricao' => $falha];
        }

        $confirmacao = $xpath->query('//*[local-name()="NfseCancelamento"]//*[local-name()="Confirmacao"]')?->item(0);
        $dataHora = $confirmacao instanceof DOMElement ? (self::filho($confirmacao, 'DataHora') ?: null) : null;
        $jaCancelada = false;

        foreach ($erros as $erro) {
            if (preg_match('/j[aá]\s+(se\s+encontra\s+|est[aá]\s+)?cancelad/iu', $erro['descricao']) === 1) {
                $jaCancelada = true;
            }
        }

        $cancelada = $confirmacao instanceof DOMElement && $erros === [];

        if (! $cancelada && ! $jaCancelada && $erros === []) {
            $erros[] = ['codigo' => '', 'descricao' => 'O provedor IPM não confirmou o cancelamento.'];
        }

        return new self($cancelada || $jaCancelada, $cancelada ? [] : $erros, $dataHora, $interno ?? $decodificado, $jaCancelada);
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

    private static function filho(DOMElement $pai, string $nome): string
    {
        foreach ($pai->childNodes as $filho) {
            if ($filho instanceof DOMElement && $filho->localName === $nome) {
                return trim($filho->textContent);
            }
        }

        return '';
    }
}
