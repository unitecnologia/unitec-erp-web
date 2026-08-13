<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\ErpOperacaoLog;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\Financeiro\ContaPagarEstornoService;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;
use App\Support\Erp\ProductEstoqueSaldoService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reabre compra fechada: estorna estoque, preços e financeiro gerados na finalização
 * e devolve o status para aberta (editável no lançamento).
 */
final class ReabrirCompraLancamentoService
{
    public const OPERACAO = 'REABRIR_LANCAMENTO_COMPRA';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ContaPagarEstornoService $contaPagarEstorno = new ContaPagarEstornoService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * @throws DomainException
     */
    public function reabrir(Compra $compra): Compra
    {
        if ($compra->status === Compra::STATUS_CANCELADA) {
            throw new DomainException('Compra cancelada não pode ser reaberta.');
        }

        if ($compra->status !== Compra::STATUS_FECHADA) {
            throw new DomainException('Só é possível reabrir compra fechada.');
        }

        if ($this->temDevolucaoFinalizada($compra)) {
            throw new DomainException('Existe devolução de compra finalizada vinculada. Estorne a devolução antes de reabrir.');
        }

        $params = $this->parametrosFinalizacao($compra);
        $empresaId = $compra->empresa_id ? (int) $compra->empresa_id : null;
        $estoqueId = $this->resolveEstoqueId($empresaId);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        DB::transaction(function () use ($compra, $params, $estoqueId, $empresa): void {
            if ($params['gera_estoque']) {
                $this->estornarEstoque($compra, $estoqueId, $empresa);
            }

            $this->restaurarPrecosProdutos($compra, $params['ajusta_preco']);

            if ($params['gerar_financeiro']) {
                $this->estornarFinanceiro($compra);
            }

            $compra->update([
                'status' => Compra::STATUS_ABERTA,
                'lancamento_draft' => null,
            ]);
        });

        $compra->refresh();

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Compra #'.$compra->numero.' reaberta para edição.',
            origem: 'lista_compras',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: $params,
            empresaId: $empresaId,
        );

        return $compra;
    }

    /**
     * @return array{gera_estoque: bool, ajusta_preco: bool, gerar_financeiro: bool}
     */
    private function parametrosFinalizacao(Compra $compra): array
    {
        $log = ErpOperacaoLog::query()
            ->where('documento_tipo', 'compra')
            ->where('documento_id', $compra->id)
            ->where('operacao', FinalizarCompraLancamentoService::OPERACAO)
            ->orderByDesc('id')
            ->first();

        $detalhes = is_array($log?->detalhes) ? $log->detalhes : [];

        return [
            'gera_estoque' => (bool) ($detalhes['gera_estoque'] ?? true),
            'ajusta_preco' => (bool) ($detalhes['ajusta_preco'] ?? true),
            'gerar_financeiro' => (bool) ($detalhes['gerar_financeiro'] ?? true),
        ];
    }

    private function temDevolucaoFinalizada(Compra $compra): bool
    {
        return DevolucaoCompra::query()
            ->where('compra_id', $compra->id)
            ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
            ->exists();
    }

    private function estornarEstoque(Compra $compra, ?int $estoqueId, ?Empresa $empresa): void
    {
        $compra->loadMissing('itens');

        foreach ($compra->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::query()->find($item->product_id);

            if ($product && ! $product->is_servico) {
                $this->saldos->decrementar(
                    (int) $product->id,
                    (float) $item->quantidade,
                    $estoqueId,
                    $empresa,
                );
            }
        }
    }

    private function restaurarPrecosProdutos(Compra $compra, bool $ajustaPrecoVenda): void
    {
        $compra->loadMissing('itens');
        $productIds = [];

        foreach ($compra->itens as $item) {
            if ($item->product_id) {
                $productIds[(int) $item->product_id] = true;
            }
        }

        foreach (array_keys($productIds) as $productId) {
            $this->restaurarPrecoProduto($productId, $ajustaPrecoVenda);
        }
    }

    private function restaurarPrecoProduto(int $productId, bool $ajustaPrecoVenda): void
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            return;
        }

        $ultimaCompra = ProductPriceHistory::query()
            ->where('product_id', $productId)
            ->where('forma_alteracao', ProductPriceHistoryRecorder::FORMA_COMPRA)
            ->orderByDesc('id')
            ->first();

        if (! $ultimaCompra) {
            return;
        }

        $anterior = ProductPriceHistory::query()
            ->where('product_id', $productId)
            ->where('id', '<', $ultimaCompra->id)
            ->orderByDesc('id')
            ->first();

        if ($anterior) {
            $updates = [
                'preco_custo' => (float) $anterior->preco_custo,
                'preco_compra' => (float) $anterior->preco_custo,
                'ult_compra' => (float) $anterior->preco_custo,
            ];

            if ($ajustaPrecoVenda) {
                $updates['preco_venda'] = (float) $anterior->ultimo_preco;
                $updates['preco_atacado'] = (float) $anterior->preco_atacado;
                $updates['preco_especial'] = (float) $anterior->preco_especial;
            }

            $product->update($updates);
        }

        $ultimaCompra->delete();
    }

    private function estornarFinanceiro(Compra $compra): void
    {
        foreach ($this->contasPagarDaCompra($compra) as $conta) {
            $conta->loadMissing('pagamentos');

            foreach ($conta->pagamentos as $pagamento) {
                if ((float) $pagamento->valor_pago > 0) {
                    $this->contaPagarEstorno->estornarPagamento((int) $pagamento->id);
                } else {
                    $pagamento->delete();
                }
            }

            $conta->delete();
        }
    }

    /**
     * @return Collection<int, ContaPagar>
     */
    private function contasPagarDaCompra(Compra $compra): Collection
    {
        $numero = trim((string) $compra->numero);

        if ($numero === '') {
            return new Collection;
        }

        $prefixo = 'COMPRA #'.$numero;

        return ContaPagar::query()
            ->when(
                $compra->fornecedor_id,
                fn ($query) => $query->where('fornecedor_id', (int) $compra->fornecedor_id),
            )
            ->where(function ($query) use ($prefixo, $compra): void {
                $query->where('produto', 'like', $prefixo.'%')
                    ->orWhere('produto', $prefixo);

                if (filled($compra->numero_nota)) {
                    $query->orWhere('documento', (string) $compra->numero_nota);
                }
            })
            ->get();
    }

    private function resolveEstoqueId(?int $empresaId): ?int
    {
        if (! $empresaId) {
            return null;
        }

        $id = \App\Models\Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
