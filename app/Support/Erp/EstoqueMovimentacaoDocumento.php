<?php

namespace App\Support\Erp;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
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

    /**
     * Doc. fiscal da NFC-e vinculada à venda PDV (número + série), só quando o número
     * já pertence a um documento fiscal real (autorizada, contingência ou cancelada).
     *
     * @return array{docFiscalTipo: string, docFiscalNumero: string}|null
     */
    public static function fromPdvVendaNfce(?PdvVendaNfce $nfce): ?array
    {
        if ($nfce === null || (bool) $nfce->simulada) {
            return null;
        }

        if (! in_array((string) $nfce->status, [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_CONTINGENCIA,
            PdvVendaNfce::STATUS_CANCELADA,
        ], true)) {
            return null;
        }

        $numero = self::nfceNumeroSerie($nfce->numero, $nfce->serie);

        return $numero !== null
            ? ['docFiscalTipo' => 'nfce', 'docFiscalNumero' => $numero]
            : null;
    }

    public static function fromPdvVenda(?PdvVenda $pdvVenda): ?array
    {
        if ($pdvVenda === null) {
            return null;
        }

        $nfce = $pdvVenda->relationLoaded('nfce') ? $pdvVenda->nfce : $pdvVenda->nfce()->first();

        return self::fromPdvVendaNfce($nfce);
    }

    /**
     * Preenche Doc. fiscal (vazio) dos movimentos de venda/estorno da venda PDV
     * quando a NFC-e é numerada depois da baixa. Não altera quantidades, saldos, datas nem origem.
     */
    public static function sincronizarNfcePdvVenda(PdvVendaNfce $nfce): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')
            || ! Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_numero')) {
            return;
        }

        $doc = self::fromPdvVendaNfce($nfce);
        $pdvVendaId = (int) ($nfce->pdv_venda_id ?? 0);
        if ($doc === null || $pdvVendaId <= 0) {
            return;
        }

        $vendaId = (int) (DB::table('pdv_vendas')->where('id', $pdvVendaId)->value('venda_id') ?? 0);

        DB::table('estoque_movimentacoes')
            ->whereIn('tipo', [
                EstoqueMovimentacao::TIPO_VENDA,
                EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
            ])
            ->where(function ($q): void {
                $q->whereNull('doc_fiscal_numero')->orWhere('doc_fiscal_numero', '');
            })
            ->where(function ($q) use ($pdvVendaId, $vendaId): void {
                $q->where(function ($q2) use ($pdvVendaId): void {
                    $q2->where('origem_tipo', 'pdv_venda')->where('origem_id', $pdvVendaId);
                });
                if ($vendaId > 0) {
                    $q->orWhere(function ($q2) use ($vendaId): void {
                        $q2->where('origem_tipo', 'venda')->where('origem_id', $vendaId);
                    });
                }
            })
            ->update([
                'doc_fiscal_tipo' => $doc['docFiscalTipo'],
                'doc_fiscal_numero' => $doc['docFiscalNumero'],
            ]);
    }

    /**
     * Resolve, só para exibição, a NFC-e de movimentos antigos sem Doc. fiscal gravado.
     *
     * @param  iterable<object{origem_tipo: ?string, origem_id: ?int}>  $rows
     * @return array<string, array{docFiscalTipo: string, docFiscalNumero: string}> chave "origem_tipo:origem_id"
     */
    public static function nfcePorOrigem(iterable $rows): array
    {
        $pdvIds = [];
        $vendaIds = [];

        foreach ($rows as $row) {
            $id = (int) ($row->origem_id ?? 0);
            if ($id <= 0) {
                continue;
            }
            match ((string) ($row->origem_tipo ?? '')) {
                'pdv_venda' => $pdvIds[$id] = $id,
                'venda' => $vendaIds[$id] = $id,
                default => null,
            };
        }

        if ($pdvIds === [] && $vendaIds === []) {
            return [];
        }

        $vendas = PdvVenda::query()
            ->with('nfce')
            ->where(function ($q) use ($pdvIds, $vendaIds): void {
                $q->whereIn('id', array_values($pdvIds) ?: [0])
                    ->orWhereIn('venda_id', array_values($vendaIds) ?: [0]);
            })
            ->get(['id', 'venda_id']);

        $map = [];
        foreach ($vendas as $pdvVenda) {
            $doc = self::fromPdvVendaNfce($pdvVenda->nfce);
            if ($doc === null) {
                continue;
            }
            if (isset($pdvIds[(int) $pdvVenda->id])) {
                $map['pdv_venda:'.(int) $pdvVenda->id] = $doc;
            }
            if ($pdvVenda->venda_id && isset($vendaIds[(int) $pdvVenda->venda_id])) {
                $map['venda:'.(int) $pdvVenda->venda_id] = $doc;
            }
        }

        return $map;
    }

    public static function nfceNumeroSerie(mixed $numero, mixed $serie): ?string
    {
        $numero = self::numeroAmigavel((string) ($numero ?? ''));
        if ($numero === '' || $numero === '0') {
            return null;
        }

        $serie = self::numeroAmigavel((string) ($serie ?? ''));

        return $serie !== '' ? $numero.' Série '.$serie : $numero;
    }

    public static function numeroAmigavel(string $numero): string
    {
        $trimmed = ltrim(trim($numero), '0');

        return $trimmed !== '' ? $trimmed : trim($numero);
    }
}
