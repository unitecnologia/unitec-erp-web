<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Estorno de parcela/baixa de Contas a Pagar + contra-lançamento no Livro Caixa.
 */
final class ContaPagarEstornoService
{
    /**
     * @return array{ok: bool, valor: float}
     */
    public function estornarPagamento(int $pagamentoId): array
    {
        return DB::transaction(function () use ($pagamentoId): array {
            /** @var ContaPagarPagamento|null $pagamento */
            $pagamento = ContaPagarPagamento::query()
                ->with('contaPagar')
                ->whereKey($pagamentoId)
                ->lockForUpdate()
                ->first();

            if (! $pagamento) {
                throw new InvalidArgumentException('Parcela não encontrada.');
            }

            $conta = $pagamento->contaPagar;

            if (! $conta) {
                throw new InvalidArgumentException('Título da parcela não encontrado.');
            }

            /** @var ContaPagar $conta */
            $conta = ContaPagar::query()
                ->whereKey($conta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $valorPago = round((float) $pagamento->valor_pago, 2);
            $juros = round((float) $pagamento->juros, 2);
            $desconto = round((float) $pagamento->desconto, 2);

            if ($valorPago <= 0) {
                throw new InvalidArgumentException('Parcela sem valor pago para estornar.');
            }

            $conta->valor_pago = round(max(0, (float) $conta->valor_pago - $valorPago), 2);
            $conta->juros = round(max(0, (float) $conta->juros - $juros), 2);
            $conta->desconto = round(max(0, (float) $conta->desconto - $desconto), 2);

            // Recalcula saldo no saving(); se ainda houver saldo, limpa pago_em.
            $conta->save();

            $conta->refresh();
            if ((float) $conta->saldo > 0) {
                $conta->pago_em = null;
                $conta->save();
            }

            $this->desfazerSaidaDaBaixa($pagamento, $conta);

            $pagamento->delete();

            app(ComissaoPeriodoService::class)->syncStatusPagaFromContaPagar($conta->fresh() ?? $conta);

            return [
                'ok' => true,
                'valor' => $valorPago,
            ];
        });
    }

    /**
     * Contra-lançamento só da saída criada por esta baixa.
     * Baixa sem movimento de caixa (boleto, cartão, cheque etc.) não gera entrada.
     */
    private function desfazerSaidaDaBaixa(ContaPagarPagamento $pagamento, ContaPagar $conta): void
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return;
        }

        $saida = $this->localizarSaidaDaBaixa($pagamento, $conta);

        if (! $saida || (float) $saida->saida <= 0) {
            return;
        }

        $empresaId = $saida->empresa_id
            ?: ($conta->empresa_id ?: ErpContext::currentEmpresaId());

        $payload = [
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => optional($saida->emissao)?->toDateString() ?? optional($pagamento->data)?->toDateString() ?? now()->toDateString(),
            'documento' => mb_substr((string) ($saida->documento ?: ''), 0, 40),
            'historico' => app(ContaPagarBaixaService::class)->historicoEstorno($conta, (int) $pagamento->id),
            'plano_contas' => $saida->plano_contas,
            'plano_conta_id' => $saida->plano_conta_id,
            'caixa_conta_id' => $saida->caixa_conta_id,
            'entrada' => round((float) $saida->saida, 2),
            'saida' => 0,
        ];

        if (Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $payload['empresa_id'] = $empresaId;
        }

        CaixaLancamento::query()->create($payload);
    }

    private function localizarSaidaDaBaixa(ContaPagarPagamento $pagamento, ContaPagar $conta): ?CaixaLancamento
    {
        $marca = 'baixa:'.(int) $pagamento->id;

        $marcada = CaixaLancamento::query()
            ->where('saida', '>', 0)
            ->whereRaw('RIGHT(historico, ?) = ?', [strlen($marca), $marca])
            ->orderByDesc('id')
            ->first();

        if ($marcada) {
            return $marcada;
        }

        $numero = $conta->numero ?: $conta->id;
        $historicoLegado = 'Pagamento conta a pagar #'.$numero;
        $estornoLegado = 'Estorno pagamento conta a pagar #'.$numero;
        $valor = round((float) $pagamento->valor_pago, 2);
        $documento = mb_substr((string) ($conta->documento ?: $conta->numero ?: ('CP-'.$conta->id)), 0, 40);
        $data = optional($pagamento->data)?->toDateString();
        $caixaId = $pagamento->caixa_conta_id ? (int) $pagamento->caixa_conta_id : null;

        $saidas = CaixaLancamento::query()
            ->where('historico', $historicoLegado)
            ->where('saida', $valor)
            ->where('documento', $documento)
            ->when($caixaId, fn ($query) => $query->where('caixa_conta_id', $caixaId))
            ->when($data, fn ($query) => $query->whereDate('emissao', $data))
            ->orderBy('id')
            ->get();

        if ($saidas->isEmpty()) {
            return null;
        }

        $estornos = CaixaLancamento::query()
            ->where('historico', $estornoLegado)
            ->where('entrada', $valor)
            ->where('documento', $documento)
            ->when($caixaId, fn ($query) => $query->where('caixa_conta_id', $caixaId))
            ->when($data, fn ($query) => $query->whereDate('emissao', $data))
            ->count();

        return $saidas->slice($estornos, 1)->first();
    }
}
