<?php

namespace App\Support\Erp\Reports\LucratividadePorVenda;

use App\Support\Erp\Dre\VendaItemCustoHistorico;
use App\Support\Erp\Tef\TefMoney;

/**
 * D2.6 por venda — cobre itens da venda e devoluções vinculadas.
 *
 * Não mistura custo cadastral atual. Sem captura / indisponível = lucro indisponível.
 */
final class LucratividadeVendaCustoResolver
{
    public const LABEL_INDISPONIVEL = 'Indisponível';

    /**
     * @param  list<object|array<string, mixed>>  $itensVenda  campos: custo_situacao, custo_total
     * @param  list<object|array<string, mixed>>  $itensDevolucao  idem (somente devoluções finalizadas)
     * @return array{
     *   disponivel: bool,
     *   custo_bruto: ?string,
     *   custo_devolucoes: string,
     *   custo_liquido: ?string,
     *   pendencia: string
     * }
     */
    public static function resolver(array $itensVenda, array $itensDevolucao = []): array
    {
        $venda = self::agregar($itensVenda);
        $dev = self::agregar($itensDevolucao);

        if (! $venda['completo']) {
            return [
                'disponivel' => false,
                'custo_bruto' => null,
                'custo_devolucoes' => $dev['custo'],
                'custo_liquido' => null,
                'pendencia' => $venda['pendencia'],
            ];
        }

        // Há devolução finalizada com mercadoria sem custo confiável → não inventa líquido.
        if ($itensDevolucao !== [] && ! $dev['completo']) {
            // Devolução só de serviços (nao_aplicavel) ou vazia de elegíveis com ok=0 e indisp/sem=0:
            // "completo" false com elegiveis=0 e nao_apl=0 significa lista vazia de itens — se a
            // devolução existe sem itens, não dá para validar CMV da devolução.
            if ($dev['elegiveis'] > 0 || ($dev['nao_aplicavel'] === 0 && $dev['ok'] === 0)) {
                return [
                    'disponivel' => false,
                    'custo_bruto' => $venda['custo'],
                    'custo_devolucoes' => $dev['custo'],
                    'custo_liquido' => null,
                    'pendencia' => $dev['pendencia'] !== ''
                        ? 'Devolução sem custo histórico completo: '.$dev['pendencia']
                        : 'Devolução sem itens de custo histórico confiável.',
                ];
            }
        }

        $liquido = TefMoney::sub($venda['custo'], $dev['custo']);
        if (TefMoney::cmp($liquido, '0') === -1) {
            $liquido = '0.00';
        }

        return [
            'disponivel' => true,
            'custo_bruto' => $venda['custo'],
            'custo_devolucoes' => $dev['custo'],
            'custo_liquido' => $liquido,
            'pendencia' => '',
        ];
    }

    /**
     * @param  list<object|array<string, mixed>>  $itens
     * @return array{
     *   completo: bool,
     *   custo: string,
     *   ok: int,
     *   indisponivel: int,
     *   sem_captura: int,
     *   nao_aplicavel: int,
     *   elegiveis: int,
     *   pendencia: string
     * }
     */
    public static function agregar(array $itens): array
    {
        $ok = 0;
        $indisp = 0;
        $sem = 0;
        $naoApl = 0;
        $custo = '0.00';

        foreach ($itens as $item) {
            $sit = self::campo($item, 'custo_situacao');
            $total = self::campo($item, 'custo_total');

            if ($sit === null || $sit === '') {
                $sem++;

                continue;
            }
            if ($sit === VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL) {
                $naoApl++;

                continue;
            }
            if ($sit === VendaItemCustoHistorico::SITUACAO_OK && $total !== null && $total !== '') {
                $ok++;
                $custo = TefMoney::add($custo, (string) $total);

                continue;
            }
            $indisp++;
        }

        $elegiveis = $ok + $indisp + $sem;
        $completo = false;

        if ($elegiveis > 0) {
            $completo = $indisp === 0 && $sem === 0 && $ok > 0;
        } elseif ($naoApl > 0) {
            // Só serviços: CMV 0 disponível (mesma regra D2.6).
            $completo = true;
            $custo = '0.00';
        } elseif ($itens === []) {
            // Sem itens: não há base confiável.
            $completo = false;
        }

        $pendencia = $completo
            ? ''
            : ($sem > 0
                ? 'Há itens sem captura de custo (vendas anteriores à D2.6).'
                : ($indisp > 0
                    ? 'Há itens com custo indisponível no fechamento.'
                    : 'Sem itens de mercadoria com custo histórico.'));

        return [
            'completo' => $completo,
            'custo' => $custo,
            'ok' => $ok,
            'indisponivel' => $indisp,
            'sem_captura' => $sem,
            'nao_aplicavel' => $naoApl,
            'elegiveis' => $elegiveis,
            'pendencia' => $pendencia,
        ];
    }

    private static function campo(object|array $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
