<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ContaReceber;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            'empresa' => 'EMPRESA',
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
        return [
            'numero',
            'emissao',
            'vencimento',
            'cliente',
            'documento',
            'forma',
            'valor',
            'recebido',
            'saldo',
        ];
    }

    public function numericColumns(): array
    {
        return ['valor', 'recebido', 'saldo'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
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
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'emissao',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $multi = $this->isMultiEmpresaScope($request);
        $hasEmpresa = Schema::hasColumn((new ContaReceber)->getTable(), 'empresa_id');

        $query = ContaReceber::query()
            ->with(['cliente', 'empresa'])
            ->whereBetween('vencimento', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('vencimento')
            ->orderBy('id');

        if ($hasEmpresa) {
            ReportEmpresaScope::applyToQueryAllowingNullForSingle($query, $request, 'empresa_id');
        }

        if ($situacao === 'abertos') {
            $query->where('saldo', '>', 0);
        } elseif ($situacao === 'baixados') {
            $query->where('saldo', '<=', 0);
        } elseif ($situacao === 'vencidos') {
            $query->where('saldo', '>', 0)->whereDate('vencimento', '<', now()->toDateString());
        }

        $rows = $query->limit(5000)->get()->map(function (ContaReceber $conta) use ($multi): array {
            $row = [
                'numero' => (string) $conta->numero,
                'emissao' => static::formatDate($conta->emissao),
                'vencimento' => static::formatDate($conta->vencimento),
                'cliente' => (string) ($conta->cliente?->nome_razao ?? ''),
                'documento' => (string) ($conta->documento ?? ''),
                'forma' => ContaReceber::formaLabels()[$conta->forma] ?? (string) $conta->forma,
                'valor' => static::formatMoney((float) $conta->valor),
                'recebido' => static::formatMoney((float) $conta->valor_recebido),
                'saldo' => static::formatMoney((float) $conta->saldo),
            ];

            if ($multi) {
                $row['empresa'] = ReportEmpresaScope::labelEmpresa($conta->empresa);
            }

            return $row;
        })->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'VENCIMENTO: '.$this->periodLabel($de, $ate),
                'SITUAÇÃO: '.mb_strtoupper($situacao, 'UTF-8'),
            ], $request),
        );
    }
}
