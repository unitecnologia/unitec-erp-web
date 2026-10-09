<?php

namespace App\Support\Erp\Compra;

use App\Models\CaixaLancamento;
use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\ErpOperacaoLog;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueMovimentacaoDocumento;
use App\Support\Erp\Financeiro\ContaPagarEstornoService;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Models\EstoqueMovimentacao;
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

        if ($this->temDevolucaoAtiva($compra)) {
            throw new DomainException('Existe devolução de compra vinculada (aberta ou finalizada). Cancele ou estorne a devolução antes de reabrir.');
        }

        $params = $this->parametrosFinalizacao($compra);
        $empresaId = $compra->empresa_id ? (int) $compra->empresa_id : null;
        $estoqueId = $this->resolveEstoqueId($empresaId);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        DB::transaction(function () use ($compra, $params, $estoqueId, $empresa): void {
            $travada = Compra::query()->whereKey($compra->id)->lockForUpdate()->first();

            if (! $travada || $travada->status !== Compra::STATUS_FECHADA) {
                throw new DomainException('Só é possível reabrir compra fechada.');
            }

            if ($params['gera_estoque']) {
                $this->estornarEstoque($compra, $estoqueId, $empresa, $params['lotes']);
            }

            $this->restaurarPrecosProdutos($compra, $params['ajusta_preco']);

            if ($params['gerar_financeiro']) {
                $this->estornarFinanceiro($compra);
                $this->estornarCaixa($params['caixa_lancamentos']);
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
     * @return array{gera_estoque: bool, ajusta_preco: bool, gerar_financeiro: bool, lotes: array<int, mixed>|null, caixa_lancamentos: list<int>}
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
            'lotes' => array_key_exists('lotes', $detalhes) && is_array($detalhes['lotes'])
                ? $detalhes['lotes']
                : null,
            'caixa_lancamentos' => is_array($detalhes['caixa_lancamentos'] ?? null)
                ? array_values(array_filter(array_map('intval', $detalhes['caixa_lancamentos'])))
                : [],
        ];
    }

    private function temDevolucaoAtiva(Compra $compra): bool
    {
        return DevolucaoCompra::query()
            ->where('compra_id', $compra->id)
            ->where('situacao', '!=', DevolucaoCompra::SITUACAO_CANCELADA)
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>|null  $lotesLancados
     */
    private function estornarEstoque(Compra $compra, ?int $estoqueId, ?Empresa $empresa, ?array $lotesLancados): void
    {
        $compra->loadMissing('itens');
        $lotesService = new \App\Support\Erp\ProductLoteService();

        if ($lotesLancados === null && $this->compraControlaLote($compra)) {
            throw new DomainException('Não é possível reabrir esta compra: os lotes lançados não ficaram registrados. O estorno não usa o lote que vence primeiro, para não baixar a quantidade errada.');
        }

        foreach ($compra->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::query()->find($item->product_id);

            if ($product && ! $product->is_servico) {
                $doc = EstoqueMovimentacaoDocumento::fromCompra($compra);
                $this->saldos->decrementar(
                    (int) $product->id,
                    (float) $item->quantidade,
                    $estoqueId,
                    $empresa,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                        empresaId: $empresa?->id !== null ? (int) $empresa->id : ($compra->empresa_id ? (int) $compra->empresa_id : null),
                        origemTipo: $doc['origemTipo'],
                        origemId: $doc['origemId'],
                        origemNumero: $doc['origemNumero'],
                        docFiscalTipo: $doc['docFiscalTipo'],
                        docFiscalNumero: $doc['docFiscalNumero'],
                    ),
                );

                $lotesItem = $this->lotesDoItem($lotesLancados ?? [], $item, $compra);

                if ($lotesItem === []) {
                    continue;
                }

                try {
                    $lotesService->estornarEntrada($product, $lotesItem);
                } catch (\RuntimeException $e) {
                    throw new DomainException($e->getMessage(), 0, $e);
                }
            }
        }
    }

    private function compraControlaLote(Compra $compra): bool
    {
        foreach ($compra->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);

            if ($product && $product->controla_lote_validade) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $lotesLancados
     * @return list<array<string, mixed>>
     */
    private function lotesDoItem(array $lotesLancados, mixed $item, Compra $compra): array
    {
        $itemId = (int) ($item->id ?? 0);
        $porItem = [];

        foreach ($lotesLancados as $lote) {
            if (! is_array($lote)) {
                continue;
            }

            if ($itemId > 0 && (int) ($lote['compra_item_id'] ?? 0) === $itemId) {
                $porItem[] = $lote;
            }
        }

        if ($porItem !== [] || $itemId <= 0) {
            return $porItem;
        }

        $productId = (int) ($item->product_id ?? 0);
        $mesmoProduto = 0;

        foreach ($lotesLancados as $lote) {
            if (is_array($lote) && (int) ($lote['product_id'] ?? 0) === $productId && (int) ($lote['compra_item_id'] ?? 0) <= 0) {
                $mesmoProduto++;
            }
        }

        if ($mesmoProduto === 0) {
            return [];
        }

        $itensDesseProduto = 0;

        foreach ($compra->itens as $irmao) {
            if ((int) ($irmao->product_id ?? 0) === $productId) {
                $itensDesseProduto++;
            }
        }

        if ($itensDesseProduto !== 1) {
            throw new DomainException('Não é possível reabrir: há mais de uma linha do mesmo produto e os lotes não estão ligados a cada item.');
        }

        $porProduto = [];

        foreach ($lotesLancados as $lote) {
            if (is_array($lote) && (int) ($lote['product_id'] ?? 0) === $productId) {
                $porProduto[] = $lote;
            }
        }

        return $porProduto;
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
            $this->restaurarPrecoProduto($productId, $ajustaPrecoVenda, (int) $compra->id);
        }
    }

    private function restaurarPrecoProduto(int $productId, bool $ajustaPrecoVenda, int $compraId): void
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            return;
        }

        $destaCompra = ProductPriceHistory::query()
            ->where('product_id', $productId)
            ->where('compra_id', $compraId)
            ->orderByDesc('id')
            ->first();

        if (! $destaCompra) {
            return;
        }

        $ultima = ProductPriceHistory::query()
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->first();

        if (! $ultima || (int) $ultima->id !== (int) $destaCompra->id) {
            return;
        }

        $anterior = ProductPriceHistory::query()
            ->where('product_id', $productId)
            ->where('id', '<', $destaCompra->id)
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

        $destaCompra->delete();
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
     * Saídas diretas no Livro Caixa (dinheiro/PIX) voltam como entrada de estorno.
     *
     * @param  list<int>  $ids
     */
    private function estornarCaixa(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $hoje = ErpTimezone::toLocal()->toDateString();
        $temEmpresa = \Illuminate\Support\Facades\Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id');

        $lancamentos = CaixaLancamento::query()
            ->whereIn('id', $ids)
            ->where('saida', '>', 0)
            ->get();

        foreach ($lancamentos as $lancamento) {
            $payload = [
                'codigo' => CaixaLancamento::nextCodigo(),
                'emissao' => $hoje,
                'documento' => $lancamento->documento,
                'historico' => mb_substr('ESTORNO '.trim((string) $lancamento->historico), 0, 180),
                'plano_contas' => $lancamento->plano_contas,
                'plano_conta_id' => $lancamento->plano_conta_id,
                'caixa_conta_id' => $lancamento->caixa_conta_id,
                'entrada' => (float) $lancamento->saida,
                'saida' => 0,
            ];

            if ($temEmpresa) {
                $payload['empresa_id'] = $lancamento->empresa_id;
            }

            CaixaLancamento::query()->create($payload);
        }
    }

    /**
     * @return Collection<int, ContaPagar>
     */
    private function contasPagarDaCompra(Compra $compra): Collection
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('contas_pagar', 'compra_id')) {
            $porId = ContaPagar::query()
                ->where('compra_id', (int) $compra->id)
                ->get();

            if ($porId->isNotEmpty()) {
                return $porId;
            }
        }

        $numero = trim((string) $compra->numero);

        if ($numero === '') {
            return new Collection;
        }

        $exata = 'COMPRA #'.$numero;
        $query = ContaPagar::query()
            ->when(
                $compra->fornecedor_id,
                fn ($query) => $query->where('fornecedor_id', (int) $compra->fornecedor_id),
            )
            ->where(function ($query) use ($exata): void {
                $query->where('produto', $exata)
                    ->orWhere('produto', 'like', $exata.' %');
            });

        if (\Illuminate\Support\Facades\Schema::hasColumn('contas_pagar', 'compra_id')) {
            $query->whereNull('compra_id');
        }

        $legado = $query->get();

        if ($legado->count() > 1) {
            throw new DomainException('Há mais de uma conta a pagar antiga para esta compra, sem vínculo seguro. Nenhuma foi estornada.');
        }

        return $legado;
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
