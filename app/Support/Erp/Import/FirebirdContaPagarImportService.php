<?php

namespace App\Support\Erp\Import;

use App\Models\ContaPagar;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdContaPagarImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, array $fornecedorIdByCodigo): ?array
    {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);

        if ($codigo < 1) {
            return null;
        }

        $fkFornece = filled($row['FKFORNECE'] ?? $row['fkfornece'] ?? null)
            ? trim((string) ($row['FKFORNECE'] ?? $row['fkfornece']))
            : '';

        $fornecedorId = $fkFornece !== ''
            ? ($fornecedorIdByCodigo[$fkFornece] ?? $fornecedorIdByCodigo[(string) (int) $fkFornece] ?? null)
            : null;

        if (! $fornecedorId) {
            return null;
        }

        $emissao = $this->mapDate($row['DATA'] ?? $row['data'] ?? null)
            ?? $this->mapDate($row['DTVENCIMENTO'] ?? $row['dtvencimento'] ?? null);

        $vencimento = $this->mapDate($row['DTVENCIMENTO'] ?? $row['dtvencimento'] ?? null) ?? $emissao;

        if ($emissao === null || $vencimento === null) {
            return null;
        }

        $historico = trim((string) ($row['HISTORICO'] ?? $row['historico'] ?? ''));

        return [
            'numero' => str_pad((string) $codigo, 6, '0', STR_PAD_LEFT),
            'emissao' => $emissao,
            'produto' => $historico !== '' ? Str::upper($historico) : null,
            'documento' => trim((string) ($row['DOC'] ?? $row['doc'] ?? '')) ?: null,
            'fornecedor_id' => (int) $fornecedorId,
            'vencimento' => $vencimento,
            'valor' => BrDecimalImport::parse($row['VALOR'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'juros' => BrDecimalImport::parse($row['JUROS'] ?? 0),
            'valor_pago' => BrDecimalImport::parse($row['VLPAGO'] ?? 0),
            'pago_em' => $this->mapDate($row['DATA_PAGAMENTO'] ?? $row['data_pagamento'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $fornecedorIdByCodigo = Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $fornecedorIdByCodigo, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $fornecedorIdByCodigo);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = ContaPagar::query()->where('numero', $payload['numero'])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    ContaPagar::query()->create($payload);
                    $stats['created']++;
                }
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
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
