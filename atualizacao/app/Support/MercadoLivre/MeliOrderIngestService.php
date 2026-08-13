<?php

namespace App\Support\MercadoLivre;

use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MeliOrderIngestService
{
    /** @var list<string> */
    private const INGEST_STATUSES = [
        'paid',
        'confirmed',
    ];

    public function __construct(
        private readonly MeliApiClient $apiClient,
    ) {}

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function ingestNotification(string $topic, string $resource, string $meliUserId): array
    {
        $empresa = $this->resolveEmpresaByMeliUserId($meliUserId);

        if (! $empresa) {
            return [
                'ok' => false,
                'message' => 'Nenhuma empresa conectada ao usuário ML '.$meliUserId.'.',
            ];
        }

        if (! $empresa->param_meli_habilitar) {
            return [
                'ok' => false,
                'message' => 'Integração Mercado Livre desabilitada para a empresa.',
            ];
        }

        $orderId = $this->resolveOrderIdFromNotification($topic, $resource, $empresa);

        if ($orderId === null) {
            return [
                'ok' => true,
                'message' => 'Notificação ignorada (tópico/recurso sem pedido).',
            ];
        }

        return $this->ingestOrder($empresa, $orderId);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function ingestOrder(Empresa $empresa, string|int $orderId): array
    {
        $meliOrderId = trim((string) $orderId);

        if ($meliOrderId === '') {
            return [
                'ok' => false,
                'message' => 'ID do pedido ML ausente.',
            ];
        }

        $existing = ForcaVendasOrder::query()
            ->where('meli_order_id', $meliOrderId)
            ->first();

        if ($existing && $existing->status !== ForcaVendasOrder::STATUS_ERRO) {
            return [
                'ok' => true,
                'message' => 'Pedido ML já importado.',
                'data' => [
                    'forca_vendas_order_id' => $existing->id,
                    'duplicado' => true,
                ],
            ];
        }

        if ($existing && $existing->status === ForcaVendasOrder::STATUS_ERRO) {
            $existing->delete();
        }

        $accessToken = $this->apiClient->accessTokenForEmpresa($empresa);

        if ($accessToken === null) {
            return [
                'ok' => false,
                'message' => 'Token Mercado Livre indisponível para a empresa.',
            ];
        }

        $orderResult = $this->apiClient->getOrder($accessToken, $meliOrderId);

        if (! $orderResult['ok'] || ! is_array($orderResult['data'])) {
            Log::warning('meli.order.fetch_failed', [
                'empresa_id' => $empresa->id,
                'meli_order_id' => $meliOrderId,
                'message' => $orderResult['message'] ?? 'erro',
            ]);

            return [
                'ok' => false,
                'message' => $orderResult['message'] ?? 'Falha ao buscar pedido no Mercado Livre.',
            ];
        }

        $meliOrder = $orderResult['data'];
        $status = strtolower((string) ($meliOrder['status'] ?? ''));

        if (! in_array($status, self::INGEST_STATUSES, true)) {
            return [
                'ok' => true,
                'message' => 'Pedido ML ignorado (status '.$status.').',
                'data' => ['meli_order_id' => $meliOrderId, 'status' => $status],
            ];
        }

        try {
            return DB::transaction(function () use ($empresa, $meliOrder, $meliOrderId): array {
                return $this->createOrderFromMeliPayload($empresa, $meliOrder, $meliOrderId);
            });
        } catch (\Throwable $e) {
            Log::error('meli.order.ingest_failed', [
                'empresa_id' => $empresa->id,
                'meli_order_id' => $meliOrderId,
                'error' => $e->getMessage(),
            ]);

            $uuid = $this->uuidFromMeliOrderId($meliOrderId);

            ForcaVendasOrder::query()->updateOrCreate(
                ['meli_order_id' => $meliOrderId],
                [
                    'uuid' => $uuid,
                    'empresa_id' => $empresa->id,
                    'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
                    'total' => 0,
                    'status' => ForcaVendasOrder::STATUS_ERRO,
                    'erro' => $e->getMessage(),
                    'payload' => [
                        'origem' => 'mercado_livre',
                        'meli_order_id' => $meliOrderId,
                        'meli_raw' => $meliOrder,
                    ],
                    'received_at' => now(),
                ],
            );

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $meliOrder
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    private function createOrderFromMeliPayload(Empresa $empresa, array $meliOrder, string $meliOrderId): array
    {
        $mappedItems = $this->mapOrderItems($meliOrder);
        $linkedItems = array_values(array_filter($mappedItems, fn (array $item): bool => ($item['product_id'] ?? 0) > 0));
        $pendingItems = array_values(array_filter($mappedItems, fn (array $item): bool => ($item['product_id'] ?? 0) <= 0));

        if ($linkedItems === []) {
            throw new \RuntimeException('Nenhum item do pedido ML vinculado por SKU/código no ERP.');
        }

        $cliente = $this->resolveBuyerPerson($meliOrder);
        $clientCreatedAt = $this->parseMeliDate($meliOrder['date_created'] ?? null) ?? now();
        $momentoLocal = ErpTimezone::toLocal($clientCreatedAt);

        $subtotal = 0.0;
        foreach ($linkedItems as $item) {
            $subtotal += (float) $item['total'];
        }

        $total = round((float) ($meliOrder['total_amount'] ?? $subtotal), 2);
        $buyer = is_array($meliOrder['buyer'] ?? null) ? $meliOrder['buyer'] : [];
        $nickname = trim((string) ($buyer['nickname'] ?? ''));
        $identificacao = 'ML #'.$meliOrderId.($nickname !== '' ? ' — '.$nickname : '');

        $orcamento = Orcamento::query()->create([
            'numero' => Orcamento::nextNumero(),
            'data' => $momentoLocal->toDateString(),
            'hora' => $momentoLocal->format('H:i:s'),
            'cliente_id' => $cliente->id,
            'vendedor_id' => null,
            'subtotal' => $subtotal,
            'percentual_desconto' => 0,
            'desconto_valor' => max(0, round($subtotal - $total, 2)),
            'forma_pagamento' => 'Mercado Livre',
            'validade_dias' => 0,
            'observacoes' => 'Pedido Mercado Livre #'.$meliOrderId,
            'total' => $total,
            'status' => Orcamento::STATUS_ABERTO,
            'plataforma' => Orcamento::PLATAFORMA_MELI,
        ]);

        $linha = 1;

        foreach ($linkedItems as $item) {
            OrcamentoItem::query()->create([
                'orcamento_id' => $orcamento->id,
                'item' => $linha,
                'product_id' => (int) $item['product_id'],
                'product_grade_id' => null,
                'quantidade' => (float) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'total' => (float) $item['total'],
                'desconto' => 0,
                'descricao' => $item['descricao'] ?? null,
            ]);

            $linha++;
        }

        $payload = [
            'origem' => 'mercado_livre',
            'meli_order_id' => $meliOrderId,
            'meli_status' => (string) ($meliOrder['status'] ?? ''),
            'meli_buyer_id' => (string) ($buyer['id'] ?? ''),
            'meli_buyer_nickname' => $nickname,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'forma_pagamento' => 'Mercado Livre',
            'created_at' => $clientCreatedAt->toIso8601String(),
            'itens' => $linkedItems,
            'itens_pendentes_vinculo' => $pendingItems,
            'meli_raw' => $meliOrder,
        ];

        $fvOrder = ForcaVendasOrder::query()->create([
            'uuid' => $this->uuidFromMeliOrderId($meliOrderId),
            'meli_order_id' => $meliOrderId,
            'device_uuid' => null,
            'user_id' => null,
            'empresa_id' => $empresa->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'vendedor_id' => null,
            'orcamento_id' => $orcamento->id,
            'venda_id' => null,
            'total' => $total,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'identificacao' => Str::limit($identificacao, 60, ''),
            'payload' => $payload,
            'client_created_at' => $clientCreatedAt,
            'received_at' => now(),
        ]);

        try {
            $reservaUser = $this->resolveReservaUser($empresa);

            if ($reservaUser) {
                (new EstoqueReservaService())->reservarPedido($fvOrder, $orcamento, $reservaUser);
            }
        } catch (\Throwable $e) {
            Log::warning('meli.order.reserva_failed', [
                'forca_vendas_order_id' => $fvOrder->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(\App\Support\Gestor\GestorPushService::class)->notifyPedidoPendente($fvOrder);
        } catch (\Throwable) {
            // Push não deve quebrar a ingestão.
        }

        if ($pendingItems !== []) {
            Log::info('meli.order.items_pending_link', [
                'forca_vendas_order_id' => $fvOrder->id,
                'meli_order_id' => $meliOrderId,
                'pending_count' => count($pendingItems),
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Pedido ML importado.',
            'data' => [
                'forca_vendas_order_id' => $fvOrder->id,
                'orcamento_id' => $orcamento->id,
                'meli_order_id' => $meliOrderId,
                'pending_items' => count($pendingItems),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meliOrder
     * @return list<array<string, mixed>>
     */
    private function mapOrderItems(array $meliOrder): array
    {
        $rows = is_array($meliOrder['order_items'] ?? null) ? $meliOrder['order_items'] : [];
        $mapped = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = is_array($row['item'] ?? null) ? $row['item'] : [];
            $sku = trim((string) ($item['seller_sku'] ?? $item['seller_custom_field'] ?? ''));
            $title = trim((string) ($item['title'] ?? 'Item Mercado Livre'));
            $quantidade = (float) ($row['quantity'] ?? 0);
            $precoUnitario = (float) ($row['unit_price'] ?? 0);
            $total = round($quantidade * $precoUnitario, 2);
            $productId = $this->resolveProductIdBySku($sku);

            $mapped[] = [
                'product_id' => $productId,
                'meli_item_id' => (string) ($item['id'] ?? ''),
                'sku' => $sku,
                'descricao' => $title,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoUnitario,
                'total' => $total,
            ];
        }

        return $mapped;
    }

    private function resolveProductIdBySku(string $sku): ?int
    {
        $sku = trim($sku);

        if ($sku === '') {
            return null;
        }

        $product = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($sku): void {
                $query->where('codigo', $sku)
                    ->orWhere('referencia', $sku)
                    ->orWhere('codigo_barras', $sku)
                    ->orWhere('codigo_barras_caixa', $sku);
            })
            ->first();

        return $product?->id;
    }

    /**
     * @param  array<string, mixed>  $meliOrder
     */
    private function resolveBuyerPerson(array $meliOrder): Person
    {
        $buyer = is_array($meliOrder['buyer'] ?? null) ? $meliOrder['buyer'] : [];
        $firstName = trim((string) ($buyer['first_name'] ?? ''));
        $lastName = trim((string) ($buyer['last_name'] ?? ''));
        $nickname = trim((string) ($buyer['nickname'] ?? ''));
        $nome = trim($firstName.' '.$lastName);

        if ($nome === '') {
            $nome = $nickname !== '' ? $nickname : 'Comprador Mercado Livre';
        }

        $billing = is_array($meliOrder['billing_info'] ?? null) ? $meliOrder['billing_info'] : [];
        $doc = preg_replace('/\D/', '', (string) ($billing['doc_number'] ?? ''));

        if ($doc !== '') {
            $existing = Person::query()
                ->where('cpf_cnpj', $doc)
                ->where('is_cliente', true)
                ->first();

            if ($existing) {
                return $existing;
            }

            return Person::query()->create([
                'codigo' => Person::nextCodigo(),
                'pessoa_tipo' => strlen($doc) > 11 ? Person::PESSOA_JURIDICA : Person::PESSOA_FISICA,
                'nome_razao' => $nome,
                'cpf_cnpj' => $doc,
                'is_cliente' => true,
                'ativo' => true,
            ]);
        }

        $consumidor = Person::query()
            ->whereIn('codigo', Person::codigosConsumidorFinal())
            ->where('is_cliente', true)
            ->first();

        if ($consumidor) {
            return $consumidor;
        }

        return Person::query()->create([
            'codigo' => Person::CODIGO_CONSUMIDOR_FINAL,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => $nome,
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }

    private function resolveEmpresaByMeliUserId(string $meliUserId): ?Empresa
    {
        $meliUserId = trim($meliUserId);

        if ($meliUserId === '') {
            return null;
        }

        return Empresa::query()
            ->where('param_meli_habilitar', true)
            ->where('param_meli_user_id', $meliUserId)
            ->first();
    }

    private function resolveOrderIdFromNotification(string $topic, string $resource, Empresa $empresa): ?string
    {
        $topic = strtolower(trim($topic));
        $resource = trim($resource);

        if ($resource === '') {
            return null;
        }

        if ($topic === 'orders' || str_contains($resource, '/orders/')) {
            return $this->extractResourceId($resource, 'orders');
        }

        if ($topic === 'shipments' || str_contains($resource, '/shipments/')) {
            $shipmentId = $this->extractResourceId($resource, 'shipments');

            if ($shipmentId === null) {
                return null;
            }

            $token = $this->apiClient->accessTokenForEmpresa($empresa);

            if ($token === null) {
                return null;
            }

            $shipment = $this->apiClient->getShipment($token, $shipmentId);

            if (! $shipment['ok'] || ! is_array($shipment['data'])) {
                return null;
            }

            $orderId = (string) ($shipment['data']['order_id'] ?? '');

            return $orderId !== '' ? $orderId : null;
        }

        return null;
    }

    private function extractResourceId(string $resource, string $segment): ?string
    {
        if (preg_match('#/'.preg_quote($segment, '#').'/(\d+)#', $resource, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function uuidFromMeliOrderId(string $meliOrderId): string
    {
        $hash = md5('meli-order-'.$meliOrderId);

        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function resolveReservaUser(Empresa $empresa): ?User
    {
        return User::query()
            ->where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderBy('id')
            ->first();
    }

    private function parseMeliDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
