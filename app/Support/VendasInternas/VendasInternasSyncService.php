<?php

namespace App\Support\VendasInternas;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use App\Models\User;
use App\Models\VendasInternasOrder;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendasInternasSyncService
{
    private const HISTORICO_DIAS = 120;

    /**
     * @return array<string, mixed>
     */
    public function buildPull(?Carbon $since, ?int $vendedorId = null): array
    {
        return [
            'server_time' => now()->toIso8601String(),
            'since' => $since?->toIso8601String(),
            'products' => $this->products($since),
            'price_tables' => $this->priceTables($since),
            'price_table_items' => $this->priceTableItems($since),
            'customers' => $this->customers($since),
            'vendedores' => $this->vendedores(),
            'pedidos' => $this->pedidos($since, $vendedorId),
        ];
    }

    public function pullSignature(?int $vendedorId = null): string
    {
        $parts = ['vendedor:'.($vendedorId ?? 0)];

        foreach ([
            'products' => Product::query(),
            'price_tables' => PriceTable::query(),
            'price_table_items' => ProductPriceTableItem::query(),
            'people' => Person::query()->where('is_cliente', true),
            'vendedores' => Vendedor::query(),
            'vendas_internas_orders' => VendasInternasOrder::query(),
        ] as $label => $query) {
            $table = $query->getModel()->getTable();
            $count = (clone $query)->count();
            $max = Schema::hasColumn($table, 'updated_at')
                ? (string) (clone $query)->max('updated_at')
                : '';
            $parts[] = "{$label}:{$count}:{$max}";
        }

        return sha1(implode('|', $parts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function applyPush(array $orders, User $user): array
    {
        $results = [];

        foreach ($orders as $order) {
            $uuid = (string) ($order['uuid'] ?? '');

            if ($uuid === '') {
                $results[] = ['uuid' => null, 'status' => 'erro', 'erro' => 'UUID ausente.'];

                continue;
            }

            $existing = VendasInternasOrder::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                $results[] = $this->orderPushResult($existing, duplicado: true);

                continue;
            }

            $results[] = $this->createOrder($uuid, $order, $user);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(?Carbon $since): array
    {
        return $this->applySince(Product::query(), $since, 'products')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'codigo_barras' => $p->codigo_barras,
                'descricao' => $p->descricao,
                'unidade' => $p->unidade,
                'marca' => $p->marca,
                'grupo' => $p->grupo,
                'preco_venda' => (float) $p->preco_venda,
                'preco_venda_prazo' => (float) $p->preco_venda_prazo,
                'preco_atacado' => (float) $p->preco_atacado,
                'qtd_atacado' => (float) $p->qtd_atacado,
                'estoque' => (float) $p->estoque,
                'usa_tab_preco' => (bool) $p->usa_tab_preco,
                'mostrar_no_app' => (bool) $p->mostrar_no_app,
                'promo_preco_venda' => (float) $p->promo_preco_venda,
                'promo_data_inicio' => optional($p->promo_data_inicio)->toDateString(),
                'promo_data_fim' => optional($p->promo_data_fim)->toDateString(),
                'foto_url' => $this->fotoApp($p),
                'ativo' => (bool) $p->ativo,
                'updated_at' => optional($p->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    private function fotoApp(Product $p): ?string
    {
        if (blank($p->foto_path)) {
            return null;
        }

        $url = route('vendasinternas.produto.foto', ['product' => $p->id], false);
        $version = optional($p->updated_at)->timestamp;

        return $version ? $url.'?v='.$version : $url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priceTables(?Carbon $since): array
    {
        return $this->applySince(PriceTable::query(), $since, 'price_tables')
            ->orderBy('id')
            ->get()
            ->map(fn (PriceTable $t): array => [
                'id' => $t->id,
                'codigo' => $t->codigo,
                'descricao' => $t->descricao,
                'ativo' => (bool) $t->ativo,
                'updated_at' => optional($t->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priceTableItems(?Carbon $since): array
    {
        return $this->applySince(ProductPriceTableItem::query(), $since, 'product_price_table_items')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductPriceTableItem $i): array => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'price_table_id' => $i->price_table_id,
                'valor' => (float) $i->valor,
                'fator' => (float) $i->fator,
                'updated_at' => optional($i->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(?Carbon $since): array
    {
        return $this->applySince(
            Person::query()->where('is_cliente', true),
            $since,
            'people',
        )
            ->with('tabelaPrazo:id,dias')
            ->orderBy('id')
            ->get()
            ->map(fn (Person $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nome_razao' => $c->nome_razao,
                'apelido_fantasia' => $c->apelido_fantasia,
                'cpf_cnpj' => $c->cpf_cnpj,
                'endereco' => $c->endereco,
                'numero' => $c->numero,
                'bairro' => $c->bairro,
                'cidade_nome' => $c->cidade_nome,
                'uf' => $c->uf,
                'cep' => $c->cep,
                'email' => $c->email,
                'fone1' => $c->fone1,
                'celular1' => $c->celular1,
                'whatsapp' => $c->whatsapp,
                'limite_credito' => (float) $c->limite_credito,
                'dia_pgto' => $c->dia_pgto,
                'forma_pagamento_id' => $c->forma_pagamento_id,
                'tabela_prazo_id' => $c->tabela_prazo_id,
                'tabela_prazo_dias' => $c->tabelaPrazo?->dias,
                'ativo' => (bool) $c->ativo,
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendedores(): array
    {
        return Vendedor::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (Vendedor $v): array => [
                'id' => $v->id,
                'codigo' => $v->codigo,
                'nome' => $v->nome,
                'ativo' => (bool) $v->ativo,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pedidos(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = VendasInternasOrder::query()
            ->with([
                'orcamento.itens.product:id,descricao',
                'venda:id,numero',
            ])
            ->where('received_at', '>=', now()->subDays(self::HISTORICO_DIAS));

        if ($vendedorId !== null) {
            $query->where('vendedor_id', $vendedorId);
        }

        return $this->applySince($query, $since, 'vendas_internas_orders')
            ->orderByDesc('received_at')
            ->limit(2000)
            ->get()
            ->map(function (VendasInternasOrder $order): array {
                $payload = is_array($order->payload) ? $order->payload : [];
                $orcamento = $order->orcamento;

                $itens = $orcamento?->itens
                    ->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'descricao' => $item->descricao ?: $item->product?->descricao,
                        'quantidade' => (float) $item->quantidade,
                        'preco_unitario' => (float) $item->preco_unitario,
                        'desconto' => (float) ($item->desconto ?? 0),
                    ])
                    ->values()
                    ->all() ?? [];

                return [
                    'uuid' => $order->uuid,
                    'numero' => $orcamento?->numero,
                    'numero_pedido' => $order->venda?->numero,
                    'situacao' => $order->situacao,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'cliente_id' => $order->cliente_id,
                    'cliente_nome' => $order->clienteNome(),
                    'observacoes' => $orcamento?->observacoes,
                    'desconto_valor' => (float) ($orcamento?->desconto_valor ?? 0),
                    'itens' => $itens,
                    'data' => optional($order->dataAberturaAt())->toDateString(),
                    'created_at' => optional($order->client_created_at ?? $order->received_at)->toIso8601String(),
                    'updated_at' => optional($order->updated_at)->toIso8601String(),
                    'pago_at' => optional($order->pago_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPushResult(VendasInternasOrder $order, bool $duplicado = false): array
    {
        $order->loadMissing(['orcamento:id,numero', 'venda:id,numero']);

        return [
            'uuid' => $order->uuid,
            'status' => $order->status,
            'situacao' => $order->situacao,
            'orcamento_id' => $order->orcamento_id,
            'numero' => $order->orcamento?->numero,
            'numero_pedido' => $order->venda?->numero,
            'total' => (float) $order->total,
            'duplicado' => $duplicado,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function createOrder(string $uuid, array $order, User $user): array
    {
        try {
            return DB::transaction(function () use ($uuid, $order, $user): array {
                $clienteId = (int) ($order['cliente_id'] ?? 0);

                if ($clienteId <= 0 || ! Person::query()->whereKey($clienteId)->exists()) {
                    throw new \RuntimeException('Cliente inválido ou não encontrado.');
                }

                $itens = is_array($order['itens'] ?? null) ? $order['itens'] : [];

                if ($itens === []) {
                    throw new \RuntimeException('Orçamento sem itens.');
                }

                $subtotal = 0.0;
                $descontoValor = (float) ($order['desconto_valor'] ?? 0);
                $clientCreatedAt = isset($order['created_at']) ? Carbon::parse($order['created_at']) : null;
                $dataPedido = $clientCreatedAt
                    ? ErpTimezone::toLocal($clientCreatedAt)->toDateString()
                    : ErpTimezone::toLocal()->toDateString();

                $orcamento = Orcamento::query()->create([
                    'numero' => Orcamento::nextNumero(),
                    'data' => $dataPedido,
                    'cliente_id' => $clienteId,
                    'vendedor_id' => $user->vendedor_id,
                    'subtotal' => 0,
                    'percentual_desconto' => (float) ($order['percentual_desconto'] ?? 0),
                    'desconto_valor' => $descontoValor,
                    'forma_pagamento' => $order['forma_pagamento'] ?? null,
                    'validade_dias' => (int) ($order['validade_dias'] ?? 0),
                    'observacoes' => $order['observacoes'] ?? null,
                    'total' => 0,
                    'status' => Orcamento::STATUS_ABERTO,
                ]);

                $linha = 1;

                foreach ($itens as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);

                    if ($productId <= 0 || ! Product::query()->whereKey($productId)->exists()) {
                        throw new \RuntimeException('Produto inválido no item '.$linha.'.');
                    }

                    $quantidade = (float) ($item['quantidade'] ?? 0);
                    $preco = (float) ($item['preco_unitario'] ?? 0);
                    $descItem = (float) ($item['desconto'] ?? 0);
                    $totalItem = round(($quantidade * $preco) - $descItem, 2);
                    $subtotal += $totalItem;

                    OrcamentoItem::query()->create([
                        'orcamento_id' => $orcamento->id,
                        'item' => $linha,
                        'product_id' => $productId,
                        'product_grade_id' => $item['product_grade_id'] ?? null,
                        'quantidade' => $quantidade,
                        'preco_unitario' => $preco,
                        'total' => $totalItem,
                        'desconto' => $descItem,
                        'descricao' => $item['descricao'] ?? null,
                    ]);

                    $linha++;
                }

                $total = round($subtotal - $descontoValor, 2);

                $orcamento->update([
                    'subtotal' => $subtotal,
                    'total' => $total,
                ]);

                $viOrder = VendasInternasOrder::query()->create([
                    'uuid' => $uuid,
                    'device_uuid' => $order['device_uuid'] ?? null,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'cliente_id' => $clienteId,
                    'vendedor_id' => $user->vendedor_id,
                    'orcamento_id' => $orcamento->id,
                    'venda_id' => null,
                    'total' => $total,
                    'status' => VendasInternasOrder::STATUS_IMPORTADO,
                    'situacao' => VendasInternasOrder::SITUACAO_AGUARDANDO,
                    'payload' => $order,
                    'client_created_at' => $clientCreatedAt,
                    'received_at' => now(),
                ]);

                return array_merge(
                    $this->orderPushResult($viOrder),
                    ['orcamento_id' => $orcamento->id],
                );
            });
        } catch (\Throwable $e) {
            VendasInternasOrder::query()->updateOrCreate(
                ['uuid' => $uuid],
                [
                    'device_uuid' => $order['device_uuid'] ?? null,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'cliente_id' => $order['cliente_id'] ?? null,
                    'vendedor_id' => $user->vendedor_id,
                    'total' => 0,
                    'status' => VendasInternasOrder::STATUS_ERRO,
                    'situacao' => VendasInternasOrder::SITUACAO_CANCELADO,
                    'erro' => $e->getMessage(),
                    'payload' => $order,
                    'received_at' => now(),
                ],
            );

            return [
                'uuid' => $uuid,
                'status' => VendasInternasOrder::STATUS_ERRO,
                'erro' => $e->getMessage(),
            ];
        }
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function applySince($query, ?Carbon $since, string $table)
    {
        if ($since !== null && Schema::hasColumn($table, 'updated_at')) {
            $query->where('updated_at', '>', $since);
        }

        return $query;
    }
}
