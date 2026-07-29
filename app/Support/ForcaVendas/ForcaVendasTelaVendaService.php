<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForcaVendasTelaVendaService
{
    /**
     * Situações em que o pedido ainda pode ser editado na Tela de Venda.
     *
     * @return list<string>
     */
    public static function situacoesEditaveis(): array
    {
        return [
            ForcaVendasOrder::SITUACAO_PENDENTE,
            ForcaVendasOrder::SITUACAO_FINANCEIRO,
            ForcaVendasOrder::SITUACAO_CONFIRMADO,
        ];
    }

    public function assertEditavel(ForcaVendasOrder $order): void
    {
        if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
            throw new \RuntimeException('Somente pedidos podem ser editados nesta tela.');
        }

        if (! in_array((string) $order->situacao, self::situacoesEditaveis(), true)) {
            throw new \RuntimeException('Este pedido não pode mais ser editado (situação: '.$order->situacaoLabel().').');
        }

        if ($order->venda_id) {
            throw new \RuntimeException('Pedido já faturado. Não é possível editar.');
        }
    }

    /**
     * Grava pedido novo ou atualiza um existente (mesmo ciclo: Orcamento + ForcaVendasOrder).
     *
     * @param  array{
     *   cliente_id: int,
     *   vendedor_id?: int|null,
     *   observacoes?: string|null,
     *   desconto_valor?: float,
     *   percentual_desconto?: float,
     *   forma_pagamento_id?: int|null,
     *   forma_pagamento?: string|null,
     *   tabela_prazo_dias?: list<int>|string|null,
     *   itens: list<array{product_id: int, quantidade: float, preco_unitario: float, desconto?: float, acrescimo?: float, descricao?: string|null, product_grade_id?: int|null}>
     * }  $data
     */
    public function gravarPedido(User $user, array $data, ?ForcaVendasOrder $existente = null): ForcaVendasOrder
    {
        if ($existente) {
            return $this->atualizarPedido($existente, $user, $data);
        }

        return $this->criarPedido($user, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function criarPedido(User $user, array $data): ForcaVendasOrder
    {
        [$clienteId, $itens, $vendedorId, $descontoValor, $percentualDesconto, $formaId, $formaNome] = $this->validarPayload($data, $user);

        $uuid = (string) Str::uuid();
        $momentoLocal = ErpTimezone::toLocal();

        return DB::transaction(function () use (
            $user,
            $uuid,
            $clienteId,
            $vendedorId,
            $itens,
            $descontoValor,
            $percentualDesconto,
            $formaId,
            $formaNome,
            $data,
            $momentoLocal,
        ): ForcaVendasOrder {
            $orcamento = Orcamento::query()->create([
                'numero' => Orcamento::nextNumero(),
                'data' => $momentoLocal->toDateString(),
                'hora' => $momentoLocal->format('H:i:s'),
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'subtotal' => 0,
                'percentual_desconto' => $percentualDesconto,
                'desconto_valor' => $descontoValor,
                'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
                'validade_dias' => 0,
                'observacoes' => $data['observacoes'] ?? null,
                'total' => 0,
                'status' => Orcamento::STATUS_ABERTO,
                'plataforma' => Orcamento::PLATAFORMA_FV,
            ]);

            [$subtotal, $payloadItens] = $this->gravarItens($orcamento, $itens);
            $total = round($subtotal - $descontoValor, 2);

            $orcamento->update([
                'subtotal' => round($subtotal, 2),
                'total' => $total,
            ]);

            $payload = $this->montarPayload(
                uuid: $uuid,
                clienteId: $clienteId,
                payloadItens: $payloadItens,
                descontoValor: $descontoValor,
                percentualDesconto: $percentualDesconto,
                formaId: $formaId,
                formaNome: $formaNome,
                data: $data,
                tabelaPrazo: $data['tabela_prazo_dias'] ?? null,
            );

            $fvOrder = ForcaVendasOrder::query()->create([
                'uuid' => $uuid,
                'device_uuid' => 'monitor-web',
                'user_id' => $user->id,
                'empresa_id' => $user->empresa_id,
                'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'orcamento_id' => $orcamento->id,
                'venda_id' => null,
                'total' => $total,
                'status' => ForcaVendasOrder::STATUS_IMPORTADO,
                'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
                'payload' => $payload,
                'client_created_at' => now(),
                'received_at' => now(),
            ]);

            (new EstoqueReservaService())->reservarPedido($fvOrder, $orcamento, $user);

            try {
                app(\App\Support\Gestor\GestorPushService::class)->notifyPedidoPendente($fvOrder);
            } catch (\Throwable) {
                // Push não deve quebrar a gravação.
            }

            return $fvOrder->fresh(['orcamento', 'cliente']) ?? $fvOrder;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function atualizarPedido(ForcaVendasOrder $order, User $user, array $data): ForcaVendasOrder
    {
        $this->assertEditavel($order);

        [$clienteId, $itens, $vendedorId, $descontoValor, $percentualDesconto, $formaId, $formaNome] = $this->validarPayload($data, $user);

        return DB::transaction(function () use (
            $order,
            $user,
            $clienteId,
            $vendedorId,
            $itens,
            $descontoValor,
            $percentualDesconto,
            $formaId,
            $formaNome,
            $data,
        ): ForcaVendasOrder {
            $order = ForcaVendasOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertEditavel($order);

            $orcamento = Orcamento::query()->lockForUpdate()->find($order->orcamento_id);

            if (! $orcamento) {
                throw new \RuntimeException('Orçamento do pedido não encontrado.');
            }

            $reserva = new EstoqueReservaService();
            $reserva->liberarPedido($order);

            OrcamentoItem::query()->where('orcamento_id', $orcamento->id)->delete();

            [$subtotal, $payloadItens] = $this->gravarItens($orcamento, $itens);
            $total = round($subtotal - $descontoValor, 2);

            $orcamento->update([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'percentual_desconto' => $percentualDesconto,
                'desconto_valor' => $descontoValor,
                'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
                'observacoes' => $data['observacoes'] ?? null,
                'subtotal' => round($subtotal, 2),
                'total' => $total,
                'status' => Orcamento::STATUS_ABERTO,
            ]);

            $payloadAnterior = is_array($order->payload) ? $order->payload : [];
            $tabelaPrazo = $data['tabela_prazo_dias'] ?? ($payloadAnterior['tabela_prazo_dias'] ?? null);

            if (is_array($tabelaPrazo)) {
                $tabelaPrazo = implode(',', array_map('intval', $tabelaPrazo));
            }

            $payload = $this->montarPayload(
                uuid: (string) $order->uuid,
                clienteId: $clienteId,
                payloadItens: $payloadItens,
                descontoValor: $descontoValor,
                percentualDesconto: $percentualDesconto,
                formaId: $formaId,
                formaNome: $formaNome,
                data: $data,
                tabelaPrazo: $tabelaPrazo,
            );
            $payload['origem'] = 'monitor_tela_venda_edicao';
            $payload['editado_em'] = now()->toIso8601String();
            $payload['device_uuid'] = $payloadAnterior['device_uuid'] ?? 'monitor-web';

            $order->update([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'total' => $total,
                'payload' => $payload,
                'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            ]);

            $order = $order->fresh(['orcamento', 'cliente']) ?? $order;
            $reserva->reservarPedido($order, $orcamento->fresh('itens') ?? $orcamento, $user);

            return $order->fresh(['orcamento', 'cliente']) ?? $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: list<array<string, mixed>>, 2: int|null, 3: float, 4: float, 5: int|null, 6: string}
     */
    private function validarPayload(array $data, User $user): array
    {
        $clienteId = (int) ($data['cliente_id'] ?? 0);
        $itens = is_array($data['itens'] ?? null) ? $data['itens'] : [];

        if ($clienteId <= 0 || ! Person::query()->whereKey($clienteId)->exists()) {
            throw new \RuntimeException('Selecione um cliente válido.');
        }

        if ($itens === []) {
            throw new \RuntimeException('Inclua ao menos um item na venda.');
        }

        $vendedorId = (int) ($data['vendedor_id'] ?? $user->vendedor_id ?? 0) ?: null;
        $descontoValor = round((float) ($data['desconto_valor'] ?? 0), 2);
        $percentualDesconto = round((float) ($data['percentual_desconto'] ?? 0), 4);
        $formaId = filled($data['forma_pagamento_id'] ?? null) ? (int) $data['forma_pagamento_id'] : null;
        $formaNome = trim((string) ($data['forma_pagamento'] ?? ''));

        if ($formaId && $formaNome === '') {
            $formaNome = (string) (FormaPagamento::query()->whereKey($formaId)->value('descricao') ?? '');
        }

        return [$clienteId, $itens, $vendedorId, $descontoValor, $percentualDesconto, $formaId, $formaNome];
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return array{0: float, 1: list<array<string, mixed>>}
     */
    private function gravarItens(Orcamento $orcamento, array $itens): array
    {
        $subtotal = 0.0;
        $linha = 1;
        $payloadItens = [];

        foreach ($itens as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::query()->find($productId);

            if (! $product) {
                throw new \RuntimeException('Produto inválido no item '.$linha.'.');
            }

            $quantidade = (float) ($item['quantidade'] ?? 0);
            $preco = (float) ($item['preco_unitario'] ?? 0);
            $acrItem = (float) ($item['acrescimo'] ?? 0);
            $descItem = (float) ($item['desconto'] ?? 0);

            if ($quantidade <= 0 || $preco < 0) {
                throw new \RuntimeException('Quantidade/preço inválidos no item '.$linha.'.');
            }

            // OrcamentoItem não tem acréscimo: incorpora no unitário para não perder valor.
            if ($acrItem > 0 && $quantidade > 0) {
                $preco = round($preco + ($acrItem / $quantidade), 2);
            }

            $totalItem = round(($quantidade * $preco) - $descItem, 2);
            $subtotal += $totalItem;
            $descricao = (string) ($item['descricao'] ?? $product->descricao ?? '');

            OrcamentoItem::query()->create([
                'orcamento_id' => $orcamento->id,
                'item' => $linha,
                'product_id' => $productId,
                'product_grade_id' => $item['product_grade_id'] ?? null,
                'quantidade' => $quantidade,
                'preco_unitario' => $preco,
                'total' => $totalItem,
                'desconto' => $descItem,
                'descricao' => $descricao,
            ]);

            $payloadItens[] = [
                'product_id' => $productId,
                'product_grade_id' => $item['product_grade_id'] ?? null,
                'quantidade' => $quantidade,
                'preco_unitario' => $preco,
                'desconto' => $descItem,
                'acrescimo' => $acrItem,
                'descricao' => $descricao,
            ];

            $linha++;
        }

        return [$subtotal, $payloadItens];
    }

    /**
     * @param  list<array<string, mixed>>  $payloadItens
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function montarPayload(
        string $uuid,
        int $clienteId,
        array $payloadItens,
        float $descontoValor,
        float $percentualDesconto,
        ?int $formaId,
        string $formaNome,
        array $data,
        mixed $tabelaPrazo,
    ): array {
        $acrescimoValor = round(array_sum(array_map(
            static fn (array $i): float => (float) ($i['acrescimo'] ?? 0),
            $payloadItens,
        )), 2);

        // Se o desconto foi rateado nos itens, o cabeçalho vem 0 — usa a soma das linhas
        // para o monitor/exibição. Se veio no cabeçalho (app antigo), mantém.
        $descontoExibicao = $descontoValor > 0
            ? $descontoValor
            : round(array_sum(array_map(
                static fn (array $i): float => (float) ($i['desconto'] ?? 0),
                $payloadItens,
            )), 2);

        return [
            'uuid' => $uuid,
            'device_uuid' => 'monitor-web',
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $clienteId,
            'itens' => $payloadItens,
            'desconto_valor' => $descontoExibicao,
            'percentual_desconto' => $percentualDesconto,
            'acrescimo_valor' => $acrescimoValor,
            'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
            'forma_pagamento_id' => $formaId,
            'tabela_prazo_dias' => $tabelaPrazo,
            'cartao_canhoto' => is_array($data['cartao_canhoto'] ?? null) ? $data['cartao_canhoto'] : null,
            'caixa_conta_id' => filled($data['caixa_conta_id'] ?? null) ? (int) $data['caixa_conta_id'] : null,
            'estoque_id' => filled($data['estoque_id'] ?? null) ? (int) $data['estoque_id'] : null,
            'observacoes' => $data['observacoes'] ?? null,
            'created_at' => now()->toIso8601String(),
            'origem' => 'monitor_tela_venda',
        ];
    }
}
