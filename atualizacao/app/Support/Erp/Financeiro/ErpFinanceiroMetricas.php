<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    public static function saldoCaixa(?int $caixaContaId = null): float
    {
        try {
            if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            $qEntrada = CaixaLancamento::query();
            $qSaida = CaixaLancamento::query();

            if ($caixaContaId && $caixaContaId > 0) {
                $qEntrada->where('caixa_conta_id', $caixaContaId);
                $qSaida->where('caixa_conta_id', $caixaContaId);
            }

            return round((float) $qEntrada->sum('entrada') - (float) $qSaida->sum('saida'), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    public static function saldoCaixaAte(Carbon $day, ?int $caixaContaId = null): float
    {
        try {
            if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            $qEntrada = CaixaLancamento::query()->whereDate('emissao', '<=', $day->toDateString());
            $qSaida = CaixaLancamento::query()->whereDate('emissao', '<=', $day->toDateString());

            if ($caixaContaId && $caixaContaId > 0) {
                $qEntrada->where('caixa_conta_id', $caixaContaId);
                $qSaida->where('caixa_conta_id', $caixaContaId);
            }

            return round((float) $qEntrada->sum('entrada') - (float) $qSaida->sum('saida'), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public static function receberNoDia(Carbon $day): array
    {
        return self::titulosReceber($day, $day);
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public static function pagarNoDia(Carbon $day): array
    {
        return self::titulosPagar($day, $day);
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public static function receberVencido(?Carbon $hoje = null): array
    {
        $hoje ??= self::hoje();

        return self::titulosReceber(null, $hoje->copy()->subDay());
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public static function pagarVencido(?Carbon $hoje = null): array
    {
        $hoje ??= self::hoje();

        return self::titulosPagar(null, $hoje->copy()->subDay());
    }

    /**
     * Mesma base do filtro "atrasadas" do ERP (saldo > 0 e vencimento < hoje).
     *
     * @return array{clientes: int, valor: float, qtd: int}
     */
    public static function inadimplencia(?Carbon $hoje = null): array
    {
        $hoje ??= self::hoje();
        $base = self::receberVencido($hoje);

        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return ['clientes' => 0, 'valor' => 0.0, 'qtd' => 0];
            }

            $clientes = (int) ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', $hoje->toDateString())
                ->whereNotNull('cliente_id')
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
     * @return array{qtd: int, valor: float}
     */
    public static function titulosReceber(?Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $q = ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<=', $to->toDateString());

            if ($from) {
                $q->whereDate('vencimento', '>=', $from->toDateString());
            }

            return [
                'qtd' => (int) (clone $q)->count(),
                'valor' => round((float) (clone $q)->sum('saldo'), 2),
            ];
        } catch (Throwable) {
            return ['qtd' => 0, 'valor' => 0.0];
        }
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public static function titulosPagar(?Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable((new ContaPagar)->getTable())) {
                return ['qtd' => 0, 'valor' => 0.0];
            }

            $q = ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<=', $to->toDateString());

            if ($from) {
                $q->whereDate('vencimento', '>=', $from->toDateString());
            }

            return [
                'qtd' => (int) (clone $q)->count(),
                'valor' => round((float) (clone $q)->sum('saldo'), 2),
            ];
        } catch (Throwable) {
            return ['qtd' => 0, 'valor' => 0.0];
        }
    }

    /**
     * @return array{entradas: float, saidas: float, resultado: float}
     */
    public static function movimentosCaixaNoDia(Carbon $day): array
    {
        $entradas = self::sumCaixaCampo($day, $day, 'entrada');
        $saidas = self::sumCaixaCampo($day, $day, 'saida');

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'resultado' => round($entradas - $saidas, 2),
        ];
    }

    public static function sumCaixaCampo(Carbon $from, Carbon $to, string $campo): float
    {
        try {
            if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
                return 0.0;
            }

            return round((float) CaixaLancamento::query()
                ->whereDate('emissao', '>=', $from->toDateString())
                ->whereDate('emissao', '<=', $to->toDateString())
                ->sum($campo), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * Snapshot rápido para cruzar ERP × Gestor (debug / testes).
     *
     * @return array<string, float|int>
     */
    public static function snapshotComparacao(): array
    {
        $hoje = self::hoje();
        $recHoje = self::receberNoDia($hoje);
        $pagHoje = self::pagarNoDia($hoje);
        $recVenc = self::receberVencido($hoje);
        $pagVenc = self::pagarVencido($hoje);
        $inad = self::inadimplencia($hoje);

        return [
            'hoje' => $hoje->toDateString(),
            'saldo_caixa' => self::saldoCaixa(),
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
}
