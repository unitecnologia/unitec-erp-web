<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ContaReceber;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class ContasReceberReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'contas-receber';
    }

    public function title(): string
    {
        return 'CONTAS A RECEBER';
    }

    public function permission(): string
    {
        return 'contas_receber.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'emissao' => 'EMISSÃO',
            'vencimento' => 'VENCIMENTO',
            'cliente' => 'CLIENTE',
            'documento' => 'DOCUMENTO',
            'forma' => 'FORMA',
            'valor' => 'VALOR',
            'recebido' => 'RECEBIDO',
            'saldo' => 'SALDO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['valor', 'recebido', 'saldo'];
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
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $situacao = (string) $request->query('situacao', 'todos');

        $query = ContaReceber::query()
            ->with('cliente')
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('vencimento')
            ->orderBy('id');

        if ($situacao === 'abertos') {
            $query->where('saldo', '>', 0);
        } elseif ($situacao === 'baixados') {
            $query->where('saldo', '<=', 0);
        } elseif ($situacao === 'vencidos') {
            $query->where('saldo', '>', 0)->whereDate('vencimento', '<', now()->toDateString());
        }

        $rows = $query->limit(5000)->get()->map(fn (ContaReceber $conta): array => [
            'numero' => (string) $conta->numero,
            'emissao' => static::formatDate($conta->emissao),
            'vencimento' => static::formatDate($conta->vencimento),
            'cliente' => (string) ($conta->cliente?->nome_razao ?? ''),
            'documento' => (string) ($conta->documento ?? ''),
            'forma' => ContaReceber::formaLabels()[$conta->forma] ?? (string) $conta->forma,
            'valor' => static::formatMoney((float) $conta->valor),
            'recebido' => static::formatMoney((float) $conta->valor_recebido),
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
