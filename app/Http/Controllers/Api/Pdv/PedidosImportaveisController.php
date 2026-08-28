<?php

namespace App\Http\Controllers\Api\Pdv;

use App\Models\ForcaVendasOrder;
use App\Models\Terminal;
use App\Models\Venda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvImportarPedidoQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos da retaguarda sem documento fiscal — listagem/detalhe para o PDV offline.
 * Inclui vendas tipo pedido e DAVs Força pendentes (orcamento ainda sem venda).
 */
class PedidosImportaveisController
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $terminal = $this->terminal($request);
        $numero = trim((string) $request->query('numero', ''));
        $dataDe = $this->parseDate($request->query('data_de'));
        $dataAte = $this->parseDate($request->query('data_ate'));

        $query = new PdvImportarPedidoQuery(
            numero: $numero,
            dataDe: $dataDe,
            dataAte: $dataAte,
            empresaId: $empresaId,
            terminal: $terminal,
        );

        $vendas = $query->build()
            ->limit(100)
            ->get()
            ->map(fn (Venda $venda): array => $this->mapVendaListRow($venda))
            ->all();

        $forca = $this->listarForcaPendentes($empresaId, $numero, $dataDe, $dataAte);

        $pedidos = collect($vendas)
            ->concat($forca)
            ->sortByDesc(fn (array $row): string => ($row['data_iso'] ?? '').'-'.($row['sort_id'] ?? 0))
            ->take(100)
            ->map(function (array $row): array {
                unset($row['data_iso'], $row['sort_id']);

                return $row;
            })
            ->values()
            ->all();

        return response()->json(['pedidos' => $pedidos]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $origem = strtolower(trim((string) $request->query('origem', 'venda')));

        if ($origem === 'forca') {
            return $this->showForca($request, $id);
        }

        return $this->showVenda($request, $id);
    }

    private function showVenda(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $terminal = $this->terminal($request);

        $venda = (new PdvImportarPedidoQuery(
            empresaId: $empresaId,
            terminal: $terminal,
            somenteSemDocumentoFiscal: false,
        ))->build()
            ->with(['itens.product', 'cliente:id,nome_razao', 'vendedor:id,nome'])
            ->whereKey($id)
            ->first();

        if (! $venda) {
            return response()->json(['message' => 'Pedido indisponível para importação.'], 404);
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            return response()->json(['message' => 'Pedido cancelado não pode ser importado.'], 422);
        }

        if (! Venda::query()->whereKey($venda->id)->semDocumentoFiscalEmitido()->exists()) {
            return response()->json(['message' => 'Pedido já possui documento fiscal emitido.'], 422);
        }

        if ($venda->itens->isEmpty()) {
            return response()->json(['message' => 'Pedido sem itens cadastrados.'], 422);
        }

        $itens = $venda->itens->map(function ($item): array {
            $product = $item->product;

            return [
                'product_id' => (int) ($item->product_id ?? 0),
                'codigo' => (string) ($product?->codigo ?? ''),
                'descricao' => mb_strtoupper((string) ($product?->descricao ?? 'ITEM'), 'UTF-8'),
                'unidade' => (string) ($product?->unidade ?? 'UN'),
                'quantidade' => (float) $item->quantidade,
                'preco' => (float) $item->valor_item,
                'ativo' => (bool) ($product?->ativo ?? false),
                'usa_imei' => (bool) ($product?->usa_imei ?? false),
                'is_grade' => (bool) ($product?->is_grade ?? false),
            ];
        })->values()->all();

        return response()->json([
            'origem' => 'venda',
            'venda_id' => $venda->id,
            'forca_order_id' => null,
            'orcamento_id' => null,
            'numero' => $venda->numero,
            'data' => $venda->data?->format('d/m/Y') ?? '',
            'total' => ErpMoney::formatBr($venda->total),
            'forma' => $this->formaLabel($venda->forma_pagamento ?? null),
            'cancelado' => false,
            'cliente' => [
                'id' => $venda->cliente?->id,
                'nome' => mb_strtoupper((string) ($venda->cliente?->nome_razao ?? 'CONSUMIDOR FINAL'), 'UTF-8'),
            ],
            'vendedor' => $venda->vendedor
                ? [
                    'id' => $venda->vendedor->id,
                    'nome' => mb_strtoupper((string) $venda->vendedor->nome, 'UTF-8'),
                ]
                : null,
            'vendedor_nome' => filled($venda->vendedor_nome)
                ? mb_strtoupper((string) $venda->vendedor_nome, 'UTF-8')
                : null,
            'itens' => $itens,
        ]);
    }

    private function showForca(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $orderQuery = ForcaVendasOrder::query()
            ->with([
                'orcamento.itens.product',
                'orcamento.cliente:id,nome_razao',
                'vendedor:id,nome',
            ])
            ->whereKey($id)
            ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
            ->where('situacao', ForcaVendasOrder::SITUACAO_PENDENTE)
            ->whereNull('venda_id');

        if ($empresaId > 0 && Schema::hasColumn('forca_vendas_orders', 'empresa_id')) {
            $orderQuery->where(function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        $order = $orderQuery->first();
        $orcamento = $order?->orcamento;

        if (! $order || ! $orcamento || $orcamento->itens->isEmpty()) {
            return response()->json(['message' => 'Pedido indisponível para importação.'], 404);
        }

        $itens = $orcamento->itens->map(function ($item): array {
            $product = $item->product;
            $descricao = trim((string) ($item->descricao ?? ''));
            if ($descricao === '') {
                $descricao = (string) ($product?->descricao ?? 'ITEM');
            }

            return [
                'product_id' => (int) ($item->product_id ?? 0),
                'codigo' => (string) ($product?->codigo ?? ''),
                'descricao' => mb_strtoupper($descricao, 'UTF-8'),
                'unidade' => (string) ($product?->unidade ?? 'UN'),
                'quantidade' => (float) $item->quantidade,
                'preco' => (float) $item->preco_unitario,
                'ativo' => (bool) ($product?->ativo ?? false),
                'usa_imei' => (bool) ($product?->usa_imei ?? false),
                'is_grade' => (bool) ($product?->is_grade ?? false),
            ];
        })->values()->all();

        $forma = $this->formaForca($order, $orcamento);
        $data = $order->dataAberturaAt() ?? $orcamento->created_at;

        return response()->json([
            'origem' => 'forca',
            'venda_id' => null,
            'forca_order_id' => $order->id,
            'orcamento_id' => $orcamento->id,
            'numero' => $orcamento->numero,
            'data' => $data?->format('d/m/Y') ?? '',
            'total' => ErpMoney::formatBr($orcamento->total ?? $order->total),
            'forma' => $forma,
            'cancelado' => false,
            'cliente' => [
                'id' => $orcamento->cliente?->id ?? $order->cliente_id,
                'nome' => mb_strtoupper((string) ($orcamento->cliente?->nome_razao ?? $order->clienteNome()), 'UTF-8'),
            ],
            'vendedor' => $order->vendedor
                ? [
                    'id' => $order->vendedor->id,
                    'nome' => mb_strtoupper((string) $order->vendedor->nome, 'UTF-8'),
                ]
                : null,
            'vendedor_nome' => $order->vendedor
                ? mb_strtoupper((string) $order->vendedor->nome, 'UTF-8')
                : null,
            'itens' => $itens,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarForcaPendentes(int $empresaId, string $numero, ?string $dataDe, ?string $dataAte): array
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            return [];
        }

        $query = ForcaVendasOrder::query()
            ->with(['orcamento.cliente:id,nome_razao', 'vendedor:id,nome'])
            ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
            ->where('situacao', ForcaVendasOrder::SITUACAO_PENDENTE)
            ->whereNull('venda_id')
            ->whereHas('orcamento.itens')
            ->orderByDesc('id')
            ->limit(100);

        if ($empresaId > 0 && Schema::hasColumn('forca_vendas_orders', 'empresa_id')) {
            $query->where(function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        if ($numero !== '') {
            $like = '%'.$numero.'%';
            $query->whereHas('orcamento', function ($q) use ($like, $numero): void {
                $q->where('numero', 'like', $like);
                if (ctype_digit($numero)) {
                    $q->orWhere('numero', ltrim($numero, '0') ?: '0');
                }
            });
        }

        if ($dataDe !== null || $dataAte !== null) {
            $query->whereHas('orcamento', function ($q) use ($dataDe, $dataAte): void {
                if ($dataDe !== null) {
                    $q->whereDate('created_at', '>=', $dataDe);
                }
                if ($dataAte !== null) {
                    $q->whereDate('created_at', '<=', $dataAte);
                }
            });
        }

        return $query->get()
            ->map(fn (ForcaVendasOrder $order): array => $this->mapForcaListRow($order))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVendaListRow(Venda $venda): array
    {
        $dataIso = $venda->data?->toDateString() ?? '';

        return [
            'origem' => 'venda',
            'venda_id' => $venda->id,
            'forca_order_id' => null,
            'numero' => $venda->numero,
            'cliente' => mb_strtoupper($venda->cliente?->nome_razao ?? '—', 'UTF-8'),
            'data' => $venda->data?->format('d/m/Y') ?? '',
            'data_iso' => $dataIso,
            'sort_id' => $venda->id,
            'forma' => $this->formaLabel($venda->forma_pagamento ?? null),
            'total' => ErpMoney::formatBr($venda->total),
            'cancelado' => $venda->status === Venda::STATUS_CANCELADO,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapForcaListRow(ForcaVendasOrder $order): array
    {
        $orcamento = $order->orcamento;
        $data = $order->dataAberturaAt() ?? $orcamento?->created_at;
        $forma = $this->formaForca($order, $orcamento);

        return [
            'origem' => 'forca',
            'venda_id' => null,
            'forca_order_id' => $order->id,
            'numero' => $orcamento?->numero ?? ('#'.$order->id),
            'cliente' => mb_strtoupper((string) ($orcamento?->cliente?->nome_razao ?? $order->clienteNome()), 'UTF-8'),
            'data' => $data?->format('d/m/Y') ?? '',
            'data_iso' => $data?->toDateString() ?? '',
            'sort_id' => $order->id,
            'forma' => $forma,
            'total' => ErpMoney::formatBr($orcamento?->total ?? $order->total),
            'cancelado' => false,
        ];
    }

    private function formaForca(ForcaVendasOrder $order, mixed $orcamento): string
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $forma = trim((string) ($payload['forma_pagamento'] ?? ''));
        if ($forma === '' && $orcamento !== null) {
            $forma = trim((string) ($orcamento->forma_pagamento ?? ''));
        }

        return $this->formaLabel($forma);
    }

    private function formaLabel(mixed $forma): string
    {
        $forma = trim((string) ($forma ?? ''));

        return $forma !== '' ? mb_strtoupper($forma, 'UTF-8') : '—';
    }

    private function terminal(Request $request): ?Terminal
    {
        $terminal = $request->attributes->get('pdv_terminal');

        return $terminal instanceof Terminal ? $terminal : null;
    }

    private function empresaId(Request $request): int
    {
        return (int) ($request->attributes->get('pdv_empresa_id')
            ?: $request->query('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 1);
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
