<?php

namespace App\Support\Fiscal;

/**
 * CFOPs padrão oficiais do Unitec ERP por operação fiscal.
 *
 * São apenas fallback da operação. Regras fiscais mais específicas
 * (produto, destinatário, ST, devolução vinculada, etc.) continuam
 * podendo substituir o CFOP final no motor fiscal.
 */
final class FiscalOperationDefaults
{
    /**
     * Ordem e metadados das operações exibidas em CFOP — Operações fiscais.
     *
     * @return array<string, array{
     *     label: string,
     *     estadual: ?int,
     *     interestadual: ?int,
     *     interestadual_aplicavel: bool
     * }>
     */
    public static function operacoes(): array
    {
        return [
            'venda_mercadoria' => [
                'label' => 'Venda de mercadoria',
                'estadual' => 5102,
                'interestadual' => 6102,
                'interestadual_aplicavel' => true,
            ],
            'devolucao_vendas' => [
                'label' => 'Devolução de vendas',
                'estadual' => 1202,
                'interestadual' => 2202,
                'interestadual_aplicavel' => true,
            ],
            'devolucao_compras' => [
                'label' => 'Devolução de compras',
                'estadual' => 5202,
                'interestadual' => 6202,
                'interestadual_aplicavel' => true,
            ],
            'transferencias' => [
                'label' => 'Transferências',
                'estadual' => 5152,
                'interestadual' => 6152,
                'interestadual_aplicavel' => true,
            ],
            'outras_saidas' => [
                'label' => 'Outras saídas de estoque',
                'estadual' => 5949,
                'interestadual' => 6949,
                'interestadual_aplicavel' => true,
            ],
            'entrada_futura' => [
                'label' => 'Venda para entrega futura — faturamento',
                'estadual' => 5922,
                'interestadual' => 6922,
                'interestadual_aplicavel' => true,
            ],
            'entrega_futura' => [
                'label' => 'Venda para entrega futura — entrega',
                'estadual' => 5117,
                'interestadual' => 6117,
                'interestadual_aplicavel' => true,
            ],
            'bonificacao' => [
                'label' => 'Bonificação / doação / brinde',
                'estadual' => 5910,
                'interestadual' => 6910,
                'interestadual_aplicavel' => true,
            ],
            'saida_perda' => [
                'label' => 'Saída de estoque por perda',
                'estadual' => 5927,
                'interestadual' => null,
                'interestadual_aplicavel' => false,
            ],
            // Operação genérica: sem CFOP padrão (depende da finalidade no ERP).
            'financeiro' => [
                'label' => 'Processamento Fiscal',
                'estadual' => null,
                'interestadual' => null,
                'interestadual_aplicavel' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::operacoes() as $key => $meta) {
            $labels[$key] = $meta['label'];
        }

        return $labels;
    }

    public static function defaultCfop(string $operacao, string $escopo): ?int
    {
        $meta = self::operacoes()[$operacao] ?? null;
        if ($meta === null) {
            return null;
        }

        $escopo = $escopo === 'interestadual' ? 'interestadual' : 'estadual';

        if ($escopo === 'interestadual' && ! ($meta['interestadual_aplicavel'] ?? true)) {
            return null;
        }

        $value = $meta[$escopo] ?? null;

        return is_int($value) && $value > 0 ? $value : null;
    }

    public static function interestadualAplicavel(string $operacao): bool
    {
        return (bool) (self::operacoes()[$operacao]['interestadual_aplicavel'] ?? true);
    }

    /**
     * Atributos para empresa nova (firstOrCreate). Não inclui chaves sem padrão.
     *
     * @return array<string, int>
     */
    public static function attributesForCreate(): array
    {
        $attrs = [];

        foreach (self::operacoes() as $operacao => $meta) {
            if (is_int($meta['estadual'] ?? null) && $meta['estadual'] > 0) {
                $attrs['cfop_'.$operacao.'_estadual'] = $meta['estadual'];
            }

            if (
                ($meta['interestadual_aplicavel'] ?? true)
                && is_int($meta['interestadual'] ?? null)
                && $meta['interestadual'] > 0
            ) {
                $attrs['cfop_'.$operacao.'_interestadual'] = $meta['interestadual'];
            }
        }

        return $attrs;
    }

    /**
     * Atributos para "Restaurar padrões" (substitui a configuração da empresa).
     *
     * @return array<string, int|null>
     */
    public static function attributesForRestore(): array
    {
        $attrs = [];

        foreach (self::operacoes() as $operacao => $meta) {
            $attrs['cfop_'.$operacao.'_estadual'] = is_int($meta['estadual'] ?? null) && $meta['estadual'] > 0
                ? $meta['estadual']
                : null;

            if (! ($meta['interestadual_aplicavel'] ?? true)) {
                $attrs['cfop_'.$operacao.'_interestadual'] = null;
            } else {
                $attrs['cfop_'.$operacao.'_interestadual'] = is_int($meta['interestadual'] ?? null) && $meta['interestadual'] > 0
                    ? $meta['interestadual']
                    : null;
            }
        }

        return $attrs;
    }

    public static function column(string $operacao, string $escopo): string
    {
        $escopo = $escopo === 'interestadual' ? 'interestadual' : 'estadual';

        return 'cfop_'.$operacao.'_'.$escopo;
    }
}
