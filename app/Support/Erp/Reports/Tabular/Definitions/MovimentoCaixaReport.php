<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class MovimentoCaixaReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'movimento-caixa';
    }

    public function title(): string
    {
        return 'MOVIMENTO DE CAIXA';
    }

    public function permission(): string
    {
        return 'caixa.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'emissao' => 'EMISSÃO',
            'documento' => 'DOCUMENTO',
            'historico' => 'HISTÓRICO',
            'plano' => 'PLANO',
            'conta' => 'CONTA',
            'entrada' => 'ENTRADA',
            'saida' => 'SAÍDA',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['entrada', 'saida'];
    }

    public function filterFields(): array
    {
        $contas = ['todos' => 'Todas'] + CaixaConta::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn ($nome, $id): array => [(string) $id => (string) $nome])
            ->all();

        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'conta',
                'label' => 'Conta',
                'type' => 'select',
                'options' => $contas,
            ],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $conta = (string) $request->query('conta', 'todos');

        $query = CaixaLancamento::query()
            ->with(['conta', 'planoConta'])
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('emissao')
            ->orderBy('codigo');

        if ($conta !== 'todos' && is_numeric($conta)) {
            $query->where('caixa_conta_id', (int) $conta);
        }

        $rows = $query->limit(5000)->get()->map(fn (CaixaLancamento $l): array => [
            'codigo' => (string) $l->codigo,
            'emissao' => static::formatDate($l->emissao),
            'documento' => (string) ($l->documento ?? ''),
            'historico' => (string) ($l->historico ?? ''),
            'plano' => (string) ($l->planoConta?->descricao ?: $l->plano_contas ?: ''),
            'conta' => (string) ($l->conta?->nome ?? ''),
            'entrada' => static::formatMoney((float) $l->entrada),
            'saida' => static::formatMoney((float) $l->saida),
        ])->all();

        $contaLabel = $conta === 'todos'
            ? 'TODAS'
            : (string) (CaixaConta::query()->find((int) $conta)?->nome ?? $conta);

        return $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'conta' => $conta,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            [
                'PERÍODO: ' . $this->periodLabel($de, $ate),
                'CONTA: ' . mb_strtoupper($contaLabel, 'UTF-8'),
            ],
        );
    }
}
