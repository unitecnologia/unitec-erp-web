<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BalancoFinanceiroReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'balanco-financeiro';
    }

    public function title(): string
    {
        return 'BALANÇO FINANCEIRO';
    }

    public function permission(): string
    {
        return 'caixa.print';
    }

    public function columns(): array
    {
        return [
            'grupo' => 'GRUPO',
            'descricao' => 'DESCRIÇÃO',
            'valor' => 'VALOR',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['valor'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter($this->periodFilterFields()));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $multi = $this->isMultiEmpresaScope($request);
        $hasReceberEmpresa = Schema::hasColumn((new ContaReceber)->getTable(), 'empresa_id');
        $hasPagarEmpresa = Schema::hasColumn((new ContaPagar)->getTable(), 'empresa_id');

        if ($multi && ($hasReceberEmpresa || $hasPagarEmpresa)) {
            $rows = $this->buildMultiEmpresaRows($request, $de, $ate, $hasReceberEmpresa, $hasPagarEmpresa);
        } else {
            $rows = $this->buildSingleScopeRows($request, $de, $ate, $hasReceberEmpresa, $hasPagarEmpresa);
        }

        $summary = ['PERÍODO: '.$this->periodLabel($de, $ate)];
        if ($multi) {
            $summary[] = 'CAIXA: VALORES GLOBAIS (SEM VÍNCULO POR EMPRESA)';
        }

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary($summary, $request),
            withTotals: false,
        );
    }

    /**
     * @return list<array{grupo: string, descricao: string, valor: string}>
     */
    private function buildSingleScopeRows(
        Request $request,
        $de,
        $ate,
        bool $hasReceberEmpresa,
        bool $hasPagarEmpresa,
    ): array {
        $receberAbertoQ = ContaReceber::query()
            ->where('saldo', '>', 0)
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()]);
        $receberRecebidoQ = ContaReceber::query()
            ->whereBetween('recebido_em', [$de->toDateString(), $ate->toDateString()]);
        $pagarAbertoQ = ContaPagar::query()
            ->where('saldo', '>', 0)
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()]);
        $pagarPagoQ = ContaPagar::query()
            ->whereBetween('pago_em', [$de->toDateString(), $ate->toDateString()]);

        if ($hasReceberEmpresa) {
            ReportEmpresaScope::applyToQueryAllowingNullForSingle($receberAbertoQ, $request, 'empresa_id');
            ReportEmpresaScope::applyToQueryAllowingNullForSingle($receberRecebidoQ, $request, 'empresa_id');
        }
        if ($hasPagarEmpresa) {
            ReportEmpresaScope::applyToQueryAllowingNullForSingle($pagarAbertoQ, $request, 'empresa_id');
            ReportEmpresaScope::applyToQueryAllowingNullForSingle($pagarPagoQ, $request, 'empresa_id');
        }

        $receberAberto = (float) $receberAbertoQ->sum('saldo');
        $receberRecebido = (float) $receberRecebidoQ->sum('valor_recebido');
        $pagarAberto = (float) $pagarAbertoQ->sum('saldo');
        $pagarPago = (float) $pagarPagoQ->sum('valor_pago');

        $entradas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('entrada');
        $saidas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('saida');

        return [
            ['grupo' => 'RECEBER', 'descricao' => 'Títulos em aberto (vencimento no período)', 'valor' => static::formatMoney($receberAberto)],
            ['grupo' => 'RECEBER', 'descricao' => 'Valores recebidos no período', 'valor' => static::formatMoney($receberRecebido)],
            ['grupo' => 'PAGAR', 'descricao' => 'Títulos em aberto (vencimento no período)', 'valor' => static::formatMoney($pagarAberto)],
            ['grupo' => 'PAGAR', 'descricao' => 'Valores pagos no período', 'valor' => static::formatMoney($pagarPago)],
            ['grupo' => 'CAIXA', 'descricao' => 'Entradas de caixa', 'valor' => static::formatMoney($entradas)],
            ['grupo' => 'CAIXA', 'descricao' => 'Saídas de caixa', 'valor' => static::formatMoney($saidas)],
            ['grupo' => 'CAIXA', 'descricao' => 'Saldo líquido de caixa', 'valor' => static::formatMoney($entradas - $saidas)],
            ['grupo' => 'RESULTADO', 'descricao' => 'Recebidos − Pagos', 'valor' => static::formatMoney($receberRecebido - $pagarPago)],
        ];
    }

    /**
     * @return list<array{grupo: string, descricao: string, valor: string}>
     */
    private function buildMultiEmpresaRows(
        Request $request,
        $de,
        $ate,
        bool $hasReceberEmpresa,
        bool $hasPagarEmpresa,
    ): array {
        $ids = ReportEmpresaScope::resolveIds($request);
        $labels = ReportEmpresaScope::labelsById();
        $rows = [];

        $totalReceberAberto = 0.0;
        $totalReceberRecebido = 0.0;
        $totalPagarAberto = 0.0;
        $totalPagarPago = 0.0;

        foreach ($ids as $empresaId) {
            $label = $labels[$empresaId] ?? ('Empresa #'.$empresaId);

            $receberAbertoQ = ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()]);
            $receberRecebidoQ = ContaReceber::query()
                ->whereBetween('recebido_em', [$de->toDateString(), $ate->toDateString()]);
            $pagarAbertoQ = ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()]);
            $pagarPagoQ = ContaPagar::query()
                ->whereBetween('pago_em', [$de->toDateString(), $ate->toDateString()]);

            if ($hasReceberEmpresa) {
                $receberAbertoQ->where('empresa_id', $empresaId);
                $receberRecebidoQ->where('empresa_id', $empresaId);
            }
            if ($hasPagarEmpresa) {
                $pagarAbertoQ->where('empresa_id', $empresaId);
                $pagarPagoQ->where('empresa_id', $empresaId);
            }

            $receberAberto = (float) $receberAbertoQ->sum('saldo');
            $receberRecebido = (float) $receberRecebidoQ->sum('valor_recebido');
            $pagarAberto = (float) $pagarAbertoQ->sum('saldo');
            $pagarPago = (float) $pagarPagoQ->sum('valor_pago');

            $totalReceberAberto += $receberAberto;
            $totalReceberRecebido += $receberRecebido;
            $totalPagarAberto += $pagarAberto;
            $totalPagarPago += $pagarPago;

            $rows[] = ['grupo' => $label, 'descricao' => 'Receber em aberto', 'valor' => static::formatMoney($receberAberto)];
            $rows[] = ['grupo' => $label, 'descricao' => 'Recebido no período', 'valor' => static::formatMoney($receberRecebido)];
            $rows[] = ['grupo' => $label, 'descricao' => 'Pagar em aberto', 'valor' => static::formatMoney($pagarAberto)];
            $rows[] = ['grupo' => $label, 'descricao' => 'Pago no período', 'valor' => static::formatMoney($pagarPago)];
            $rows[] = ['grupo' => $label, 'descricao' => 'Recebidos − Pagos', 'valor' => static::formatMoney($receberRecebido - $pagarPago)];
        }

        $entradas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('entrada');
        $saidas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('saida');

        $rows[] = ['grupo' => 'TOTAL GERAL', 'descricao' => 'Receber em aberto', 'valor' => static::formatMoney($totalReceberAberto)];
        $rows[] = ['grupo' => 'TOTAL GERAL', 'descricao' => 'Recebido no período', 'valor' => static::formatMoney($totalReceberRecebido)];
        $rows[] = ['grupo' => 'TOTAL GERAL', 'descricao' => 'Pagar em aberto', 'valor' => static::formatMoney($totalPagarAberto)];
        $rows[] = ['grupo' => 'TOTAL GERAL', 'descricao' => 'Pago no período', 'valor' => static::formatMoney($totalPagarPago)];
        $rows[] = ['grupo' => 'TOTAL GERAL', 'descricao' => 'Recebidos − Pagos', 'valor' => static::formatMoney($totalReceberRecebido - $totalPagarPago)];
        $rows[] = ['grupo' => 'CAIXA', 'descricao' => 'Entradas de caixa (global)', 'valor' => static::formatMoney($entradas)];
        $rows[] = ['grupo' => 'CAIXA', 'descricao' => 'Saídas de caixa (global)', 'valor' => static::formatMoney($saidas)];
        $rows[] = ['grupo' => 'CAIXA', 'descricao' => 'Saldo líquido de caixa (global)', 'valor' => static::formatMoney($entradas - $saidas)];

        return $rows;
    }
}
