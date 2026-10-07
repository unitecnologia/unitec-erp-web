<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Support\Erp\ErpTimezone;

/**
 * Pagamento registrado no faturamento das OS de uma NFS-e (só leitura; não vai no XML).
 */
final class NfsePagamentosOs
{
    /**
     * @param  list<int>  $osIds
     * @return array{linhas: list<array{os: string, forma: string, parcela: string, vencimento: string, valor: string}>, avisos: list<string>}
     */
    public static function porOsIds(array $osIds): array
    {
        $osIds = array_values(array_unique(array_filter(array_map('intval', $osIds), fn (int $id): bool => $id > 0)));

        if ($osIds === []) {
            return ['linhas' => [], 'avisos' => []];
        }

        $ordens = OrdemServico::query()
            ->whereIn('id', $osIds)
            ->orderBy('numero')
            ->get(['id', 'numero', 'faturamento_pagamentos', 'data_termino', 'total_geral', 'total_servicos']);

        $linhas = [];
        $avisos = [];

        foreach ($ordens as $ordem) {
            $numero = (string) ($ordem->numero ?: $ordem->id);
            $pagamentos = is_array($ordem->faturamento_pagamentos) ? $ordem->faturamento_pagamentos : [];
            $dataOs = $ordem->data_termino ? ErpTimezone::toLocal($ordem->data_termino)->format('d/m/Y') : '—';

            if ($pagamentos === []) {
                $avisos[] = 'OS nº '.$numero.': sem pagamento registrado no faturamento.';

                continue;
            }

            foreach ($pagamentos as $pagamento) {
                if (! is_array($pagamento)) {
                    continue;
                }

                $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8') ?: '—';
                $parcelas = is_array($pagamento['parcelas'] ?? null) ? array_values($pagamento['parcelas']) : [];

                if ($parcelas === []) {
                    $linhas[] = [
                        'os' => $numero,
                        'forma' => $forma,
                        'parcela' => 'À vista',
                        'vencimento' => $dataOs,
                        'valor' => self::moeda($pagamento['valor'] ?? 0),
                    ];

                    continue;
                }

                $total = count($parcelas);

                foreach ($parcelas as $i => $parcela) {
                    $linhas[] = [
                        'os' => $numero,
                        'forma' => $forma,
                        'parcela' => ($i + 1).'/'.$total,
                        'vencimento' => (string) ($parcela['vencimento'] ?? '—'),
                        'valor' => self::moeda($parcela['valor'] ?? 0),
                    ];
                }
            }

            if (bccomp((string) ($ordem->total_geral ?? 0), (string) ($ordem->total_servicos ?? 0), 2) === 1) {
                $avisos[] = 'OS nº '.$numero.': o pagamento cobre o total da OS (R$ '
                    .self::moeda($ordem->total_geral).'), que inclui peças; a NFS-e é só dos serviços.';
            }
        }

        return ['linhas' => $linhas, 'avisos' => $avisos];
    }

    /**
     * @return list<int>
     */
    public static function osIdsDaNfse(Nfse $nfse): array
    {
        $nfse->loadMissing('itens');

        $ids = [(int) ($nfse->ordem_servico_id ?? 0)];

        foreach ($nfse->itens as $item) {
            $ids[] = (int) ($item->ordem_servico_id ?? 0);
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /**
     * Linhas para a impressão: "DINHEIRO - À vista - 06/10/2026".
     *
     * @return list<string>
     */
    public static function linhasImpressao(Nfse $nfse): array
    {
        $linhas = self::porOsIds(self::osIdsDaNfse($nfse))['linhas'];

        return array_map(
            fn (array $linha): string => $linha['forma'].' - '.$linha['parcela'].' - Venc. '.$linha['vencimento'],
            $linhas,
        );
    }

    private static function moeda(mixed $valor): string
    {
        $texto = str_replace(',', '.', trim((string) ($valor ?? '0')));

        return number_format(is_numeric($texto) ? (float) $texto : 0.0, 2, ',', '.');
    }
}
