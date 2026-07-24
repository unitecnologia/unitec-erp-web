<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class ContaReceberBaixaService
{
    /**
     * Formas ativas para baixa em Contas a Receber.
     *
     * Prefere `aparece_contas_receber`; se nenhuma estiver marcada, usa todas as ativas.
     *
     * @return list<array{id: int, label: string, tipo: string|null}>
     */
    public function formasDisponiveis(): array
    {
        $base = FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao');

        $query = (clone $base)->where('aparece_contas_receber', true);
        $formas = $query->get(['id', 'codigo', 'descricao', 'tipo']);

        if ($formas->isEmpty()) {
            $formas = $base->get(['id', 'codigo', 'descricao', 'tipo']);
        }

        return $formas
            ->map(function (FormaPagamento $forma): array {
                $codigo = (int) ($forma->codigo ?? 0);
                $descricao = trim((string) ($forma->descricao ?? ''));
                $label = $codigo > 0
                    ? str_pad((string) $codigo, 2, '0', STR_PAD_LEFT).' — '.($descricao !== '' ? $descricao : 'Sem descrição')
                    : ($descricao !== '' ? $descricao : 'Forma #'.$forma->id);

                return [
                    'id' => (int) $forma->id,
                    'label' => $label,
                    'tipo' => $forma->tipo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $contaIds
     * @return array{ok: int, total: float}
     */
    public function baixarMuitas(array $contaIds, int $formaPagamentoId): array
    {
        $ids = collect($contaIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw new InvalidArgumentException('Nenhuma conta selecionada para baixar.');
        }

        $forma = FormaPagamento::query()
            ->whereKey($formaPagamentoId)
            ->where('ativo', true)
            ->first();

        if (! $forma) {
            throw new InvalidArgumentException('Meio de pagamento inválido ou inativo.');
        }

        $formaConta = $this->mapFormaConta($forma);
        $hoje = ErpTimezone::toLocal()->toDateString();
        $caixaContaId = (int) ($forma->conta_destino_id ?? 0);

        $ok = 0;
        $total = 0.0;

        DB::transaction(function () use ($ids, $formaConta, $hoje, $caixaContaId, &$ok, &$total): void {
            $contas = ContaReceber::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($contas as $conta) {
                $saldo = round((float) $conta->saldo, 2);

                if ($saldo <= 0) {
                    continue;
                }

                $conta->valor_recebido = round((float) $conta->valor_recebido + $saldo, 2);
                $conta->recebido_em = $hoje;
                $conta->forma = $formaConta;
                $conta->save();

                $this->lancarEntradaCaixa(
                    valor: $saldo,
                    data: $hoje,
                    documento: (string) ($conta->documento ?: $conta->numero ?: ('CR-'.$conta->id)),
                    historico: 'Recebimento conta a receber #'.($conta->numero ?: $conta->id),
                    caixaContaId: $caixaContaId > 0 ? $caixaContaId : null,
                );

                $ok++;
                $total += $saldo;
            }
        });

        return [
            'ok' => $ok,
            'total' => round($total, 2),
        ];
    }

    private function lancarEntradaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
    ): void {
        try {
            if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
                return;
            }

            CaixaLancamento::query()->create([
                'codigo' => CaixaLancamento::nextCodigo(),
                'emissao' => $data,
                'documento' => mb_substr($documento, 0, 40),
                'historico' => mb_substr($historico, 0, 180),
                'plano_contas' => null,
                'plano_conta_id' => null,
                'caixa_conta_id' => $caixaContaId,
                'entrada' => $valor,
                'saida' => 0,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Registra entrada no Livro Caixa (ex.: baixa à vista / faturamento FV).
     */
    public function registrarEntradaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
    ): void {
        $this->lancarEntradaCaixa($valor, $data, $documento, $historico, $caixaContaId);
    }

    public function mapFormaConta(FormaPagamento $forma): string
    {
        $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
        $descricao = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return match (true) {
            $tipo === 'pix' || str_contains($descricao, 'PIX') => ContaReceber::FORMA_PIX,
            $tipo === 'cheque' || str_contains($descricao, 'CHEQUE') => ContaReceber::FORMA_CHEQUE,
            $tipo === 'boleto' || str_contains($descricao, 'BOLETO') => ContaReceber::FORMA_BOLETO,
            $tipo === 'crediario'
                || str_contains($descricao, 'CREDI')
                || str_contains($descricao, 'PRAZO')
                || str_contains($descricao, 'CARTEIRA') => ContaReceber::FORMA_CARTEIRA,
            in_array($tipo, ['cartao_debito', 'cartao_credito', 'tef'], true)
                || str_contains($descricao, 'CART')
                || str_contains($descricao, 'TEF')
                || str_contains($descricao, 'POS') => ContaReceber::FORMA_CARTAO,
            // Dinheiro / depósito / demais à vista → dinheiro (aparece no gráfico)
            $tipo === 'dinheiro'
                || str_contains($descricao, 'DINHEIRO')
                || str_contains($descricao, 'ESPÉCIE')
                || str_contains($descricao, 'ESPECIE') => 'dinheiro',
            $tipo === 'deposito' || str_contains($descricao, 'DEPOSIT') => 'deposito',
            default => ContaReceber::FORMA_CARTEIRA,
        };
    }
}
