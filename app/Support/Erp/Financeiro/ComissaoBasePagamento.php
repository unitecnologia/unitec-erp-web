<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;

/**
 * Resolve a base comissionável de uma venda a partir dos meios de pagamento.
 *
 * Usa o flag `nao_pagar_comissao` do cadastro (nunca exclusão por nome fixo).
 * Em pagamento misto (pdv_venda_pagamentos), exclui só as parcelas marcadas.
 *
 * TROCA como desconto identificado (Tela de Venda): o valor já saiu do total da venda
 * via rateio nos itens. O flag decide se a parcela da troca volta à base (paga comissão)
 * ou permanece de fora — sem subtrair de novo.
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

    /**
     * Meta de TROCA rateada nos itens (Tela de Venda), indexada por venda_id.
     *
     * @param  list<int>  $vendaIds
     * @return array<int, array{valor: float, nao_pagar_comissao: bool, descricao: string}>
     */
    public static function trocaIdentificadaPorVendaIds(array $vendaIds): array
    {
        $vendaIds = array_values(array_unique(array_filter(
            array_map('intval', $vendaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($vendaIds === []
            || ! ErpSchema::hasTable((new ForcaVendasOrder)->getTable())
            || ! ErpSchema::hasColumn('forca_vendas_orders', 'venda_id')
            || ! ErpSchema::hasColumn('forca_vendas_orders', 'payload')
        ) {
            return [];
        }

        $rows = ForcaVendasOrder::query()
            ->whereIn('venda_id', $vendaIds)
            ->whereNotNull('venda_id')
            ->get(['venda_id', 'payload']);

        if ($rows->isEmpty()) {
            return [];
        }

        $formasSem = self::formasSemComissao();
        $out = [];

        foreach ($rows as $row) {
            $vid = (int) $row->venda_id;
            if ($vid <= 0 || isset($out[$vid])) {
                continue;
            }

            $payload = is_array($row->payload) ? $row->payload : [];
            $meta = is_array($payload['troca_desconto'] ?? null) ? $payload['troca_desconto'] : null;
            $valor = 0.0;
            $descricao = 'TROCA';
            $naoPagar = null;

            if ($meta !== null) {
                $valor = round((float) ($meta['valor'] ?? 0), 2);
                $descricao = trim((string) ($meta['descricao'] ?? 'TROCA'));
                if ($descricao === '') {
                    $descricao = 'TROCA';
                }
                if (array_key_exists('nao_pagar_comissao', $meta)) {
                    $naoPagar = (bool) $meta['nao_pagar_comissao'];
                }
            }

            if ($valor <= 0.009) {
                $valor = self::valorTrocaEmObservacoes((string) ($payload['observacoes'] ?? ''));
            }

            if ($valor <= 0.009) {
                continue;
            }

            if ($naoPagar === null) {
                $naoPagar = self::formaExcluiComissao($descricao, $formasSem);
            }

            $out[$vid] = [
                'valor' => $valor,
                'nao_pagar_comissao' => (bool) $naoPagar,
                'descricao' => mb_strtoupper($descricao, 'UTF-8'),
            ];
        }

        return $out;
    }

    /**
     * Aplica o flag da forma TROCA após o total já ter sido reduzido pelo rateio.
     * Não subtrai de novo quando nao_pagar_comissao; devolve o valor à base quando paga.
     */
    public static function aplicarTrocaIdentificadaNaBase(
        float $baseComissionavel,
        float $totalVendaLiquido,
        ?array $trocaMeta,
    ): float {
        $base = round(max(0.0, $baseComissionavel), 2);

        if ($trocaMeta === null) {
            return $base;
        }

        $valor = round(max(0.0, (float) ($trocaMeta['valor'] ?? 0)), 2);
        if ($valor <= 0.009) {
            return $base;
        }

        if (! empty($trocaMeta['nao_pagar_comissao'])) {
            // Total já líquido da troca — não descontar outra vez.
            return $base;
        }

        // Flag permite comissão sobre a troca: recoloca o valor na base.
        return round($base + $valor, 2);
    }

    private static function valorTrocaEmObservacoes(string $observacoes): float
    {
        if ($observacoes === '') {
            return 0.0;
        }

        if (preg_match('/TROCA:\s*R\$\s*([\d.]+,\d{2})/iu', $observacoes, $m) !== 1) {
            return 0.0;
        }

        return round(max(0.0, ErpMoney::parseBr($m[1])), 2);
    }
}
