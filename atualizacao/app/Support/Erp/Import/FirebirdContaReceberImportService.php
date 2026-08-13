<?php

namespace App\Support\Erp\Import;

use App\Models\ContaReceber;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdContaReceberImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $clienteIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, array $clienteIdByCodigo): ?array
    {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);

        if ($codigo < 1) {
            return null;
        }

        $fkCliente = filled($row['FKCLIENTE'] ?? $row['fkcliente'] ?? null)
            ? trim((string) ($row['FKCLIENTE'] ?? $row['fkcliente']))
            : '';

        $clienteId = $fkCliente !== ''
            ? ($clienteIdByCodigo[$fkCliente] ?? $clienteIdByCodigo[(string) (int) $fkCliente] ?? null)
            : null;

        if (! $clienteId) {
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
            'historico' => $historico !== '' ? Str::upper($historico) : '-',
            'documento' => trim((string) ($row['DOC'] ?? $row['doc'] ?? '')) ?: null,
            'cliente_id' => (int) $clienteId,
            'vencimento' => $vencimento,
            'valor' => BrDecimalImport::parse($row['VALOR'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'juros' => BrDecimalImport::parse($row['JUROS'] ?? 0),
            'valor_recebido' => BrDecimalImport::parse($row['VRECEBIDO'] ?? $row['vrecebido'] ?? 0),
            'recebido_em' => $this->mapDate($row['DATA_RECEBIMENTO'] ?? $row['data_recebimento'] ?? null),
            'forma' => ContaReceber::FORMA_CARTEIRA,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $clienteIdByCodigo = Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $clienteIdByCodigo, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $clienteIdByCodigo);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = ContaReceber::query()->where('numero', $payload['numero'])->first();

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
                    ContaReceber::query()->create($payload);
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
