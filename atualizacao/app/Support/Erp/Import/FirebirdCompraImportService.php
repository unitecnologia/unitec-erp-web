<?php

namespace App\Support\Erp\Import;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FirebirdCompraImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $fornecedorIdByCodigo
     * @param  array<string, int>  $empresaIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapMasterRow(
        array $row,
        array $fornecedorIdByCodigo,
        array $empresaIdByCodigo,
        ?int $fallbackEmpresaId,
    ): ?array {
        $id = (int) ($row['ID'] ?? $row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        $fkFornecedor = trim((string) ($row['FORNECEDOR'] ?? $row['fornecedor'] ?? ''));
        $fornecedorId = $fkFornecedor !== '' && (int) $fkFornecedor > 0
            ? ($fornecedorIdByCodigo[$fkFornecedor] ?? $fornecedorIdByCodigo[(string) (int) $fkFornecedor] ?? null)
            : null;

        if (! $fornecedorId) {
            Log::warning('Migra FB compras: fornecedor não encontrado', [
                'compra_id' => $id,
                'fornecedor' => $fkFornecedor,
            ]);

            return null;
        }

        $fkEmpresa = trim((string) ($row['EMPRESA'] ?? $row['empresa'] ?? ''));
        $empresaId = $fkEmpresa !== ''
            ? ($empresaIdByCodigo[$fkEmpresa] ?? $empresaIdByCodigo[(string) (int) $fkEmpresa] ?? $fallbackEmpresaId)
            : $fallbackEmpresaId;

        $dataEmissao = $this->mapDate($row['DTEMISSAO'] ?? $row['dtemissao'] ?? null)
            ?? $this->mapDate($row['DTENTRADA'] ?? $row['dtentrada'] ?? null);

        if ($dataEmissao === null) {
            return null;
        }

        $dataEntrada = $this->mapDate($row['DTENTRADA'] ?? $row['dtentrada'] ?? null) ?? $dataEmissao;
        $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: null;
        $nrNota = trim((string) ($row['NR_NOTA'] ?? $row['nr_nota'] ?? ''));

        return [
            '_fb_id' => $id,
            'codigo_legado' => $id,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'numero' => str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'data_emissao' => $dataEmissao,
            'data_entrada' => $dataEntrada,
            'numero_nota' => $nrNota !== '' && strtoupper($nrNota) !== '<NULL>'
                ? substr($nrNota, 0, 20)
                : null,
            'fornecedor_id' => (int) $fornecedorId,
            'chave_nfe' => $chave !== null ? substr($chave, 0, 44) : null,
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? 0),
            'status' => $this->mapStatus((string) ($row['STATUS'] ?? $row['status'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $productIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapItemRow(array $row, array $productIdByCodigo): ?array
    {
        $fkCompra = (int) ($row['FK_COMPRA'] ?? $row['fk_compra'] ?? 0);
        if ($fkCompra < 1) {
            return null;
        }

        $fkProduto = trim((string) ($row['FK_PRODUTO'] ?? $row['fk_produto'] ?? ''));
        $productId = $fkProduto !== '' && (int) $fkProduto > 0
            ? ($productIdByCodigo[$fkProduto] ?? $productIdByCodigo[(string) (int) $fkProduto] ?? null)
            : null;

        if (! $productId) {
            Log::warning('Migra FB compras: produto não encontrado no item', [
                'fk_compra' => $fkCompra,
                'fk_produto' => $fkProduto,
                'item' => $row['ITEM'] ?? $row['item'] ?? null,
            ]);

            return null;
        }

        $qtd = BrDecimalImport::parse($row['QTD'] ?? $row['qtd'] ?? 0, 3);
        $unitario = BrDecimalImport::parse($row['VL_UNITARIO'] ?? $row['vl_unitario'] ?? 0);
        $total = BrDecimalImport::parse($row['TOTAL'] ?? $row['total'] ?? $row['VL_ITEM'] ?? $row['vl_item'] ?? 0);
        if ($total <= 0 && $qtd > 0 && $unitario > 0) {
            $total = round($qtd * $unitario, 2);
        }

        return [
            '_fb_compra' => $fkCompra,
            'product_id' => (int) $productId,
            'quantidade' => $qtd,
            'valor_unitario' => $unitario,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $masterRows
     * @param  list<array<string, mixed>>  $itemRows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $masterRows, array $itemRows = [], bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $fornecedorIdByCodigo = Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $empresaIdByCodigo = Empresa::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $productIdByCodigo = Product::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $fallbackEmpresaId = Empresa::query()->orderBy('id')->value('id');
        $fallbackEmpresaId = $fallbackEmpresaId !== null ? (int) $fallbackEmpresaId : null;

        /** @var array<int, list<array<string, mixed>>> $itemsByFb */
        $itemsByFb = [];
        foreach ($itemRows as $itemRow) {
            if (! is_array($itemRow)) {
                continue;
            }
            $mapped = $this->mapItemRow($itemRow, $productIdByCodigo);
            if ($mapped === null) {
                continue;
            }
            $fb = (int) $mapped['_fb_compra'];
            unset($mapped['_fb_compra']);
            $itemsByFb[$fb][] = $mapped;
        }

        DB::transaction(function () use (
            $masterRows,
            $itemsByFb,
            $fornecedorIdByCodigo,
            $empresaIdByCodigo,
            $fallbackEmpresaId,
            $updateExisting,
            $dryRun,
            &$stats,
        ): void {
            foreach ($masterRows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapMasterRow(
                    $row,
                    $fornecedorIdByCodigo,
                    $empresaIdByCodigo,
                    $fallbackEmpresaId,
                );

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $fbId = (int) $payload['_fb_id'];
                unset($payload['_fb_id']);

                $existing = Compra::query()->where('codigo_legado', $payload['codigo_legado'])->first();
                if (! $existing && ! empty($payload['chave_nfe'])) {
                    $existing = Compra::query()->where('chave_nfe', $payload['chave_nfe'])->first();
                }
                if (! $existing) {
                    $existing = Compra::query()->where('numero', $payload['numero'])->first();
                }

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                $itens = $itemsByFb[$fbId] ?? [];

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $compra = $existing;
                    $stats['updated']++;
                } else {
                    $compra = Compra::query()->create($payload);
                    $stats['created']++;
                }

                CompraItem::query()->where('compra_id', $compra->id)->delete();
                foreach ($itens as $itemPayload) {
                    $itemPayload['compra_id'] = $compra->id;
                    CompraItem::query()->create($itemPayload);
                }
            }
        });

        return $stats;
    }

    protected function mapStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return match ($status) {
            'A', 'ABERTA' => Compra::STATUS_ABERTA,
            'C', 'CANCELADA', 'CANCELADO' => Compra::STATUS_CANCELADA,
            'F', 'FECHADA', 'FINALIZADA', 'CONCLUIDA', 'CONCLUÍDA' => Compra::STATUS_FECHADA,
            default => Compra::STATUS_FECHADA,
        };
    }

    protected function mapDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || strtoupper($raw) === '<NULL>') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
