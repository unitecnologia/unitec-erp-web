<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Reports\ComissaoVendedoresReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Comissão do vendedor logado (app): alíquotas do cadastro × vendas no período.
 * Online — mesma regra do relatório ERP (à vista / a prazo).
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
        $qtd = 0;
        $itens = [];

        if ($vendedorId > 0) {
            foreach ($this->coletarVendas($vendedorId, $de, $ate) as $row) {
                $qtd++;
                $total = (float) $row['total'];
                $aPrazo = ComissaoVendedoresReport::isAPrazo($row['forma'] ?? null);

                if ($aPrazo) {
                    $totalAprazo += $total;
                    $comissao = round($total * $pctAp / 100, 2);
                    $tipo = 'aprazo';
                } else {
                    $totalAvista += $total;
                    $comissao = round($total * $pctAv / 100, 2);
                    $tipo = 'avista';
                }

                $itens[] = [
                    'data' => $row['data'],
                    'origem' => $row['origem'],
                    'cliente' => $row['cliente'],
                    'forma' => $row['forma'] ?: '—',
                    'tipo' => $tipo,
                    'total' => round($total, 2),
                    'comissao' => $comissao,
                ];
            }
        }

        usort($itens, static fn (array $a, array $b): int => strcmp((string) $b['data'], (string) $a['data']));

        $comissaoAvista = round($totalAvista * $pctAv / 100, 2);
        $comissaoAprazo = round($totalAprazo * $pctAp / 100, 2);
        $totalGeral = round($totalAvista + $totalAprazo, 2);
        $comissaoTotal = round($comissaoAvista + $comissaoAprazo, 2);

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
            'itens' => $itens,
            'periodo' => [
                'inicio' => $de->toDateString(),
                'fim' => $ate->toDateString(),
            ],
        ];
    }

    /**
     * @return list<array{data: string, origem: string, cliente: string, forma: ?string, total: float}>
     */
    private function coletarVendas(int $vendedorId, Carbon $de, Carbon $ate): array
    {
        $rows = [];
        $vendaIds = [];

        if (Schema::hasTable((new Venda)->getTable()) && Schema::hasColumn('vendas', 'vendedor_id')) {
            $query = Venda::query()
                ->with('cliente:id,nome_razao')
                ->where('vendedor_id', $vendedorId)
                ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);

            if (Schema::hasColumn('vendas', 'status')) {
                $query->where('status', Venda::STATUS_FECHADO);
            } elseif (Schema::hasColumn('vendas', 'cancelada')) {
                $query->where(fn ($w) => $w->whereNull('cancelada')->orWhere('cancelada', false));
            }

            foreach ($query->orderBy('data')->get() as $venda) {
                $vendaIds[(int) $venda->id] = true;
                $dataRaw = $venda->data;
                $dataStr = $dataRaw instanceof \DateTimeInterface
                    ? $dataRaw->format('Y-m-d')
                    : (string) $dataRaw;

                $rows[] = [
                    'data' => $dataStr,
                    'origem' => 'venda',
                    'cliente' => (string) ($venda->cliente?->nome_razao ?? '—'),
                    'forma' => $venda->forma_pagamento,
                    'total' => (float) $venda->total,
                ];
            }
        }

        if (Schema::hasTable((new PdvVenda)->getTable())) {
            $query = PdvVenda::query()
                ->with('person:id,nome_razao')
                ->where('vendedor_id', $vendedorId)
                ->where('situacao', '!=', 'C')
                ->where(function ($q) use ($de, $ate): void {
                    $q->where(function ($fechamento) use ($de, $ate): void {
                        $fechamento->whereNotNull('fechado_em')
                            ->whereDate('fechado_em', '>=', $de->toDateString())
                            ->whereDate('fechado_em', '<=', $ate->toDateString());
                    })->orWhere(function ($fallback) use ($de, $ate): void {
                        $fallback->whereNull('fechado_em')
                            ->whereDate('created_at', '>=', $de->toDateString())
                            ->whereDate('created_at', '<=', $ate->toDateString());
                    });
                });

            foreach ($query->get() as $pdv) {
                $linked = (int) ($pdv->venda_id ?? 0);
                if ($linked > 0 && isset($vendaIds[$linked])) {
                    continue;
                }

                $data = $pdv->fechado_em?->toDateString()
                    ?? $pdv->created_at?->toDateString()
                    ?? $de->toDateString();

                $rows[] = [
                    'data' => $data,
                    'origem' => 'pdv',
                    'cliente' => (string) ($pdv->person?->nome_razao ?? $pdv->vendedor_nome ?? '—'),
                    'forma' => $pdv->forma_pagamento,
                    'total' => (float) $pdv->total,
                ];
            }
        }

        if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
            $query = ForcaVendasOrder::query()
                ->with('cliente:id,nome_razao')
                ->where('vendedor_id', $vendedorId)
                ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
                ->where(function ($q) use ($de, $ate): void {
                    $q->where(function ($faturado) use ($de, $ate): void {
                        $faturado->whereNotNull('faturado_at')
                            ->whereDate('faturado_at', '>=', $de->toDateString())
                            ->whereDate('faturado_at', '<=', $ate->toDateString());
                    })->orWhere(function ($received) use ($de, $ate): void {
                        $received->whereNull('faturado_at')
                            ->whereNotNull('received_at')
                            ->whereDate('received_at', '>=', $de->toDateString())
                            ->whereDate('received_at', '<=', $ate->toDateString());
                    });
                });

            foreach ($query->get() as $order) {
                $linked = (int) ($order->venda_id ?? 0);
                if ($linked > 0 && isset($vendaIds[$linked])) {
                    continue;
                }

                $payload = is_array($order->payload) ? $order->payload : [];
                $data = $order->faturado_at?->toDateString()
                    ?? $order->received_at?->toDateString()
                    ?? $de->toDateString();

                $rows[] = [
                    'data' => $data,
                    'origem' => 'mobile',
                    'cliente' => $order->clienteNome(),
                    'forma' => $payload['forma_pagamento'] ?? null,
                    'total' => (float) $order->total,
                ];
            }
        }

        return $rows;
    }
}
