<?php

namespace App\Support\Erp\Import;

use App\Models\CaixaConta;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\PlanoConta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FirebirdContaPagarPagamentoImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $contaPagarIdByNumero
     * @param  array<string, int>  $planoIdByCodigo
     * @param  array<string, int>  $caixaContaIdByCodigo
     * @param  array<string, int>  $formaIdByCodigo
     * @param  array<string, int>  $fornecedorIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(
        array $row,
        array $contaPagarIdByNumero,
        array $planoIdByCodigo,
        array $caixaContaIdByCodigo,
        array $formaIdByCodigo,
        array $fornecedorIdByCodigo,
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
        $fkPagar = (int) ($row['FKPAGAR'] ?? $row['fkpagar'] ?? 0);

        if ($codigo < 1 || $fkPagar < 1) {
            return null;
        }

        $numero = str_pad((string) $fkPagar, 6, '0', STR_PAD_LEFT);
        $contaPagarId = $contaPagarIdByNumero[$numero] ?? $contaPagarIdByNumero[(string) $fkPagar] ?? null;

        if (! $contaPagarId) {
            return null;
        }

        $data = $this->mapDate($row['DATA'] ?? $row['data'] ?? null);

        if ($data === null) {
            return null;
        }

        $fkPlano = trim((string) ($row['FKPLANO'] ?? $row['fkplano'] ?? ''));
        $fkConta = trim((string) ($row['FKCONTA'] ?? $row['fkconta'] ?? ''));
        $fkForma = trim((string) ($row['FK_FORMA_PGTO'] ?? $row['fk_forma_pgto'] ?? ''));
        $fkFornecedor = trim((string) ($row['FKFORNECEDOR'] ?? $row['fkfornecedor'] ?? ''));

        return [
            'codigo_legado' => $codigo,
            'conta_pagar_id' => (int) $contaPagarId,
            'data' => $data,
            'valor_parcela' => BrDecimalImport::parse($row['VALOR_PARCELA'] ?? 0),
            'perc_juros' => BrDecimalImport::parse($row['PERC_JUROS'] ?? 0, 4),
            'juros' => BrDecimalImport::parse($row['JUROS'] ?? 0),
            'perc_desconto' => BrDecimalImport::parse($row['PERC_DESCONTO'] ?? 0, 4),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'valor_pago' => BrDecimalImport::parse($row['VALOR_RECEBIDO'] ?? $row['VALOR_PAGO'] ?? 0),
            'plano_conta_id' => $fkPlano !== ''
                ? ($planoIdByCodigo[$fkPlano] ?? $planoIdByCodigo[(string) (int) $fkPlano] ?? null)
                : null,
            'caixa_conta_id' => $fkConta !== ''
                ? ($caixaContaIdByCodigo[$fkConta] ?? $caixaContaIdByCodigo[(string) (int) $fkConta] ?? null)
                : null,
            'forma_pagamento_id' => $fkForma !== ''
                ? ($formaIdByCodigo[$fkForma] ?? $formaIdByCodigo[(string) (int) $fkForma] ?? null)
                : null,
            'numero_cheque' => trim((string) ($row['NUMERO_CHEQUE'] ?? $row['numero_cheque'] ?? '')) ?: null,
            'fornecedor_id' => $fkFornecedor !== ''
                ? ($fornecedorIdByCodigo[$fkFornecedor] ?? $fornecedorIdByCodigo[(string) (int) $fkFornecedor] ?? null)
                : null,
            'lote_legado' => filled($row['FK_LOTE'] ?? $row['fk_lote'] ?? null)
                ? (int) ($row['FK_LOTE'] ?? $row['fk_lote'])
                : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $contaPagarIdByNumero = ContaPagar::query()
            ->pluck('id', 'numero')
            ->mapWithKeys(fn ($id, $numero) => [
                (string) $numero => (int) $id,
                (string) (int) preg_replace('/\D/', '', (string) $numero) => (int) $id,
            ])
            ->all();

        $planoIdByCodigo = PlanoConta::query()
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $caixaContaIdByCodigo = CaixaConta::query()
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $formaIdByCodigo = FormaPagamento::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $fornecedorIdByCodigo = Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        DB::transaction(function () use (
            $rows,
            $updateExisting,
            $dryRun,
            $contaPagarIdByNumero,
            $planoIdByCodigo,
            $caixaContaIdByCodigo,
            $formaIdByCodigo,
            $fornecedorIdByCodigo,
            &$stats,
        ): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow(
                    $row,
                    $contaPagarIdByNumero,
                    $planoIdByCodigo,
                    $caixaContaIdByCodigo,
                    $formaIdByCodigo,
                    $fornecedorIdByCodigo,
                );

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = ContaPagarPagamento::query()
                    ->where('codigo_legado', $payload['codigo_legado'])
                    ->first();

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
                    ContaPagarPagamento::query()->create($payload);
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
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
