<?php

namespace App\Support\Erp;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\Nfe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documento operacional + documento fiscal (NF-e/NFC-e) para o extrato de estoque.
 */
final class EstoqueMovimentacaoDocumento
{
    /**
     * @return array{
     *     origemTipo: string,
     *     origemId: int,
     *     origemNumero: string,
     *     docFiscalTipo: ?string,
     *     docFiscalNumero: ?string
     * }
     */
    public static function fromCompra(Compra $compra): array
    {
        $numero = trim((string) ($compra->numero ?? ''));
        $nota = trim((string) ($compra->numero_nota ?? ''));

        return [
            'origemTipo' => 'compra',
            'origemId' => (int) $compra->id,
            'origemNumero' => self::numeroAmigavel($numero !== '' ? $numero : (string) $compra->id),
            'docFiscalTipo' => $nota !== '' ? 'nfe' : null,
            'docFiscalNumero' => $nota !== '' ? self::numeroAmigavel($nota) : null,
        ];
    }

    /**
     * @return array{
     *     origemTipo: string,
     *     origemId: int,
     *     origemNumero: string,
     *     docFiscalTipo: ?string,
     *     docFiscalNumero: ?string
     * }
     */
    public static function fromDevolucaoCompra(DevolucaoCompra $devolucao): array
    {
        $numero = trim((string) ($devolucao->numero ?? ''));
        $nfe = $devolucao->relationLoaded('nfe')
            ? $devolucao->nfe
            : $devolucao->nfe()->first();

        $fiscalNumero = null;
        if ($nfe && trim((string) ($nfe->numero ?? '')) !== '') {
            $fiscalNumero = self::numeroAmigavel((string) $nfe->numero);
        }

        return [
            'origemTipo' => 'devolucao_compra',
            'origemId' => (int) $devolucao->id,
            'origemNumero' => self::numeroAmigavel($numero !== '' ? $numero : (string) $devolucao->id),
            'docFiscalTipo' => $fiscalNumero !== null ? 'nfe' : null,
            'docFiscalNumero' => $fiscalNumero,
        ];
    }

    /**
     * Quando a NF-e de devolução é emitida depois da baixa de estoque,
     * preenche Doc. fiscal sem alterar o número da devolução.
     */
    public static function sincronizarNfeDevolucaoCompra(Nfe $nfe): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        $devolucaoId = (int) ($nfe->devolucao_compra_id ?? 0);
        $numero = trim((string) ($nfe->numero ?? ''));
        if ($devolucaoId <= 0 || $numero === '') {
            return;
        }

        $numeroAmigavel = self::numeroAmigavel($numero);
        $payload = [
            'doc_fiscal_tipo' => 'nfe',
            'doc_fiscal_numero' => $numeroAmigavel,
        ];

        // Mantém origem operacional na devolução; se ficou como nfe no passado, corrige.
        $devolucaoNumero = null;
        if (Schema::hasTable('devolucoes_compra')) {
            $raw = DB::table('devolucoes_compra')->where('id', $devolucaoId)->value('numero');
            if ($raw !== null && trim((string) $raw) !== '') {
                $devolucaoNumero = self::numeroAmigavel((string) $raw);
            }
        }

        $query = DB::table('estoque_movimentacoes')
            ->whereIn('tipo', [
                'devolucao_compra',
                'cancelamento_estorno',
            ])
            ->where(function ($q) use ($devolucaoId, $nfe): void {
                $q->where(function ($q2) use ($devolucaoId): void {
                    $q2->where('origem_tipo', 'devolucao_compra')
                        ->where('origem_id', $devolucaoId);
                })->orWhere(function ($q2) use ($nfe): void {
                    $q2->where('origem_tipo', 'nfe')
                        ->where('origem_id', (int) $nfe->id);
                });
            });

        $fixPayload = $payload;
        $fixPayload['origem_tipo'] = 'devolucao_compra';
        $fixPayload['origem_id'] = $devolucaoId;
        if ($devolucaoNumero !== null) {
            $fixPayload['origem_numero'] = $devolucaoNumero;
        }

        $query->update($fixPayload);
    }

    public static function numeroAmigavel(string $numero): string
    {
        $trimmed = ltrim(trim($numero), '0');

        return $trimmed !== '' ? $trimmed : trim($numero);
    }
}
