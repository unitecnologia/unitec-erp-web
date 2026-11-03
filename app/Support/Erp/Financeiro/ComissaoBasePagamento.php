<?php

namespace App\Support\Erp\Financeiro;

use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;

/**
 * Resolve a base comissionável de uma venda a partir dos meios de pagamento.
 *
 * Usa o flag `nao_pagar_comissao` do cadastro (nunca exclusão por nome fixo).
 * Em pagamento misto (pdv_venda_pagamentos), exclui só as parcelas marcadas.
 */
final class ComissaoBasePagamento
{
    /**
     * Descrições (UPPER) das formas com "Não pagar comissão".
     *
     * @return array<string, true>
     */
    public static function formasSemComissao(): array
    {
        if (! ErpSchema::hasTable((new FormaPagamento)->getTable())
            || ! ErpSchema::hasColumn('formas_pagamento', 'nao_pagar_comissao')
        ) {
            return [];
        }

        $out = [];
        foreach (
            FormaPagamento::query()
                ->where('nao_pagar_comissao', true)
                ->get(['descricao']) as $forma
        ) {
            $key = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
            if ($key !== '') {
                $out[$key] = true;
            }
        }

        return $out;
    }

    /**
     * Pagamentos reais por venda (PDV), ignorando regularização fiscal.
     *
     * @param  list<int>  $vendaIds
     * @return array<int, list<array{forma: string, valor: float}>>
     */
    public static function pagamentosPorVendaIds(array $vendaIds): array
    {
        $vendaIds = array_values(array_unique(array_filter(
            array_map('intval', $vendaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($vendaIds === []
            || ! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            || ! ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            return [];
        }

        $rows = PdvVendaPagamento::query()
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->whereIn('pdv_vendas.venda_id', $vendaIds)
            ->whereNotNull('pdv_vendas.venda_id')
            ->where(function ($o): void {
                $o->whereNull('pdv_vendas.origem')
                    ->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
            })
            ->where('pdv_venda_pagamentos.valor', '>', 0)
            ->orderBy('pdv_venda_pagamentos.id')
            ->get([
                'pdv_vendas.venda_id',
                'pdv_venda_pagamentos.forma',
                'pdv_venda_pagamentos.valor',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $vid = (int) $row->venda_id;
            $out[$vid][] = [
                'forma' => (string) ($row->forma ?? ''),
                'valor' => (float) $row->valor,
            ];
        }

        return $out;
    }

    /**
     * Base comissionável (R$) da venda.
     *
     * @param  list<array{forma: string, valor: float}>|null  $pagamentos
     * @param  array<string, true>  $formasSemComissao
     */
    public static function baseComissionavel(
        float $totalVenda,
        ?string $formaCabecalho,
        ?array $pagamentos,
        array $formasSemComissao,
    ): float {
        $total = round(max(0.0, $totalVenda), 2);

        if ($formasSemComissao === []) {
            return $total;
        }

        if (is_array($pagamentos) && $pagamentos !== []) {
            $base = 0.0;
            foreach ($pagamentos as $pag) {
                $valor = round((float) ($pag['valor'] ?? 0), 2);
                if ($valor <= 0) {
                    continue;
                }
                if (self::formaExcluiComissao((string) ($pag['forma'] ?? ''), $formasSemComissao)) {
                    continue;
                }
                $base += $valor;
            }

            return round(min($total, max(0.0, $base)), 2);
        }

        // Sem detalhe de parcelas: aplica só quando o cabeçalho (não misto) está marcado.
        $cab = trim((string) $formaCabecalho);
        if ($cab === '') {
            return $total;
        }

        if (VendasPorFormaPagamentoAggregator::isFormaMista($cab)) {
            // Sem valores por parcela não dá para excluir só a marcada — preserva base integral.
            return $total;
        }

        if (self::formaExcluiComissao($cab, $formasSemComissao)) {
            return 0.0;
        }

        return $total;
    }

    /**
     * @param  array<string, true>  $formasSemComissao
     */
    public static function formaExcluiComissao(string $forma, array $formasSemComissao): bool
    {
        if ($formasSemComissao === []) {
            return false;
        }

        $base = mb_strtoupper(VendasPorFormaPagamentoAggregator::formaBase($forma), 'UTF-8');

        return $base !== '' && isset($formasSemComissao[$base]);
    }

    /**
     * Rateia adicional de produto na mesma proporção da base comissionável.
     */
    public static function adicionalProporcional(float $adicional, float $baseComissionavel, float $totalVenda): float
    {
        $adicional = max(0.0, $adicional);
        $total = round(max(0.0, $totalVenda), 2);
        $base = round(max(0.0, $baseComissionavel), 2);

        if ($adicional <= 0.0 || $total <= 0.0 || $base <= 0.0) {
            return 0.0;
        }

        if (abs($base - $total) < 0.0001) {
            return round($adicional, 2);
        }

        return round($adicional * ($base / $total), 2);
    }
}
