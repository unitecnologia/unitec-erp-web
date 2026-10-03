<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\NotaFornecedor;
use App\Models\NotaFornecedorItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Upsert de itens da nota fornecedor a partir do XML.
 * Preserva IDs (FK em compra_itens). Nunca faz DELETE+INSERT geral.
 */
final class NotaFornecedorItensSyncService
{
    public function __construct(
        private readonly NotaFornecedorFiscalSnapshotParser $parser = new NotaFornecedorFiscalSnapshotParser(),
    ) {}

    /**
     * @return array{synced: int, inconsistencias: int, removidos: int}
     */
    public function sync(NotaFornecedor $nota): array
    {
        $xml = trim((string) ($nota->xml ?? ''));

        if ($xml === '') {
            return ['synced' => 0, 'inconsistencias' => 0, 'removidos' => 0];
        }

        $parsed = $this->parser->parseItens($xml);

        if ($parsed === null) {
            return ['synced' => 0, 'inconsistencias' => 0, 'removidos' => 0];
        }

        return DB::transaction(function () use ($nota, $parsed): array {
            $synced = 0;
            $inconsistencias = 0;
            $seenNItems = [];

            foreach ($parsed as $row) {
                $nItem = (int) $row['n_item'];
                $seenNItems[] = $nItem;

                /** @var NotaFornecedorItem|null $existente */
                $existente = NotaFornecedorItem::query()
                    ->where('nota_fornecedor_id', $nota->id)
                    ->where('n_item', $nItem)
                    ->first();

                $cfopXml = $row['cfop'] !== '' ? $row['cfop'] : null;

                $comercial = [
                    'c_prod' => $row['c_prod'] !== '' ? $row['c_prod'] : null,
                    'c_ean' => $row['c_ean'] !== '' ? $row['c_ean'] : null,
                    'descricao' => $row['descricao'] !== '' ? $row['descricao'] : null,
                    'ncm' => $row['ncm'] !== '' ? $row['ncm'] : null,
                    'cfop' => $existente
                        ? $this->preservarCfopEntrada($existente->cfop, $cfopXml)
                        : $cfopXml,
                    'unidade' => $row['unidade'] !== '' ? $row['unidade'] : null,
                    'quantidade' => round((float) $row['quantidade'], 4),
                    'valor_unitario' => round((float) $row['valor_unitario'], 4),
                    'valor_total' => round((float) $row['valor_total'], 2),
                ];

                $novoSnapshot = $row['fiscal_snapshot'];

                if ($existente === null) {
                    NotaFornecedorItem::query()->create([
                        'nota_fornecedor_id' => $nota->id,
                        'n_item' => $nItem,
                        ...$comercial,
                        'fiscal_snapshot' => $novoSnapshot,
                        'fiscal_inconsistente' => false,
                        'fiscal_snapshot_conflito' => null,
                        'fiscal_inconsistente_em' => null,
                    ]);
                    $synced++;

                    continue;
                }

                $vinculado = $existente->estaVinculadoACompra();
                $snapshotMudou = ! $this->parser->snapshotsIguais(
                    $existente->fiscal_snapshot,
                    $novoSnapshot,
                );

                $updates = $comercial;

                if ($vinculado && $snapshotMudou) {
                    $updates['fiscal_inconsistente'] = true;
                    $updates['fiscal_snapshot_conflito'] = $novoSnapshot;
                    $updates['fiscal_inconsistente_em'] = now();
                    // Mantém fiscal_snapshot histórico — não sobrescreve.
                    $inconsistencias++;

                    Log::warning('Snapshot fiscal divergente em item de nota já vinculado à compra.', [
                        'nota_fornecedor_id' => $nota->id,
                        'nota_fornecedor_item_id' => $existente->id,
                        'n_item' => $nItem,
                    ]);
                } else {
                    $updates['fiscal_snapshot'] = $novoSnapshot;

                    if (! $vinculado) {
                        $updates['fiscal_inconsistente'] = false;
                        $updates['fiscal_snapshot_conflito'] = null;
                        $updates['fiscal_inconsistente_em'] = null;
                    }
                }

                $existente->forceFill($updates)->save();
                $synced++;
            }

            $removidos = 0;
            $orphans = NotaFornecedorItem::query()
                ->where('nota_fornecedor_id', $nota->id)
                ->whereNotIn('n_item', $seenNItems)
                ->get();

            foreach ($orphans as $orphan) {
                if ($orphan->estaVinculadoACompra()) {
                    continue;
                }

                $orphan->delete();
                $removidos++;
            }

            return [
                'synced' => $synced,
                'inconsistencias' => $inconsistencias,
                'removidos' => $removidos,
            ];
        });
    }

    /**
     * O XML traz CFOP de saída. Se a compra já gravou o CFOP de entrada, o sync não o substitui.
     */
    private function preservarCfopEntrada(?string $atual, ?string $doXml): ?string
    {
        $gravado = preg_replace('/\D/', '', (string) $atual) ?? '';
        $xml = preg_replace('/\D/', '', (string) $doXml) ?? '';
        $entrada = strlen($gravado) === 4 && in_array($gravado[0], ['1', '2', '3'], true);
        $saida = strlen($xml) === 4 && in_array($xml[0], ['5', '6', '7'], true);

        if ($entrada && $saida) {
            return $gravado;
        }

        return $doXml;
    }
}
