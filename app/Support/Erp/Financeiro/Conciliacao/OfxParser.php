<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\ExtratoLancamento;
use InvalidArgumentException;

/**
 * Parser leve OFX (SGML e XML) — sem bibliotecas externas.
 * Consumo controlado: trabalha sobre string já limitada pelo serviço.
 */
final class OfxParser
{
    /**
     * @throws InvalidArgumentException
     */
    public function parse(string $rawBytes, string $fileName = 'arquivo.ofx'): OfxParseResult
    {
        if (trim($rawBytes) === '') {
            throw new InvalidArgumentException('Arquivo OFX vazio.');
        }

        [$text, $encoding] = $this->decodeToUtf8($rawBytes);

        if (! $this->pareceOfx($text)) {
            throw new InvalidArgumentException('Conteúdo não parece um arquivo OFX válido.');
        }

        // Hardening: não processar DOCTYPE/ENTITY (XXE), mesmo sem DOM XML.
        if (preg_match('/<!DOCTYPE/i', $text) || preg_match('/<!ENTITY/i', $text)) {
            throw new InvalidArgumentException('Arquivo OFX rejeitado: declaração DOCTYPE/ENTITY não é permitida.');
        }

        $dialect = str_contains($text, '<?xml') || str_contains(strtolower($text), '<ofx>')
            ? (preg_match('/<\s*OFX\b/i', $text) && preg_match('/<\s*STMTTRN\b/i', $text) && str_contains($text, '</STMTTRN>')
                ? 'xml'
                : 'sgml')
            : 'sgml';

        if ($dialect === 'xml' && ! str_contains($text, '</STMTTRN>') && preg_match('/<\s*STMTTRN\b/i', $text)) {
            $dialect = 'sgml';
        }

        $bank = $this->extractAccount($text);
        $period = $this->extractPeriod($text);
        $blocks = $this->extractStmtTrnBlocks($text);

        if ($blocks === []) {
            throw new InvalidArgumentException('OFX sem lançamentos (STMTTRN) reconhecíveis.');
        }

        $transactions = [];
        $warnings = [];
        $seq = 0;

        foreach ($blocks as $block) {
            $seq++;
            try {
                $tx = $this->parseStmtTrn($block, $seq);
                $transactions[] = $tx;
            } catch (InvalidArgumentException $e) {
                $warnings[] = 'Linha '.$seq.': '.$e->getMessage();
            }
        }

        if ($transactions === []) {
            throw new InvalidArgumentException('Nenhum lançamento OFX válido encontrado.');
        }

        return new OfxParseResult(
            encoding: $encoding,
            dialect: $dialect,
            bankId: $bank['bank_id'],
            branchId: $bank['branch_id'],
            accountId: $bank['account_id'],
            periodoInicio: $period['start'],
            periodoFim: $period['end'],
            transactions: $transactions,
            warnings: $warnings,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeToUtf8(string $raw): array
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return [substr($raw, 3), 'UTF-8'];
        }

        $header = substr($raw, 0, 2048);
        $charset = null;
        if (preg_match('/CHARSET\s*:\s*([A-Za-z0-9\-]+)/i', $header, $m)) {
            $charset = strtoupper(trim($m[1]));
        } elseif (preg_match('/encoding\s*=\s*["\']?([A-Za-z0-9\-]+)/i', $header, $m)) {
            $charset = strtoupper(trim($m[1]));
        }

        $map = [
            '1252' => 'Windows-1252',
            'CP1252' => 'Windows-1252',
            'WINDOWS-1252' => 'Windows-1252',
            'ISO-8859-1' => 'ISO-8859-1',
            'LATIN1' => 'ISO-8859-1',
            'UTF-8' => 'UTF-8',
            'UTF8' => 'UTF-8',
            'NONE' => 'Windows-1252',
            'USASCII' => 'ASCII',
            'ASCII' => 'ASCII',
        ];

        $from = $map[$charset ?? ''] ?? null;

        if ($from === null) {
            if (mb_check_encoding($raw, 'UTF-8')) {
                return [$raw, 'UTF-8'];
            }
            $from = 'Windows-1252';
        }

        if ($from === 'UTF-8') {
            return [$raw, 'UTF-8'];
        }

        $converted = @iconv($from, 'UTF-8//IGNORE', $raw);
        if ($converted === false || $converted === '') {
            $converted = mb_convert_encoding($raw, 'UTF-8', $from);
        }

        return [(string) $converted, $from];
    }

    private function pareceOfx(string $text): bool
    {
        $probe = strtoupper(substr($text, 0, 4000));

        return str_contains($probe, 'OFX')
            || str_contains($probe, 'OFXHEADER')
            || str_contains($probe, '<STMTTRN')
            || str_contains($probe, 'BANKMSGSRSV1')
            || str_contains($probe, 'CREDITCARDMSGSRSV1');
    }

    /**
     * @return array{bank_id: ?string, branch_id: ?string, account_id: ?string}
     */
    private function extractAccount(string $text): array
    {
        $bankId = $this->tagValue($text, 'BANKID');
        $branchId = $this->tagValue($text, 'BRANCHID');
        $accountId = $this->tagValue($text, 'ACCTID');

        return [
            'bank_id' => $this->nullIfEmpty($bankId),
            'branch_id' => $this->nullIfEmpty($branchId),
            'account_id' => $this->nullIfEmpty($accountId),
        ];
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    private function extractPeriod(string $text): array
    {
        $start = $this->parseOfxDate($this->tagValue($text, 'DTSTART'));
        $end = $this->parseOfxDate($this->tagValue($text, 'DTEND'));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return list<string>
     */
    private function extractStmtTrnBlocks(string $text): array
    {
        $blocks = [];

        if (preg_match_all('/<\s*STMTTRN\b[^>]*>(.*?)<\s*\/\s*STMTTRN\s*>/is', $text, $m)) {
            foreach ($m[1] as $inner) {
                $blocks[] = (string) $inner;
            }

            return $blocks;
        }

        // SGML sem fechamento explícito: corta até o próximo STMTTRN ou fim de lista.
        if (preg_match_all('/<\s*STMTTRN\b[^>]*>(.*?)(?=<\s*STMTTRN\b|<\s*\/\s*BANKTRANLIST\b|<\s*\/\s*CCSTMTTRNRS\b|$)/is', $text, $m2)) {
            foreach ($m2[1] as $inner) {
                $chunk = trim((string) $inner);
                if ($chunk !== '') {
                    $blocks[] = $chunk;
                }
            }
        }

        return $blocks;
    }

    private function parseStmtTrn(string $block, int $seq): OfxParsedTransaction
    {
        $trntype = strtoupper(trim((string) $this->tagValue($block, 'TRNTYPE')));
        $dtPosted = $this->parseOfxDateTime($this->tagValue($block, 'DTPOSTED'));
        $dtUser = $this->parseOfxDateTime($this->tagValue($block, 'DTUSER'));
        $amountRaw = trim((string) $this->tagValue($block, 'TRNAMT'));
        $fitid = $this->nullIfEmpty($this->tagValue($block, 'FITID'));
        $name = $this->nullIfEmpty($this->tagValue($block, 'NAME'));
        $memo = $this->nullIfEmpty($this->tagValue($block, 'MEMO'));
        $check = $this->nullIfEmpty($this->tagValue($block, 'CHECKNUM'));
        $ref = $this->nullIfEmpty($this->tagValue($block, 'REFNUM'))
            ?? $this->nullIfEmpty($this->tagValue($block, 'SRVRTID'));

        $dataMovimento = $dtUser ?? $dtPosted;
        if ($dataMovimento === null) {
            throw new InvalidArgumentException('Data do movimento ausente.');
        }

        if ($amountRaw === '' || ! is_numeric(str_replace(',', '.', $amountRaw))) {
            throw new InvalidArgumentException('Valor TRNAMT inválido.');
        }

        $amount = (float) str_replace(',', '.', $amountRaw);
        $abs = abs($amount);
        if ($abs < 0.00001) {
            throw new InvalidArgumentException('Valor zerado ignorado.');
        }

        $tipo = $this->resolveTipo($trntype, $amount);
        $valor = number_format($abs, 2, '.', '');
        $historico = $name ?? $memo;
        if ($historico !== null) {
            $historico = mb_substr($historico, 0, 500, 'UTF-8');
        }
        if ($memo !== null) {
            $memo = mb_substr($memo, 0, 500, 'UTF-8');
        }

        $ambiguo = ($fitid === null || $fitid === '')
            && ($historico === null || trim($historico) === '')
            && ($memo === null || trim($memo) === '')
            && ($ref === null || trim($ref) === '');

        return new OfxParsedTransaction(
            dataMovimento: $dataMovimento,
            dataPosted: $dtPosted,
            tipo: $tipo,
            valor: $valor,
            historico: $historico,
            memo: $memo,
            payee: $name,
            checkNum: $check,
            fitid: $fitid !== null ? mb_substr($fitid, 0, 120, 'UTF-8') : null,
            refNum: $ref !== null ? mb_substr($ref, 0, 80, 'UTF-8') : null,
            sequenciaArquivo: $seq,
            ambiguo: $ambiguo,
        );
    }

    private function resolveTipo(string $trntype, float $amount): string
    {
        $creditTypes = ['CREDIT', 'DEP', 'DIRECTDEP', 'DIV', 'INT'];
        $debitTypes = ['DEBIT', 'ATM', 'CASH', 'CHECK', 'FEE', 'PAYMENT', 'POS', 'REPEATPMT', 'SRVCHG'];

        if (in_array($trntype, $creditTypes, true)) {
            return ExtratoLancamento::TIPO_CREDITO;
        }
        if (in_array($trntype, $debitTypes, true)) {
            return ExtratoLancamento::TIPO_DEBITO;
        }

        if ($amount > 0) {
            return ExtratoLancamento::TIPO_CREDITO;
        }
        if ($amount < 0) {
            return ExtratoLancamento::TIPO_DEBITO;
        }

        return ExtratoLancamento::TIPO_OUTRO;
    }

    private function tagValue(string $text, string $tag): ?string
    {
        // XML fechado
        if (preg_match('/<\s*'.preg_quote($tag, '/').'\b[^>]*>(.*?)<\s*\/\s*'.preg_quote($tag, '/').'\s*>/is', $text, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // SGML: <TAG>value até quebra/próxima tag
        if (preg_match('/<\s*'.preg_quote($tag, '/').'\b[^>]*>([^<\r\n]*)/i', $text, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function parseOfxDate(?string $raw): ?string
    {
        $parsed = $this->parseOfxDateTime($raw);
        if ($parsed === null) {
            return null;
        }

        return substr($parsed, 0, 10);
    }

    /**
     * DTPOSTED/DTUSER: YYYYMMDD[HHMMSS[.XXX]][[gmt offset:TZ]].
     * Ailos costuma mandar YYYYMMDDHHMMSS[-3:BRT]. Sem conversão de fuso
     * (não desloca o dia). Relógio 00:00:00 ou só data → data civil.
     */
    private function parseOfxDateTime(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?/', $raw, $m)) {
            return null;
        }

        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if (! checkdate($mo, $d, $y)) {
            return null;
        }

        $hasHour = isset($m[4]) && $m[4] !== '';
        $hasMinute = isset($m[5]) && $m[5] !== '';
        if (! $hasHour || ! $hasMinute) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        $h = (int) $m[4];
        $i = (int) $m[5];
        $s = (isset($m[6]) && $m[6] !== '') ? (int) $m[6] : 0;
        if ($h > 23 || $i > 59 || $s > 59) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if ($h === 0 && $i === 0 && $s === 0) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $i, $s);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
