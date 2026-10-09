<?php

namespace App\Support\Erp\Compra;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Compra;
use App\Models\Estoque;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueMovimentacaoDocumento;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Models\EstoqueMovimentacao;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $lotesFinalizados = [];
        $caixaLancamentoIds = [];

        DB::transaction(function () use (
            $compra,
            $rows,
            $ajustaPreco,
            $gerarFinanceiro,
            $geraEstoque,
            $estoqueId,
            $parcelasFinanceiro,
            $totalOverride,
            &$lotesFinalizados,
            &$caixaLancamentoIds,
        ): void {
            $travada = Compra::query()->whereKey($compra->id)->lockForUpdate()->first();

            if (! $travada || $travada->status !== Compra::STATUS_ABERTA) {
                throw new DomainException(
                    $travada && $travada->status === Compra::STATUS_CANCELADA
                        ? 'Compra cancelada não pode ser finalizada.'
                        : 'Esta compra já está fechada.',
                );
            }

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
                $lotesService = new \App\Support\Erp\ProductLoteService();

                foreach ($compra->itens->values() as $itemIndex => $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = $item->product ?? Product::query()->find($item->product_id);

                    if ($product && ! $product->is_servico) {
                        $doc = EstoqueMovimentacaoDocumento::fromCompra($compra);
                        $this->saldos->incrementar(
                            (int) $product->id,
                            (float) $item->quantidade,
                            $estoqueId,
                            null,
                            EstoqueMovimentacaoContext::make(
                                EstoqueMovimentacao::TIPO_ENTRADA_COMPRA,
                                empresaId: $compra->empresa_id ? (int) $compra->empresa_id : null,
                                origemTipo: $doc['origemTipo'],
                                origemId: $doc['origemId'],
                                origemNumero: $doc['origemNumero'],
                                docFiscalTipo: $doc['docFiscalTipo'],
                                docFiscalNumero: $doc['docFiscalNumero'],
                            ),
                        );

                        $linha = $this->linhaDoItem($rows, $item, $itemIndex);
                        if (is_array($linha) && ! empty($linha['controla_lote_validade']) && ! $product->controla_lote_validade) {
                            $product->forceFill(['controla_lote_validade' => true])->save();
                            $product->refresh();
                        }

                        if ($product->controla_lote_validade) {
                            $lotes = $this->lotesDaLinha(is_array($linha) ? $linha : []);
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

            $lotesFinalizados = $this->extrairLotesLancados($rows);

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
                    compraId: (int) $compra->id,
                );
            }

            if ($gerarFinanceiro) {
                $caixaLancamentoIds = $this->gerarContasPagar($compra, $parcelasFinanceiro);
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
                'lotes' => $lotesFinalizados,
                'caixa_lancamentos' => $caixaLancamentoIds,
            ],
            empresaId: $empresaId,
        );

        return $compra;
    }

    /**
     * Dinheiro/PIX saem direto do Livro Caixa; demais formas geram contas a pagar.
     *
     * @param  list<array{documento?: string, vencimento: string, valor: float|string, forma_pagamento_id?: int|null, caixa_conta_id?: int|null}>|null  $parcelasFinanceiro
     * @return list<int> ids dos lançamentos do Livro Caixa
     */
    private function gerarContasPagar(Compra $compra, ?array $parcelasFinanceiro): array
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

            $aPrazo = [];
            $aVista = [];
            foreach ($parcelasFinanceiro as $parcela) {
                $forma = $this->formaAVista((int) ($parcela['forma_pagamento_id'] ?? 0));
                if ($forma) {
                    $aVista[] = ['parcela' => $parcela, 'forma' => $forma];
                } else {
                    $aPrazo[] = $parcela;
                }
            }

            $caixaIds = $this->lancarParcelasAVistaNoCaixa($compra, $aVista);

            if ($aPrazo !== []) {
                $this->contasPagar->criarDeLista([
                    'emissao' => $emissao,
                    'fornecedor_id' => (int) $compra->fornecedor_id,
                    'historico' => $historico,
                    'documento' => $documentoBase,
                    'compra_id' => (int) $compra->id,
                ], $aPrazo);
            }

            return $caixaIds;
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

        return [];
    }

    private function formaAVista(int $formaId): ?FormaPagamento
    {
        if ($formaId <= 0) {
            return null;
        }

        $forma = FormaPagamento::query()->whereKey($formaId)->where('ativo', true)->first();
        if (! $forma) {
            return null;
        }

        $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
        $movimento = mb_strtolower(trim((string) ($forma->tipo_movimento ?? '')), 'UTF-8');

        return in_array($tipo, ['dinheiro', 'pix'], true) || $movimento === 'caixa' ? $forma : null;
    }

    /**
     * Saída direta no Livro Caixa (sem conta a pagar) para parcelas em dinheiro/PIX.
     *
     * @param  list<array{parcela: array<string, mixed>, forma: FormaPagamento}>  $aVista
     * @return list<int>
     */
    private function lancarParcelasAVistaNoCaixa(Compra $compra, array $aVista): array
    {
        if ($aVista === []) {
            return [];
        }

        $empresaId = (int) ($compra->empresa_id ?? 0);
        $planoId = EmpresaParametros::planoCompraId($empresaId);
        if (! $planoId) {
            throw new DomainException('Configure o Plano de Contas de Compra (débito) nos parâmetros da empresa.');
        }

        $planoNome = mb_substr(mb_strtoupper((string) PlanoConta::query()->whereKey($planoId)->value('descricao'), 'UTF-8'), 0, 80);
        $hoje = ErpTimezone::toLocal()->toDateString();
        $fornecedor = trim((string) ($compra->fornecedor?->nome_razao ?? ''));
        $base = 'COMPRA #'.$compra->numero
            .($compra->numero_nota ? ' NF '.$compra->numero_nota : '')
            .($fornecedor !== '' ? ' - '.$fornecedor : '');
        $temEmpresa = Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id');
        $ids = [];

        foreach ($aVista as ['parcela' => $parcela, 'forma' => $forma]) {
            $valor = round(ErpMoney::parseBr($parcela['valor'] ?? 0), 2);
            if ($valor <= 0) {
                throw new DomainException('Parcela em dinheiro ou PIX com valor inválido.');
            }

            $caixaId = (int) ($parcela['caixa_conta_id'] ?? 0);
            if ($caixaId > 0) {
                $caixaOk = CaixaConta::query()
                    ->whereKey($caixaId)
                    ->where('ativo', true)
                    ->where('tipo', CaixaConta::TIPO_SUBCAIXA)
                    ->exists();
            } else {
                $caixaId = (int) ($forma->conta_destino_id ?? 0);
                $caixaOk = $caixaId > 0 && CaixaConta::query()->whereKey($caixaId)->where('ativo', true)->exists();
            }

            if (! $caixaOk) {
                throw new DomainException('Informe um subcaixa válido na parcela em dinheiro ou PIX.');
            }

            $formaNome = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');
            $documentoParcela = trim((string) ($parcela['documento'] ?? ''));

            $payload = [
                'codigo' => CaixaLancamento::nextCodigo(),
                'emissao' => $hoje,
                'documento' => mb_substr('COMPRA-'.$compra->numero, 0, 40),
                'historico' => mb_substr(
                    $base
                    .(count($aVista) > 1 && $documentoParcela !== '' ? ' PARC '.$documentoParcela : '')
                    .($formaNome !== '' ? ' ('.$formaNome.')' : ''),
                    0,
                    180,
                ),
                'plano_contas' => $planoNome !== '' ? $planoNome : null,
                'plano_conta_id' => $planoId,
                'caixa_conta_id' => $caixaId,
                'entrada' => 0,
                'saida' => $valor,
            ];

            if ($temEmpresa) {
                $payload['empresa_id'] = $empresaId > 0 ? $empresaId : ErpContext::currentEmpresaId();
            }

            $ids[] = (int) CaixaLancamento::query()->create($payload)->id;
        }

        return $ids;
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
            $itemId = (int) ($row['compra_item_id'] ?? 0);
            $item = $itemId > 0
                ? $itens->firstWhere('id', $itemId)
                : $itens->get($index);

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
     * @return array<string, mixed>|null
     */
    private function linhaDoItem(array $rows, mixed $item, int $itemIndex): ?array
    {
        $itemId = (int) ($item->id ?? 0);

        if ($itemId > 0) {
            foreach ($rows as $row) {
                if (is_array($row) && (int) ($row['compra_item_id'] ?? 0) === $itemId) {
                    return $row;
                }
            }
        }

        $porIndice = $rows[$itemIndex] ?? null;

        if (is_array($porIndice) && (int) ($porIndice['compra_item_id'] ?? 0) <= 0) {
            return $porIndice;
        }

        return is_array($porIndice) && $itemId <= 0 ? $porIndice : null;
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return list<array{lote: string, data_validade: string, quantidade: float|string}>
     */
    private function lotesDaLinha(array $linha): array
    {
        $lotes = $linha['lotes'] ?? null;

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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{compra_item_id: int, product_id: int, lote: string, data_validade: string, quantidade: mixed}>
     */
    private function extrairLotesLancados(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($this->lotesDaLinha($row) as $lote) {
                if (trim($lote['lote']) === '' && trim($lote['data_validade']) === '') {
                    continue;
                }

                $out[] = [
                    'compra_item_id' => (int) ($row['compra_item_id'] ?? 0),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'lote' => $lote['lote'],
                    'data_validade' => $lote['data_validade'],
                    'quantidade' => $lote['quantidade'],
                ];
            }
        }

        return $out;
    }
}
