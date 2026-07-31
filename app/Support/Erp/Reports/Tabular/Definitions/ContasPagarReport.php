<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ContaPagar;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class ContasPagarReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'contas-pagar';
    }

    public function title(): string
    {
        return 'CONTAS A PAGAR';
    }

    public function permission(): string
    {
        return 'contas_pagar.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'emissao' => 'EMISSÃO',
            'vencimento' => 'VENCIMENTO',
            'fornecedor' => 'FORNECEDOR',
            'documento' => 'DOCUMENTO',
            'produto' => 'HISTÓRICO',
            'valor' => 'VALOR',
            'pago' => 'PAGO',
            'saldo' => 'SALDO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['valor', 'pago', 'saldo'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'abertos' => 'Em aberto',
                    'baixados' => 'Baixados',
                    'vencidos' => 'Vencidos',
                ],
            ],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $situacao = (string) $request->query('situacao', 'todos');

        // Vencidos: janela ampla (até ontem) para alinhar ao KPI "Pagar vencido".
        if ($situacao === 'vencidos') {
            $hoje = ErpTimezone::toLocal();
            [$de, $ate] = $this->periodFromRequest(
                $request,
                $hoje->copy()->subDays(static::MAX_PERIOD_DAYS),
                $hoje->copy()->subDay(),
            );
        } else {
            [$de, $ate] = $this->periodFromRequest($request);
        }

        $query = ContaPagar::query()
            ->with('fornecedor')
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('vencimento')
            ->orderBy('id');

        if ($situacao === 'abertos') {
            $query->where('saldo', '>', 0);
        } elseif ($situacao === 'baixados') {
            $query->where('saldo', '<=', 0);
        } elseif ($situacao === 'vencidos') {
            $query->where('saldo', '>', 0)->whereDate('vencimento', '<', ErpTimezone::toLocal()->toDateString());
        }

        $rows = $query->limit(5000)->get()->map(fn (ContaPagar $conta): array => [
            'numero' => (string) $conta->numero,
            'emissao' => static::formatDate($conta->emissao),
            'vencimento' => static::formatDate($conta->vencimento),
            'fornecedor' => (string) ($conta->fornecedor?->nome_razao ?? ''),
            'documento' => (string) ($conta->documento ?? ''),
            'produto' => (string) ($conta->produto ?? ''),
            'valor' => static::formatMoney((float) $conta->valor),
            'pago' => static::formatMoney((float) $conta->valor_pago),
            'saldo' => static::formatMoney((float) $conta->saldo),
        ])->all();

        return $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            [
                'VENCIMENTO: ' . $this->periodLabel($de, $ate),
                'SITUAÇÃO: ' . mb_strtoupper($situacao, 'UTF-8'),
            ],
        );
    }
}
