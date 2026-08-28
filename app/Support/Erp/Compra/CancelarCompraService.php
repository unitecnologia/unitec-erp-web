<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\NotaFornecedor;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cancela compra aberta: devolve a nota para "Aceita" (pode gerar de novo).
 * Compra fechada deve ser reaberta (F7) antes — estorno completo fica só no Reabrir.
 */
final class CancelarCompraService
{
    public const OPERACAO = 'CANCELAR_COMPRA';

    public function __construct(
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

        if ($compra->status === Compra::STATUS_FECHADA) {
            throw new DomainException(
                'Compra fechada. Use F7 Reabrir para estornar estoque/financeiro; depois cancele.'
            );
        }

        if ($this->temDevolucaoFinalizada($compra)) {
            throw new DomainException(
                'Existe devolução de compra finalizada vinculada. Estorne a devolução antes de cancelar.'
            );
        }

        $nota = NotaFornecedor::query()
            ->where('compra_id', $compra->id)
            ->first();

        $empresaId = $compra->empresa_id ? (int) $compra->empresa_id : null;

        DB::transaction(function () use ($compra, $nota): void {
            if ($nota && $nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
                $nota->forceFill([
                    'status' => NotaFornecedor::STATUS_ACEITA,
                    // Mantém compra_id para auditoria; nova geração cria outra compra.
                ])->save();
            }

            $compra->update([
                'status' => Compra::STATUS_CANCELADA,
                'lancamento_draft' => null,
            ]);
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Compra #'.$compra->numero.' cancelada.',
            origem: 'lista_compras',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: [
                'nota_id' => $nota?->id,
                'nota_liberada' => $nota !== null && $nota->status === NotaFornecedor::STATUS_ACEITA,
            ],
            empresaId: $empresaId,
        );

        return $compra->fresh() ?? $compra;
    }

    /**
     * Notas com status gerou_compras cuja compra já está cancelada → Aceita.
     *
     * @return int quantidade de notas reparadas
     */
    public static function repararNotasComCompraCancelada(): int
    {
        return NotaFornecedor::query()
            ->where('status', NotaFornecedor::STATUS_GEROU_COMPRAS)
            ->whereNotNull('compra_id')
            ->whereIn('compra_id', Compra::query()
                ->select('id')
                ->where('status', Compra::STATUS_CANCELADA))
            ->update(['status' => NotaFornecedor::STATUS_ACEITA]);
    }

    private function temDevolucaoFinalizada(Compra $compra): bool
    {
        return DevolucaoCompra::query()
            ->where('compra_id', $compra->id)
            ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
            ->exists();
    }
}
