<?php

namespace App\Support\Erp\Reports;

use App\Models\ContaReceber;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ContaReceberCartoesReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'numero' => 'NÚMERO',
            'emissao' => 'EMISSÃO',
            'vencimento' => 'VENCIMENTO',
            'cliente' => 'CLIENTE',
            'documento' => 'DOCUMENTO',
            'bandeira' => 'BANDEIRA',
            'maquininha' => 'MAQUININHA',
            'nsu' => 'NSU',
            'autorizacao' => 'AUTORIZAÇÃO',
            'parcela' => 'PARC.',
            'valor' => 'VALOR',
            'saldo' => 'SALDO',
            'situacao' => 'SITUAÇÃO',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return [
            'numero',
            'emissao',
            'vencimento',
            'cliente',
            'bandeira',
            'maquininha',
            'nsu',
            'parcela',
            'valor',
            'saldo',
            'situacao',
        ];
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public static function resolveColumns(?array $requested): array
    {
        $allowed = array_keys(static::columnDefinitions());

        if ($requested === null || $requested === []) {
            return static::defaultColumns();
        }

        $columns = [];

        foreach ($requested as $column) {
            if (in_array($column, $allowed, true)) {
                $columns[] = $column;
            }
        }

        return $columns !== [] ? $columns : static::defaultColumns();
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            'todos' => 'Todos',
            'a_receber' => 'À Receber',
            'atrasadas' => 'Atrasadas',
            'recebidas' => 'Recebidas',
        ];
    }

    public static function cellValue(ContaReceber $conta, string $column): string
    {
        return match ($column) {
            'numero' => (string) ($conta->numero ?? ''),
            'emissao' => static::formatDate($conta->emissao),
            'vencimento' => static::formatDate($conta->vencimento),
            'cliente' => mb_strtoupper((string) ($conta->cliente?->nome_razao ?? ''), 'UTF-8'),
            'documento' => (string) ($conta->documento ?? ''),
            'bandeira' => mb_strtoupper((string) ($conta->cartao_bandeira ?? ''), 'UTF-8'),
            'maquininha' => mb_strtoupper((string) ($conta->cartao_maquininha ?? ''), 'UTF-8'),
            'nsu' => (string) ($conta->cartao_nsu ?? ''),
            'autorizacao' => (string) ($conta->cartao_autorizacao ?? ''),
            'parcela' => (string) ($conta->cartao_parcela ?? ''),
            'valor' => static::formatMoney((float) $conta->valor),
            'saldo' => static::formatMoney((float) $conta->saldo),
            'situacao' => static::situacaoTitulo($conta),
            default => '',
        };
    }

    public static function situacaoTitulo(ContaReceber $conta): string
    {
        if ((float) $conta->saldo <= 0) {
            return 'RECEBIDA';
        }

        $hoje = ErpTimezone::toLocal()->startOfDay();
        $venc = $conta->vencimento ? ErpTimezone::toLocal($conta->vencimento)->startOfDay() : null;

        if ($venc && $venc->lt($hoje)) {
            return 'ATRASADA';
        }

        if ($venc && $venc->isSameDay($hoje)) {
            return 'VENCE HOJE';
        }

        return 'À RECEBER';
    }

    public static function formatDate(mixed $value): string
    {
        if (! filled($value)) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse((string) $value)->format('d/m/Y');
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function isNumericColumn(string $column): bool
    {
        return in_array($column, ['valor', 'saldo'], true);
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    /**
     * @param  Collection<int, ContaReceber>  $contas
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(Collection $contas, array $columns): array
    {
        $totals = [];

        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $totals[$column] = 'TOTAIS';

                continue;
            }

            if (! static::isSummableColumn($column)) {
                $totals[$column] = '';

                continue;
            }

            $sum = $contas->sum(fn (ContaReceber $conta): float => (float) $conta->{$column});
            $totals[$column] = static::formatMoney((float) $sum);
        }

        return $totals;
    }

    /**
     * @param  Collection<int, ContaReceber>  $contas
     * @return list<array{bandeira: string, qtd: int, valor: float, saldo: float}>
     */
    public static function totaisPorBandeira(Collection $contas): array
    {
        return $contas
            ->groupBy(fn (ContaReceber $c): string => filled($c->cartao_bandeira)
                ? mb_strtoupper((string) $c->cartao_bandeira, 'UTF-8')
                : 'SEM BANDEIRA')
            ->map(fn (Collection $group, string $bandeira): array => [
                'bandeira' => $bandeira,
                'qtd' => $group->count(),
                'valor' => round((float) $group->sum('valor'), 2),
                'saldo' => round((float) $group->sum('saldo'), 2),
            ])
            ->sortBy('bandeira')
            ->values()
            ->all();
    }
}
