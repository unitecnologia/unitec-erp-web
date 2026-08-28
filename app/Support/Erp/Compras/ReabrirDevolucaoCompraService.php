<?php

namespace App\Support\Erp\Compras;

use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeDevolucaoCompraService;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ProductLoteService;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reabre devolução de compra finalizada: estorna a baixa de estoque/lote
 * e devolve a situação para aberta (editável no lançamento).
 */
final class ReabrirDevolucaoCompraService
{
    public const OPERACAO = 'REABRIR_DEVOLUCAO_COMPRA';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ProductLoteService $lotes = new ProductLoteService(),
        private readonly NfeDevolucaoCompraService $nfeDevolucao = new NfeDevolucaoCompraService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * @throws DomainException
     */
    public function reabrir(DevolucaoCompra $devolucao): DevolucaoCompra
    {
        $devolucao->loadMissing(['itens.product', 'compra']);

        if ($devolucao->situacao === DevolucaoCompra::SITUACAO_CANCELADA) {
            throw new DomainException('Devolução cancelada não pode ser reaberta.');
        }

        if ($devolucao->situacao !== DevolucaoCompra::SITUACAO_FINALIZADA) {
            throw new DomainException('Só é possível reabrir devolução finalizada.');
        }

        if ($this->nfeDevolucao->temNfeAtiva($devolucao)) {
            throw new DomainException('Esta devolução possui NF-e vinculada. Cancele a NF-e antes de reabrir.');
        }

        $empresaId = $devolucao->empresa_id
            ? (int) $devolucao->empresa_id
            : ($devolucao->compra?->empresa_id ? (int) $devolucao->compra->empresa_id : ErpContext::currentEmpresaId());
        $estoqueId = $this->resolveEstoqueId($empresaId);
        $total = round((float) $devolucao->total, 2);

        DB::transaction(function () use ($devolucao, $estoqueId, $empresaId): void {
            $this->estornarEstoque($devolucao, $estoqueId, $empresaId);

            $devolucao->update([
                'situacao' => DevolucaoCompra::SITUACAO_ABERTA,
            ]);
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Devolução compra #'.$devolucao->numero.' reaberta (estorno de estoque).',
            origem: 'devolucao_compra',
            documentoTipo: 'devolucao_compra',
            documentoId: (int) $devolucao->id,
            documentoNumero: (string) $devolucao->numero,
            detalhes: [
                'compra_id' => $devolucao->compra_id,
                'compra_numero' => $devolucao->compra_numero,
                'total' => $total,
            ],
            empresaId: $empresaId,
        );

        return $devolucao->fresh(['itens', 'compra']) ?? $devolucao;
    }

    private function estornarEstoque(DevolucaoCompra $devolucao, ?int $estoqueId, ?int $empresaId = null): void
    {
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        foreach ($devolucao->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);

            if (! $product || $product->is_servico) {
                continue;
            }

            $this->saldos->incrementar(
                (int) $product->id,
                (float) $item->qtd,
                $estoqueId,
                $empresa,
            );

            if ($product->controla_lote_validade) {
                try {
                    $this->lotes->devolver($product, (float) $item->qtd);
                } catch (RuntimeException $e) {
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
