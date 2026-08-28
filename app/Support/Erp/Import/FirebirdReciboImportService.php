<?php

namespace App\Support\Erp\Import;

use App\Models\Recibo;
use App\Support\Erp\ValorPorExtenso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdReciboImportService
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

        $emissao = $this->mapDate(
            $row['DATA_EMISSAO']
            ?? $row['data_emissao']
            ?? $row['EMISSAO']
            ?? $row['emissao']
            ?? $row['DATA']
            ?? $row['data']
            ?? $row['DTEMISSAO']
            ?? $row['dtemissao']
            ?? null
        );

        if ($emissao === null) {
            return null;
        }

        $valor = BrDecimalImport::parse(
            $row['VALOR'] ?? $row['valor'] ?? $row['VL'] ?? $row['vl'] ?? null,
            2
        );

        if ($valor <= 0) {
            return null;
        }

        $recebiDe = Str::upper(trim((string) (
            $row['NOMINAL']
            ?? $row['nominal']
            ?? $row['RECEBI_DE']
            ?? $row['recebi_de']
            ?? $row['RECEBI']
            ?? $row['recebi']
            ?? $row['CLIENTE']
            ?? $row['cliente']
            ?? ''
        )));

        if ($recebiDe === '') {
            return null;
        }

        $referenteParts = [];
        foreach ([
            $row['REFERENTE_A'] ?? $row['referente_a'] ?? null,
            $row['REFERENTE'] ?? $row['referente'] ?? null,
            $row['REFERENTE1'] ?? $row['referente1'] ?? null,
            $row['REFERENTE2'] ?? $row['referente2'] ?? null,
            $row['HISTORICO'] ?? $row['historico'] ?? null,
            $row['OBS'] ?? $row['obs'] ?? null,
        ] as $part) {
            $part = Str::upper(trim((string) ($part ?? '')));
            if ($part !== '' && $part !== 'NULL' && ! in_array($part, $referenteParts, true)) {
                $referenteParts[] = $part;
            }
        }
        $referente = trim(implode("\n", $referenteParts));

        $extenso = Str::upper(trim((string) (
            $row['EXTENSO']
            ?? $row['extenso']
            ?? $row['VALOR_EXTENSO']
            ?? $row['valor_extenso']
            ?? ''
        )));

        if ($extenso === '') {
            $extenso = Str::upper(ValorPorExtenso::fromMoney($valor));
        }

        return [
            'codigo' => $codigo,
            'emissao' => $emissao,
            'valor' => $valor,
            'extenso' => $extenso !== '' ? mb_substr($extenso, 0, 500) : null,
            'recebi_de' => mb_substr($recebiDe, 0, 200),
            'referente_a' => $referente !== '' ? $referente : null,
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

                $existing = Recibo::query()->where('codigo', $payload['codigo'])->first();

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
                    Recibo::query()->create($payload);
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
