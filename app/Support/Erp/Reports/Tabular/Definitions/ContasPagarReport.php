<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ContaPagar;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            'empresa' => 'EMPRESA',
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
        return [
            'numero',
            'emissao',
            'vencimento',
            'fornecedor',
            'documento',
            'produto',
            'valor',
            'pago',
            'saldo',
        ];
    }

    public function numericColumns(): array
    {
        return ['valor', 'pago', 'saldo'];
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
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'emissao',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $multi = $this->isMultiEmpresaScope($request);
        $hasEmpresa = Schema::hasColumn((new ContaPagar)->getTable(), 'empresa_id');

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
            ->with(['fornecedor', 'empresa'])
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
            $query->where('saldo', '>', 0)->whereDate('vencimento', '<', ErpTimezone::toLocal()->toDateString());
        }

        $rows = $query->limit(5000)->get()->map(function (ContaPagar $conta) use ($multi): array {
            $row = [
                'numero' => (string) $conta->numero,
                'emissao' => static::formatDate($conta->emissao),
                'vencimento' => static::formatDate($conta->vencimento),
                'fornecedor' => (string) ($conta->fornecedor?->nome_razao ?? ''),
                'documento' => (string) ($conta->documento ?? ''),
                'produto' => (string) ($conta->produto ?? ''),
                'valor' => static::formatMoney((float) $conta->valor),
                'pago' => static::formatMoney((float) $conta->valor_pago),
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
