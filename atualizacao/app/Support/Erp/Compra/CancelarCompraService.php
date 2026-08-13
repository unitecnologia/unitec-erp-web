<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\NotaFornecedor;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ProductEstoqueSaldoService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cancela compra: se veio de nota com entrada de estoque, estorna o estoque
 * e devolve a nota para "Aceita" (pode gerar de novo). Senão, só status.
 */
final class CancelarCompraService
{
    public const OPERACAO = 'CANCELAR_COMPRA';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * @throws DomainException
     */
    public function cancelar(Compra $compra): Compra
    {
        if ($compra->status === Compra::STATUS_CANCELADA) {
            throw new DomainException('Compra já está cancelada.');
        }

        $nota = NotaFornecedor::query()
            ->where('compra_id', $compra->id)
            ->first();

        $teveEntradaEstoque = $compra->status === Compra::STATUS_FECHADA
            && $nota !== null
            && $nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS;

        $empresaId = $compra->empresa_id ? (int) $compra->empresa_id : null;
        $estoqueId = $this->resolveEstoqueId($empresaId);

        DB::transaction(function () use ($compra, $nota, $teveEntradaEstoque, $estoqueId, $empresaId): void {
            if ($teveEntradaEstoque) {
                $compra->loadMissing('itens');
                $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

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

                if ($nota) {
                    $nota->forceFill([
                        'status' => NotaFornecedor::STATUS_ACEITA,
                        // Mantém compra_id para auditoria; nova geração cria outra compra.
                    ])->save();
                }
            }

            $compra->update([
                'status' => Compra::STATUS_CANCELADA,
                'lancamento_draft' => null,
            ]);
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: $teveEntradaEstoque
                ? 'Compra #'.$compra->numero.' cancelada com estorno de estoque.'
                : 'Compra #'.$compra->numero.' cancelada (sem entrada de estoque).',
            origem: 'lista_compras',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: [
                'estoque_estornado' => $teveEntradaEstoque,
                'nota_id' => $nota?->id,
            ],
            empresaId: $empresaId,
        );

        return $compra->fresh() ?? $compra;
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
