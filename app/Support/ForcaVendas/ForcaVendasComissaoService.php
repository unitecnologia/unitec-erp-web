<?php

namespace App\Support\ForcaVendas;

use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Financeiro\ProdutoComissaoAdicional;
use App\Support\Erp\Reports\ComissaoVendedoresReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Comissao do vendedor logado (app): mesma base do relatorio ERP
 * "Comissao de operadores" — somente vendas fechadas.
 *
 * Cabecalho = round(base_agregada * aliquota / 100, 2) por AV/AP.
 * Linhas = rateio de centavos (maior resto) para a soma bater com o cabecalho.
 */
final class ForcaVendasComissaoService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Carbon $de, Carbon $ate): array
    {
        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy(), $de->copy()];
        }

        $vendedorId = (int) ($user->vendedor_id ?? 0);
        $vendedor = $vendedorId > 0 ? Vendedor::query()->find($vendedorId) : null;

        $pctAv = $vendedor ? (float) ($vendedor->comissao_av ?? 0) : 0.0;
        $pctAp = $vendedor ? (float) ($vendedor->comissao_ap ?? 0) : 0.0;

        $totalAvista = 0.0;
        $totalAprazo = 0.0;
        $adicionalAvista = 0.0;
        $adicionalAprazo = 0.0;
        $qtd = 0;
        /** @var list<array{data: string, origem: string, cliente: string, forma: string, tipo: string, total: float, comissao_exata: float}> $itens */
        $itens = [];

        if ($vendedorId > 0) {
            $vendas = $this->coletarVendas($vendedorId, $de, $ate);
            $adicionais = ProdutoComissaoAdicional::porVendaIds(
                array_map(static fn (array $row): int => (int) ($row['venda_id'] ?? 0), $vendas),
            );

            foreach ($vendas as $row) {
                $qtd++;
                $total = (float) $row['total'];
                $aPrazo = ComissaoVendedoresReport::isAPrazo($row['forma'] ?? null);
                $pct = $aPrazo ? $pctAp : $pctAv;
                $tipo = $aPrazo ? 'aprazo' : 'avista';
                $adicional = (float) ($adicionais[(int) ($row['venda_id'] ?? 0)] ?? 0);

                if ($aPrazo) {
                    $totalAprazo += $total;
                    $adicionalAprazo += $adicional;
                } else {
                    $totalAvista += $total;
                    $adicionalAvista += $adicional;
                }

                $itens[] = [
                    'data' => $row['data'],
                    'origem' => $row['origem'],
                    'cliente' => $row['cliente'],
                    'forma' => $row['forma'] ?: '—',
                    'tipo' => $tipo,
                    'total' => round($total, 2),
                    'comissao_exata' => $total * $pct / 100 + $adicional,
                ];
            }
        }

        // Oficial ERP: comissão padrão do vendedor + adicional dos produtos (Paga Comissão).
        $comissaoAvista = round($totalAvista * $pctAv / 100 + $adicionalAvista, 2);
        $comissaoAprazo = round($totalAprazo * $pctAp / 100 + $adicionalAprazo, 2);
        $totalGeral = round($totalAvista + $totalAprazo, 2);
        $comissaoTotal = round($comissaoAvista + $comissaoAprazo, 2);

        $this->aplicarRateioCentavos($itens, 'avista', $comissaoAvista);
        $this->aplicarRateioCentavos($itens, 'aprazo', $comissaoAprazo);

        usort($itens, static fn (array $a, array $b): int => strcmp((string) $b['data'], (string) $a['data']));

        $saida = [];
        foreach ($itens as $item) {
            $saida[] = [
                'data' => $item['data'],
                'origem' => $item['origem'],
                'cliente' => $item['cliente'],
                'forma' => $item['forma'],
                'tipo' => $item['tipo'],
                'total' => $item['total'],
                'comissao' => $item['comissao'],
            ];
        }

        return [
            'vendedor_nome' => $vendedor?->nome,
            'comissao_av' => $pctAv,
            'comissao_ap' => $pctAp,
            'qtd' => $qtd,
            'total_avista' => round($totalAvista, 2),
            'total_aprazo' => round($totalAprazo, 2),
            'total_geral' => $totalGeral,
            'comissao_avista' => $comissaoAvista,
            'comissao_aprazo' => $comissaoAprazo,
            'comissao_total' => $comissaoTotal,
            'itens' => $saida,
            'periodo' => [
                'inicio' => $de->toDateString(),
                'fim' => $ate->toDateString(),
            ],
        ];
    }

    /**
     * Hamilton / maior resto: floor em centavos + distribui residual
     * nas linhas com maior parte fracionaria (empate: indice menor).
     *
     * @param  list<array<string, mixed>>  $itens
     */
    private function aplicarRateioCentavos(array &$itens, string $tipo, float $alvoReais): void
    {
        $indices = [];
        foreach ($itens as $i => $item) {
            if (($item['tipo'] ?? '') === $tipo) {
                $indices[] = $i;
            }
        }

        if ($indices === []) {
            return;
        }

        $alvoCentavos = (int) round($alvoReais * 100);
        $centavos = [];
        $restos = [];

        foreach ($indices as $i) {
            $exata = (float) $itens[$i]['comissao_exata'];
            $emCentavos = $exata * 100;
            $floor = (int) floor($emCentavos + 1e-9);
            $centavos[$i] = $floor;
            $restos[$i] = $emCentavos - $floor;
        }

        $soma = array_sum($centavos);
        $faltam = $alvoCentavos - $soma;

        if ($faltam !== 0) {
            // Maior resto primeiro; empate: indice crescente (deterministico).
            $ordenado = array_keys($restos);
            usort($ordenado, static function (int $ia, int $ib) use ($restos): int {
                $cmp = $restos[$ib] <=> $restos[$ia];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $ia <=> $ib;
            });

            if ($faltam > 0) {
                for ($n = 0; $n < $faltam && $n < count($ordenado); $n++) {
                    $centavos[$ordenado[$n]]++;
                }
            } else {
                // Soma dos floors passou do alvo (raro): retira de menor resto.
                $ordenadoAsc = array_reverse($ordenado);
                $remover = -$faltam;
                for ($n = 0; $n < $remover && $n < count($ordenadoAsc); $n++) {
                    $idx = $ordenadoAsc[$n];
                    if ($centavos[$idx] > 0) {
                        $centavos[$idx]--;
                    }
                }
            }
        }

        foreach ($indices as $i) {
            $itens[$i]['comissao'] = round($centavos[$i] / 100, 2);
            unset($itens[$i]['comissao_exata']);
        }
    }

    /**
     * Mesma fonte do relatorio ERP: vendas fechadas por data e vendedor.
     *
     * @return list<array{venda_id: int, data: string, origem: string, cliente: string, forma: ?string, total: float}>
     */
    private function coletarVendas(int $vendedorId, Carbon $de, Carbon $ate): array
    {
        $rows = [];

        if (! Schema::hasTable((new Venda)->getTable()) || ! Schema::hasColumn('vendas', 'vendedor_id')) {
            return $rows;
        }

        $query = Venda::query()
            ->with('cliente:id,nome_razao')
            ->where('vendedor_id', $vendedorId)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);

        if (Schema::hasColumn('vendas', 'status')) {
            $query->where('status', Venda::STATUS_FECHADO);
        } elseif (Schema::hasColumn('vendas', 'cancelada')) {
            $query->where(fn ($w) => $w->whereNull('cancelada')->orWhere('cancelada', false));
        }

        foreach ($query->orderBy('data')->orderBy('id')->get() as $venda) {
            $dataRaw = $venda->data;
            $dataStr = $dataRaw instanceof \DateTimeInterface
                ? $dataRaw->format('Y-m-d')
                : (string) $dataRaw;

            $rows[] = [
                'venda_id' => (int) $venda->id,
                'data' => $dataStr,
                'origem' => 'venda',
                'cliente' => (string) ($venda->cliente?->nome_razao ?? '—'),
                'forma' => $venda->forma_pagamento,
                'total' => (float) $venda->total,
            ];
        }

        return $rows;
    }
}