<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

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
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $receberAberto = (float) ContaReceber::query()
            ->where('saldo', '>', 0)
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()])
            ->sum('saldo');

        $receberRecebido = (float) ContaReceber::query()
            ->whereBetween('recebido_em', [$de->toDateString(), $ate->toDateString()])
            ->sum('valor_recebido');

        $pagarAberto = (float) ContaPagar::query()
            ->where('saldo', '>', 0)
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()])
            ->sum('saldo');

        $pagarPago = (float) ContaPagar::query()
            ->whereBetween('pago_em', [$de->toDateString(), $ate->toDateString()])
            ->sum('valor_pago');

        $entradas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('entrada');

        $saidas = (float) CaixaLancamento::query()
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->sum('saida');

        $rows = [
            ['grupo' => 'RECEBER', 'descricao' => 'Títulos em aberto (vencimento no período)', 'valor' => static::formatMoney($receberAberto)],
            ['grupo' => 'RECEBER', 'descricao' => 'Valores recebidos no período', 'valor' => static::formatMoney($receberRecebido)],
            ['grupo' => 'PAGAR', 'descricao' => 'Títulos em aberto (vencimento no período)', 'valor' => static::formatMoney($pagarAberto)],
            ['grupo' => 'PAGAR', 'descricao' => 'Valores pagos no período', 'valor' => static::formatMoney($pagarPago)],
            ['grupo' => 'CAIXA', 'descricao' => 'Entradas de caixa', 'valor' => static::formatMoney($entradas)],
            ['grupo' => 'CAIXA', 'descricao' => 'Saídas de caixa', 'valor' => static::formatMoney($saidas)],
            ['grupo' => 'CAIXA', 'descricao' => 'Saldo líquido de caixa', 'valor' => static::formatMoney($entradas - $saidas)],
            ['grupo' => 'RESULTADO', 'descricao' => 'Recebidos − Pagos', 'valor' => static::formatMoney($receberRecebido - $pagarPago)],
        ];

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
            withTotals: false,
        );
    }
}
