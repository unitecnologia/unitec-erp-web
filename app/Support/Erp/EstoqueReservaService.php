<?php

namespace App\Support\Erp;

use App\Models\EstoqueReserva;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\Pdv\PdvStockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de estoque para pedidos do app Força de Vendas (pendentes no monitor).
 * O estoque físico só baixa no faturamento; a reserva evita overselling entre vendedores.
 * Quando o vendedor tem estoque_id, a reserva/disponível ficam no depósito dele.
 */
final class EstoqueReservaService
{
    public function __construct(
        private readonly PdvStockService $stock = new PdvStockService(),
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    /**
     * @return array<int, float> product_id => quantidade reservada ativa
     */
    public function totaisReservadosAtivos(?int $estoqueId = null): array
    {
        $query = EstoqueReserva::query()
            ->where('status', EstoqueReserva::STATUS_ATIVA);

        $this->aplicarFiltroEstoque($query, $estoqueId);

        return $query
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantidade) as total')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    public function reservadoAtivo(int $productId, ?int $estoqueId = null): float
    {
        return $this->reservadoAtivoParaProduto($productId, null, $estoqueId);
    }

    public function disponivel(Product $product, ?int $ignorarOrderId = null, ?int $estoqueId = null): float
    {
        $fisico = $this->saldos->fisico((int) $product->id, $estoqueId);
        $reservado = $this->reservadoAtivoParaProduto($product->id, $ignorarOrderId, $estoqueId);

        return $fisico - $reservado;
    }

    public function reservadoAtivoParaProduto(int $productId, ?int $ignorarOrderId = null, ?int $estoqueId = null): float
    {
        $query = EstoqueReserva::query()
            ->where('product_id', $productId)
            ->where('status', EstoqueReserva::STATUS_ATIVA);

        $this->aplicarFiltroEstoque($query, $estoqueId);

        if ($ignorarOrderId !== null) {
            $query->where(fn ($q) => $q
                ->whereNull('forca_vendas_order_id')
                ->orWhere('forca_vendas_order_id', '!=', $ignorarOrderId));
        }

        return (float) $query->sum('quantidade');
    }

    /**
     * Cria reservas para todos os itens de um pedido importado (tipo pedido).
     */
    public function reservarPedido(ForcaVendasOrder $order, Orcamento $orcamento, User $user): void
    {
        if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
            return;
        }

        if ($this->pedidoJaTemReservasAtivas($order->id)) {
            return;
        }

        $orcamento->loadMissing('itens.product', 'cliente');
        $vendedor = $order->vendedor_id ? Vendedor::query()->find($order->vendedor_id) : null;
        $estoqueId = $vendedor?->estoque_id ? (int) $vendedor->estoque_id : null;
        $clienteNome = $orcamento->cliente?->nome_razao
            ?? ($order->payload['cliente_nome'] ?? null);

        foreach ($orcamento->itens as $item) {
            $linhas = $this->expandirLinhasReserva($item);

            foreach ($linhas as $linha) {
                $product = $linha['product'];
                $quantidade = $linha['quantidade'];

                if ($product->is_servico) {
                    continue;
                }

                $erro = $this->validarDisponivel(
                    $product,
                    $quantidade,
                    $item->product_grade_id ? (int) $item->product_grade_id : null,
                    $estoqueId,
                );

                if ($erro !== null) {
                    throw new \RuntimeException($erro);
                }

                Product::query()->whereKey($product->id)->lockForUpdate()->first();

                EstoqueReserva::query()->create([
                    'product_id' => $product->id,
                    'estoque_id' => $estoqueId,
                    'quantidade' => $quantidade,
                    'forca_vendas_order_id' => $order->id,
                    'orcamento_id' => $orcamento->id,
                    'orcamento_item_id' => $item->id,
                    'vendedor_id' => $vendedor?->id,
                    'vendedor_nome' => $vendedor?->nome,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'plataforma' => EstoqueReserva::PLATAFORMA_MOBILE,
                    'cliente_nome' => $clienteNome,
                    'pedido_numero' => $orcamento->numero,
                    'status' => EstoqueReserva::STATUS_ATIVA,
                ]);
            }
        }
    }

    public function consumirPedido(ForcaVendasOrder $order): void
    {
        EstoqueReserva::query()
            ->where('forca_vendas_order_id', $order->id)
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->update([
                'status' => EstoqueReserva::STATUS_CONSUMIDA,
                'consumida_at' => now(),
            ]);
    }

    public function liberarPedido(ForcaVendasOrder $order): void
    {
        EstoqueReserva::query()
            ->where('forca_vendas_order_id', $order->id)
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->update([
                'status' => EstoqueReserva::STATUS_LIBERADA,
                'liberada_at' => now(),
            ]);
    }

    /**
     * @return Collection<int, EstoqueReserva>
     */
    public function reservasAtivasDoProduto(int $productId, ?int $estoqueId = null): Collection
    {
        $query = EstoqueReserva::query()
            ->where('product_id', $productId)
            ->where('status', EstoqueReserva::STATUS_ATIVA);

        $this->aplicarFiltroEstoque($query, $estoqueId);

        return $query
            ->with(['forcaVendasOrder', 'vendedor'])
            ->orderByDesc('id')
            ->get();
    }

    private function pedidoJaTemReservasAtivas(int $orderId): bool
    {
        return EstoqueReserva::query()
            ->where('forca_vendas_order_id', $orderId)
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->exists();
    }

    /**
     * @return array<int, array{product: Product, quantidade: float}>
     */
    private function expandirLinhasReserva(OrcamentoItem $item): array
    {
        $product = $item->product;

        if ($product === null) {
            throw new \RuntimeException('Produto não encontrado no item '.$item->item.'.');
        }

        $quantidade = (float) $item->quantidade;

        if ($product->is_composicao) {
            return $this->expandirComposicao($product, $quantidade);
        }

        return [['product' => $product, 'quantidade' => $quantidade]];
    }

    /**
     * @return array<int, array{product: Product, quantidade: float}>
     */
    private function expandirComposicao(Product $kit, float $quantidadeKit): array
    {
        $linhas = [];
        $componentes = ProductComposition::query()
            ->where('product_id', $kit->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if ($comp === null || $comp->is_servico) {
                continue;
            }

            $qtd = $quantidadeKit * (float) $componente->quantidade;

            if ($comp->is_composicao) {
                foreach ($this->expandirComposicao($comp, $qtd) as $sub) {
                    $linhas[] = $sub;
                }

                continue;
            }

            $linhas[] = ['product' => $comp, 'quantidade' => $qtd];
        }

        return $linhas;
    }

    private function validarDisponivel(
        Product $product,
        float $quantidade,
        ?int $productGradeId,
        ?int $estoqueId = null,
    ): ?string {
        $erroGrade = $this->stock->validaEstoqueGrade($product, $productGradeId, $quantidade);

        if ($erroGrade !== null) {
            return $erroGrade.' ('.$product->descricao.')';
        }

        if ($product->is_composicao) {
            $erro = $this->stock->validaEstoqueComposicao($product, $quantidade, $estoqueId);

            return $erro !== null ? $erro : null;
        }

        $disponivel = $this->disponivel($product, null, $estoqueId);

        if ($disponivel < $quantidade) {
            return 'Estoque insuficiente para '.$product->descricao
                .' (disponível: '.number_format($disponivel, 3, ',', '.').', pedido: '
                .number_format($quantidade, 3, ',', '.').').';
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EstoqueReserva>  $query
     */
    private function aplicarFiltroEstoque($query, ?int $estoqueId): void
    {
        if ($estoqueId === null || ! Schema::hasColumn('estoque_reservas', 'estoque_id')) {
            return;
        }

        // Conta reservas do depósito + reservas legadas (sem estoque_id).
        $query->where(function ($q) use ($estoqueId): void {
            $q->where('estoque_id', $estoqueId)->orWhereNull('estoque_id');
        });
    }
}
