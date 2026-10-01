<?php

namespace App\Support\Erp\Compras;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\DevolucaoCompra;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueMovimentacaoDocumento;
use App\Support\Erp\ProductEstoqueSaldoService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Finaliza devolução de compra: baixa estoque (retorno ao fornecedor).
 *
 * Cancelamento de devolução finalizada não é suportado aqui — só aberta
 * (ver ListDevolucoesCompra::cancelDevolucao).
 */
final class FinalizarDevolucaoCompraService
{
    public const OPERACAO = 'FINALIZAR_DEVOLUCAO_COMPRA';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * Aplica efeitos de finalização na devolução já persistida (situacao ainda
     * pode estar aberta; este método marca finalizada ao concluir).
     *
     * @throws DomainException
     */
    public function finalizar(DevolucaoCompra $devolucao): DevolucaoCompra
    {
        $devolucao->loadMissing(['itens.product', 'compra']);

        if ($devolucao->situacao === DevolucaoCompra::SITUACAO_FINALIZADA) {
            return $devolucao;
        }

        if ($devolucao->situacao === DevolucaoCompra::SITUACAO_CANCELADA) {
            throw new DomainException('Devolução cancelada não pode ser finalizada.');
        }

        if ($devolucao->situacao !== DevolucaoCompra::SITUACAO_ABERTA) {
            throw new DomainException('Somente devolução aberta pode ser finalizada.');
        }

        $compra = $devolucao->compra;

        if (! $compra) {
            throw new DomainException('Devolução sem compra de origem.');
        }

        if ($compra->status === Compra::STATUS_CANCELADA) {
            throw new DomainException('Não é possível devolver compra cancelada.');
        }

        if ($devolucao->itens->isEmpty()) {
            throw new DomainException('Devolução sem itens.');
        }

        $this->validarQuantidades($devolucao, $compra);

        $total = round((float) $devolucao->total, 2);
        $empresaId = $devolucao->empresa_id
            ? (int) $devolucao->empresa_id
            : ($compra->empresa_id ? (int) $compra->empresa_id : ErpContext::currentEmpresaId());
        $estoqueId = $this->resolveEstoqueId($empresaId);

        DB::transaction(function () use ($devolucao, $estoqueId, $empresaId): void {
            $this->baixarEstoque($devolucao, $estoqueId, $empresaId);

            $devolucao->update([
                'situacao' => DevolucaoCompra::SITUACAO_FINALIZADA,
            ]);
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Devolução compra #'.$devolucao->numero.' finalizada (baixa de estoque).',
            origem: 'devolucao_compra',
            documentoTipo: 'devolucao_compra',
            documentoId: (int) $devolucao->id,
            documentoNumero: (string) $devolucao->numero,
            detalhes: [
                'compra_id' => $compra->id,
                'compra_numero' => $compra->numero,
                'total' => $total,
            ],
            empresaId: $empresaId,
        );

        return $devolucao->fresh(['itens', 'compra']) ?? $devolucao;
    }

    private function validarQuantidades(DevolucaoCompra $devolucao, Compra $compra): void
    {
        $fiscal = app(\App\Support\Erp\NotaFornecedor\NotaFornecedorDevolucaoFiscalService::class);

        foreach ($devolucao->itens as $item) {
            $qtd = round((float) $item->qtd, 3);

            if ($qtd <= 0) {
                throw new DomainException('Item com quantidade inválida na devolução.');
            }

            $compraItemId = $item->compra_item_id ? (int) $item->compra_item_id : 0;

            if ($compraItemId <= 0) {
                continue;
            }

            $compraItem = CompraItem::query()->with('notaFornecedorItem')->find($compraItemId);

            if (! $compraItem) {
                continue;
            }

            try {
                $fiscal->assertQuantidadePermitida(
                    $compraItem,
                    $qtd,
                    (int) $devolucao->id,
                );
            } catch (DomainException $e) {
                $desc = $item->produto_descricao ?: ('item #'.$item->item);
                throw new DomainException(
                    "Quantidade devolvida de \"{$desc}\" excede o disponível. ".$e->getMessage()
                );
            }
        }
    }

    private function baixarEstoque(DevolucaoCompra $devolucao, ?int $estoqueId, ?int $empresaId = null): void
    {
        $empresa = $empresaId ? \App\Models\Empresa::query()->find($empresaId) : null;

        foreach ($devolucao->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);

            if (! $product || $product->is_servico) {
                continue;
            }

            $doc = EstoqueMovimentacaoDocumento::fromDevolucaoCompra($devolucao);
            $this->saldos->decrementar(
                (int) $product->id,
                (float) $item->qtd,
                $estoqueId,
                $empresa,
                EstoqueMovimentacaoContext::make(
                    EstoqueMovimentacao::TIPO_DEVOLUCAO_COMPRA,
                    empresaId: $empresaId,
                    origemTipo: $doc['origemTipo'],
                    origemId: $doc['origemId'],
                    origemNumero: $doc['origemNumero'],
                    docFiscalTipo: $doc['docFiscalTipo'],
                    docFiscalNumero: $doc['docFiscalNumero'],
                ),
            );

            if ($product->controla_lote_validade) {
                try {
                    (new \App\Support\Erp\ProductLoteService())->consumirFefo($product, (float) $item->qtd);
                } catch (\RuntimeException $e) {
                    throw new DomainException($e->getMessage(), 0, $e);
                }
            }
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
}
