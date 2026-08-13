<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Baixa de Contas a Pagar + saída no Livro Caixa.
 *
 * Falha ao lançar no Livro Caixa propaga a exceção (e a transaction da baixa
 * faz rollback) — não engole erro silenciosamente.
 */
final class ContaPagarBaixaService
{
    /**
     * Formas ativas para baixa em Contas a Pagar.
     *
     * @return list<array{id: int, label: string, tipo: string|null, caixa_conta_id: int|null}>
     */
    public function formasDisponiveis(): array
    {
        $formas = FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao', 'tipo', 'conta_destino_id']);

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
                    'caixa_conta_id' => filled($forma->conta_destino_id) ? (int) $forma->conta_destino_id : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function planosDisponiveis(): array
    {
        return PlanoConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PlanoConta $plano): array => [
                'id' => (int) $plano->id,
                'label' => trim((string) $plano->codigo).' — '.mb_strtoupper((string) $plano->descricao, 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function caixasDisponiveis(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn (CaixaConta $caixa): array => [
                'id' => (int) $caixa->id,
                'label' => trim((string) $caixa->codigo).' — '.mb_strtoupper((string) $caixa->nome, 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    /**
     * Baixa total do saldo de um título a pagar (compatível com fluxo antigo).
     *
     * @return array{ok: int, total: float}
     */
    public function baixarUma(int $contaId, int $formaPagamentoId, array $opcoes = []): array
    {
        return $this->baixarMuitas([$contaId], $formaPagamentoId, $opcoes);
    }

    /**
     * @param  list<int>  $contaIds
     * @param  array{
     *     plano_conta_id?: int|null,
     *     caixa_conta_id?: int|null,
     *     perc_juros?: float,
     *     juros?: float,
     *     perc_desconto?: float,
     *     desconto?: float,
     *     valor_pago?: float|null,
     *     pago_em?: string|null
     * }  $opcoes
     * @return array{ok: int, total: float}
     */
    public function baixarMuitas(array $contaIds, int $formaPagamentoId, array $opcoes = []): array
    {
        $ids = collect($contaIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw new InvalidArgumentException('Nenhuma conta selecionada para pagar.');
        }

        $forma = FormaPagamento::query()
            ->whereKey($formaPagamentoId)
            ->where('ativo', true)
            ->first();

        if (! $forma) {
            throw new InvalidArgumentException('Meio de pagamento inválido ou inativo.');
        }

        $pagoEm = trim((string) ($opcoes['pago_em'] ?? ''));
        if ($pagoEm === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pagoEm)) {
            $pagoEm = ErpTimezone::toLocal()->toDateString();
        }

        $planoContaId = filled($opcoes['plano_conta_id'] ?? null) ? (int) $opcoes['plano_conta_id'] : null;
        if ($planoContaId && ! PlanoConta::query()->whereKey($planoContaId)->where('ativo', true)->exists()) {
            throw new InvalidArgumentException('Plano de contas inválido.');
        }

        $caixaContaId = filled($opcoes['caixa_conta_id'] ?? null)
            ? (int) $opcoes['caixa_conta_id']
            : (int) ($forma->conta_destino_id ?? 0);

        if ($caixaContaId > 0 && ! CaixaConta::query()->whereKey($caixaContaId)->where('ativo', true)->exists()) {
            throw new InvalidArgumentException('Conta de destino inválida.');
        }

        $percJuros = round((float) ($opcoes['perc_juros'] ?? 0), 4);
        $jurosInformado = round((float) ($opcoes['juros'] ?? 0), 2);
        $percDesconto = round((float) ($opcoes['perc_desconto'] ?? 0), 4);
        $descontoInformado = round((float) ($opcoes['desconto'] ?? 0), 2);
        $valorPagoInformado = array_key_exists('valor_pago', $opcoes) && $opcoes['valor_pago'] !== null
            ? round((float) $opcoes['valor_pago'], 2)
            : null;

        $ok = 0;
        $total = 0.0;

        DB::transaction(function () use (
            $ids,
            $forma,
            $pagoEm,
            $planoContaId,
            $caixaContaId,
            $percJuros,
            $jurosInformado,
            $percDesconto,
            $descontoInformado,
            $valorPagoInformado,
            &$ok,
            &$total,
        ): void {
            $contas = ContaPagar::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $unica = count($ids) === 1;

            foreach ($contas as $conta) {
                $saldo = round((float) $conta->saldo, 2);

                if ($saldo <= 0) {
                    continue;
                }

                $juros = $unica ? $jurosInformado : 0.0;
                $desconto = $unica ? $descontoInformado : 0.0;
                $percJ = $unica ? $percJuros : 0.0;
                $percD = $unica ? $percDesconto : 0.0;

                if ($unica && $juros <= 0 && $percJ > 0) {
                    $juros = round($saldo * ($percJ / 100), 2);
                }

                $saldoComJuros = round($saldo + $juros, 2);

                if ($unica && $desconto <= 0 && $percD > 0) {
                    $desconto = round($saldoComJuros * ($percD / 100), 2);
                }

                $desconto = min($desconto, $saldoComJuros);
                $valorAPagar = round(max(0, $saldoComJuros - $desconto), 2);

                $valorPago = $unica && $valorPagoInformado !== null
                    ? $valorPagoInformado
                    : $valorAPagar;

                if ($valorPago <= 0) {
                    throw new InvalidArgumentException('Informe o valor pago.');
                }

                if ($valorPago > $valorAPagar + 0.009) {
                    throw new InvalidArgumentException('Valor pago maior que o valor a pagar.');
                }

                ContaPagarPagamento::query()->create([
                    'codigo_legado' => $this->nextCodigoLegado(),
                    'conta_pagar_id' => (int) $conta->id,
                    'data' => $pagoEm,
                    'valor_parcela' => round((float) $conta->valor, 2),
                    'perc_juros' => $percJ,
                    'juros' => $juros,
                    'perc_desconto' => $percD,
                    'desconto' => $desconto,
                    'valor_pago' => $valorPago,
                    'plano_conta_id' => $planoContaId,
                    'forma_pagamento_id' => (int) $forma->id,
                    'caixa_conta_id' => $caixaContaId > 0 ? $caixaContaId : null,
                    'fornecedor_id' => $conta->fornecedor_id,
                ]);

                $conta->juros = round((float) $conta->juros + $juros, 2);
                $conta->desconto = round((float) $conta->desconto + $desconto, 2);
                $conta->valor_pago = round((float) $conta->valor_pago + $valorPago, 2);
                $conta->pago_em = $pagoEm;
                $conta->save();

                $this->lancarSaidaCaixa(
                    valor: $valorPago,
                    data: $pagoEm,
                    documento: (string) ($conta->documento ?: $conta->numero ?: ('CP-'.$conta->id)),
                    historico: 'Pagamento conta a pagar #'.($conta->numero ?: $conta->id),
                    caixaContaId: $caixaContaId > 0 ? $caixaContaId : null,
                    planoContaId: $planoContaId,
                );

                $ok++;
                $total += $valorPago;
            }
        });

        return [
            'ok' => $ok,
            'total' => round($total, 2),
        ];
    }

    private function nextCodigoLegado(): int
    {
        $max = (int) ContaPagarPagamento::query()->max('codigo_legado');

        return max($max + 1, 1);
    }

    private function lancarSaidaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $planoContaId = null,
    ): void {
        if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
            return;
        }

        $planoNome = null;
        if ($planoContaId) {
            $planoNome = PlanoConta::query()->whereKey($planoContaId)->value('descricao');
        }

        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $data,
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $planoNome ? mb_substr(mb_strtoupper((string) $planoNome, 'UTF-8'), 0, 80) : null,
            'plano_conta_id' => $planoContaId,
            'caixa_conta_id' => $caixaContaId,
            'entrada' => 0,
            'saida' => $valor,
        ]);
    }
}
