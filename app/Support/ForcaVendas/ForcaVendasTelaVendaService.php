<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpContext;
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
     * Grava pedido novo ou atualiza um existente (Pedido + ForcaVendasOrder).
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
            $pedido = Pedido::query()->create([
                'numero' => Pedido::nextNumero(),
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
                'status' => Pedido::STATUS_ABERTO,
                'plataforma' => Pedido::PLATAFORMA_FV,
            ]);

            [$subtotal, $payloadItens] = $this->gravarItens($pedido, $itens);
            $total = round($subtotal - $descontoValor, 2);

            $pedido->update([
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
                'empresa_id' => ErpContext::currentEmpresaId() ?? $user->empresa_id,
                'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'pedido_id' => $pedido->id,
                'venda_id' => null,
                'total' => $total,
                'status' => ForcaVendasOrder::STATUS_IMPORTADO,
                'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
                'payload' => $payload,
                'client_created_at' => now(),
                'received_at' => now(),
            ]);

            (new EstoqueReservaService())->reservarPedido($fvOrder, $pedido, $user);

            try {
                app(\App\Support\Gestor\GestorPushService::class)->notifyPedidoPendente($fvOrder);
            } catch (\Throwable) {
                // Push não deve quebrar a gravação.
            }

            return $fvOrder->fresh(['pedido', 'cliente']) ?? $fvOrder;
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

            $pedido = Pedido::query()->lockForUpdate()->find($order->pedido_id);

            if (! $pedido) {
                throw new \RuntimeException('Pedido (DAV) não encontrado.');
            }

            $reserva = new EstoqueReservaService();
            $reserva->liberarPedido($order);

            PedidoItem::query()->where('pedido_id', $pedido->id)->delete();

            [$subtotal, $payloadItens] = $this->gravarItens($pedido, $itens);
            $total = round($subtotal - $descontoValor, 2);

            $pedido->update([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'percentual_desconto' => $percentualDesconto,
                'desconto_valor' => $descontoValor,
                'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
                'observacoes' => $data['observacoes'] ?? null,
                'subtotal' => round($subtotal, 2),
                'total' => $total,
                'status' => Pedido::STATUS_ABERTO,
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

            $order = $order->fresh(['pedido', 'cliente']) ?? $order;
            $reserva->reservarPedido($order, $pedido->fresh('itens') ?? $pedido, $user);

            return $order->fresh(['pedido', 'cliente']) ?? $order;
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

        $caixaId = filled($data['caixa_conta_id'] ?? null) ? (int) $data['caixa_conta_id'] : null;

        if ($caixaId === null || $caixaId <= 0) {
            throw new \RuntimeException(
                'Caixa não definido. Vincule um caixa ao vendedor antes de vender.'
            );
        }

        $estoqueId = filled($data['estoque_id'] ?? null) ? (int) $data['estoque_id'] : null;

        if ($estoqueId === null || $estoqueId <= 0) {
            $productIds = collect($itens)
                ->pluck('product_id')
                ->filter(fn ($id): bool => (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($productIds !== []) {
                $temProdutoFisico = Product::query()
                    ->whereIn('id', $productIds)
                    ->where('is_servico', false)
                    ->exists();

                if ($temProdutoFisico) {
                    throw new \RuntimeException(
                        'Depósito de estoque não definido. Vincule um estoque ao vendedor antes de vender produtos.'
                    );
                }
            }
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
    private function gravarItens(Pedido $pedido, array $itens): array
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
            $precoInformado = (float) ($item['preco_unitario'] ?? 0);
            $acrItem = (float) ($item['acrescimo'] ?? 0);
            $descItem = (float) ($item['desconto'] ?? 0);

            if ($quantidade <= 0 || $precoInformado < 0) {
                throw new \RuntimeException('Quantidade/preço inválidos no item '.$linha.'.');
            }

            // PedidoItem não tem acréscimo: incorpora no unitário para não perder valor.
            $precoPedido = $precoInformado;
            if ($acrItem > 0 && $quantidade > 0) {
                $precoPedido = round($precoInformado + ($acrItem / $quantidade), 2);
            }

            $totalItem = round(($quantidade * $precoInformado) + $acrItem - $descItem, 2);
            $subtotal += $totalItem;
            $descricao = (string) ($item['descricao'] ?? $product->descricao ?? '');

            PedidoItem::query()->create([
                'pedido_id' => $pedido->id,
                'item' => $linha,
                'product_id' => $productId,
                'product_grade_id' => $item['product_grade_id'] ?? null,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoPedido,
                'total' => $totalItem,
                'desconto' => $descItem,
                'descricao' => $descricao,
            ]);

            $payloadItens[] = [
                'product_id' => $productId,
                'product_grade_id' => $item['product_grade_id'] ?? null,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoInformado,
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

    /**
     * Copia só o comercial de um pedido cancelado para um DAV novo e pendente.
     * Venda, financeiro e fiscal do original não são copiados.
     * Reserva ativa do cancelado é liberada; só o clone fica segurando estoque.
     */
    public function clonarPedidoCancelado(
        ForcaVendasOrder $order,
        User $user,
        ?callable $aoAvancar = null,
    ): ForcaVendasOrder {
        $avancar = function (string $nome) use ($aoAvancar): void {
            if ($aoAvancar !== null) {
                $aoAvancar($nome);
            }
        };

        $avancar('Validando pedido cancelado');

        if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
            throw new \RuntimeException('Somente pedidos podem ser clonados nesta tela.');
        }

        if ($order->situacao !== ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Somente um pedido cancelado pode ser clonado por aqui.');
        }

        $order->loadMissing('pedido.itens');
        $pedidoOrigem = $order->pedido;
        $payload = is_array($order->payload) ? $order->payload : [];
        $payloadItens = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
        $linhas = $pedidoOrigem
            ? $this->linhasComerciais($pedidoOrigem, $payloadItens)
            : $this->linhasComerciaisDoPayload($payloadItens);

        if ($linhas === []) {
            throw new \RuntimeException('Pedido cancelado sem itens para clonar.');
        }

        $clienteId = (int) ($order->cliente_id ?: $pedidoOrigem?->cliente_id ?: ($payload['cliente_id'] ?? 0));

        if ($clienteId <= 0 || ! Person::query()->whereKey($clienteId)->exists()) {
            throw new \RuntimeException('Pedido cancelado sem cliente válido para clonar.');
        }

        $vendedorId = (int) ($order->vendedor_id ?: $pedidoOrigem?->vendedor_id ?: 0) ?: null;
        $uuid = (string) Str::uuid();
        $momentoLocal = ErpTimezone::toLocal();

        return DB::transaction(function () use (
            $order,
            $user,
            $pedidoOrigem,
            $payload,
            $linhas,
            $clienteId,
            $vendedorId,
            $uuid,
            $momentoLocal,
            $avancar,
        ): ForcaVendasOrder {
            $avancar('Preparando clonagem');

            $travado = ForcaVendasOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $travado instanceof ForcaVendasOrder
                || $travado->situacao !== ForcaVendasOrder::SITUACAO_CANCELADO) {
                throw new \RuntimeException('O pedido não está mais cancelado.');
            }

            $avancar('Copiando cliente e vendedor');
            $avancar('Copiando itens');
            $avancar('Copiando condições comerciais');

            $descontoValor = round((float) ($pedidoOrigem?->desconto_valor ?? ($payload['desconto_valor'] ?? 0)), 2);
            $percentualDesconto = round((float) ($pedidoOrigem?->percentual_desconto ?? ($payload['percentual_desconto'] ?? 0)), 4);
            $subtotal = round(array_sum(array_map(
                static fn (array $linha): float => (float) $linha['total'],
                $linhas,
            )), 2);
            $total = round((float) ($pedidoOrigem?->total ?? ($subtotal - $descontoValor)), 2);
            $formaNome = trim((string) ($pedidoOrigem?->forma_pagamento ?? ($payload['forma_pagamento'] ?? '')));
            $observacoes = $pedidoOrigem?->observacoes ?? ($payload['observacoes'] ?? null);

            $avancar('Gerando novo DAV/pedido');

            $pedido = Pedido::query()->create([
                'numero' => Pedido::nextNumero(),
                'data' => $momentoLocal->toDateString(),
                'hora' => $momentoLocal->format('H:i:s'),
                'cliente_id' => $clienteId,
                'cliente_nome' => $pedidoOrigem?->cliente_nome,
                'cliente_cpf_cnpj' => $pedidoOrigem?->cliente_cpf_cnpj,
                'cliente_endereco' => $pedidoOrigem?->cliente_endereco,
                'cliente_numero' => $pedidoOrigem?->cliente_numero,
                'cliente_bairro' => $pedidoOrigem?->cliente_bairro,
                'cliente_cep' => $pedidoOrigem?->cliente_cep,
                'cliente_cidade' => $pedidoOrigem?->cliente_cidade,
                'cliente_uf' => $pedidoOrigem?->cliente_uf,
                'cliente_fone' => $pedidoOrigem?->cliente_fone,
                'cliente_whatsapp' => $pedidoOrigem?->cliente_whatsapp,
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'percentual_desconto' => $percentualDesconto,
                'desconto_valor' => $descontoValor,
                'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
                'validade_dias' => (int) ($pedidoOrigem?->validade_dias ?? 0),
                'observacoes' => $observacoes,
                'total' => $total,
                'status' => Pedido::STATUS_ABERTO,
                'plataforma' => Pedido::PLATAFORMA_FV,
            ]);

            $payloadItensNovos = [];

            foreach ($linhas as $linha) {
                PedidoItem::query()->create([
                    'pedido_id' => $pedido->id,
                    'item' => $linha['item'],
                    'product_id' => $linha['product_id'],
                    'product_grade_id' => $linha['product_grade_id'],
                    'quantidade' => $linha['quantidade'],
                    'preco_unitario' => $linha['preco_unitario'],
                    'total' => $linha['total'],
                    'desconto' => $linha['desconto'],
                    'descricao' => $linha['descricao'],
                ]);
                $payloadItensNovos[] = $linha['payload'];
            }

            $novoPayload = [
                'uuid' => $uuid,
                'device_uuid' => 'monitor-web',
                'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
                'cliente_id' => $clienteId,
                'itens' => $payloadItensNovos,
                'desconto_valor' => $descontoValor,
                'percentual_desconto' => $percentualDesconto,
                'acrescimo_valor' => round(array_sum(array_map(
                    static fn (array $item): float => (float) ($item['acrescimo'] ?? 0),
                    $payloadItensNovos,
                )), 2),
                'forma_pagamento' => $formaNome !== '' ? $formaNome : null,
                'forma_pagamento_id' => filled($payload['forma_pagamento_id'] ?? null)
                    ? (int) $payload['forma_pagamento_id']
                    : null,
                'observacoes' => $observacoes,
                'created_at' => now()->toIso8601String(),
                'origem' => 'monitor_tela_venda_clone',
            ];

            foreach ([
                'tabela_prazo_dias',
                'condicao_pagamento',
                'cartao_canhoto',
                'caixa_conta_id',
                'caixa_id',
                'estoque_id',
                'transporte',
                'convenio_id',
                'convenio',
                'propriedade_id',
                'propriedade',
                'local_estoque_id',
                'tabela_preco_id',
            ] as $chave) {
                if (! array_key_exists($chave, $payload) || $payload[$chave] === null || $payload[$chave] === '') {
                    continue;
                }

                $novoPayload[$chave] = $payload[$chave];
            }

            $fvOrder = ForcaVendasOrder::query()->create([
                'uuid' => $uuid,
                'device_uuid' => 'monitor-web',
                'user_id' => $user->id,
                'empresa_id' => $order->empresa_id ?: (ErpContext::currentEmpresaId() ?? $user->empresa_id),
                'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'pedido_id' => $pedido->id,
                'venda_id' => null,
                'total' => $total,
                'status' => ForcaVendasOrder::STATUS_IMPORTADO,
                'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
                'payload' => $novoPayload,
                'client_created_at' => now(),
                'received_at' => now(),
            ]);

            $avancar('Criando reserva de estoque');

            $reservas = new EstoqueReservaService();
            $reservas->liberarPedido($travado);
            $reservas->reservarPedido(
                $fvOrder,
                $pedido->fresh('itens') ?? $pedido,
                $user,
            );

            return $fvOrder->fresh(['pedido', 'cliente']) ?? $fvOrder;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $payloadItens
     * @return list<array<string, mixed>>
     */
    private function linhasComerciais(Pedido $pedido, array $payloadItens): array
    {
        $fila = [];

        foreach ($payloadItens as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $chave = ((int) ($raw['product_id'] ?? 0)).':'
                .(filled($raw['product_grade_id'] ?? null) ? (int) $raw['product_grade_id'] : 0);
            $fila[$chave][] = $raw;
        }

        $linhas = [];
        $numero = 1;

        foreach ($pedido->itens->sortBy(fn ($item) => (int) ($item->item ?? 0)) as $item) {
            $chave = ((int) $item->product_id).':'
                .(filled($item->product_grade_id) ? (int) $item->product_grade_id : 0);
            $raw = ! empty($fila[$chave]) ? (array_shift($fila[$chave]) ?? []) : [];

            $linhas[] = [
                'item' => $numero,
                'product_id' => (int) $item->product_id,
                'product_grade_id' => $item->product_grade_id ? (int) $item->product_grade_id : null,
                'quantidade' => $item->quantidade,
                'preco_unitario' => $item->preco_unitario,
                'total' => $item->total,
                'desconto' => $item->desconto ?? 0,
                'descricao' => $item->descricao,
                'payload' => $this->itemPayloadComercial($raw, $item),
            ];
            $numero++;
        }

        return $linhas;
    }

    /**
     * @param  list<mixed>  $payloadItens
     * @return list<array<string, mixed>>
     */
    private function linhasComerciaisDoPayload(array $payloadItens): array
    {
        $linhas = [];
        $numero = 1;

        foreach ($payloadItens as $raw) {
            if (! is_array($raw) || (int) ($raw['product_id'] ?? 0) <= 0) {
                continue;
            }

            $quantidade = (float) ($raw['quantidade'] ?? 0);
            $preco = (float) ($raw['preco_unitario'] ?? 0);
            $desconto = (float) ($raw['desconto'] ?? 0);
            $acrescimo = (float) ($raw['acrescimo'] ?? 0);

            $linhas[] = [
                'item' => $numero,
                'product_id' => (int) $raw['product_id'],
                'product_grade_id' => filled($raw['product_grade_id'] ?? null) ? (int) $raw['product_grade_id'] : null,
                'quantidade' => $quantidade,
                'preco_unitario' => $preco,
                'total' => round(($quantidade * $preco) + $acrescimo - $desconto, 2),
                'desconto' => $desconto,
                'descricao' => $raw['descricao'] ?? null,
                'payload' => $this->itemPayloadComercial($raw, null),
            ];
            $numero++;
        }

        return $linhas;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function itemPayloadComercial(array $raw, ?PedidoItem $item): array
    {
        $payload = [
            'product_id' => (int) ($item?->product_id ?? $raw['product_id'] ?? 0),
            'product_grade_id' => filled($item?->product_grade_id ?? $raw['product_grade_id'] ?? null)
                ? (int) ($item?->product_grade_id ?? $raw['product_grade_id'])
                : null,
            'quantidade' => (float) ($raw['quantidade'] ?? $item?->quantidade ?? 0),
            'preco_unitario' => (float) ($raw['preco_unitario'] ?? $item?->preco_unitario ?? 0),
            'desconto' => (float) ($raw['desconto'] ?? $item?->desconto ?? 0),
            'acrescimo' => (float) ($raw['acrescimo'] ?? 0),
            'descricao' => (string) ($raw['descricao'] ?? $item?->descricao ?? ''),
        ];

        if (filled($raw['codigo'] ?? null)) {
            $payload['codigo'] = (string) $raw['codigo'];
        }

        return $payload;
    }
}
