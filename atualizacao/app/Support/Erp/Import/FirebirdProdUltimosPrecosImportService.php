<?php

namespace App\Support\Erp\Import;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdProdUltimosPrecosImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $productIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, array $productIdByCodigo): ?array
    {
        $fkProduto = trim((string) ($row['FKPRODUTO'] ?? $row['fkproduto'] ?? ''));

        if ($fkProduto === '') {
            return null;
        }

        $productId = $productIdByCodigo[$fkProduto]
            ?? $productIdByCodigo[(string) (int) $fkProduto]
            ?? null;

        if (! $productId) {
            return null;
        }

        $registradoEm = $this->mapDate($row['DT_ULTIMO_PRECO'] ?? $row['dt_ultimo_preco'] ?? null);

        if ($registradoEm === null) {
            return null;
        }

        return [
            'product_id' => (int) $productId,
            'ultimo_preco' => BrDecimalImport::parse($row['ULTIMO_PRECO'] ?? 0),
            'registrado_em' => $registradoEm,
            'usuario' => Str::upper(trim((string) ($row['USUARIO'] ?? $row['usuario'] ?? ''))) ?: null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $productIdByCodigo = Product::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $productIdByCodigo, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $productIdByCodigo);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = ProductPriceHistory::query()
                    ->where('product_id', $payload['product_id'])
                    ->whereDate('registrado_em', $payload['registrado_em'])
                    ->where('ultimo_preco', $payload['ultimo_preco'])
                    ->first();

                if ($existing) {
                    if (! $updateExisting) {
                        $stats['skipped']++;

                        continue;
                    }

                    if ($dryRun) {
                        $stats['updated']++;

                        continue;
                    }

                    $existing->update(['usuario' => $payload['usuario']]);
                    $stats['updated']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['created']++;

                    continue;
                }

                ProductPriceHistory::query()->create($payload);
                $stats['created']++;
            }
        });

        return $stats;
    }

    protected function mapDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
