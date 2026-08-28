<?php

namespace App\Support\Erp\Compra;

use App\Models\CaixaConta;
use App\Models\Compra;
use App\Models\Estoque;
use App\Models\FormaPagamento;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ContaPagarBaixaService;
use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;
use App\Support\Erp\ProductEstoqueSaldoService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Finaliza o lançamento de compra: estoque / preço / financeiro conforme parâmetros,
 * e marca a compra como fechada.
 */
final class FinalizarCompraLancamentoService
{
    public const OPERACAO = 'FINALIZAR_LANCAMENTO_COMPRA';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ContaPagarCadastroService $contasPagar = new ContaPagarCadastroService(),
        private readonly ContaPagarBaixaService $contasPagarBaixa = new ContaPagarBaixaService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
        private readonly ProductPriceHistoryRecorder $priceHistory = new ProductPriceHistoryRecorder(),
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  linhas do modal (product_id opcional / preco_venda)
     * @param  list<array{documento?: string, vencimento: string, valor: float|string, forma_pagamento_id?: int|null, caixa_conta_id?: int|null}>|null  $parcelasFinanceiro
     *
     * @throws DomainException
     */
    public function finalizar(
        Compra $compra,
        array $rows,
        bool $ajustaPreco,
        bool $gerarFinanceiro,
        bool $geraEstoque,
        ?array $parcelasFinanceiro = null,
        ?float $totalOverride = null,
    ): Compra {
        if ($compra->status === Compra::STATUS_CANCELADA) {
            throw new DomainException('Compra cancelada não pode ser finalizada.');
        }

        if ($compra->status === Compra::STATUS_FECHADA) {
            throw new DomainException('Esta compra já está fechada.');
        }

        if ($gerarFinanceiro && ! $compra->fornecedor_id) {
            throw new DomainException('Compra sem fornecedor. Não é possível gerar o financeiro.');
        }

        $compra->loadMissing(['itens.product', 'fornecedor']);
        $empresaId = $compra->empresa_id ? (int) $compra->empresa_id : null;
        $estoqueId = $this->resolveEstoqueId($empresaId);

        DB::transaction(function () use (
            $compra,
            $rows,
            $ajustaPreco,
            $gerarFinanceiro,
            $geraEstoque,
            $estoqueId,
            $parcelasFinanceiro,
            $totalOverride,
        ): void {
            $this->sincronizarItensDoLancamento($compra, $rows);
            $compra->load('itens.product');

            $totalItens = round((float) $compra->itens->sum('total'), 2);
            $total = $totalOverride !== null && $totalOverride > 0
                ? round($totalOverride, 2)
                : $totalItens;

            if ($total > 0) {
                $compra->forceFill(['total' => $total])->save();
            }

            $usuario = Auth::user()?->name ?? 'Sistema';
            $precosAnteriores = [];

            foreach ($compra->itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()->find($item->product_id);
                if (! $product || isset($precosAnteriores[$product->id])) {
                    continue;
                }

                $precosAnteriores[(int) $product->id] = [
                    'varejo' => (float) $product->preco_venda,
                    'atacado' => (float) $product->preco_atacado,
                    'especial' => (float) $product->preco_especial,
                    'custo' => (float) $product->preco_custo,
                ];
            }

            if ($ajustaPreco) {
                $this->aplicarPrecosVenda($compra, $rows);
            }

            if ($geraEstoque) {
                foreach ($compra->itens as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = $item->product ?? Product::query()->find($item->product_id);

                    if ($product && ! $product->is_servico) {
                        $this->saldos->incrementar(
                            (int) $product->id,
                            (float) $item->quantidade,
                            $estoqueId,
                        );

                        if ($product->controla_lote_validade) {
                            $lotes = $this->lotesDaLinha($rows, $item);
                            $lotesService = new \App\Support\Erp\ProductLoteService();
                            try {
                                $lotesService->validarLinhasEntrada((float) $item->quantidade, $lotes);
                                $lotesService->entrar($product, $lotes);
                            } catch (\RuntimeException $e) {
                                throw new DomainException($e->getMessage(), 0, $e);
                            }
                        }
                    }
                }
            }

            foreach ($compra->itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $custo = (float) $item->valor_unitario;
                if ($custo <= 0) {
                    continue;
                }

                $product = Product::query()->find($item->product_id);

                if ($product) {
                    $product->update([
                        'preco_compra' => $custo,
                        'preco_custo' => $custo,
                        'ult_compra' => $custo,
                    ]);
                }
            }

            foreach ($precosAnteriores as $productId => $anterior) {
                $product = Product::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $this->priceHistory->recordSalePricesIfChanged(
                    product: $product,
                    forma: ProductPriceHistoryRecorder::FORMA_COMPRA,
                    varejoAnterior: $anterior['varejo'],
                    atacadoAnterior: $anterior['atacado'],
                    especialAnterior: $anterior['especial'],
                    custoAnterior: $anterior['custo'],
                    usuario: $usuario,
                );
            }

            if ($gerarFinanceiro) {
                $this->gerarContasPagar($compra, $parcelasFinanceiro);
            }

            $compra->update([
                'status' => Compra::STATUS_FECHADA,
                'lancamento_draft' => null,
            ]);
        });

        $compra->refresh();

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Compra #'.$compra->numero.' finalizada no lançamento.',
            origem: 'compra',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: [
                'ajusta_preco' => $ajustaPreco,
                'gerar_financeiro' => $gerarFinanceiro,
                'gera_estoque' => $geraEstoque,
                'total' => (float) $compra->total,
                'parcelas' => $parcelasFinanceiro !== null ? count($parcelasFinanceiro) : ($gerarFinanceiro ? 1 : 0),
            ],
            empresaId: $empresaId,
        );

        return $compra;
    }

    /**
     * @param  list<array{documento?: string, vencimento: string, valor: float|string, forma_pagamento_id?: int|null, caixa_conta_id?: int|null}>|null  $parcelasFinanceiro
     */
    private function gerarContasPagar(Compra $compra, ?array $parcelasFinanceiro): void
    {
        if (! $compra->fornecedor_id) {
            throw new DomainException('Compra sem fornecedor. Não é possível gerar contas a pagar.');
        }

        $emissao = $compra->data_emissao?->toDateString()
            ?? $compra->data_entrada?->toDateString()
            ?? now()->toDateString();
        $historico = 'COMPRA #'.$compra->numero
            .($compra->numero_nota ? ' NF '.$compra->numero_nota : '');
        $documentoBase = $compra->numero_nota ? (string) $compra->numero_nota : (string) $compra->numero;

        if (is_array($parcelasFinanceiro) && $parcelasFinanceiro !== []) {
            $somaParcelas = 0.0;
            foreach ($parcelasFinanceiro as $parcela) {
                $somaParcelas += ErpMoney::parseBr($parcela['valor'] ?? 0);
            }
            $somaParcelas = round($somaParcelas, 2);

            if ($somaParcelas <= 0) {
                throw new DomainException('Parcelas do financeiro com valor total zero.');
            }

            $contas = $this->contasPagar->criarDeLista([
                'emissao' => $emissao,
                'fornecedor_id' => (int) $compra->fornecedor_id,
                'historico' => $historico,
                'documento' => $documentoBase,
                'compra_id' => (int) $compra->id,
            ], $parcelasFinanceiro);

            $this->baixarParcelasDinheiro($contas, $parcelasFinanceiro, $emissao);

            return;
        }

        $valor = (float) $compra->total;
        if ($valor <= 0) {
            $valor = round((float) $compra->itens->sum('total'), 2);
        }

        if ($valor <= 0) {
            throw new DomainException('Total da compra é zero. Não é possível gerar o financeiro.');
        }

        $vencimento = $compra->data_entrada?->toDateString() ?? $emissao;

        $this->contasPagar->criar([
            'emissao' => $emissao,
            'vencimento' => $vencimento,
            'fornecedor_id' => (int) $compra->fornecedor_id,
            'valor' => $valor,
            'documento' => $documentoBase,
            'historico' => $historico,
            'parcelas' => 1,
            'compra_id' => (int) $compra->id,
        ]);
    }

    /**
     * Parcelas em dinheiro: baixa imediata + saída no subcaixa informado.
     *
     * @param  list<\App\Models\ContaPagar>  $contas
     * @param  list<array{forma_pagamento_id?: int|null, caixa_conta_id?: int|null}>  $parcelasFinanceiro
     */
    private function baixarParcelasDinheiro(array $contas, array $parcelasFinanceiro, string $pagoEm): void
    {
        foreach ($parcelasFinanceiro as $i => $parcela) {
            $conta = $contas[$i] ?? null;
            if (! $conta) {
                continue;
            }

            $formaId = (int) ($parcela['forma_pagamento_id'] ?? 0);
            if ($formaId <= 0) {
                continue;
            }

            $forma = FormaPagamento::query()->whereKey($formaId)->where('ativo', true)->first();
            if (! $forma || mb_strtolower(trim((string) $forma->tipo), 'UTF-8') !== 'dinheiro') {
                continue;
            }

            $caixaId = (int) ($parcela['caixa_conta_id'] ?? 0);
            if ($caixaId <= 0) {
                throw new DomainException('Parcela em dinheiro sem subcaixa informado.');
            }

            $caixaOk = CaixaConta::query()
                ->whereKey($caixaId)
                ->where('ativo', true)
                ->where('tipo', CaixaConta::TIPO_SUBCAIXA)
                ->exists();

            if (! $caixaOk) {
                throw new DomainException('Subcaixa inválido na parcela em dinheiro.');
            }

            $this->contasPagarBaixa->baixarUma((int) $conta->id, $formaId, [
                'caixa_conta_id' => $caixaId,
                'pago_em' => $pagoEm,
            ]);
        }
    }

    /**
     * Aplica Qtd.Compra / vL. custo editados no lançamento nos itens da compra.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function sincronizarItensDoLancamento(Compra $compra, array $rows): void
    {
        $itens = $compra->itens->values();

        foreach ($rows as $index => $row) {
            $item = $itens->firstWhere('product_id', (int) ($row['product_id'] ?? 0))
                ?? $itens->get($index);

            if (! $item) {
                continue;
            }

            $qtd = BrDecimal::parse($row['qtd'] ?? $row['qtd_num'] ?? $item->quantidade, 3);
            $valorCheio = BrDecimal::parse($row['preco'] ?? $item->total, 4);
            if ($valorCheio <= 0) {
                $valorCheio = (float) $item->total;
            }

            if ($qtd <= 0) {
                continue;
            }

            $vlCusto = BrDecimal::parse($row['vl_custo'] ?? 0, 4);
            if ($vlCusto <= 0) {
                $vlCusto = round($valorCheio / $qtd, 4);
            }

            $item->update([
                'quantidade' => $qtd,
                'valor_unitario' => $vlCusto,
                'total' => round($valorCheio, 2),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function aplicarPrecosVenda(Compra $compra, array $rows): void
    {
        $byProduct = [];

        foreach ($rows as $index => $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                $item = $compra->itens->values()->get($index);
                $productId = (int) ($item?->product_id ?? 0);
            }
            if ($productId <= 0) {
                continue;
            }

            $byProduct[$productId] = [
                'preco_venda' => BrDecimal::parse($row['preco_venda'] ?? 0, 4),
                'preco_atacado' => BrDecimal::parse($row['preco_atacado'] ?? 0, 4),
                'preco_especial' => BrDecimal::parse($row['preco_especial'] ?? 0, 4),
            ];
        }

        foreach ($byProduct as $productId => $precos) {
            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $updates = [];
            if ($precos['preco_venda'] > 0) {
                $updates['preco_venda'] = $precos['preco_venda'];
            }
            if ($precos['preco_atacado'] > 0) {
                $updates['preco_atacado'] = $precos['preco_atacado'];
            }
            if ($precos['preco_especial'] > 0) {
                $updates['preco_especial'] = $precos['preco_especial'];
            }

            if ($updates === []) {
                continue;
            }

            $product->update($updates);
        }
    }

    private function resolveEstoqueId(?int $empresaId): ?int
    {
        if (! $empresaId) {
            return null;
        }

        $id = Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{lote: string, data_validade: string, quantidade: float|string}>
     */
    private function lotesDaLinha(array $rows, mixed $item): array
    {
        $productId = (int) ($item->product_id ?? 0);

        foreach ($rows as $row) {
            if ((int) ($row['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $lotes = $row['lotes'] ?? null;
            if (! is_array($lotes)) {
                return [];
            }

            $out = [];
            foreach ($lotes as $lote) {
                if (! is_array($lote)) {
                    continue;
                }
                $out[] = [
                    'lote' => (string) ($lote['lote'] ?? ''),
                    'data_validade' => (string) ($lote['data_validade'] ?? ''),
                    'quantidade' => $lote['quantidade'] ?? 0,
                ];
            }

            return $out;
        }

        return [];
    }
}
