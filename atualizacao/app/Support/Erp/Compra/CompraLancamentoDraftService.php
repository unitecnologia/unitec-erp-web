<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use Illuminate\Support\Carbon;

/**
 * Persiste/restaura o rascunho do lançamento (grade + totais + parâmetros).
 *
 * Importante: gravar rascunho NÃO dá entrada de estoque, NÃO ajusta preço no
 * cadastro e NÃO gera financeiro. Isso permanece exclusivo do Finalizar.
 */
final class CompraLancamentoDraftService
{
    public const VERSION = 1;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totais
     * @param  array{
     *     ajusta_preco: bool,
     *     gerar_financeiro: bool,
     *     gera_estoque: bool,
     *     margem_escopo?: string,
     *     margem_percent_varejo?: string,
     *     margem_percent_atacado?: string,
     *     margem_percent_especial?: string,
     *     item_index?: int
     * }  $meta
     * @return array<string, mixed>
     */
    public function buildPayload(array $rows, array $totais, array $meta = []): array
    {
        return [
            'version' => self::VERSION,
            'saved_at' => Carbon::now()->toIso8601String(),
            'rows' => array_values($rows),
            'totais' => $totais,
            'params' => [
                'ajusta_preco' => (bool) ($meta['ajusta_preco'] ?? true),
                'gerar_financeiro' => (bool) ($meta['gerar_financeiro'] ?? true),
                'gera_estoque' => (bool) ($meta['gera_estoque'] ?? true),
            ],
            'margem' => [
                'escopo' => (string) ($meta['margem_escopo'] ?? 'item'),
                'percent_varejo' => (string) ($meta['margem_percent_varejo'] ?? '0,00'),
                'percent_atacado' => (string) ($meta['margem_percent_atacado'] ?? '0,00'),
                'percent_especial' => (string) ($meta['margem_percent_especial'] ?? '0,00'),
            ],
            'item_index' => (int) ($meta['item_index'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(Compra $compra, array $payload): void
    {
        if ($compra->status !== Compra::STATUS_ABERTA) {
            return;
        }

        $compra->forceFill([
            'lancamento_draft' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ])->save();
    }

    public function clear(Compra $compra): void
    {
        if ($compra->lancamento_draft === null) {
            return;
        }

        $compra->forceFill(['lancamento_draft' => null])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(Compra $compra): ?array
    {
        $raw = $compra->lancamento_draft;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        if ((int) ($decoded['version'] ?? 0) !== self::VERSION) {
            return null;
        }

        if (! isset($decoded['rows']) || ! is_array($decoded['rows']) || $decoded['rows'] === []) {
            return null;
        }

        return $decoded;
    }

    /**
     * Garante que o rascunho ainda corresponde aos itens da compra.
     *
     * @param  array<string, mixed>  $draft
     */
    public function isCompatible(Compra $compra, array $draft): bool
    {
        $rows = $draft['rows'] ?? null;

        if (! is_array($rows) || $rows === []) {
            return false;
        }

        $compra->loadMissing('itens');
        $itens = $compra->itens;

        if ($itens->count() !== count($rows)) {
            return false;
        }

        foreach ($itens->values() as $index => $item) {
            $draftProductId = (int) ($rows[$index]['product_id'] ?? 0);
            $itemProductId = (int) ($item->product_id ?? 0);

            if ($draftProductId > 0 && $itemProductId > 0 && $draftProductId !== $itemProductId) {
                return false;
            }
        }

        return true;
    }
}
