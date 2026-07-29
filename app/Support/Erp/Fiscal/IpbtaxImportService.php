<?php

namespace App\Support\Erp\Fiscal;

use App\Models\FiscalIbptItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IpbtaxImportService
{
    /**
     * Rejeita página HTML, Excel ou conteúdo que não seja TabelaIBPTax.
     */
    public static function assertContentLooksLikeIbptCsv(string $content): void
    {
        $trimmed = ltrim($content);

        if ($trimmed === '') {
            throw new \RuntimeException('Arquivo vazio.');
        }

        if (
            str_starts_with($trimmed, '<!')
            || str_starts_with(strtolower($trimmed), '<html')
            || stripos($trimmed, '<!doctype') === 0
        ) {
            throw new \RuntimeException(
                'O endereço retornou uma página HTML, não o CSV. Baixe a TabelaIBPTax em deolhonoimposto.ibpt.org.br e selecione o arquivo com o botão … ou cole a URL direta do .csv.'
            );
        }

        if (str_starts_with($trimmed, 'PK') || str_starts_with($trimmed, "\xD0\xCF\x11\xE0")) {
            throw new \RuntimeException(
                'Arquivo Excel (.xlsx/.xls) não é suportado. Abra no Excel e salve como CSV (TabelaIBPTax).'
            );
        }

        $firstLine = strtok($trimmed, "\r\n");

        if ($firstLine === false || trim($firstLine) === '') {
            throw new \RuntimeException('Arquivo sem conteúdo legível.');
        }

        $lower = strtolower($firstLine);

        if (
            ! str_contains($lower, 'ncm')
            && ! str_contains($lower, 'codigo')
            && ! preg_match('/^\d{4,}/', $firstLine)
        ) {
            throw new \RuntimeException(
                'Conteúdo não parece ser TabelaIBPTax. Use o CSV oficial do portal IBPT (arquivo TabelaIBPTax…).'
            );
        }
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     preview: list<array<string, mixed>>,
     *     errors: list<array{linha: int, mensagem: string}>,
     *     meta: array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string},
     *     skipped: int
     * }
     */
    public function parseFromPath(string $absolutePath, int $previewLimit = 300, int $errorLimit = 100): array
    {
        $parsed = $this->readRows($absolutePath, $errorLimit);

        $preview = [];
        foreach (array_values($parsed['rows']) as $index => $row) {
            if ($index >= $previewLimit) {
                break;
            }

            $preview[] = $this->toPreviewRow($row);
        }

        return [
            'rows' => array_values($parsed['rows']),
            'preview' => $preview,
            'errors' => $parsed['errors'],
            'meta' => $parsed['meta'],
            'skipped' => $parsed['skipped'],
        ];
    }

    /**
     * @return array{imported: int, skipped: int, meta: array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string}}
     */
    public function importFromPath(string $absolutePath): array
    {
        $parsed = $this->readRows($absolutePath, 200);
        $imported = $this->persistRows($parsed['rows']);

        return [
            'imported' => $imported,
            'skipped' => $parsed['skipped'],
            'meta' => $parsed['meta'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function persistRows(array $rows): int
    {
        if ($rows === []) {
            throw new \RuntimeException('Nenhum registro IBPT válido para gravar. A tabela padrão do sistema não foi apagada.');
        }

        // Tabela completa (~15–20k linhas) + sync do catálogo NCM: livewire/update padrão de 30s estoura.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        DB::transaction(function () use ($rows): void {
            FiscalIbptItem::query()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                $now = now();
                $payload = array_map(static function (array $row) use ($now): array {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;

                    return $row;
                }, $chunk);

                FiscalIbptItem::query()->insert($payload);
            }
        });

        // Catálogo único de NCM (lupa do produto) alimentado pela IPBTAX.
        try {
            (new NcmCatalogService)->syncFromIbpt();
        } catch (\Throwable $e) {
            report($e);
        }

        return count($rows);
    }

    /**
     * @return array{
     *     rows: array<string, array<string, mixed>>,
     *     errors: list<array{linha: int, mensagem: string}>,
     *     meta: array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string},
     *     skipped: int
     * }
     */
    private function readRows(string $absolutePath, int $errorLimit): array
    {
        $absolutePath = $this->normalizeEncodingToTempFile($absolutePath);
        $this->assertFileLooksLikeIbptCsv($absolutePath);

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo IPBTAX.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new \RuntimeException('Arquivo IPBTAX vazio.');
            }

            $firstLine = $this->stripBom($firstLine);
            $delimiter = $this->detectDelimiter($firstLine);
            $header = $this->parseCsvLine($firstLine, $delimiter);
            $map = $this->mapHeader($header);
            $lineNumber = 1;

            if ($map === null) {
                rewind($handle);
                $lineNumber = 0;
                $map = [
                    'ncm' => 0,
                    'ex' => 1,
                    'tipo' => 2,
                    'descricao' => 3,
                    'nacional' => 4,
                    'importado' => 5,
                    'estadual' => 6,
                    'municipal' => 7,
                    'vigencia_inicio' => 8,
                    'vigencia_fim' => 9,
                    'chave' => 10,
                    'versao' => 11,
                    'fonte' => 12,
                ];
            }

            $rows = [];
            $errors = [];
            $skipped = 0;
            $meta = [
                'versao' => '',
                'quantidade' => 0,
                'vigencia' => '',
                'chave' => '',
                'fonte' => '',
            ];

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $cols = $this->parseCsvLine($line, $delimiter);
                $ncmRaw = $this->col($cols, $map['ncm'] ?? null);
                $ncm = preg_replace('/\D/', '', $ncmRaw) ?? '';

                if ($ncm === '' || strlen($ncm) < 4) {
                    $skipped++;

                    if (count($errors) < $errorLimit) {
                        $errors[] = [
                            'linha' => $lineNumber,
                            'mensagem' => 'NCM inválido: '.($ncmRaw !== '' ? $ncmRaw : '(vazio)'),
                        ];
                    }

                    continue;
                }

                $ex = $this->col($cols, $map['ex'] ?? null);
                $tipo = $this->col($cols, $map['tipo'] ?? null);
                $versao = $this->col($cols, $map['versao'] ?? null);
                $chave = $this->col($cols, $map['chave'] ?? null);
                $fonte = $this->col($cols, $map['fonte'] ?? null);
                $vigenciaInicio = $this->parseDate($this->col($cols, $map['vigencia_inicio'] ?? null));
                $vigenciaFim = $this->parseDate($this->col($cols, $map['vigencia_fim'] ?? null));
                $key = implode('|', [$ncm, $ex, $tipo, $versao]);

                $rows[$key] = [
                    'ncm' => Str::limit($ncm, 10, ''),
                    'ex_tipi' => $ex === '' ? null : Str::limit($ex, 4, ''),
                    'tipo' => $tipo === '' ? null : Str::limit($tipo, 1, ''),
                    'descricao' => $this->nullableCol($cols, $map['descricao'] ?? null, 500),
                    'aliq_nacional' => $this->parseDecimal($this->col($cols, $map['nacional'] ?? null)),
                    'aliq_importado' => $this->parseDecimal($this->col($cols, $map['importado'] ?? null)),
                    'aliq_estadual' => $this->parseDecimal($this->col($cols, $map['estadual'] ?? null)),
                    'aliq_municipal' => $this->parseDecimal($this->col($cols, $map['municipal'] ?? null)),
                    'vigencia_inicio' => $vigenciaInicio,
                    'vigencia_fim' => $vigenciaFim,
                    'chave' => $chave === '' ? null : Str::limit($chave, 80, ''),
                    'versao' => $versao === '' ? null : Str::limit($versao, 40, ''),
                    'fonte' => $fonte === '' ? null : Str::limit($fonte, 80, ''),
                ];

                if ($meta['versao'] === '' && $versao !== '') {
                    $meta['versao'] = $versao;
                }
                if ($meta['chave'] === '' && $chave !== '') {
                    $meta['chave'] = $chave;
                }
                if ($meta['fonte'] === '' && $fonte !== '') {
                    $meta['fonte'] = $fonte;
                }
                if ($meta['vigencia'] === '' && ($vigenciaInicio || $vigenciaFim)) {
                    $meta['vigencia'] = trim(
                        ($vigenciaInicio ? $this->formatBrDate($vigenciaInicio) : '').
                        ($vigenciaInicio && $vigenciaFim ? ' a ' : '').
                        ($vigenciaFim ? $this->formatBrDate($vigenciaFim) : '')
                    );
                }
            }

            $meta['quantidade'] = count($rows);

            return [
                'rows' => $rows,
                'errors' => $errors,
                'meta' => $meta,
                'skipped' => $skipped,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toPreviewRow(array $row): array
    {
        return [
            'ncm' => (string) ($row['ncm'] ?? ''),
            'ex' => (string) ($row['ex_tipi'] ?? ''),
            'tipo' => (string) ($row['tipo'] ?? ''),
            'descricao' => (string) ($row['descricao'] ?? ''),
            'nacional' => number_format((float) ($row['aliq_nacional'] ?? 0), 2, ',', '.'),
            'importado' => number_format((float) ($row['aliq_importado'] ?? 0), 2, ',', '.'),
            'estadual' => number_format((float) ($row['aliq_estadual'] ?? 0), 2, ',', '.'),
            'municipal' => number_format((float) ($row['aliq_municipal'] ?? 0), 2, ',', '.'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     preview: list<array<string, mixed>>,
     *     meta: array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string}
     * }
     */
    public function previewFromRows(array $rows, int $previewLimit = 300): array
    {
        $meta = [
            'versao' => '',
            'quantidade' => count($rows),
            'vigencia' => '',
            'chave' => '',
            'fonte' => '',
        ];

        $preview = [];

        foreach ($rows as $index => $row) {
            if ($meta['versao'] === '' && filled($row['versao'] ?? null)) {
                $meta['versao'] = (string) $row['versao'];
            }
            if ($meta['chave'] === '' && filled($row['chave'] ?? null)) {
                $meta['chave'] = (string) $row['chave'];
            }
            if ($meta['fonte'] === '' && filled($row['fonte'] ?? null)) {
                $meta['fonte'] = (string) $row['fonte'];
            }
            if ($meta['vigencia'] === '') {
                $inicio = (string) ($row['vigencia_inicio'] ?? '');
                $fim = (string) ($row['vigencia_fim'] ?? '');
                if ($inicio !== '' || $fim !== '') {
                    $meta['vigencia'] = trim(
                        ($inicio !== '' ? $this->formatBrDate($inicio) : '').
                        ($inicio !== '' && $fim !== '' ? ' a ' : '').
                        ($fim !== '' ? $this->formatBrDate($fim) : '')
                    );
                }
            }

            if ($index < $previewLimit) {
                $preview[] = $this->toPreviewRow($row);
            }
        }

        return [
            'preview' => $preview,
            'meta' => $meta,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, meta: array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string}}
     */
    public function importFromRows(array $rows): array
    {
        $imported = $this->persistRows($rows);
        $preview = $this->previewFromRows($rows);

        return [
            'imported' => $imported,
            'meta' => $preview['meta'],
        ];
    }

    private function formatBrDate(string $isoDate): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return $isoDate;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>|null
     */
    private function mapHeader(array $header): ?array
    {
        $normalized = [];

        foreach ($header as $index => $name) {
            $key = Str::of($name)
                ->lower()
                ->ascii()
                ->replaceMatches('/[^a-z0-9]+/', '')
                ->toString();

            $normalized[$key] = $index;
        }

        $ncm = $normalized['codigo']
            ?? $normalized['ncm']
            ?? $normalized['codigoncm']
            ?? null;

        if ($ncm === null) {
            return null;
        }

        return [
            'ncm' => $ncm,
            'ex' => $normalized['ex'] ?? $normalized['extipi'] ?? null,
            'tipo' => $normalized['tipo'] ?? null,
            'descricao' => $normalized['descricao'] ?? null,
            'nacional' => $normalized['nacionalfederal'] ?? $normalized['aliqnacional'] ?? $normalized['nacional'] ?? null,
            'importado' => $normalized['importadosfederal'] ?? $normalized['aliqimportado'] ?? $normalized['importado'] ?? null,
            'estadual' => $normalized['estadual'] ?? $normalized['aliqestadual'] ?? null,
            'municipal' => $normalized['municipal'] ?? $normalized['aliqmunicipal'] ?? null,
            'vigencia_inicio' => $normalized['vigenciainicio'] ?? $normalized['datainicio'] ?? null,
            'vigencia_fim' => $normalized['vigenciafim'] ?? $normalized['datafim'] ?? null,
            'chave' => $normalized['chave'] ?? $normalized['chaveibpt'] ?? null,
            'versao' => $normalized['versao'] ?? null,
            'fonte' => $normalized['fonte'] ?? null,
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            '|' => substr_count($line, '|'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);
        $delimiter = array_key_first($counts);

        return ($counts[$delimiter] ?? 0) > 0 ? (string) $delimiter : ';';
    }

    /**
     * @return list<string>
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        $parsed = str_getcsv($line, $delimiter);

        return array_map(static fn ($value): string => trim((string) $value), $parsed);
    }

    /**
     * @param  list<string>  $cols
     */
    private function col(array $cols, ?int $index): string
    {
        if ($index === null || ! array_key_exists($index, $cols)) {
            return '';
        }

        return trim((string) $cols[$index]);
    }

    /**
     * @param  list<string>  $cols
     */
    private function nullableCol(array $cols, ?int $index, int $maxLen): ?string
    {
        $value = $this->col($cols, $index);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $maxLen, '');
    }

    private function parseDecimal(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        return null;
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function assertFileLooksLikeIbptCsv(string $absolutePath): void
    {
        $sample = file_get_contents($absolutePath, false, null, 0, 8192);

        if ($sample === false || $sample === '') {
            throw new \RuntimeException('Não foi possível ler o arquivo IPBTAX.');
        }

        self::assertContentLooksLikeIbptCsv($sample);
    }

    /**
     * Normaliza UTF-16 / Windows-1252 para UTF-8.
     */
    private function normalizeEncodingToTempFile(string $absolutePath): string
    {
        $raw = file_get_contents($absolutePath);

        if ($raw === false || $raw === '') {
            return $absolutePath;
        }

        $bom2 = substr($raw, 0, 2);
        $from = null;

        if ($bom2 === "\xFF\xFE") {
            $from = 'UTF-16LE';
        } elseif ($bom2 === "\xFE\xFF") {
            $from = 'UTF-16BE';
        } elseif (! mb_check_encoding($raw, 'UTF-8')) {
            $from = 'Windows-1252';
        }

        if ($from === null) {
            return $absolutePath;
        }

        $utf8 = mb_convert_encoding($raw, 'UTF-8', $from);
        $temp = tempnam(sys_get_temp_dir(), 'ipbtax_utf8_');

        if ($temp === false) {
            throw new \RuntimeException('Não foi possível preparar o arquivo para importação.');
        }

        file_put_contents($temp, $utf8);

        return $temp;
    }
}
