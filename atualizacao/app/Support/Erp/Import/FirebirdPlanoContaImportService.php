<?php

namespace App\Support\Erp\Import;

use App\Models\PlanoConta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdPlanoContaImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row): ?array
    {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);

        if ($codigo < 1) {
            return null;
        }

        $descricao = Str::upper(trim((string) ($row['DESCRICAO'] ?? $row['descricao'] ?? '')));

        if ($descricao === '') {
            return null;
        }

        $dc = Str::upper(trim((string) ($row['DC'] ?? $row['dc'] ?? '')));
        $pai = filled($row['PAI'] ?? $row['pai'] ?? null)
            ? (int) ($row['PAI'] ?? $row['pai'])
            : null;

        return [
            'codigo' => $codigo,
            'descricao' => $descricao,
            'dc' => in_array($dc, ['D', 'C'], true) ? $dc : null,
            'nivel' => filled($row['NIVEL'] ?? $row['nivel'] ?? null) ? (int) ($row['NIVEL'] ?? $row['nivel']) : null,
            'codigo_plano' => trim((string) ($row['CODIGO_PLANO'] ?? $row['codigo_plano'] ?? '')) ?: null,
            'pai_codigo' => $pai > 0 ? $pai : null,
            'conta_completa' => trim((string) ($row['CONTA_COMPLETA'] ?? $row['conta_completa'] ?? '')) ?: null,
            'flag' => trim((string) ($row['FLAG'] ?? $row['flag'] ?? '')) ?: null,
            'despesas' => $this->snToBool($row['DESPESAS'] ?? $row['despesas'] ?? 'N'),
            'compras' => $this->snToBool($row['COMPRAS'] ?? $row['compras'] ?? 'N'),
            'entradas' => $this->snToBool($row['ENTRADAS'] ?? $row['entradas'] ?? 'N'),
            'taxa_juros' => BrDecimalImport::parse($row['TAXA_JUROS'] ?? $row['taxa_juros'] ?? null, 4),
            'ativo' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = PlanoConta::query()->where('codigo', $payload['codigo'])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->update($payload);
                    $stats['updated']++;
                } else {
                    PlanoConta::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    protected function snToBool(mixed $value): bool
    {
        return in_array(Str::upper(trim((string) $value)), ['S', '1', 'T', 'TRUE', 'Y'], true);
    }
}
