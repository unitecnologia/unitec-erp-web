<?php

namespace App\Support\Erp\Reports;

use App\Models\ForcaVendasOrder;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class MonitorPedidosReport
{
    public const ORD_ALFABETICA = 'alfabetica';

    public const ORD_CODIGO = 'codigo';

    public const ORD_QUANTIDADE = 'quantidade';

    /**
     * @return array<string, string>
     */
    public static function ordenacaoLabels(): array
    {
        return [
            self::ORD_ALFABETICA => 'Alfabética',
            self::ORD_CODIGO => 'Código',
            self::ORD_QUANTIDADE => 'Quantidade',
        ];
    }

    public static function normalizeOrdenacao(?string $ordenacao): string
    {
        return array_key_exists((string) $ordenacao, self::ordenacaoLabels())
            ? (string) $ordenacao
            : self::ORD_ALFABETICA;
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<array<string, mixed>>
     */
    public static function buildBlocos(
        array $orderIds,
        ?int $empresaId = null,
        bool $impValorLiquido = false,
        ?string $ordenacao = null,
    ): array {
        $ordenacao = self::normalizeOrdenacao($ordenacao);
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        if ($orderIds === []) {
            return [];
        }

        $orders = ForcaVendasOrder::query()
            ->whereIn('id', $orderIds)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->with([
                'cliente',
                'vendedor',
                'pedido.cliente',
                'pedido.vendedor',
                'pedido.itens' => fn ($q) => $q->orderBy('item')->with('product:id,codigo,descricao,unidade'),
                'venda.cliente',
                'venda.vendedor',
                'venda.itens.product:id,codigo,descricao,unidade',
            ])
            ->get()
            ->keyBy('id');

        $blocos = [];

        foreach ($orderIds as $id) {
            /** @var ForcaVendasOrder|null $order */
            $order = $orders->get($id);

            if (! $order) {
                continue;
            }

            $bloco = self::blocoFromOrder($order, $impValorLiquido, $ordenacao);

            if ($bloco !== null) {
                $blocos[] = $bloco;
            }
        }

        return $blocos;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function blocoFromOrder(
        ForcaVendasOrder $order,
        bool $impValorLiquido = false,
        string $ordenacao = self::ORD_ALFABETICA,
    ): ?array {
        $pedido = $order->pedido;
        $venda = $order->venda;

        if ($pedido instanceof Pedido) {
            return self::blocoFromPedido($order, $pedido, $impValorLiquido, $ordenacao);
        }

        if ($venda instanceof Venda) {
            return self::blocoFromVenda($venda, $order, $impValorLiquido, $ordenacao);
        }

        return null;
    }

    /**
     * @param  list<int>  $vendaIds
     * @param  array<int, string>  $cargaNumeroByVendaId
     * @return list<array<string, mixed>>
     */
    public static function buildBlocosFromVendas(
        array $vendaIds,
        ?int $empresaId = null,
        array $cargaNumeroByVendaId = [],
        bool $impValorLiquido = false,
        ?string $ordenacao = null,
    ): array {
        $ordenacao = self::normalizeOrdenacao($ordenacao);
        $vendaIds = array_values(array_unique(array_filter(array_map('intval', $vendaIds))));

        if ($vendaIds === []) {
            return [];
        }

        $vendas = Venda::query()
            ->whereIn('id', $vendaIds)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->with([
                'cliente',
                'vendedor',
                'itens.product:id,codigo,descricao,unidade',
            ])
            ->get()
            ->keyBy('id');

        $ordersByVenda = ForcaVendasOrder::query()
            ->whereIn('venda_id', $vendaIds)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->with('pedido:id,observacoes')
            ->get()
            ->keyBy(static fn (ForcaVendasOrder $order): int => (int) $order->venda_id);

        $blocos = [];

        foreach ($vendaIds as $id) {
            /** @var Venda|null $venda */
            $venda = $vendas->get($id);

            if (! $venda) {
                continue;
            }

            /** @var ForcaVendasOrder|null $order */
            $order = $ordersByVenda->get($id);
            $bloco = self::blocoFromVenda($venda, $order, $impValorLiquido, $ordenacao);
            $cargaNumero = trim((string) ($cargaNumeroByVendaId[$id] ?? ''));

            if ($cargaNumero !== '') {
                $bloco['carga'] = $cargaNumero;
            }

            $blocos[] = $bloco;
        }

        return $blocos;
    }

    /**
     * @return array<string, mixed>
     */
    private static function blocoFromPedido(
        ForcaVendasOrder $order,
        Pedido $pedido,
        bool $impValorLiquido = false,
        string $ordenacao = self::ORD_ALFABETICA,
    ): array {
        $cliente = $pedido->cliente ?? $order->cliente;
        $vendedor = $pedido->vendedor ?? $order->vendedor;

        $itens = $pedido->itens
            ->map(static function (PedidoItem $item) use ($impValorLiquido): array {
                $qtd = (float) $item->quantidade;
                $unitario = (float) $item->preco_unitario;
                $desconto = (float) ($item->desconto ?? 0);
                $subtotal = (float) ($item->total ?: round(($qtd * $unitario) - $desconto, 2));
                $descricao = filled($item->descricao)
                    ? (string) $item->descricao
                    : (string) ($item->product?->descricao ?: 'PRODUTO');

                if ($impValorLiquido) {
                    $unitario = $qtd > 0 ? round($subtotal / $qtd, 2) : 0.0;
                    $desconto = 0.0;
                }

                return [
                    'codigo' => (string) ($item->product?->codigo ?: '—'),
                    'produto' => $descricao,
                    'unidade' => (string) ($item->product?->unidade ?: 'UN'),
                    'quantidade' => $qtd,
                    'valor_unitario' => $unitario,
                    'desconto' => $desconto,
                    'subtotal' => $subtotal,
                ];
            })
            ->values()
            ->all();

        $itens = self::ordenarItensImpressao($itens, $ordenacao);

        // Mesmo critério da coluna "Nº Pedido" do Monitor: venda.numero; DAV só se vazio.
        $numeroFonte = $order->venda?->numero;
        if (! filled($numeroFonte)) {
            $numeroFonte = $pedido->numero;
        }
        $numero = self::formatNumero($numeroFonte !== null ? (string) $numeroFonte : null);
        $data = $pedido->data?->format('d/m/Y') ?? '';

        return self::montarBloco(
            pedidoLabel: $numero.($data !== '' ? ' - '.$data : ''),
            cliente: $cliente,
            clienteFallback: [
                'nome' => (string) ($pedido->cliente_nome ?: $order->clienteNome()),
                'documento' => (string) ($pedido->cliente_cpf_cnpj ?: ''),
                'endereco' => trim(implode(', ', array_filter([
                    (string) ($pedido->cliente_endereco ?? ''),
                    (string) ($pedido->cliente_numero ?? ''),
                ]))),
                'bairro' => (string) ($pedido->cliente_bairro ?? ''),
                'cep' => (string) ($pedido->cliente_cep ?? ''),
                'municipio' => (string) ($pedido->cliente_cidade ?? ''),
                'uf' => (string) ($pedido->cliente_uf ?? ''),
                'fone' => (string) ($pedido->cliente_fone ?: ($pedido->cliente_whatsapp ?? '')),
                'fantasia' => '',
                'obs' => '',
                'ie' => '',
            ],
            vendedor: $vendedor,
            condicaoPagamento: self::formatCondicaoPagamentoComParcelas(
                forma: (string) ($pedido->forma_pagamento ?: ($order->payload['forma_pagamento'] ?? '')),
                order: $order,
                baseDate: $pedido->data,
            ),
            obsPedido: (string) ($pedido->observacoes ?: (is_array($order->payload) ? ($order->payload['observacoes'] ?? '') : '')),
            itens: $itens,
            status: self::statusFromOrder($order),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function blocoFromVenda(
        Venda $venda,
        ?ForcaVendasOrder $order = null,
        bool $impValorLiquido = false,
        string $ordenacao = self::ORD_ALFABETICA,
    ): array {
        $cliente = $venda->cliente ?? $order?->cliente;
        $vendedor = $venda->vendedor ?? $order?->vendedor;

        $itens = $venda->itens
            ->map(static function (VendaItem $item) use ($impValorLiquido): array {
                $qtd = (float) $item->quantidade;
                $unitario = (float) $item->valor_item;
                $desconto = (float) ($item->desconto ?? 0);
                $subtotal = (float) ($item->total ?: round(($qtd * $unitario) - $desconto, 2));

                if ($impValorLiquido) {
                    $unitario = $qtd > 0 ? round($subtotal / $qtd, 2) : 0.0;
                    $desconto = 0.0;
                }

                return [
                    'codigo' => (string) ($item->product?->codigo ?: '—'),
                    'produto' => (string) ($item->product?->descricao ?: 'PRODUTO'),
                    'unidade' => (string) ($item->product?->unidade ?: 'UN'),
                    'quantidade' => $qtd,
                    'valor_unitario' => $unitario,
                    'desconto' => $desconto,
                    'subtotal' => $subtotal,
                ];
            })
            ->values()
            ->all();

        $itens = self::ordenarItensImpressao($itens, $ordenacao);
        $numero = self::formatNumero($venda->numero);
        $data = $venda->data?->format('d/m/Y') ?? '';

        return self::montarBloco(
            pedidoLabel: $numero.($data !== '' ? ' - '.$data : ''),
            cliente: $cliente,
            clienteFallback: [
                'nome' => $order?->clienteNome() ?: 'CONSUMIDOR',
                'documento' => '',
                'endereco' => '',
                'bairro' => '',
                'cep' => '',
                'municipio' => '',
                'uf' => '',
                'fone' => '',
                'fantasia' => '',
                'obs' => '',
                'ie' => '',
            ],
            vendedor: $vendedor,
            condicaoPagamento: self::formatCondicaoPagamentoComParcelas(
                forma: (string) ($venda->forma_pagamento ?: ($order?->payload['forma_pagamento'] ?? '')),
                order: $order,
                baseDate: $venda->data,
            ),
            obsPedido: (string) ($order?->pedido?->observacoes ?: (is_array($order?->payload) ? ($order->payload['observacoes'] ?? '') : '')),
            itens: $itens,
            status: $order ? self::statusFromOrder($order) : self::statusFromVenda($venda),
        );
    }

    /**
     * Ordenação só para impressão (mesmas regras do resumo de produtos do romaneio).
     *
     * @param  list<array{codigo: string, produto: string, unidade: string, quantidade: float, valor_unitario: float, desconto: float, subtotal: float}>  $itens
     * @return list<array{codigo: string, produto: string, unidade: string, quantidade: float, valor_unitario: float, desconto: float, subtotal: float}>
     */
    private static function ordenarItensImpressao(array $itens, string $ordenacao): array
    {
        $ordenacao = self::normalizeOrdenacao($ordenacao);
        $collection = collect($itens);

        $sorted = match ($ordenacao) {
            self::ORD_QUANTIDADE => $collection->sortByDesc('quantidade'),
            self::ORD_CODIGO => $collection->sortBy('codigo', SORT_NATURAL | SORT_FLAG_CASE),
            default => $collection->sortBy('produto', SORT_NATURAL | SORT_FLAG_CASE),
        };

        return $sorted->values()->all();
    }

    /**
     * Monta "BOLETO 30 dias 25/09/2026" ou "BOLETO 2x — 30 dias 25/09/2026 · 60 dias 25/10/2026".
     */
    private static function formatCondicaoPagamentoComParcelas(
        string $forma,
        ?ForcaVendasOrder $order,
        mixed $baseDate,
    ): string {
        $forma = trim($forma);
        $dias = self::parcelasDiasFromOrder($order);

        if ($dias === []) {
            return $forma;
        }

        try {
            $base = $baseDate
                ? Carbon::parse($baseDate)->startOfDay()
                : Carbon::today();
        } catch (\Throwable) {
            $base = Carbon::today();
        }

        $trechos = [];
        foreach ($dias as $dia) {
            $dia = max(0, (int) $dia);
            $venc = $base->copy()->addDays($dia)->format('d/m/Y');

            if ($dia === 0) {
                $trechos[] = 'à vista '.$venc;
                continue;
            }

            $trechos[] = $dia.' dias '.$venc;
        }

        $qtd = count($trechos);
        $detalhe = implode(' · ', $trechos);

        if ($forma === '') {
            return $qtd > 1 ? $qtd.'x — '.$detalhe : $detalhe;
        }

        if ($qtd > 1) {
            return mb_strtoupper($forma, 'UTF-8').' '.$qtd.'x — '.$detalhe;
        }

        return mb_strtoupper($forma, 'UTF-8').' '.$detalhe;
    }

    /**
     * @return list<int>
     */
    private static function parcelasDiasFromOrder(?ForcaVendasOrder $order): array
    {
        if (! $order) {
            return [];
        }

        $payload = is_array($order->payload) ? $order->payload : [];

        $canhotoDias = $payload['cartao_canhoto']['dias'] ?? null;

        if (is_array($canhotoDias) && $canhotoDias !== []) {
            $dias = collect($canhotoDias)
                ->map(static fn ($d): int => (int) $d)
                ->filter(static fn (int $d): bool => $d >= 0)
                ->values()
                ->all();

            if ($dias !== []) {
                return $dias;
            }
        }

        $avulso = self::diasDeStringParcelas((string) ($payload['condicao_pagamento'] ?? ''));

        if ($avulso !== []) {
            return $avulso;
        }

        $prazoRaw = $payload['tabela_prazo_dias'] ?? '';

        if (is_array($prazoRaw)) {
            $dias = collect($prazoRaw)
                ->map(static fn ($d): int => (int) $d)
                ->filter(static fn (int $d): bool => $d >= 0)
                ->values()
                ->all();

            return $dias;
        }

        return self::diasDeStringParcelas((string) $prazoRaw);
    }

    /**
     * @return list<int>
     */
    private static function diasDeStringParcelas(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(static fn ($d): string => trim((string) $d))
            ->filter(static fn (string $d): bool => $d !== '' && is_numeric($d))
            ->map(static fn (string $d): int => (int) $d)
            ->filter(static fn (int $d): bool => $d >= 0)
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string}
     */
    private static function statusFromOrder(ForcaVendasOrder $order): array
    {
        $key = (string) ($order->situacao ?: ForcaVendasOrder::SITUACAO_PENDENTE);

        if (! array_key_exists($key, ForcaVendasOrder::situacaoLabels())) {
            $key = ForcaVendasOrder::SITUACAO_PENDENTE;
        }

        return [
            'key' => $key,
            'label' => ForcaVendasOrder::situacaoLabels()[$key],
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    private static function statusFromVenda(Venda $venda): array
    {
        return match ((string) $venda->status) {
            Venda::STATUS_CANCELADO => [
                'key' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'label' => 'Cancelado',
            ],
            Venda::STATUS_FECHADO => [
                'key' => ForcaVendasOrder::SITUACAO_FATURADO,
                'label' => 'Faturado',
            ],
            default => [
                'key' => ForcaVendasOrder::SITUACAO_PENDENTE,
                'label' => 'Pendente',
            ],
        };
    }

    /**
     * @param  array{
     *     nome: string,
     *     documento: string,
     *     endereco: string,
     *     bairro: string,
     *     cep: string,
     *     municipio: string,
     *     uf: string,
     *     fone: string,
     *     fantasia: string,
     *     obs: string,
     *     ie: string
     * }  $clienteFallback
     * @param  list<array{codigo: string, produto: string, unidade: string, quantidade: float, valor_unitario: float, desconto: float, subtotal: float}>  $itens
     * @param  array{key: string, label: string}  $status
     * @return array<string, mixed>
     */
    private static function montarBloco(
        string $pedidoLabel,
        ?Person $cliente,
        array $clienteFallback,
        ?Vendedor $vendedor,
        string $condicaoPagamento,
        string $obsPedido,
        array $itens,
        array $status,
    ): array {
        $endereco = '';
        if ($cliente) {
            $endereco = trim(implode(', ', array_filter([
                trim((string) ($cliente->endereco ?? '')),
                trim((string) ($cliente->numero ?? '')),
                filled($cliente->complemento) ? trim((string) $cliente->complemento) : null,
            ])));
        }

        $foneCliente = '';
        if ($cliente) {
            $foneCliente = (string) (
                $cliente->fone1
                ?: $cliente->celular1
                ?: $cliente->whatsapp
                ?: $cliente->fone2
                ?: $cliente->celular2
                ?: ''
            );
        }

        $represNome = trim((string) ($vendedor?->nome ?? ''));
        $represFone = trim((string) ($vendedor?->telefone ?? ''));
        $repres = $represNome;
        if ($represFone !== '') {
            $repres = $represNome !== '' ? $represNome.' - '.$represFone : $represFone;
        }

        $qtdTotal = array_sum(array_column($itens, 'quantidade'));
        $valorTotal = array_sum(array_column($itens, 'subtotal'));

        return [
            'cliente' => (string) ($cliente?->nome_razao ?: $clienteFallback['nome'] ?: 'CONSUMIDOR'),
            'fantasia' => (string) ($cliente?->apelido_fantasia ?: $clienteFallback['fantasia']),
            'endereco' => $endereco !== '' ? $endereco : $clienteFallback['endereco'],
            'municipio' => (string) ($cliente?->cidade_nome ?: $clienteFallback['municipio']),
            'bairro' => (string) ($cliente?->bairro ?: $clienteFallback['bairro']),
            'cep' => (string) ($cliente?->cep ?: $clienteFallback['cep']),
            'uf' => (string) ($cliente?->uf ?: $clienteFallback['uf']),
            'fone' => $foneCliente !== '' ? $foneCliente : $clienteFallback['fone'],
            'documento' => (string) ($cliente?->cpf_cnpj ?: $clienteFallback['documento']),
            'ie' => (string) ($cliente?->rg_ie ?: ($clienteFallback['ie'] ?? '')),
            'obs' => (string) ($cliente?->observacoes ?: $clienteFallback['obs']),
            'repres' => $repres,
            'pedido' => $pedidoLabel,
            'condicao_pagamento' => $condicaoPagamento,
            'obs_pedido' => $obsPedido,
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'itens' => $itens,
            'qtd_total' => (float) $qtdTotal,
            'valor_total' => (float) $valorTotal,
        ];
    }

    public static function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '0';
        }

        $trimmed = ltrim((string) $numero, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function formatQuantidade(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return number_format($value, 2, ',', '.');
        }

        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, ForcaVendasOrder>
     */
    public static function ordersPrintaveis(array $orderIds, ?int $empresaId = null): Collection
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        if ($orderIds === []) {
            return collect();
        }

        return ForcaVendasOrder::query()
            ->whereIn('id', $orderIds)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNotNull('pedido_id')->where('pedido_id', '>', 0);
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('venda_id')->where('venda_id', '>', 0);
                });
            })
            ->get()
            ->sortBy(fn (ForcaVendasOrder $o) => array_search((int) $o->id, $orderIds, true))
            ->values();
    }
}
