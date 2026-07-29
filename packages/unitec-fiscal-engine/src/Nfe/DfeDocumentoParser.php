<?php

namespace Unitec\FiscalEngine\Nfe;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Dto\DfeResumoNfe;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class DfeDocumentoParser
{
    public function parseFromConsultaXml(string $xml, string $chave): ?DfeResumoNfe
    {
        $chave = preg_replace('/\D/', '', $chave) ?? '';

        if (! $this->isNfeModelo55($chave)) {
            return null;
        }

        if (str_contains($xml, '<nfeProc') || str_contains($xml, '<NFe')) {
            $resumo = $this->parseProcNfe('consulta', 'procNFe', $xml);

            if ($resumo !== null) {
                return $resumo;
            }
        }

        if (str_contains($xml, '<resNFe')) {
            $resumo = $this->parseResNfe('consulta', 'resNFe', $xml);

            if ($resumo !== null) {
                return $resumo;
            }
        }

        if (preg_match('/<procNFe[\s\S]*<\/procNFe>/', $xml, $matches) === 1) {
            $resumo = $this->parseProcNfe('consulta', 'procNFe', $matches[0]);

            if ($resumo !== null) {
                return $resumo;
            }
        }

        $dom = $this->loadXml($xml);
        $chNFe = $this->nodeValue($dom, 'chNFe') ?: $chave;

        if (! $this->isNfeModelo55($chNFe)) {
            return null;
        }

        $dhRecbto = $this->parseDate($this->nodeValue($dom, 'dhRecbto'));

        return new DfeResumoNfe(
            nsu: 'consulta',
            schema: 'consSitNFe',
            chave: preg_replace('/\D/', '', $chNFe) ?? '',
            cnpj: preg_replace('/\D/', '', $this->nodeValue($dom, 'CNPJ') ?: $this->nodeValue($dom, 'CPF')) ?? '',
            nome: $this->nodeValue($dom, 'xNome'),
            numero: $this->numeroFromChave($chNFe),
            dataEmissao: $dhRecbto ?? new \DateTimeImmutable('today'),
            dataRecebimento: $dhRecbto,
            total: (float) str_replace(',', '.', $this->nodeValue($dom, 'vNF') ?: '0'),
            xml: $xml,
        );
    }

    public function parseDocZip(string $nsu, string $schema, string $conteudoBase64): ?DfeResumoNfe
    {
        $binary = base64_decode($conteudoBase64, true);

        if ($binary === false) {
            return null;
        }

        $xml = @gzdecode($binary);

        if ($xml === false) {
            $xml = $binary;
        }

        $xml = trim($xml);

        if ($xml === '') {
            return null;
        }

        return match (true) {
            str_contains($schema, 'resNFe') || str_starts_with($xml, '<resNFe') => $this->parseResNfe($nsu, $schema, $xml),
            str_contains($schema, 'procNFe') || str_contains($xml, '<nfeProc') => $this->parseProcNfe($nsu, $schema, $xml),
            default => null,
        };
    }

    private function parseResNfe(string $nsu, string $schema, string $xml): ?DfeResumoNfe
    {
        $dom = $this->loadXml($xml);
        $root = $dom->documentElement;

        if (! $root instanceof DOMElement) {
            return null;
        }

        $chave = $this->nodeValue($dom, 'chNFe');

        if (! $this->isNfeModelo55($chave)) {
            return null;
        }

        $cnpj = $this->nodeValue($dom, 'CNPJ') ?: $this->nodeValue($dom, 'CPF');
        $nome = $this->nodeValue($dom, 'xNome');
        $total = (float) str_replace(',', '.', $this->nodeValue($dom, 'vNF') ?: '0');
        $dataEmissao = $this->parseDate($this->nodeValue($dom, 'dhEmi')) ?? new \DateTimeImmutable('today');
        $dataRecebimento = $this->parseDate($this->nodeValue($dom, 'dhRecbto'));

        return new DfeResumoNfe(
            nsu: $nsu,
            schema: $schema,
            chave: $chave,
            cnpj: preg_replace('/\D/', '', $cnpj) ?? '',
            nome: $nome,
            numero: $this->numeroFromChave($chave),
            dataEmissao: $dataEmissao,
            dataRecebimento: $dataRecebimento,
            total: $total,
            xml: $xml,
        );
    }

    private function parseProcNfe(string $nsu, string $schema, string $xml): ?DfeResumoNfe
    {
        $dom = $this->loadXml($xml);
        $infNfe = $dom->getElementsByTagName('infNFe')->item(0);

        if (! $infNfe instanceof DOMElement) {
            return null;
        }

        $chave = preg_replace('/\D/', '', (string) $infNfe->getAttribute('Id')) ?? '';
        $chave = str_starts_with($chave, 'NFe') ? substr($chave, 3) : $chave;

        if (! $this->isNfeModelo55($chave)) {
            return null;
        }

        $emit = $infNfe->getElementsByTagName('emit')->item(0);
        $ide = $infNfe->getElementsByTagName('ide')->item(0);
        $totalNode = $infNfe->getElementsByTagName('total')->item(0);

        $cnpj = '';
        $nome = '';

        if ($emit instanceof DOMElement) {
            $cnpj = $this->childValue($emit, 'CNPJ') ?: $this->childValue($emit, 'CPF');
            $nome = $this->childValue($emit, 'xNome');
        }

        $numero = $ide instanceof DOMElement ? $this->childValue($ide, 'nNF') : $this->numeroFromChave($chave);
        $dhEmi = $ide instanceof DOMElement ? $this->childValue($ide, 'dhEmi') : '';
        $vNF = '0';

        if ($totalNode instanceof DOMElement) {
            $icmsTot = $totalNode->getElementsByTagName('ICMSTot')->item(0);

            if ($icmsTot instanceof DOMElement) {
                $vNF = $this->childValue($icmsTot, 'vNF') ?: '0';
            }
        }

        $prot = $dom->getElementsByTagName('infProt')->item(0);
        $dhRecbto = $prot instanceof DOMElement ? $this->childValue($prot, 'dhRecbto') : '';

        return new DfeResumoNfe(
            nsu: $nsu,
            schema: $schema,
            chave: $chave,
            cnpj: preg_replace('/\D/', '', $cnpj) ?? '',
            nome: $nome,
            numero: ltrim($numero, '0') ?: $numero,
            dataEmissao: $this->parseDate($dhEmi) ?? new \DateTimeImmutable('today'),
            dataRecebimento: $this->parseDate($dhRecbto),
            total: (float) str_replace(',', '.', $vNF),
            xml: $xml,
        );
    }

    private function isNfeModelo55(string $chave): bool
    {
        $digits = preg_replace('/\D/', '', $chave) ?? '';

        return strlen($digits) === 44 && substr($digits, 20, 2) === '55';
    }

    private function numeroFromChave(string $chave): string
    {
        $digits = preg_replace('/\D/', '', $chave) ?? '';

        if (strlen($digits) !== 44) {
            return '';
        }

        $numero = ltrim(substr($digits, 25, 9), '0');

        return $numero !== '' ? $numero : '0';
    }

    private function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new FiscalEngineException('Documento DF-e inválido retornado pela SEFAZ.');
        }

        return $dom;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function nodeValue(DOMDocument $dom, string $tag): string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }

    private function childValue(DOMElement $parent, string $tag): string
    {
        $nodes = $parent->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }
}
