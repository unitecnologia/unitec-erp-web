<?php

namespace App\Support\Gestor;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\Dashboard\ErpDashboardGauges;
use App\Support\Erp\Dashboard\ErpDashboardSalesMetrics;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Métricas ricas da tela Financeiro do Painel Executivo.
 */
final class GestorFinanceiroService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: app(GestorExecutivoService::class)->empresaId();
        $hoje = ErpFinanceiroMetricas::hoje();
        $ontem = $hoje->copy()->subDay();
        $inicioMes = $hoje->copy()->startOfMonth();
        $inicioMesAnt = $hoje->copy()->subMonthNoOverflow()->startOfMonth();
        $fimMesAnt = $hoje->copy()->subMonthNoOverflow()->endOfMonth();

        $saldo = ErpFinanceiroMetricas::saldoCaixa();
        $saldoOntem = ErpFinanceiroMetricas::saldoCaixaAte($ontem);
        $varSaldoPct = ErpDashboardSalesMetrics::variacaoPercentual($saldo, $saldoOntem);

        $hojeMov = ErpFinanceiroMetricas::movimentosCaixaNoDia($hoje);
        $ontemMov = ErpFinanceiroMetricas::movimentosCaixaNoDia($ontem);

        $receberHoje = $this->comVariacao(ErpFinanceiroMetricas::receberNoDia($hoje), ErpFinanceiroMetricas::receberNoDia($ontem));
        $pagarHoje = $this->comVariacao(ErpFinanceiroMetricas::pagarNoDia($hoje), ErpFinanceiroMetricas::pagarNoDia($ontem));

        $receberVencido = $this->comVariacao(ErpFinanceiroMetricas::receberVencido($hoje));
        $pagarVencido = $this->comVariacao(ErpFinanceiroMetricas::pagarVencido($hoje));

        $receberMes = $this->comVariacao(ErpFinanceiroMetricas::titulosReceber($inicioMes, $hoje));
        $pagarMes = $this->comVariacao(ErpFinanceiroMetricas::titulosPagar($inicioMes, $hoje));

        $serie7d = $this->serieSaldo7Dias($saldo, $hoje);
        $projecao = $this->projecaoCaixa($saldo, $hoje);
        $inadimplencia = ErpFinanceiroMetricas::inadimplencia($hoje);
        $acimaLimite = $this->clientesAcimaLimite();
        $proximos = $this->proximosVencimentos($hoje, 5);
        $aprovacoes = 0;
        try {
            $aprovacoes = app(GestorAprovacaoService::class)->countPendencias($empresaId);
        } catch (Throwable) {
            $aprovacoes = 0;
        }

        try {
            $saude = ErpDashboardGauges::saudeSnapshot($empresaId > 0 ? $empresaId : null);
        } catch (Throwable) {
            $saude = $this->saudeFinanceira(
                saldo: $saldo,
                receberVencido: (float) $receberVencido['valor'],
                pagarVencido: (float) $pagarVencido['valor'],
                resultadoHoje: (float) $hojeMov['resultado'],
            );
        }

        $atencao = $this->montarAtencao(
            receberVencido: $receberVencido,
            pagarVencido: $pagarVencido,
            saldo: $saldo,
            aprovacoes: $aprovacoes,
            acimaLimite: $acimaLimite,
            boletosHoje: $receberHoje,
            projecao: $projecao,
        );

        return [
            'atualizado_em' => ErpFinanceiroMetricas::agoraLabelHora(),
            'data_label' => ErpFinanceiroMetricas::agoraLabelData(),
            'saldo' => $saldo,
            'saldo_ontem' => $saldoOntem,
            'saldo_variacao_pct' => $varSaldoPct,
            'hoje' => $hojeMov,
            'ontem' => $ontemMov,
            'mes' => [
                'entradas' => ErpFinanceiroMetricas::sumCaixaCampo($inicioMes, $hoje, 'entrada'),
                'saidas' => ErpFinanceiroMetricas::sumCaixaCampo($inicioMes, $hoje, 'saida'),
            ],
            'mes_anterior' => [
                'entradas' => ErpFinanceiroMetricas::sumCaixaCampo($inicioMesAnt, $fimMesAnt, 'entrada'),
                'saidas' => ErpFinanceiroMetricas::sumCaixaCampo($inicioMesAnt, $fimMesAnt, 'saida'),
            ],
            'receber_hoje' => $receberHoje,
            'pagar_hoje' => $pagarHoje,
            'receber_vencido' => $receberVencido,
            'pagar_vencido' => $pagarVencido,
            'receber_mes' => $receberMes,
            'pagar_mes' => $pagarMes,
            'serie_7d' => $serie7d,
            'projecao' => $projecao,
            'inadimplencia' => $inadimplencia,
            'acima_limite' => $acimaLimite,
            'proximos' => $proximos,
            'saude' => $saude,
            'atencao' => $atencao,
            'fluxo_previsto_hoje' => round(
                (float) $receberHoje['valor'] - (float) $pagarHoje['valor'],
                2
            ),
        ];
    }

    /**
     * @param  array{qtd: int, valor: float}  $atual
     * @param  array{qtd: int, valor: float}|null  $anterior
     * @return array{qtd: int, valor: float, variacao_pct: ?float}
     */
    private function comVariacao(array $atual, ?array $anterior = null): array
    {
        return [
            'qtd' => (int) ($atual['qtd'] ?? 0),
            'valor' => (float) ($atual['valor'] ?? 0),
            'variacao_pct' => $anterior
                ? ErpDashboardSalesMetrics::variacaoPercentual((float) $atual['valor'], (float) $anterior['valor'])
                : null,
        ];
    }

    /**
     * @return list<array{id: int, pessoa: string, documento: string, valor: float, vencimento: string, situacao: string, vencido?: bool, pode_pagar?: bool, qtd?: int, modo?: string}>
     */
    public function detalheTitulos(string $tipo): array
    {
        $hoje = ErpFinanceiroMetricas::hoje();

        return match ($tipo) {
            'receber_hoje' => $this->listarReceber($hoje, $hoje),
            'pagar_hoje' => $this->listarPagar($hoje, $hoje),
            'receber_vencido' => $this->listarReceber(null, $hoje->copy()->subDay()),
            'pagar_vencido' => $this->listarPagar(null, $hoje->copy()->subDay()),
            'proximos_receber' => $this->listarReceber($hoje, $hoje->copy()->addDays(14), 8),
            'inadimplencia' => $this->listarClientesInadimplentes($hoje),
            'acima_limite' => $this->listarClientesAcimaLimite(),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, valor: float}>
     */
    private function serieSaldo7Dias(float $saldoAtual, Carbon $hoje): array
    {
        $nets = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $hoje->copy()->subDays($i);
            $mov = ErpFinanceiroMetricas::movimentosCaixaNoDia($day);
            $nets[$day->toDateString()] = (float) $mov['resultado'];
        }

        // Reconstrói saldo diário a partir do saldo atual.
        $cursor = $saldoAtual;
        $reversed = [];
        for ($i = 0; $i <= 6; $i++) {
            $day = $hoje->copy()->subDays($i);
            $reversed[$day->toDateString()] = $cursor;
            $cursor = round($cursor - ($nets[$day->toDateString()] ?? 0), 2);
        }

        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $hoje->copy()->subDays($i);
            $out[] = [
                'label' => $day->translatedFormat('D'),
                'dia' => $day->format('d/m'),
                'valor' => (float) ($reversed[$day->toDateString()] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{dias_negativo: ?int, receber_7d: float, pagar_7d: float, mensagem: string, tom: string}
     */
    private function projecaoCaixa(float $saldo, Carbon $hoje): array
    {
        $ate = $hoje->copy()->addDays(14);
        $receberPorDia = $this->saldosPorVencimentoReceber($hoje, $ate);
        $pagarPorDia = $this->saldosPorVencimentoPagar($hoje, $ate);

        $receber7 = 0.0;
        $pagar7 = 0.0;
        $cursor = $saldo;
        $diasNeg = null;

        for ($i = 0; $i <= 14; $i++) {
            $key = $hoje->copy()->addDays($i)->toDateString();
            $rec = (float) ($receberPorDia[$key] ?? 0);
            $pag = (float) ($pagarPorDia[$key] ?? 0);
            if ($i <= 7) {
                $receber7 += $rec;
                $pagar7 += $pag;
            }
            $cursor = round($cursor + $rec - $pag, 2);
            if ($cursor < 0 && $diasNeg === null) {
                $diasNeg = $i;
            }
        }

        $receber7 = round($receber7, 2);
        $pagar7 = round($pagar7, 2);

        if ($saldo < 0) {
            return [
                'dias_negativo' => 0,
                'receber_7d' => $receber7,
                'pagar_7d' => $pagar7,
                'mensagem' => 'Caixa já está negativo. Priorize recebimentos.',
                'tom' => 'danger',
            ];
        }

        if ($diasNeg !== null) {
            return [
                'dias_negativo' => $diasNeg,
                'receber_7d' => $receber7,
                'pagar_7d' => $pagar7,
                'mensagem' => $diasNeg === 0
                    ? 'O caixa pode fechar negativo ainda hoje.'
                    : "O caixa pode ficar negativo em {$diasNeg} dia(s).",
                'tom' => 'warning',
            ];
        }

        return [
            'dias_negativo' => null,
            'receber_7d' => $receber7,
            'pagar_7d' => $pagar7,
            'mensagem' => 'Fluxo dos próximos dias parece confortável.',
            'tom' => 'ok',
        ];
    }

    /**
     * @return array<string, float>
     */
    private function saldosPorVencimentoReceber(Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return [];
            }

            return ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '>=', $from->toDateString())
                ->whereDate('vencimento', '<=', $to->toDateString())
                ->get(['vencimento', 'saldo'])
                ->groupBy(fn (ContaReceber $c): string => $c->vencimento?->toDateString() ?? '')
                ->map(fn ($group): float => round((float) $group->sum('saldo'), 2))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, float>
     */
    private function saldosPorVencimentoPagar(Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable((new ContaPagar)->getTable())) {
                return [];
            }

            return ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '>=', $from->toDateString())
                ->whereDate('vencimento', '<=', $to->toDateString())
                ->get(['vencimento', 'saldo'])
                ->groupBy(fn (ContaPagar $c): string => $c->vencimento?->toDateString() ?? '')
                ->map(fn ($group): float => round((float) $group->sum('saldo'), 2))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Clientes com aberto acima do limite de crédito.
     *
     * @return list<array{id: int, pessoa: string, documento: string, valor: float, vencimento: string, situacao: string, vencido: bool, pode_pagar: bool, qtd: int, modo: string}>
     */
    private function listarClientesAcimaLimite(int $limit = 80): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable()) || ! Schema::hasTable((new Person)->getTable())) {
                return [];
            }

            $rows = ContaReceber::query()
                ->select('cliente_id', DB::raw('SUM(saldo) as aberto'))
                ->where('saldo', '>', 0)
                ->whereNotNull('cliente_id')
                ->groupBy('cliente_id')
                ->havingRaw('SUM(saldo) > 0')
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $pessoas = Person::query()
                ->whereIn('id', $rows->pluck('cliente_id'))
                ->where('limite_credito', '>', 0)
                ->get(['id', 'nome_razao', 'cpf_cnpj', 'limite_credito'])
                ->keyBy('id');

            $out = [];
            foreach ($rows as $row) {
                $pessoa = $pessoas->get((int) $row->cliente_id);
                if (! $pessoa) {
                    continue;
                }

                $limite = (float) $pessoa->limite_credito;
                $aberto = round((float) $row->aberto, 2);
                if ($limite <= 0 || $aberto <= $limite) {
                    continue;
                }

                $excesso = round($aberto - $limite, 2);

                $out[] = [
                    'id' => (int) $row->cliente_id,
                    'pessoa' => (string) ($pessoa->nome_razao ?? '—'),
                    'documento' => (string) ($pessoa->cpf_cnpj ?: 'Cliente #'.$row->cliente_id),
                    'valor' => $excesso,
                    'vencimento' => 'limite R$ '.number_format($limite, 2, ',', '.'),
                    'situacao' => 'aberto R$ '.number_format($aberto, 2, ',', '.'),
                    'vencido' => true,
                    'pode_pagar' => false,
                    'qtd' => 1,
                    'modo' => 'cliente',
                ];
            }

            usort($out, fn (array $a, array $b): int => $b['valor'] <=> $a['valor']);

            return array_slice($out, 0, $limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    private function clientesAcimaLimite(): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable()) || ! Schema::hasTable((new Person)->getTable())) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $rows = ContaReceber::query()
                ->select('cliente_id', DB::raw('SUM(saldo) as aberto'))
                ->where('saldo', '>', 0)
                ->whereNotNull('cliente_id')
                ->groupBy('cliente_id')
                ->havingRaw('SUM(saldo) > 0')
                ->get();

            if ($rows->isEmpty()) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $limites = Person::query()
                ->whereIn('id', $rows->pluck('cliente_id'))
                ->where('limite_credito', '>', 0)
                ->pluck('limite_credito', 'id');

            $qtd = 0;
            $excesso = 0.0;
            foreach ($rows as $row) {
                $limite = (float) ($limites[$row->cliente_id] ?? 0);
                $aberto = (float) $row->aberto;
                if ($limite > 0 && $aberto > $limite) {
                    $qtd++;
                    $excesso += ($aberto - $limite);
                }
            }

            return ['qtd' => $qtd, 'valor' => round($excesso, 2)];
        } catch (Throwable) {
            return ['qtd' => 0, 'valor' => 0.0];
        }
    }

    /**
     * @return list<array{tipo: string, pessoa: string, valor: float, vencimento: string}>
     */
    private function proximosVencimentos(Carbon $hoje, int $limit): array
    {
        $itens = [];

        try {
            foreach ($this->listarReceber($hoje, $hoje->copy()->addDays(10), $limit) as $t) {
                $itens[] = [
                    'tipo' => 'receber',
                    'pessoa' => $t['pessoa'],
                    'valor' => $t['valor'],
                    'vencimento' => $t['vencimento'],
                ];
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            foreach ($this->listarPagar($hoje, $hoje->copy()->addDays(10), $limit) as $t) {
                $itens[] = [
                    'tipo' => 'pagar',
                    'pessoa' => $t['pessoa'],
                    'valor' => $t['valor'],
                    'vencimento' => $t['vencimento'],
                ];
            }
        } catch (Throwable) {
            // ignore
        }

        usort($itens, fn ($a, $b) => strcmp($a['vencimento'], $b['vencimento']));

        return array_slice($itens, 0, $limit);
    }

    /**
     * Clientes com títulos a receber vencidos (agrupado por cliente).
     *
     * @return list<array{id: int, pessoa: string, documento: string, valor: float, vencimento: string, situacao: string, vencido: bool, pode_pagar: bool, qtd: int, modo: string}>
     */
    private function listarClientesInadimplentes(Carbon $hoje, int $limit = 80): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return [];
            }

            $rows = ContaReceber::query()
                ->select([
                    'cliente_id',
                    DB::raw('SUM(saldo) as valor'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('MIN(vencimento) as vencimento_mais_antigo'),
                ])
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', $hoje->toDateString())
                ->whereNotNull('cliente_id')
                ->groupBy('cliente_id')
                ->orderByDesc('valor')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $pessoas = Person::query()
                ->whereIn('id', $rows->pluck('cliente_id'))
                ->get(['id', 'nome_razao', 'cpf_cnpj'])
                ->keyBy('id');

            return $rows->map(function ($row) use ($pessoas): array {
                $pessoa = $pessoas->get((int) $row->cliente_id);
                $qtd = (int) $row->qtd;
                $venc = $row->vencimento_mais_antigo
                    ? Carbon::parse($row->vencimento_mais_antigo)->format('d/m/Y')
                    : '—';

                return [
                    'id' => (int) $row->cliente_id,
                    'pessoa' => (string) ($pessoa?->nome_razao ?? '—'),
                    'documento' => (string) ($pessoa?->cpf_cnpj ?: 'Cliente #'.$row->cliente_id),
                    'valor' => round((float) $row->valor, 2),
                    'vencimento' => $venc,
                    'situacao' => $qtd === 1 ? '1 título vencido' : $qtd.' títulos vencidos',
                    'vencido' => true,
                    'pode_pagar' => false,
                    'qtd' => $qtd,
                    'modo' => 'cliente',
                ];
            })->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{id: int, pessoa: string, documento: string, valor: float, vencimento: string, situacao: string}>
     */
    private function listarReceber(?Carbon $from, Carbon $to, int $limit = 40): array
    {
        $q = ContaReceber::query()
            ->with('cliente:id,nome_razao')
            ->where('saldo', '>', 0)
            ->whereDate('vencimento', '<=', $to->toDateString())
            ->orderBy('vencimento')
            ->limit($limit);

        if ($from) {
            $q->whereDate('vencimento', '>=', $from->toDateString());
        }

        $hoje = ErpFinanceiroMetricas::hoje();

        return $q->get()->map(function (ContaReceber $c) use ($hoje): array {
            $venc = $c->vencimento;
            $situacao = $venc && $venc->lt($hoje) ? 'Vencido' : ($venc && $venc->isSameDay($hoje) ? 'Vence hoje' : 'A vencer');

            return [
                'id' => (int) $c->id,
                'pessoa' => (string) ($c->cliente?->nome_razao ?? '—'),
                'documento' => (string) ($c->documento ?: $c->numero ?: '#' . $c->id),
                'valor' => round((float) $c->saldo, 2),
                'vencimento' => $venc?->format('d/m/Y') ?? '—',
                'situacao' => $situacao,
                'vencido' => $venc && $venc->lt($hoje),
                'pode_pagar' => false,
                'pode_receber' => true,
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, pessoa: string, documento: string, valor: float, vencimento: string, situacao: string}>
     */
    private function listarPagar(?Carbon $from, Carbon $to, int $limit = 40): array
    {
        $q = ContaPagar::query()
            ->with('fornecedor:id,nome_razao')
            ->where('saldo', '>', 0)
            ->whereDate('vencimento', '<=', $to->toDateString())
            ->orderBy('vencimento')
            ->limit($limit);

        if ($from) {
            $q->whereDate('vencimento', '>=', $from->toDateString());
        }

        $hoje = ErpFinanceiroMetricas::hoje();

        return $q->get()->map(function (ContaPagar $c) use ($hoje): array {
            $venc = $c->vencimento;
            $situacao = $venc && $venc->lt($hoje) ? 'Vencido' : ($venc && $venc->isSameDay($hoje) ? 'Vence hoje' : 'A vencer');

            return [
                'id' => (int) $c->id,
                'pessoa' => (string) ($c->fornecedor?->nome_razao ?? '—'),
                'documento' => (string) ($c->documento ?: $c->numero ?: '#' . $c->id),
                'valor' => round((float) $c->saldo, 2),
                'vencimento' => $venc?->format('d/m/Y') ?? '—',
                'situacao' => $situacao,
                'vencido' => $venc && $venc->lt($hoje),
                'pode_pagar' => true,
                'pode_receber' => false,
            ];
        })->all();
    }

    /**
     * Fallback simplificado se o snapshot completo do dashboard falhar.
     *
     * @return array{percent: float, tone: string, label: string, short: string, message: string, factors: list<array<string, mixed>>}
     */
    private function saudeFinanceira(float $saldo, float $receberVencido, float $pagarVencido, float $resultadoHoje): array
    {
        $caixaPct = $saldo >= 0 ? min(100.0, 70 + min(30, $saldo / 1000)) : max(5.0, 40 + ($saldo / 500));
        $receberPct = $receberVencido <= 0 ? 95.0 : max(10.0, 90.0 - min(80.0, $receberVencido / 500));
        $pagarPct = $pagarVencido <= 0 ? 95.0 : max(10.0, 90.0 - min(80.0, $pagarVencido / 500));
        $diaPct = $resultadoHoje >= 0 ? min(100.0, 70 + ($resultadoHoje > 0 ? 20 : 0)) : max(15.0, 50 + ($resultadoHoje / 200));

        $score = ErpDashboardGauges::scoreFromFactors([
            ['key' => 'caixa', 'label' => 'Caixa', 'percent' => $caixaPct, 'weight' => 35],
            ['key' => 'receber', 'label' => 'Receber', 'percent' => $receberPct, 'weight' => 25],
            ['key' => 'pagar', 'label' => 'Pagar', 'percent' => $pagarPct, 'weight' => 25],
            ['key' => 'dia', 'label' => 'Dia', 'percent' => $diaPct, 'weight' => 15],
        ]);

        $status = ErpDashboardGauges::healthStatus((float) $score['percent']);

        return [
            'percent' => (float) $score['percent'],
            'tone' => $status['tone'],
            'label' => $status['label'],
            'short' => $status['short'],
            'message' => $status['message'],
            'factors' => $score['factors'],
        ];
    }

    /**
     * @param  array{qtd: int, valor: float}  $receberVencido
     * @param  array{qtd: int, valor: float}  $pagarVencido
     * @param  array{qtd: int, valor: float}  $acimaLimite
     * @param  array{qtd: int, valor: float}  $boletosHoje
     * @param  array{dias_negativo: ?int, mensagem: string, tom: string}  $projecao
     * @return list<array{texto: string, tom: string}>
     */
    private function montarAtencao(
        array $receberVencido,
        array $pagarVencido,
        float $saldo,
        int $aprovacoes,
        array $acimaLimite,
        array $boletosHoje,
        array $projecao,
    ): array {
        $itens = [];

        if (($receberVencido['qtd'] ?? 0) > 0) {
            $itens[] = [
                'texto' => $receberVencido['qtd'].' conta(s) a receber vencida(s)',
                'tom' => 'danger',
            ];
        }
        if (($pagarVencido['qtd'] ?? 0) > 0) {
            $itens[] = [
                'texto' => $pagarVencido['qtd'].' conta(s) a pagar vencida(s)',
                'tom' => 'warning',
            ];
        }
        if (($acimaLimite['qtd'] ?? 0) > 0) {
            $itens[] = [
                'texto' => $acimaLimite['qtd'].' cliente(s) acima do limite',
                'tom' => 'warning',
            ];
        }
        if ($saldo < 0) {
            $itens[] = ['texto' => 'Caixa negativo', 'tom' => 'danger'];
        } elseif (($projecao['dias_negativo'] ?? null) !== null) {
            $itens[] = ['texto' => $projecao['mensagem'], 'tom' => 'warning'];
        }
        if ($aprovacoes > 0) {
            $itens[] = [
                'texto' => $aprovacoes.' item(ns) aguardando aprovação',
                'tom' => 'info',
            ];
        }
        if (($boletosHoje['qtd'] ?? 0) > 0) {
            $itens[] = [
                'texto' => $boletosHoje['qtd'].' título(s) a receber vencem hoje',
                'tom' => 'info',
            ];
        }

        if ($itens === []) {
            $itens[] = ['texto' => 'Nada urgente no financeiro agora', 'tom' => 'ok'];
        }

        return array_slice($itens, 0, 6);
    }
}
