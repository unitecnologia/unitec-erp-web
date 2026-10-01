<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Baixa de Contas a Receber + lançamento no Livro Caixa.
 *
 * Regra operacional de caixa (PDV vs Força de Vendas):
 * - PDV: dinheiro/cartão à vista entram em `pdv_caixa_movimentos` da sessão
 *   aberta; o Livro Caixa (`caixa_lancamentos`) recebe o consolidado no
 *   fechamento da sessão (`PdvCaixaFechamentoService`).
 * - Contas a Receber / Força de Vendas: baixa ou faturamento à vista
 *   (dinheiro/PIX) registram entrada direto no Livro Caixa neste serviço.
 * - PIX de título (`PixCobrancaService`): baixa o CR e também gera entrada
 *   no Livro Caixa (mesmo caminho deste serviço).
 */
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
     * @param  array{
     *     perc_juros?: float,
     *     juros?: float,
     *     perc_desconto?: float,
     *     desconto?: float,
     *     valor_recebido?: float|null,
     *     recebido_em?: string|null,
     *     numero_cheque?: string|null,
     *     plano_conta_id?: int|null,
     *     multa?: float
     * }  $opcoes
     * @return array{ok: int, total: float, parciais: int}
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
            throw new InvalidArgumentException('Nenhuma conta selecionada para baixar.');
        }

        $forma = FormaPagamento::query()
            ->whereKey($formaPagamentoId)
            ->where('ativo', true)
            ->first();

        if (! $forma) {
            throw new InvalidArgumentException('Meio de pagamento inválido ou inativo.');
        }

        $hoje = ErpTimezone::toLocal()->toDateString();
        $caixaContaId = (int) ($forma->conta_destino_id ?? 0);

        $recebidoEm = trim((string) ($opcoes['recebido_em'] ?? ''));
        if ($recebidoEm === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $recebidoEm)) {
            $recebidoEm = $hoje;
        }

        $percJuros = round((float) ($opcoes['perc_juros'] ?? 0), 4);
        $jurosInformado = round((float) ($opcoes['juros'] ?? 0), 2);
        $multaInformada = round((float) ($opcoes['multa'] ?? 0), 2);
        $percDesconto = round((float) ($opcoes['perc_desconto'] ?? 0), 4);
        $descontoInformado = round((float) ($opcoes['desconto'] ?? 0), 2);
        $valorInformado = array_key_exists('valor_recebido', $opcoes) && $opcoes['valor_recebido'] !== null
            ? round((float) $opcoes['valor_recebido'], 2)
            : null;
        $numeroCheque = trim((string) ($opcoes['numero_cheque'] ?? ''));
        $personalizada = count($ids) === 1 && $valorInformado !== null;
        $planoContaId = filled($opcoes['plano_conta_id'] ?? null) ? (int) $opcoes['plano_conta_id'] : null;
        $planoNome = null;

        if ($planoContaId) {
            $plano = PlanoConta::query()
                ->whereKey($planoContaId)
                ->where('ativo', true)
                ->where('dc', 'C')
                ->first();

            if (! $plano) {
                throw new InvalidArgumentException('Plano de contas inválido.');
            }

            $planoNome = mb_substr(mb_strtoupper((string) $plano->descricao, 'UTF-8'), 0, 120);
        }

        $ok = 0;
        $total = 0.0;
        $parciais = 0;

        DB::transaction(function () use (
            $ids,
            $forma,
            $recebidoEm,
            $caixaContaId,
            $percJuros,
            $jurosInformado,
            $multaInformada,
            $percDesconto,
            $descontoInformado,
            $valorInformado,
            $numeroCheque,
            $personalizada,
            $planoContaId,
            $planoNome,
            &$ok,
            &$total,
            &$parciais,
        ): void {
            $contas = ContaReceber::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($contas as $conta) {
                if ($personalizada) {
                    $saldo = round((float) $conta->saldo, 2);

                    if ($saldo <= 0) {
                        continue;
                    }

                    $juros = $jurosInformado;
                    $desconto = $descontoInformado;
                    $percJ = $percJuros;
                    $percD = $percDesconto;
                    $multa = round((float) $conta->multa, 2) > 0.009
                        ? 0.0
                        : $multaInformada;

                    if ($juros <= 0 && $percJ > 0) {
                        $juros = round($saldo * ($percJ / 100), 2);
                    }

                    $saldoComJuros = round($saldo + $juros + $multa, 2);

                    if ($desconto <= 0 && $percD > 0) {
                        $desconto = round($saldoComJuros * ($percD / 100), 2);
                    }

                    $desconto = min($desconto, $saldoComJuros);
                    $valorAReceber = round(max(0, $saldoComJuros - $desconto), 2);
                    $valorRecebido = $valorInformado ?? $valorAReceber;

                    if ($valorRecebido <= 0) {
                        throw new InvalidArgumentException('Informe o valor recebido.');
                    }

                    if ($valorRecebido > $valorAReceber + 0.009) {
                        throw new InvalidArgumentException('Valor recebido maior que o saldo.');
                    }
                } else {
                    $jurosAntes = round((float) $conta->juros, 2);
                    $multa = ContaReceberJurosCarteira::calcularMulta($conta, \Carbon\Carbon::parse($recebidoEm));
                    $conta->multa = round((float) $conta->multa + $multa, 2);
                    ContaReceberJurosCarteira::aplicarNaConta($conta, \Carbon\Carbon::parse($recebidoEm));
                    $conta->save();

                    $saldo = round((float) $conta->saldo, 2);

                    if ($saldo <= 0) {
                        continue;
                    }

                    $juros = round(max(0, (float) $conta->juros - $jurosAntes), 2);
                    $desconto = 0.0;
                    $percJ = 0.0;
                    $percD = 0.0;
                    $valorAReceber = $saldo;
                    $valorRecebido = $saldo;
                }

                $this->registrarPagamento(
                    $conta,
                    (int) $forma->id,
                    $recebidoEm,
                    $juros,
                    $desconto,
                    $percJ,
                    $percD,
                    $valorRecebido,
                    $numeroCheque !== '' ? $numeroCheque : null,
                    $caixaContaId > 0 ? $caixaContaId : null,
                    $planoContaId,
                    $multa,
                );

                $conta->juros = round((float) $conta->juros + ($personalizada ? $juros : 0), 2);
                $conta->multa = round((float) $conta->multa + ($personalizada ? $multa : 0), 2);
                $conta->desconto = round((float) $conta->desconto + ($personalizada ? $desconto : 0), 2);
                $conta->valor_recebido = round((float) $conta->valor_recebido + $valorRecebido, 2);
                $conta->recebido_em = $recebidoEm;
                $conta->save();

                $this->lancarEntradaCaixa(
                    valor: $valorRecebido,
                    data: $recebidoEm,
                    documento: (string) ($conta->documento ?: $conta->numero ?: ('CR-'.$conta->id)),
                    historico: 'Recebimento conta a receber #'.($conta->numero ?: $conta->id),
                    caixaContaId: $caixaContaId > 0 ? $caixaContaId : null,
                    empresaId: $conta->empresa_id ? (int) $conta->empresa_id : null,
                    planoContaId: $planoContaId,
                    planoNome: $planoNome,
                );

                $ok++;
                $total += $valorRecebido;

                if ($valorRecebido + 0.009 < $valorAReceber) {
                    $parciais++;
                }
            }
        });

        return [
            'ok' => $ok,
            'total' => round($total, 2),
            'parciais' => $parciais,
        ];
    }

    private function registrarPagamento(
        ContaReceber $conta,
        int $formaPagamentoId,
        string $data,
        float $juros,
        float $desconto,
        float $percJuros,
        float $percDesconto,
        float $valorRecebido,
        ?string $numeroCheque,
        ?int $caixaContaId,
        ?int $planoContaId = null,
        float $multa = 0,
    ): void {
        $max = (int) ContaReceberPagamento::query()->max('codigo_legado');

        ContaReceberPagamento::query()->create([
            'codigo_legado' => max($max + 1, 1),
            'conta_receber_id' => (int) $conta->id,
            'data' => $data,
            'valor_parcela' => round((float) $conta->valor, 2),
            'perc_juros' => $percJuros,
            'juros' => $juros,
            'multa' => round($multa, 2),
            'perc_desconto' => $percDesconto,
            'desconto' => $desconto,
            'valor_recebido' => $valorRecebido,
            'forma_pagamento_id' => $formaPagamentoId,
            'caixa_conta_id' => $caixaContaId,
            'plano_conta_id' => $planoContaId,
            'numero_cheque' => $numeroCheque,
            'cliente_id' => $conta->cliente_id,
        ]);
    }

    private function lancarCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $empresaId = null,
        bool $saida = false,
        ?int $planoContaId = null,
        ?string $planoNome = null,
    ): void {
        if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
            return;
        }

        $payload = [
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $data,
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $planoNome,
            'plano_conta_id' => $planoContaId,
            'caixa_conta_id' => $caixaContaId,
            'entrada' => $saida ? 0 : $valor,
            'saida' => $saida ? $valor : 0,
        ];

        if (Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $payload['empresa_id'] = $empresaId ?? \App\Support\Erp\ErpContext::currentEmpresaId();
        }

        CaixaLancamento::query()->create($payload);
    }

    private function lancarEntradaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $empresaId = null,
        ?int $planoContaId = null,
        ?string $planoNome = null,
    ): void {
        $this->lancarCaixa($valor, $data, $documento, $historico, $caixaContaId, $empresaId, false, $planoContaId, $planoNome);
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
        ?int $empresaId = null,
        ?int $planoContaId = null,
        ?string $planoNome = null,
    ): void {
        $this->lancarEntradaCaixa($valor, $data, $documento, $historico, $caixaContaId, $empresaId, $planoContaId, $planoNome);
    }

    /**
     * Registra saída no Livro Caixa (estorno de entrada do faturamento FV).
     */
    public function registrarSaidaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $empresaId = null,
        ?int $planoContaId = null,
        ?string $planoNome = null,
    ): void {
        $this->lancarCaixa($valor, $data, $documento, $historico, $caixaContaId, $empresaId, true, $planoContaId, $planoNome);
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
