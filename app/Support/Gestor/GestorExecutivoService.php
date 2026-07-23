<?php

namespace App\Support\Gestor;

use App\Models\Entrega;
use App\Models\ForcaVendasOrder;
use App\Models\Product;
use App\Support\Erp\Dashboard\ErpDashboardGauges;
use App\Support\Erp\Dashboard\ErpDashboardSalesMetrics;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use App\Support\Erp\ErpTimezone;
use App\Support\Gestor\GestorAprovacaoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Snapshot rápido do Painel Executivo (/gestor).
 *
 * Etapas do produto:
 * 1) Shell app + dashboard + financeiro/vendas/estoque + produtos
 * 2) PWA instalável + cache offline de consultas
 * 3) Central de aprovações
 * 4) Push notifications (HTTPS) — atual
 */
final class GestorExecutivoService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: $this->empresaId();
        $hoje = ErpFinanceiroMetricas::hoje();
        $ontem = $hoje->copy()->subDay();
        $inicioMes = $hoje->copy()->startOfMonth();

        $fatHoje = ErpDashboardSalesMetrics::faturamentoDia($hoje);
        $fatOntem = ErpDashboardSalesMetrics::faturamentoDia($ontem);
        $fatMes = ErpDashboardSalesMetrics::faturamentoPeriodo($inicioMes, $hoje);
        $varDia = ErpDashboardSalesMetrics::variacaoPercentual($fatHoje, $fatOntem);

        $caixa = ErpFinanceiroMetricas::saldoCaixa();
        $receberHoje = (float) ErpFinanceiroMetricas::receberNoDia($hoje)['valor'];
        $pagarHoje = (float) ErpFinanceiroMetricas::pagarNoDia($hoje)['valor'];
        $receberVencido = (float) ErpFinanceiroMetricas::receberVencido($hoje)['valor'];
        $pagarVencido = (float) ErpFinanceiroMetricas::pagarVencido($hoje)['valor'];

        $pedidosPendentes = $this->countPedidosPendentes($empresaId);
        $entregasPendentes = $this->countEntregasPendentes();
        $estoqueBaixo = $this->countEstoqueBaixo();

        try {
            $saude = ErpDashboardGauges::saudeSnapshot($empresaId > 0 ? $empresaId : null);
        } catch (Throwable) {
            $saude = $this->saudeRapida(
                caixa: $caixa,
                fatHoje: $fatHoje,
                fatOntem: $fatOntem,
                receberVencido: $receberVencido,
                pagarVencido: $pagarVencido,
                estoqueBaixo: $estoqueBaixo,
            );
        }

        $metas = [];
        try {
            $metas = ErpDashboardGauges::buildVendedores($empresaId > 0 ? $empresaId : null);
        } catch (Throwable) {
            $metas = [];
        }

        $pulso = $this->montarPulso(
            receberVencido: $receberVencido,
            pagarVencido: $pagarVencido,
            estoqueBaixo: $estoqueBaixo,
            pedidosPendentes: $pedidosPendentes,
            entregasPendentes: $entregasPendentes,
            receberHoje: $receberHoje,
            pagarHoje: $pagarHoje,
        );

        $aprovacoes = 0;
        try {
            $aprovacoes = app(GestorAprovacaoService::class)->countPendencias($empresaId);
        } catch (Throwable) {
            $aprovacoes = $pedidosPendentes;
        }

        return [
            'atualizado_em' => ErpFinanceiroMetricas::agoraLabelHora(),
            'saudacao' => $this->saudacao(),
            'faturamento_hoje' => $fatHoje,
            'faturamento_ontem' => $fatOntem,
            'faturamento_mes' => $fatMes,
            'variacao_dia_pct' => $varDia,
            'variacao_dia_hint' => ErpDashboardSalesMetrics::hintVariacaoDia($fatHoje, $fatOntem),
            'caixa' => $caixa,
            'receber_hoje' => $receberHoje,
            'pagar_hoje' => $pagarHoje,
            'receber_vencido' => $receberVencido,
            'pagar_vencido' => $pagarVencido,
            'pedidos_pendentes' => $pedidosPendentes,
            'entregas_pendentes' => $entregasPendentes,
            'estoque_baixo' => $estoqueBaixo,
            'aprovacoes_pendentes' => $aprovacoes,
            'saude' => $saude,
            'metas_vendedores' => array_slice($metas, 0, 6),
            'pulso' => $pulso,
        ];
    }

    public function empresaId(): int
    {
        return (int) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 0);
    }

    private function saudacao(): string
    {
        $h = (int) ErpTimezone::toLocal()->format('G');
        $nome = trim((string) (Auth::user()?->name ?? ''));
        $primeiro = $nome !== '' ? explode(' ', $nome)[0] : '';

        $base = match (true) {
            $h < 12 => 'Bom dia',
            $h < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        return $primeiro !== '' ? "{$base}, {$primeiro}" : $base;
    }

    private function countPedidosPendentes(int $empresaId): int
    {
        try {
            if (! Schema::hasTable((new ForcaVendasOrder)->getTable())) {
                return 0;
            }

            $q = ForcaVendasOrder::query()
                ->where('situacao', ForcaVendasOrder::SITUACAO_PENDENTE)
                ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO);

            if ($empresaId > 0 && Schema::hasColumn((new ForcaVendasOrder)->getTable(), 'empresa_id')) {
                $q->where('empresa_id', $empresaId);
            }

            return (int) $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countEntregasPendentes(): int
    {
        try {
            if (! Schema::hasTable((new Entrega)->getTable())) {
                return 0;
            }

            return (int) Entrega::query()
                ->whereIn('status', Entrega::statusControleFiltro('pendentes'))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countEstoqueBaixo(): int
    {
        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return 0;
            }

            return (int) Product::query()->estoqueCritico()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Saúde leve (sem varrer tabela inteira de produtos / margem).
     *
     * @return array{percent: float, tone: string, label: string, short: string, message: string, factors: list<array<string, mixed>>}
     */
    private function saudeRapida(
        float $caixa,
        float $fatHoje,
        float $fatOntem,
        float $receberVencido,
        float $pagarVencido,
        int $estoqueBaixo,
    ): array {
        $vendasPct = 55.0;
        if ($fatOntem > 0) {
            $ratio = $fatHoje / $fatOntem;
            $vendasPct = max(15.0, min(100.0, $ratio * 70));
        } elseif ($fatHoje > 0) {
            $vendasPct = 85.0;
        }

        $caixaPct = $caixa >= 0 ? min(100.0, 60 + ($caixa > 0 ? 25 : 0)) : max(10.0, 40 + ($caixa / 1000));
        if ($caixa < 0) {
            $caixaPct = max(5.0, min(45.0, 40 + ($caixa / 500)));
        }

        $receberPct = $receberVencido <= 0 ? 95.0 : max(10.0, 90.0 - min(80.0, $receberVencido / 500));
        $pagarPct = $pagarVencido <= 0 ? 95.0 : max(10.0, 90.0 - min(80.0, $pagarVencido / 500));
        $estoquePct = $estoqueBaixo <= 0 ? 95.0 : max(15.0, 90.0 - min(75.0, $estoqueBaixo * 4));

        $metaPct = 70.0;

        $score = ErpDashboardGauges::scoreFromFactors([
            ['key' => 'caixa', 'label' => 'Caixa', 'percent' => $caixaPct, 'weight' => 20, 'hint' => 'Saldo R$ '.number_format($caixa, 2, ',', '.')],
            ['key' => 'vendas', 'label' => 'Vendas', 'percent' => $vendasPct, 'weight' => 25, 'hint' => 'Hoje vs ontem'],
            ['key' => 'receber', 'label' => 'Receber', 'percent' => $receberPct, 'weight' => 20, 'hint' => 'Vencido R$ '.number_format($receberVencido, 2, ',', '.')],
            ['key' => 'pagar', 'label' => 'Pagar', 'percent' => $pagarPct, 'weight' => 15, 'hint' => 'Vencido R$ '.number_format($pagarVencido, 2, ',', '.')],
            ['key' => 'estoque', 'label' => 'Estoque', 'percent' => $estoquePct, 'weight' => 20, 'hint' => $estoqueBaixo.' item(ns) crítico(s)'],
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
     * @return list<array{tipo: string, titulo: string, detalhe: string, tom: string}>
     */
    private function montarPulso(
        float $receberVencido,
        float $pagarVencido,
        int $estoqueBaixo,
        int $pedidosPendentes,
        int $entregasPendentes,
        float $receberHoje,
        float $pagarHoje,
    ): array {
        $itens = [];

        if ($receberVencido > 0) {
            $itens[] = [
                'tipo' => 'receber',
                'titulo' => 'Contas vencidas a receber',
                'detalhe' => 'R$ '.number_format($receberVencido, 2, ',', '.'),
                'tom' => 'danger',
            ];
        }

        if ($pagarVencido > 0) {
            $itens[] = [
                'tipo' => 'pagar',
                'titulo' => 'Contas vencidas a pagar',
                'detalhe' => 'R$ '.number_format($pagarVencido, 2, ',', '.'),
                'tom' => 'warning',
            ];
        }

        if ($estoqueBaixo > 0) {
            $itens[] = [
                'tipo' => 'estoque',
                'titulo' => 'Estoque baixo',
                'detalhe' => $estoqueBaixo.' produto(s)',
                'tom' => 'warning',
            ];
        }

        if ($pedidosPendentes > 0) {
            $itens[] = [
                'tipo' => 'pedidos',
                'titulo' => 'Pedidos pendentes',
                'detalhe' => (string) $pedidosPendentes,
                'tom' => 'info',
            ];
        }

        if ($entregasPendentes > 0) {
            $itens[] = [
                'tipo' => 'entregas',
                'titulo' => 'Entregas em aberto',
                'detalhe' => (string) $entregasPendentes,
                'tom' => 'info',
            ];
        }

        if ($receberHoje > 0) {
            $itens[] = [
                'tipo' => 'receber_hoje',
                'titulo' => 'Receber hoje',
                'detalhe' => 'R$ '.number_format($receberHoje, 2, ',', '.'),
                'tom' => 'ok',
            ];
        }

        if ($pagarHoje > 0) {
            $itens[] = [
                'tipo' => 'pagar_hoje',
                'titulo' => 'Pagar hoje',
                'detalhe' => 'R$ '.number_format($pagarHoje, 2, ',', '.'),
                'tom' => 'ok',
            ];
        }

        if ($itens === []) {
            $itens[] = [
                'tipo' => 'ok',
                'titulo' => 'Nada urgente agora',
                'detalhe' => 'Empresa sob controle',
                'tom' => 'ok',
            ];
        }

        return array_slice($itens, 0, 6);
    }
}
