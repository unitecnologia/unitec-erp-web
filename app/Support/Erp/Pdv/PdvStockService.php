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
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->baixaSerial($productSerialId, $docSaida);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->baixaComposicao($product, $quantidade, $docSaida, $estoqueId, $empresa, $movimentacao);

            return;
        }

        if (EstoqueNegativoPolicy::ativo($empresa)) {
            EstoqueNegativoPolicy::garantirSaidaPermitida($product, $quantidade, $estoqueId, $empresa);

            if ($productGradeId && $product->contr_est_grade) {
                if ($msg = $this->validaEstoqueGrade($product, $productGradeId, $quantidade)) {
                    throw new \RuntimeException($msg.' Bloqueio de estoque negativo está ativo.');
                }
            }
        }

        $this->decrementarEstoqueProduto($product, $quantidade, $estoqueId, $empresa, $this->ctxBaixa($movimentacao, $empresa, $docSaida));

        if ($product->controla_lote_validade) {
            (new \App\Support\Erp\ProductLoteService())->consumirFefo($product, $quantidade);
        }

        if ($productGradeId && $product->contr_est_grade) {
            ProductGrade::query()
                ->whereKey($productGradeId)
                ->where('product_id', $product->id)
                ->decrement('qtd', $quantidade);
        }

        if ($productSerialId) {
            $this->baixaSerial($productSerialId, $docSaida);
        }
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

            if ($this->saldos->fisico((int) $comp->id, $estoqueId) < $qtdNecessaria) {
                return 'Estoque insuficiente do componente: ' . $comp->descricao;
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
            $this->baixaItemVenda($comp, $qtd, null, null, $docSaida, $estoqueId, $empresa, $movimentacao);
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
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->estornoSerial($productSerialId);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->estornoComposicao($product, $quantidade, $estoqueId, $movimentacao);

            return;
        }

        $this->saldos->incrementar((int) $product->id, $quantidade, $estoqueId, null, $this->ctxEstorno($movimentacao));

        if ($product->controla_lote_validade) {
            (new \App\Support\Erp\ProductLoteService())->devolver($product, $quantidade);
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
    }

    private function estornoComposicao(
        Product $product,
        float $quantidade,
        ?int $estoqueId = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
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
            $this->estornoItemVenda($comp, $qtd, null, null, $estoqueId, $movimentacao);
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
