<?php

namespace App\Support\Erp\Pdv;

use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductGrade;
use App\Models\ProductSerial;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueNegativoPolicy;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ProductLoteService;
use Illuminate\Support\Facades\DB;

final class PdvStockService
{
    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    public function baixaItemVenda(
        Product $product,
        float $quantidade,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $docSaida = null,
        ?int $estoqueId = null,
        ?\App\Models\Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
        ?string $loteOrigemTipo = null,
        ?int $loteOrigemId = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->baixaSerial($productSerialId, $docSaida);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->baixaComposicao(
                $product,
                $quantidade,
                $docSaida,
                $estoqueId,
                $empresa,
                $movimentacao,
                $loteOrigemTipo,
                $loteOrigemId,
            );

            return;
        }

        // Estoque global e lotes na mesma transação: evita baixa global órfã se o FEFO falhar.
        DB::transaction(function () use (
            $product,
            $quantidade,
            $productGradeId,
            $productSerialId,
            $docSaida,
            $estoqueId,
            $empresa,
            $movimentacao,
            $loteOrigemTipo,
            $loteOrigemId,
        ): void {
            $bloquearNegativo = EstoqueNegativoPolicy::ativo($empresa);

            if ($bloquearNegativo) {
                EstoqueNegativoPolicy::garantirSaidaPermitida($product, $quantidade, $estoqueId, $empresa);

                if ($productGradeId && $product->contr_est_grade) {
                    if ($msg = $this->validaEstoqueGrade($product, $productGradeId, $quantidade)) {
                        throw new \RuntimeException($msg.' Bloqueio de estoque negativo está ativo.');
                    }
                }
            }

            if ($product->controla_lote_validade) {
                $lotes = new ProductLoteService();
                $fatias = $lotes->consumirFefo($product, $quantidade, permitirNegativo: ! $bloquearNegativo);
                if ($loteOrigemTipo && $loteOrigemId) {
                    $lotes->registrarSaidas($product, $fatias, $loteOrigemTipo, $loteOrigemId);
                }
            }

            $this->decrementarEstoqueProduto(
                $product,
                $quantidade,
                $estoqueId,
                $empresa,
                $this->ctxBaixa($movimentacao, $empresa, $docSaida),
            );

            if ($productGradeId && $product->contr_est_grade) {
                ProductGrade::query()
                    ->whereKey($productGradeId)
                    ->where('product_id', $product->id)
                    ->decrement('qtd', $quantidade);
            }

            if ($productSerialId) {
                $this->baixaSerial($productSerialId, $docSaida);
            }
        });
    }

    public function validaEstoqueComposicao(Product $product, float $quantidade, ?int $estoqueId = null): ?string
    {
        if (! $product->is_composicao) {
            return null;
        }

        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp || $comp->is_servico) {
                continue;
            }

            $qtdNecessaria = $quantidade * (float) $componente->quantidade;

            if ($comp->is_composicao) {
                $erro = $this->validaEstoqueComposicao($comp, $qtdNecessaria, $estoqueId);

                if ($erro) {
                    return $erro;
                }

                continue;
            }

            if (EstoqueNegativoPolicy::ativo()
                && $this->saldos->fisico((int) $comp->id, $estoqueId) < $qtdNecessaria
            ) {
                return 'Estoque insuficiente do componente: ' . $comp->descricao;
            }

            if ($comp->controla_lote_validade) {
                $lotes = new ProductLoteService();
                if (! $lotes->possuiLotes($comp)) {
                    return 'Componente "'.$comp->descricao.'" controla lote/validade sem lotes cadastrados.';
                }
                if (EstoqueNegativoPolicy::ativo()) {
                    $saldoLotes = $lotes->saldoDisponivel($comp);
                    if ($saldoLotes + 0.0005 < $qtdNecessaria) {
                        return 'Estoque por lote insuficiente do componente: '.$comp->descricao
                            .' (lotes: '.number_format($saldoLotes, 3, ',', '.').').';
                    }
                }
            }
        }

        return null;
    }

    public function validaEstoqueGrade(Product $product, ?int $productGradeId, float $quantidade): ?string
    {
        if (! $product->is_grade || ! $product->contr_est_grade || ! $productGradeId) {
            return null;
        }

        $grade = ProductGrade::query()
            ->whereKey($productGradeId)
            ->where('product_id', $product->id)
            ->first();

        if (! $grade) {
            return 'Grade não encontrada.';
        }

        if ((float) $grade->qtd < $quantidade) {
            return 'Quantidade grade insuficiente.';
        }

        return null;
    }

    private function baixaComposicao(
        Product $product,
        float $quantidade,
        ?string $docSaida,
        ?int $estoqueId = null,
        ?\App\Models\Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
        ?string $loteOrigemTipo = null,
        ?int $loteOrigemId = null,
    ): void {
        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp) {
                continue;
            }

            $qtd = $quantidade * (float) $componente->quantidade;
            $this->baixaItemVenda(
                $comp,
                $qtd,
                null,
                null,
                $docSaida,
                $estoqueId,
                $empresa,
                $movimentacao,
                $loteOrigemTipo,
                $loteOrigemId,
            );
        }
    }

    private function decrementarEstoqueProduto(
        Product $product,
        float $quantidade,
        ?int $estoqueId = null,
        ?\App\Models\Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
    ): void {
        $this->saldos->decrementar((int) $product->id, $quantidade, $estoqueId, $empresa, $movimentacao);
    }

    private function ctxBaixa(
        ?EstoqueMovimentacaoContext $movimentacao,
        ?\App\Models\Empresa $empresa,
        ?string $docSaida,
    ): EstoqueMovimentacaoContext {
        $empresaId = $empresa?->id !== null ? (int) $empresa->id : null;
        if ($empresaId === null || $empresaId <= 0) {
            $empresaId = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? 0) ?: null;
        }

        if ($movimentacao !== null) {
            if ($movimentacao->empresaId !== null && $movimentacao->empresaId > 0) {
                return $movimentacao;
            }

            return EstoqueMovimentacaoContext::make(
                $movimentacao->tipo,
                empresaId: $empresaId,
                origemTipo: $movimentacao->origemTipo,
                origemId: $movimentacao->origemId,
                origemNumero: $movimentacao->origemNumero,
                docFiscalTipo: $movimentacao->docFiscalTipo,
                docFiscalNumero: $movimentacao->docFiscalNumero,
                usuarioId: $movimentacao->usuarioId,
                observacao: $movimentacao->observacao,
            );
        }

        return EstoqueMovimentacaoContext::make(
            EstoqueMovimentacao::TIPO_VENDA,
            empresaId: $empresaId,
            observacao: $docSaida,
        );
    }

    private function ctxEstorno(?EstoqueMovimentacaoContext $movimentacao): EstoqueMovimentacaoContext
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? 0) ?: null;

        if ($movimentacao !== null) {
            if ($movimentacao->empresaId !== null && $movimentacao->empresaId > 0) {
                return $movimentacao;
            }

            return EstoqueMovimentacaoContext::make(
                $movimentacao->tipo,
                empresaId: $empresaId,
                origemTipo: $movimentacao->origemTipo,
                origemId: $movimentacao->origemId,
                origemNumero: $movimentacao->origemNumero,
                docFiscalTipo: $movimentacao->docFiscalTipo,
                docFiscalNumero: $movimentacao->docFiscalNumero,
                usuarioId: $movimentacao->usuarioId,
                observacao: $movimentacao->observacao,
            );
        }

        return EstoqueMovimentacaoContext::make(
            EstoqueMovimentacao::TIPO_DEVOLUCAO_VENDA,
            empresaId: $empresaId,
        );
    }

    private function baixaSerial(int $productSerialId, ?string $docSaida): void
    {
        ProductSerial::query()
            ->whereKey($productSerialId)
            ->where('situacao', 'DISPONIVEL')
            ->update([
                'situacao' => 'VENDIDO',
                'doc_saida' => $docSaida,
                'data_baixa' => now()->toDateString(),
            ]);
    }

    public function estornoItemVenda(
        Product $product,
        float $quantidade,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?int $estoqueId = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
        ?string $loteOrigemTipo = null,
        ?int $loteOrigemId = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->estornoSerial($productSerialId);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->estornoComposicao(
                $product,
                $quantidade,
                $estoqueId,
                $movimentacao,
                $loteOrigemTipo,
                $loteOrigemId,
            );

            return;
        }

        DB::transaction(function () use (
            $product,
            $quantidade,
            $productGradeId,
            $productSerialId,
            $estoqueId,
            $movimentacao,
            $loteOrigemTipo,
            $loteOrigemId,
        ): void {
            $this->saldos->incrementar(
                (int) $product->id,
                $quantidade,
                $estoqueId,
                null,
                $this->ctxEstorno($movimentacao),
            );

            if ($product->controla_lote_validade) {
                $lotes = new ProductLoteService();
                // Só restituí lotes com rastro da venda. Vendas antigas sem rastro: não inventa lote.
                if ($loteOrigemTipo && $loteOrigemId) {
                    $lotes->estornarPorOrigem($product, $loteOrigemTipo, $loteOrigemId, $quantidade);
                }
            }

            if ($productGradeId && $product->contr_est_grade) {
                ProductGrade::query()
                    ->whereKey($productGradeId)
                    ->where('product_id', $product->id)
                    ->increment('qtd', $quantidade);
            }

            if ($productSerialId) {
                $this->estornoSerial($productSerialId);
            }
        });
    }

    private function estornoComposicao(
        Product $product,
        float $quantidade,
        ?int $estoqueId = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
        ?string $loteOrigemTipo = null,
        ?int $loteOrigemId = null,
    ): void {
        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp) {
                continue;
            }

            $qtd = $quantidade * (float) $componente->quantidade;
            $this->estornoItemVenda(
                $comp,
                $qtd,
                null,
                null,
                $estoqueId,
                $movimentacao,
                $loteOrigemTipo,
                $loteOrigemId,
            );
        }
    }

    private function estornoSerial(int $productSerialId): void
    {
        ProductSerial::query()
            ->whereKey($productSerialId)
            ->update([
                'situacao' => 'DISPONIVEL',
                'doc_saida' => null,
                'data_baixa' => null,
            ]);
    }
}
