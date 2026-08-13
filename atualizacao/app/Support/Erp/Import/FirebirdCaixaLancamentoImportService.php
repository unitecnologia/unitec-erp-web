<?php

namespace App\Support\Erp\Import;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\PlanoConta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdCaixaLancamentoImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $contaIdByCodigo
     * @param  array<string, string>  $planoNomeByCodigo
     * @param  array<string, int>  $planoIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(
        array $row,
        array $contaIdByCodigo,
        array $planoNomeByCodigo = [],
        array $planoIdByCodigo = [],
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);

        if ($codigo < 1) {
            return null;
        }

        $fkConta = filled($row['FKCONTA'] ?? $row['fkconta'] ?? null)
            ? trim((string) ($row['FKCONTA'] ?? $row['fkconta']))
            : '';

        $caixaContaId = $fkConta !== ''
            ? ($contaIdByCodigo[$fkConta] ?? $contaIdByCodigo[(string) (int) $fkConta] ?? null)
            : null;

        if (! $caixaContaId) {
            return null;
        }

        $emissao = $this->mapDate($row['EMISSAO'] ?? $row['emissao'] ?? null)
            ?? $this->mapDate($row['DT_CADASTRO'] ?? $row['dt_cadastro'] ?? null);

        if ($emissao === null) {
            return null;
        }

        $historico = trim((string) ($row['HISTORICO'] ?? $row['historico'] ?? ''));
        $planoNome = trim((string) ($row['PLANO_NOME'] ?? $row['plano_nome'] ?? ''));
        $fkPlano = filled($row['FKPLANO'] ?? $row['fkplano'] ?? null)
            ? trim((string) ($row['FKPLANO'] ?? $row['fkplano']))
            : '';

        if ($planoNome === '' && $fkPlano !== '') {
            $planoNome = $planoNomeByCodigo[$fkPlano]
                ?? $planoNomeByCodigo[(string) (int) $fkPlano]
                ?? '';
        }

        $planoContaId = $fkPlano !== ''
            ? ($planoIdByCodigo[$fkPlano] ?? $planoIdByCodigo[(string) (int) $fkPlano] ?? null)
            : null;

        return [
            'codigo' => $codigo,
            'emissao' => $emissao,
            'documento' => trim((string) ($row['DOC'] ?? $row['doc'] ?? '')) ?: null,
            'historico' => $historico !== '' ? Str::upper($historico) : '-',
            'plano_contas' => $planoNome !== '' ? Str::upper($planoNome) : null,
            'plano_conta_id' => $planoContaId,
            'caixa_conta_id' => (int) $caixaContaId,
            'entrada' => BrDecimalImport::parse($row['ENTRADA'] ?? 0),
            'saida' => BrDecimalImport::parse($row['SAIDA'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $planoNomeByCodigo
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(
        array $rows,
        bool $updateExisting = true,
        bool $dryRun = false,
        array $planoNomeByCodigo = [],
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $contaIdByCodigo = CaixaConta::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $planoIdByCodigo = PlanoConta::query()
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $contaIdByCodigo, $planoNomeByCodigo, $planoIdByCodigo, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $contaIdByCodigo, $planoNomeByCodigo, $planoIdByCodigo);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = CaixaLancamento::query()->where('codigo', $payload['codigo'])->first();

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
                    CaixaLancamento::query()->create($payload);
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
