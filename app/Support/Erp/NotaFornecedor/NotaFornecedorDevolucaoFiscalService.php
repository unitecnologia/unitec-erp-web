<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\CompraItem;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Models\Empresa;
use App\Models\NotaFornecedorItem;
use DomainException;

/**
 * Regras de quantidade e rateio fiscal para devolução de compra a partir do snapshot da NF de entrada.
 *
 * ICMS/PIS/COFINS respeitam o CRT do emitente da devolução; valores monetários
 * proporcionais vêm do snapshot da nota original (não recalcular como venda nova).
 */
final class NotaFornecedorDevolucaoFiscalService
{
    public function __construct(
        private readonly NotaFornecedorFiscalSnapshotParser $parser = new NotaFornecedorFiscalSnapshotParser(),
    ) {}

    public function resolveNotaItem(?CompraItem $compraItem): ?NotaFornecedorItem
    {
        if ($compraItem === null) {
            return null;
        }

        $compraItem->loadMissing('notaFornecedorItem');

        return $compraItem->notaFornecedorItem;
    }

    /**
     * qtd_original = nota_fornecedor_itens.quantidade (quando houver vínculo).
     * Fallback: quantidade do compra_item.
     */
    public function quantidadeOriginal(CompraItem|NotaFornecedorItem $item): float
    {
        if ($item instanceof NotaFornecedorItem) {
            return round((float) $item->quantidade, 4);
        }

        $notaItem = $this->resolveNotaItem($item);

        if ($notaItem) {
            return round((float) $notaItem->quantidade, 4);
        }

        return round((float) $item->quantidade, 4);
    }

    public function quantidadeJaDevolvida(CompraItem|NotaFornecedorItem $item, ?int $excetoDevolucaoId = null): float
    {
        if ($item instanceof NotaFornecedorItem) {
            return $item->quantidadeJaDevolvida($excetoDevolucaoId);
        }

        $notaItem = $this->resolveNotaItem($item);

        if ($notaItem) {
            return $notaItem->quantidadeJaDevolvida($excetoDevolucaoId);
        }

        $query = DevolucaoCompraItem::query()
            ->where('compra_item_id', $item->id)
            ->whereHas('devolucao', function ($q) use ($excetoDevolucaoId): void {
                $q->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA);

                if ($excetoDevolucaoId) {
                    $q->where('id', '!=', $excetoDevolucaoId);
                }
            });

        return round((float) $query->sum('qtd'), 4);
    }

    public function quantidadeDisponivel(CompraItem|NotaFornecedorItem $item, ?int $excetoDevolucaoId = null): float
    {
        $original = $this->quantidadeOriginal($item);
        $ja = $this->quantidadeJaDevolvida($item, $excetoDevolucaoId);

        return round(max(0, $original - $ja), 4);
    }

    /**
     * @throws DomainException
     */
    public function assertQuantidadePermitida(
        CompraItem|NotaFornecedorItem $item,
        float $qtdSolicitada,
        ?int $excetoDevolucaoId = null,
    ): void {
        $qtdSolicitada = round($qtdSolicitada, 4);

        if ($qtdSolicitada <= 0) {
            return;
        }

        $original = $this->quantidadeOriginal($item);
        $ja = $this->quantidadeJaDevolvida($item, $excetoDevolucaoId);
        $disponivel = round(max(0, $original - $ja), 4);

        if ($qtdSolicitada > $disponivel + 0.00005) {
            throw new DomainException(
                sprintf(
                    'Quantidade devolvida (%.4f) excede o disponível (%.4f). Original da nota: %.4f; já devolvido: %.4f.',
                    $qtdSolicitada,
                    $disponivel,
                    $original,
                    $ja,
                )
            );
        }
    }

    public function emitenteEhSimples(?Empresa $empresa): bool
    {
        if ($empresa === null) {
            return true;
        }

        $regime = strtolower((string) ($empresa->regime_tributario ?? 'simples'));

        return in_array($regime, ['simples', 'mei', 'simei'], true);
    }

    /**
     * Converte snapshot rateado em campos de linha da NF-e (overrides do NfeCalculoService).
     *
     * @return array<string, mixed>
     */
    public function montarOverridesNfe(
        ?NotaFornecedorItem $notaItem,
        float $qtdDevolvida,
        ?Empresa $empresa = null,
    ): array {
        if ($notaItem === null || ! is_array($notaItem->fiscal_snapshot)) {
            return [];
        }

        $original = $this->quantidadeOriginal($notaItem);
        $snapshot = $this->parser->ratearSnapshot(
            $notaItem->fiscal_snapshot,
            $original,
            $qtdDevolvida,
        );

        $icms = is_array($snapshot['icms'] ?? null) ? $snapshot['icms'] : [];
        $ipi = is_array($snapshot['ipi'] ?? null) ? $snapshot['ipi'] : [];
        $pis = is_array($snapshot['pis'] ?? null) ? $snapshot['pis'] : [];
        $cofins = is_array($snapshot['cofins'] ?? null) ? $snapshot['cofins'] : [];
        $ibscbs = is_array($snapshot['ibscbs'] ?? null) ? $snapshot['ibscbs'] : [];

        $origemMercadoria = (int) ($snapshot['origem'] ?? $notaItem->fiscal_snapshot['origem'] ?? 0);
        $simples = $this->emitenteEhSimples($empresa);

        $overrides = [
            'devolucao_compra_fiscal' => true,
            'origem' => $origemMercadoria,
            'ncm' => $notaItem->ncm,
            'info_adicionais' => (string) ($snapshot['inf_ad_prod'] ?? $notaItem->fiscal_snapshot['inf_ad_prod'] ?? ''),
            // ICMS proporcional da operação original (estrutura depende do CRT do emitente).
            'base_icms' => round((float) ($icms['v_bc'] ?? 0), 2),
            'aliq_icms' => round((float) ($icms['p_icms'] ?? 0), 4),
            'valor_icms' => round((float) ($icms['v_icms'] ?? 0), 2),
            'p_red_bc_icms' => round((float) ($icms['p_red_bc'] ?? 0), 4),
            'mod_bc_icms' => filled($icms['mod_bc'] ?? null) ? (string) $icms['mod_bc'] : null,
            'base_icms_st' => round((float) ($icms['v_bc_st'] ?? 0), 2),
            'valor_icms_st' => round((float) ($icms['v_icms_st'] ?? 0), 2),
            // IPI: nunca inventar BC a partir do ICMS — só o que veio da entrada.
            'base_ipi' => round((float) ($ipi['v_bc'] ?? 0), 2),
            'aliq_ipi' => round((float) ($ipi['p_ipi'] ?? 0), 4),
            'valor_ipi' => round((float) ($ipi['v_ipi'] ?? 0), 2),
            'cst_ipi' => filled($ipi['cst'] ?? null) ? (string) $ipi['cst'] : null,
            'cst_ibs_cbs' => filled($ibscbs['cst'] ?? null) ? (string) $ibscbs['cst'] : null,
            'class_trib' => filled($ibscbs['c_class_trib'] ?? null) ? (string) $ibscbs['c_class_trib'] : null,
            'bc_ibs' => round((float) ($ibscbs['v_bc'] ?? 0), 2),
            'alq_ibs_uf' => round((float) ($ibscbs['p_ibs_uf'] ?? 0), 4),
            'v_ibs_uf' => round((float) ($ibscbs['v_ibs_uf'] ?? 0), 2),
            'alq_ibs_mun' => round((float) ($ibscbs['p_ibs_mun'] ?? 0), 4),
            'v_ibs_mun' => round((float) ($ibscbs['v_ibs_mun'] ?? 0), 2),
            'alq_cbs' => round((float) ($ibscbs['p_cbs'] ?? 0), 4),
            'v_cbs' => round((float) ($ibscbs['v_cbs'] ?? 0), 2),
            'p_red_ibs' => round((float) ($ibscbs['p_red_ibs'] ?? 0), 4),
            'p_red_cbs' => round((float) ($ibscbs['p_red_cbs'] ?? 0), 4),
            'cest' => filled($snapshot['cest'] ?? null)
                ? (string) $snapshot['cest']
                : ($notaItem->fiscal_snapshot['cest'] ?? null),
        ];

        if ($simples) {
            // CRT 1/4: CSOSN 900 com detalhe ICMS da entrada; sem CST do fornecedor.
            $overrides['csosn'] = '900';
            $overrides['cst'] = null;
            // PIS/COFINS do Simples: CST 99 zerados (não copiar CST 01 nem base do ICMS).
            $overrides['cst_pis'] = '99';
            $overrides['base_pis_icms'] = 0.0;
            $overrides['aliq_pis_icms'] = 0.0;
            $overrides['valor_pis_icms'] = 0.0;
            $overrides['cst_cofins'] = '99';
            $overrides['base_cofins_icms'] = 0.0;
            $overrides['aliq_cofins_icms'] = 0.0;
            $overrides['valor_cofins_icms'] = 0.0;
        } else {
            // Regime normal: mantém CST ICMS da entrada; PIS/COFINS CST 49 (saída/devolução),
            // com bases/valores rateados do snapshot (não CST 01 do fornecedor).
            $cstIcms = (string) ($icms['cst'] ?? '');
            $csosn = (string) ($icms['csosn'] ?? '');
            $overrides['cst'] = $cstIcms !== '' ? $cstIcms : null;
            $overrides['csosn'] = $csosn !== '' ? $csosn : null;
            $overrides['cst_pis'] = '49';
            $overrides['base_pis_icms'] = round((float) ($pis['v_bc'] ?? 0), 2);
            $overrides['aliq_pis_icms'] = round((float) ($pis['p_pis'] ?? 0), 4);
            $overrides['valor_pis_icms'] = round((float) ($pis['v_pis'] ?? 0), 2);
            $overrides['cst_cofins'] = '49';
            $overrides['base_cofins_icms'] = round((float) ($cofins['v_bc'] ?? 0), 2);
            $overrides['aliq_cofins_icms'] = round((float) ($cofins['p_cofins'] ?? 0), 4);
            $overrides['valor_cofins_icms'] = round((float) ($cofins['v_cofins'] ?? 0), 2);
        }

        return array_filter(
            $overrides,
            static function (mixed $v, string|int $key): bool {
                if (in_array((string) $key, ['origem', 'devolucao_compra_fiscal'], true)) {
                    return true;
                }

                return $v !== null && $v !== '';
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
