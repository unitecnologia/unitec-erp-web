<?php

namespace App\Support\Erp\Fiscal;

use App\Models\FiscalClassificacaoTributaria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClassificacaoTributariaIvaImportService
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function importFromPath(string $absolutePath): array
    {
        $absolutePath = $this->normalizeEncodingToTempFile($absolutePath);

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo de Classificação Tributária IVA.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new \RuntimeException('Arquivo de Classificação Tributária IVA vazio.');
            }

            $firstLine = $this->stripBom($firstLine);
            $delimiter = $this->detectDelimiter($firstLine);
            $header = $this->parseCsvLine($firstLine, $delimiter);
            $map = $this->mapHeader($header);
            $hasHeader = $map !== null;

            if (! $hasHeader) {
                rewind($handle);
                // Layout oficial: CST | Situação do CST | Código - Descrição
                // Layout cClassTrib.csv: CST | Descrição CST | cClassTrib | Nome cClassTrib
                if (count($header) >= 4 && preg_match('/^\d{3}$/', trim((string) ($header[0] ?? ''))) !== 1) {
                    // Sem cabeçalho reconhecido, mas com 4+ colunas: assume layout oficial SEFAZ
                }

                $map = [
                    'cst' => 0,
                    'cst_descricao' => 1,
                    'codigo' => 2,
                    'descricao' => 3,
                    'codigo_descricao' => null,
                ];

                // Se a 3ª coluna parece "000001 - texto", usa combinado
                $probe = $this->parseCsvLine($firstLine, $delimiter);
                if (isset($probe[2]) && preg_match('/^\d{4,10}\s*[-–:]/', $probe[2]) === 1) {
                    $map = [
                        'cst' => 0,
                        'cst_descricao' => 1,
                        'codigo_descricao' => 2,
                    ];
                }
            }

            $rows = [];
            $skipped = 0;

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $cols = $this->parseCsvLine($line, $delimiter);
                $parsed = $this->resolveCodigoDescricao($cols, $map);

                if ($parsed === null) {
                    $skipped++;

                    continue;
                }

                $cst = $this->nullableCol($cols, $map['cst'] ?? null, 3);
                if ($cst !== null) {
                    $cst = preg_replace('/\D/', '', $cst) ?? '';
                    $cst = $cst === '' ? null : Str::limit($cst, 3, '');
                }

                $cstDescricao = $this->nullableCol($cols, $map['cst_descricao'] ?? null, 120);
                $descricao = $parsed['descricao'];
                $nome = $this->nullableCol($cols, $map['nome'] ?? null, 255);

                if (($descricao === null || $descricao === '') && $nome !== null) {
                    $descricao = $nome;
                }

                $rows[$parsed['codigo']] = [
                    'codigo' => $parsed['codigo'],
                    'cst_ibs_cbs' => $cst,
                    'cst_descricao' => $cstDescricao,
                    'descricao' => $descricao,
                    'nome_reduzido' => $nome,
                    'ind_nfe' => $this->parseBool($this->col($cols, $map['ind_nfe'] ?? null)),
                    'ind_nfce' => $this->parseBool($this->col($cols, $map['ind_nfce'] ?? null)),
                    'ind_nfse' => $this->parseBool($this->col($cols, $map['ind_nfse'] ?? null)),
                    'ind_cte' => $this->parseBool($this->col($cols, $map['ind_cte'] ?? null)),
                    'vigencia_inicio' => $this->parseDate($this->col($cols, $map['vigencia_inicio'] ?? null)),
                    'vigencia_fim' => $this->parseDate($this->col($cols, $map['vigencia_fim'] ?? null)),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            if ($rows === []) {
                throw new \RuntimeException(
                    'Nenhuma linha válida encontrada. Esperado CSV: CST;Descrição CST;cClassTrib;Nome cClassTrib'
                );
            }

            DB::transaction(function () use ($rows): void {
                FiscalClassificacaoTributaria::query()->delete();

                foreach (array_chunk(array_values($rows), 500) as $chunk) {
                    FiscalClassificacaoTributaria::query()->insert($chunk);
                }
            });

            return [
                'imported' => count($rows),
                'skipped' => $skipped,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int|null>|null
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

            if ($key === '') {
                continue;
            }

            $normalized[$key] = $index;
        }

        $codigo = $normalized['cclasstrib']
            ?? $normalized['codigocclasstrib']
            ?? $normalized['codigoclassificacao']
            ?? $normalized['classificacaotributaria']
            ?? null;

        // Evita mapear "codigo" genérico se for ambíguo; só usa se não houver cClassTrib
        if ($codigo === null && isset($normalized['codigo'])) {
            $codigo = $normalized['codigo'];
        }

        $codigoDescricao = $normalized['codigodescricao']
            ?? $normalized['cclasstribdescricao']
            ?? $normalized['item']
            ?? null;

        $descricao = $normalized['nomecclasstrib']
            ?? $normalized['descricaocclasstrib']
            ?? $normalized['nome']
            ?? $normalized['descricao']
            ?? null;

        // "Descrição CST-IBS/CBS" → descricaocstibscbs (não confundir com descrição do item)
        $cstDescricao = $normalized['descricaocstibscbs']
            ?? $normalized['descricaocst']
            ?? $normalized['cstdescricao']
            ?? $normalized['situacao']
            ?? $normalized['situacaocst']
            ?? $normalized['grupo']
            ?? null;

        // Se "descricao" apontou para a coluna do CST, prioriza nome do cClassTrib
        if ($descricao !== null && $cstDescricao !== null && $descricao === $cstDescricao) {
            $descricao = $normalized['nomecclasstrib'] ?? $normalized['nome'] ?? null;
        }

        // Header oficial do arquivo cClassTrib 2026: CST;Descrição CST;cClassTrib;Nome
        if ($codigo === null && $codigoDescricao === null) {
            if (
                isset($normalized['cstibscbs'])
                && (isset($normalized['nomecclasstrib']) || isset($normalized['descricaocstibscbs']))
            ) {
                return [
                    'codigo' => 2,
                    'descricao' => 3,
                    'cst' => 0,
                    'cst_descricao' => 1,
                    'codigo_descricao' => null,
                    'nome' => 3,
                ];
            }

            return null;
        }

        return [
            'codigo' => $codigo,
            'codigo_descricao' => $codigoDescricao,
            'cst' => $normalized['cstibscbs'] ?? $normalized['cst'] ?? null,
            'cst_descricao' => $cstDescricao,
            'descricao' => $descricao,
            'nome' => $normalized['nomecclasstrib'] ?? $normalized['nomereduzido'] ?? $normalized['nome'] ?? null,
            'ind_nfe' => $normalized['indnfe'] ?? $normalized['nfe'] ?? null,
            'ind_nfce' => $normalized['indnfce'] ?? $normalized['nfce'] ?? null,
            'ind_nfse' => $normalized['indnfse'] ?? $normalized['nfse'] ?? null,
            'ind_cte' => $normalized['indcte'] ?? $normalized['cte'] ?? null,
            'vigencia_inicio' => $normalized['datainicio'] ?? $normalized['vigenciainicio'] ?? null,
            'vigencia_fim' => $normalized['datafim'] ?? $normalized['vigenciafim'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int|null>  $map
     * @return array{codigo: string, descricao: ?string}|null
     */
    private function resolveCodigoDescricao(array $cols, array $map): ?array
    {
        $codigo = $this->col($cols, $map['codigo'] ?? null);
        $descricao = $this->nullableCol($cols, $map['descricao'] ?? null, 500);
        $combinado = $this->col($cols, $map['codigo_descricao'] ?? null);

        foreach ([$combinado, $codigo] as $candidato) {
            if ($candidato !== '' && preg_match('/^(\d{4,10})\s*[-–:]\s*(.+)$/u', $candidato, $m)) {
                $codigo = $m[1];
                $descricao = Str::limit(trim($m[2]), 500, '');
                break;
            }
        }

        if ($combinado !== '' && preg_match('/^(\d{4,10})$/', $combinado)) {
            $codigo = $combinado;
        }

        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        if ($codigo === '' || strlen($codigo) > 10) {
            return null;
        }

        // Código puro na coluna cClassTrib + descrição na coluna Nome
        if (($descricao === null || $descricao === '') && isset($map['nome'])) {
            $descricao = $this->nullableCol($cols, $map['nome'], 500);
        }

        return [
            'codigo' => $codigo,
            'descricao' => $descricao,
        ];
    }

    private function parseBool(string $value): ?bool
    {
        $normalized = Str::lower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 's', 'sim', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'n', 'nao', 'não', 'false', 'no'], true)) {
            return false;
        }

        return null;
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
        $parsed = str_getcsv($line, $delimiter, '"', '\\');

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
        $temp = tempnam(sys_get_temp_dir(), 'cclass_utf8_');

        if ($temp === false) {
            throw new \RuntimeException('Não foi possível preparar o arquivo para importação.');
        }

        file_put_contents($temp, $utf8);

        return $temp;
    }
}
