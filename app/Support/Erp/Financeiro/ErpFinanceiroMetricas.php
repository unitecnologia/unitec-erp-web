<?php

namespace App\Support\Erp\Financeiro;

use App\Support\Erp\ErpSchema;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fonte única de métricas financeiras (ERP Contas + Painel Executivo).
 *
 * Regras alinhadas às listas Contas a Receber / Contas a Pagar / Contas Caixa:
 * - hoje = data local America/Sao_Paulo (ErpTimezone)
 * - títulos abertos: saldo > 0
 * - hoje: vencimento = hoje
 * - vencido/atrasadas: vencimento < hoje
 * - saldo caixa: Σentrada − Σsaída em caixa_lancamentos
 *
 * Filtro por empresa: aplicado quando a coluna empresa_id existir; no caixa,
 * também restringe às contas liberadas do usuário na empresa (pivot).
 * Visão empresa (um id): inclui registros com empresa_id nulo (legado).
 * Visão grupo (lista): whereIn nas empresas acessíveis, sem null global.
 */
final class ErpFinanceiroMetricas
{
    public static function hoje(): Carbon
    {
        return ErpTimezone::toLocal()->startOfDay();
    }

    public static function agoraLabelHora(): string
    {
        return ErpTimezone::toLocal()->format('H:i');
    }

    public static function agoraLabelData(): string
    {
        return ErpTimezone::toLocal()->translatedFormat('d M Y');
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    public static function saldoCaixa(?int $caixaContaId = null, int|array|null $empresaScope = null): float
    {
        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            $q = CaixaLancamento::query();
            self::applyCaixaEscopo($q, $caixaContaId, $empresaScope);

            $row = $q
                ->selectRaw('COALESCE(SUM(entrada), 0) as entradas, COALESCE(SUM(saida), 0) as saidas')
                ->first();

            return round((float) ($row->entradas ?? 0) - (float) ($row->saidas ?? 0), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    public static function saldoCaixaAte(Carbon $day, ?int $caixaContaId = null, int|array|null $empresaScope = null): float
    {
        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            $qEntrada = CaixaLancamento::query()->whereDate('emissao', '<=', $day->toDateString());
            $qSaida = CaixaLancamento::query()->whereDate('emissao', '<=', $day->toDateString());

            self::applyCaixaEscopo($qEntrada, $caixaContaId, $empresaScope);
            self::applyCaixaEscopo($qSaida, $caixaContaId, $empresaScope);

            return round((float) $qEntrada->sum('entrada') - (float) $qSaida->sum('saida'), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function receberNoDia(Carbon $day, int|array|null $empresaScope = null): array
    {
        return self::titulosReceber($day, $day, $empresaScope);
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function pagarNoDia(Carbon $day, int|array|null $empresaScope = null): array
    {
        return self::titulosPagar($day, $day, $empresaScope);
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function receberVencido(?Carbon $hoje = null, int|array|null $empresaScope = null): array
    {
        $hoje ??= self::hoje();

        return self::titulosReceber(null, $hoje->copy()->subDay(), $empresaScope);
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function pagarVencido(?Carbon $hoje = null, int|array|null $empresaScope = null): array
    {
        $hoje ??= self::hoje();

        return self::titulosPagar(null, $hoje->copy()->subDay(), $empresaScope);
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{clientes: int, valor: float, qtd: int}
     */
    public static function inadimplencia(?Carbon $hoje = null, int|array|null $empresaScope = null): array
    {
        $hoje ??= self::hoje();
        $base = self::receberVencido($hoje, $empresaScope);

        try {
            if (! ErpSchema::hasTable((new ContaReceber)->getTable())) {
                return ['clientes' => 0, 'valor' => 0.0, 'qtd' => 0];
            }

            $q = ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', $hoje->toDateString())
                ->whereNotNull('cliente_id');

            self::applyEmpresaColumn($q, (new ContaReceber)->getTable(), $empresaScope);

            $clientes = (int) $q
                ->selectRaw('COUNT(DISTINCT cliente_id) as agregados')
                ->value('agregados');

            return [
                'clientes' => $clientes,
                'valor' => (float) $base['valor'],
                'qtd' => (int) $base['qtd'],
            ];
        } catch (Throwable) {
            return ['clientes' => 0, 'valor' => (float) $base['valor'], 'qtd' => (int) $base['qtd']];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function titulosReceber(?Carbon $from, Carbon $to, int|array|null $empresaScope = null): array
    {
        try {
            if (! ErpSchema::hasTable((new ContaReceber)->getTable())) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $q = ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<=', $to->toDateString());

            if ($from) {
                $q->whereDate('vencimento', '>=', $from->toDateString());
            }

            self::applyEmpresaColumn($q, (new ContaReceber)->getTable(), $empresaScope);

            $row = (clone $q)
                ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(saldo), 0) as valor')
                ->first();

            return [
                'qtd' => (int) ($row->qtd ?? 0),
                'valor' => round((float) ($row->valor ?? 0), 2),
            ];
        } catch (Throwable) {
            return ['qtd' => 0, 'valor' => 0.0];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{qtd: int, valor: float}
     */
    public static function titulosPagar(?Carbon $from, Carbon $to, int|array|null $empresaScope = null): array
    {
        try {
            if (! ErpSchema::hasTable((new ContaPagar)->getTable())) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $q = ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<=', $to->toDateString());

            if ($from) {
                $q->whereDate('vencimento', '>=', $from->toDateString());
            }

            self::applyEmpresaColumn($q, (new ContaPagar)->getTable(), $empresaScope);

            $row = (clone $q)
                ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(saldo), 0) as valor')
                ->first();

            return [
                'qtd' => (int) ($row->qtd ?? 0),
                'valor' => round((float) ($row->valor ?? 0), 2),
            ];
        } catch (Throwable) {
            return ['qtd' => 0, 'valor' => 0.0];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{entradas: float, saidas: float, resultado: float}
     */
    public static function movimentosCaixaNoDia(Carbon $day, int|array|null $empresaScope = null): array
    {
        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return ['entradas' => 0.0, 'saidas' => 0.0, 'resultado' => 0.0];
            }

            $q = CaixaLancamento::query()
                ->whereDate('emissao', $day->toDateString());

            self::applyCaixaEscopo($q, null, $empresaScope);

            $row = $q
                ->selectRaw('COALESCE(SUM(entrada), 0) as entradas, COALESCE(SUM(saida), 0) as saidas')
                ->first();

            $entradas = round((float) ($row->entradas ?? 0), 2);
            $saidas = round((float) ($row->saidas ?? 0), 2);

            return [
                'entradas' => $entradas,
                'saidas' => $saidas,
                'resultado' => round($entradas - $saidas, 2),
            ];
        } catch (Throwable) {
            return ['entradas' => 0.0, 'saidas' => 0.0, 'resultado' => 0.0];
        }
    }

    /**
     * Resultado líquido (entrada - saída) por dia no intervalo, uma única query.
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, float> chave Y-m-d
     */
    public static function resultadosCaixaPorDia(Carbon $from, Carbon $to, int|array|null $empresaScope = null): array
    {
        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return [];
            }

            $q = CaixaLancamento::query()
                ->whereDate('emissao', '>=', $from->toDateString())
                ->whereDate('emissao', '<=', $to->toDateString())
                ->selectRaw('DATE(emissao) as dia, SUM(entrada) as entradas, SUM(saida) as saidas')
                ->groupByRaw('DATE(emissao)');

            self::applyCaixaEscopo($q, null, $empresaScope);

            $out = [];
            foreach ($q->get() as $row) {
                $dia = (string) ($row->dia ?? '');
                if ($dia === '') {
                    continue;
                }
                // MySQL pode devolver datetime; normaliza para Y-m-d.
                $key = strlen($dia) >= 10 ? substr($dia, 0, 10) : $dia;
                $out[$key] = round((float) ($row->entradas ?? 0) - (float) ($row->saidas ?? 0), 2);
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Entradas e saídas agregadas por intervalo de semanas em uma única query.
     *
     * @param  list<array{inicio: Carbon, fim: Carbon}>  $semanas
     * @param  int|list<int>|null  $empresaScope
     * @return list<array{entradas: float, saidas: float}>
     */
    public static function entradasSaidasPorSemanas(array $semanas, int|array|null $empresaScope = null): array
    {
        if ($semanas === []) {
            return [];
        }

        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return array_fill(0, count($semanas), ['entradas' => 0.0, 'saidas' => 0.0]);
            }

            $globalInicio = $semanas[0]['inicio']->copy()->startOfDay();
            $globalFim = $semanas[count($semanas) - 1]['fim']->copy()->endOfDay();

            $selectParts = [];
            foreach ($semanas as $i => $semana) {
                $inicio = $semana['inicio']->toDateString();
                $fim = $semana['fim']->toDateString();
                $selectParts[] = "COALESCE(SUM(CASE WHEN DATE(emissao) >= '{$inicio}' AND DATE(emissao) <= '{$fim}' THEN entrada ELSE 0 END), 0) as w{$i}_ent";
                $selectParts[] = "COALESCE(SUM(CASE WHEN DATE(emissao) >= '{$inicio}' AND DATE(emissao) <= '{$fim}' THEN saida ELSE 0 END), 0) as w{$i}_sai";
            }

            $q = CaixaLancamento::query()
                ->whereDate('emissao', '>=', $globalInicio->toDateString())
                ->whereDate('emissao', '<=', $globalFim->toDateString());

            self::applyCaixaEscopo($q, null, $empresaScope);

            $row = $q->selectRaw(implode(', ', $selectParts))->first();

            $out = [];
            foreach ($semanas as $i => $_) {
                $out[] = [
                    'entradas' => round((float) ($row->{"w{$i}_ent"} ?? 0), 2),
                    'saidas' => round((float) ($row->{"w{$i}_sai"} ?? 0), 2),
                ];
            }

            return $out;
        } catch (Throwable) {
            return array_fill(0, count($semanas), ['entradas' => 0.0, 'saidas' => 0.0]);
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    public static function sumCaixaCampo(Carbon $from, Carbon $to, string $campo, int|array|null $empresaScope = null): float
    {
        try {
            if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            $q = CaixaLancamento::query()
                ->whereDate('emissao', '>=', $from->toDateString())
                ->whereDate('emissao', '<=', $to->toDateString());

            self::applyCaixaEscopo($q, null, $empresaScope);

            return round((float) $q->sum($campo), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, float|int>
     */
    public static function snapshotComparacao(int|array|null $empresaScope = null): array
    {
        $hoje = self::hoje();
        $recHoje = self::receberNoDia($hoje, $empresaScope);
        $pagHoje = self::pagarNoDia($hoje, $empresaScope);
        $recVenc = self::receberVencido($hoje, $empresaScope);
        $pagVenc = self::pagarVencido($hoje, $empresaScope);
        $inad = self::inadimplencia($hoje, $empresaScope);

        return [
            'hoje' => $hoje->toDateString(),
            'saldo_caixa' => self::saldoCaixa(null, $empresaScope),
            'receber_hoje_valor' => $recHoje['valor'],
            'receber_hoje_qtd' => $recHoje['qtd'],
            'pagar_hoje_valor' => $pagHoje['valor'],
            'pagar_hoje_qtd' => $pagHoje['qtd'],
            'receber_vencido_valor' => $recVenc['valor'],
            'receber_vencido_qtd' => $recVenc['qtd'],
            'pagar_vencido_valor' => $pagVenc['valor'],
            'pagar_vencido_qtd' => $pagVenc['qtd'],
            'inadimplencia_valor' => $inad['valor'],
            'inadimplencia_clientes' => $inad['clientes'],
        ];
    }

    /**
     * Visão empresa (um id): empresa_id = id.
     * Instalação com 1 empresa: inclui também empresa_id nulo (legado).
     * Visão grupo (lista): whereIn nas ids (sem null global).
     *
     * @param  int|list<int>|null  $empresaScope
     */
    public static function applyEmpresaColumn(Builder $query, string $table, int|array|null $empresaScope): void
    {
        if ($empresaScope === null) {
            return;
        }

        if (! ErpSchema::hasColumn($table, 'empresa_id')) {
            return;
        }

        if (is_array($empresaScope)) {
            $ids = array_values(array_filter(array_map('intval', $empresaScope)));

            if ($ids === []) {
                return;
            }

            $query->whereIn($table.'.empresa_id', $ids);

            return;
        }

        $empresaId = (int) $empresaScope;

        if ($empresaId <= 0) {
            return;
        }

        if (self::instalacaoTemUmaEmpresa()) {
            $query->where(function (Builder $outer) use ($table, $empresaId): void {
                $outer->where($table.'.empresa_id', $empresaId)
                    ->orWhereNull($table.'.empresa_id');
            });

            return;
        }

        $query->where($table.'.empresa_id', $empresaId);
    }

    private static function instalacaoTemUmaEmpresa(): bool
    {
        static $unica = null;

        if ($unica !== null) {
            return $unica;
        }

        try {
            if (! ErpSchema::hasTable('empresas')) {
                $unica = true;

                return true;
            }

            $unica = \App\Models\Empresa::query()->count() <= 1;
        } catch (Throwable) {
            $unica = true;
        }

        return $unica;
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function applyCaixaEscopo(Builder $query, ?int $caixaContaId, int|array|null $empresaScope): void
    {
        $table = (new CaixaLancamento)->getTable();

        if ($caixaContaId && $caixaContaId > 0) {
            $query->where('caixa_conta_id', $caixaContaId);
        }

        self::applyEmpresaColumn($query, $table, $empresaScope);

        if ($empresaScope === null || is_array($empresaScope) || (int) $empresaScope <= 0) {
            return;
        }

        if (ErpSchema::hasColumn($table, 'empresa_id')) {
            return;
        }

        $empresaId = (int) $empresaScope;
        $user = Auth::user();
        if ($user === null || (bool) $user->is_admin) {
            return;
        }

        $contaIds = $user->accessibleCaixaContaIds($empresaId);
        $assigned = \Illuminate\Support\Facades\DB::table('caixa_conta_user')
            ->where('user_id', $user->getKey())
            ->where('empresa_id', $empresaId)
            ->exists();

        if ($assigned && $contaIds !== []) {
            $query->whereIn('caixa_conta_id', $contaIds);
        }
    }
}
