<?php

namespace App\Support\Erp\Compras;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Models\Estoque;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
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
        $jaDevolvido = $this->quantidadesJaDevolvidas((int) $compra->id, (int) $devolucao->id);

        foreach ($devolucao->itens as $item) {
            $qtd = round((float) $item->qtd, 3);

            if ($qtd <= 0) {
                throw new DomainException('Item com quantidade inválida na devolução.');
            }

            $compraItemId = $item->compra_item_id ? (int) $item->compra_item_id : 0;
            $comprada = round((float) $item->qtd_comprada, 3);

            if ($compraItemId > 0) {
                $prev = $jaDevolvido[$compraItemId] ?? 0.0;
                $disponivel = round(max(0, $comprada - $prev), 3);

                if ($qtd > $disponivel + 0.0005) {
                    $desc = $item->produto_descricao ?: ('item #'.$item->item);
                    throw new DomainException(
                        "Quantidade devolvida de \"{$desc}\" excede o disponível ({$disponivel})."
                    );
                }
            }
        }
    }

    /**
     * @return array<int, float> compra_item_id => qtd já devolvida em devoluções finalizadas
     */
    private function quantidadesJaDevolvidas(int $compraId, int $excetoDevolucaoId): array
    {
        $rows = DevolucaoCompraItem::query()
            ->whereNotNull('compra_item_id')
            ->whereHas('devolucao', function ($q) use ($compraId, $excetoDevolucaoId): void {
                $q->where('compra_id', $compraId)
                    ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
                    ->where('id', '!=', $excetoDevolucaoId);
            })
            ->get(['compra_item_id', 'qtd']);

        $map = [];

        foreach ($rows as $row) {
            $id = (int) $row->compra_item_id;
            $map[$id] = round(($map[$id] ?? 0) + (float) $row->qtd, 3);
        }

        return $map;
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

            $this->saldos->decrementar(
                (int) $product->id,
                (float) $item->qtd,
                $estoqueId,
                $empresa,
            );
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
