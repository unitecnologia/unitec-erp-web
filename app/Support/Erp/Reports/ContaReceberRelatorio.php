<?php

namespace App\Support\Erp\Reports;

use App\Models\ContaReceber;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;

class ContaReceberRelatorio
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
            'historico' => 'HISTÓRICO',
            'forma' => 'FORMA',
            'valor' => 'VALOR',
            'desconto' => 'DESCONTO',
            'juros' => 'JUROS',
            'multa' => 'MULTA',
            'valor_recebido' => 'VALOR RECEBIDO',
            'saldo' => 'SALDO',
            'situacao' => 'SITUAÇÃO',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(static::columnDefinitions());
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

    /**
     * @return array<string, string>
     */
    public static function formaFiltroLabels(): array
    {
        return [
            'todos' => 'Todos',
            ContaReceber::FORMA_CARTEIRA => 'Carteira',
            ContaReceber::FORMA_CHEQUE => 'Cheques',
            ContaReceber::FORMA_CARTAO => 'Cartão',
            ContaReceber::FORMA_BOLETO => 'Boleto',
        ];
    }

    public static function cellValue(ContaReceber $conta, string $column): string
    {
        return match ($column) {
            'numero' => static::formatNumero($conta->numero),
            'emissao' => static::formatDate($conta->emissao),
            'vencimento' => static::formatDate($conta->vencimento),
            'cliente' => mb_strtoupper((string) ($conta->cliente?->nome_razao ?? ''), 'UTF-8'),
            'documento' => (string) ($conta->documento ?? ''),
            'historico' => mb_strtoupper((string) ($conta->historico ?? ''), 'UTF-8'),
            'forma' => ContaReceber::formaLabels()[(string) $conta->forma] ?? mb_strtoupper((string) $conta->forma, 'UTF-8'),
            'valor' => static::formatMoney((float) $conta->valor),
            'desconto' => static::formatMoney((float) $conta->desconto),
            'juros' => static::formatMoney((float) $conta->juros),
            'multa' => static::formatMoney((float) $conta->multa),
            'valor_recebido' => static::formatMoney((float) $conta->valor_recebido),
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

        return 'À RECEBER';
    }

    public static function formatNumero(mixed $numero): string
    {
        $texto = trim((string) ($numero ?? ''));

        if ($texto === '') {
            return '';
        }

        $semZeros = ltrim($texto, '0');

        return $semZeros !== '' ? $semZeros : '0';
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
        return in_array($column, ['valor', 'desconto', 'juros', 'multa', 'valor_recebido', 'saldo'], true);
    }

    /**
     * Peso relativo da coluna no papel. A largura final é a fatia entre as colunas visíveis.
     *
     * @param  list<string>  $visible
     */
    public static function columnWidthPercent(string $column, array $visible): string
    {
        $weights = [
            'numero' => 6,
            'emissao' => 12,
            'vencimento' => 13,
            'cliente' => 34,
            'documento' => 16,
            'historico' => 16,
            'forma' => 10,
            'valor' => 12,
            'desconto' => 11,
            'juros' => 10,
            'multa' => 9,
            'valor_recebido' => 13,
            'saldo' => 12,
            'situacao' => 12,
        ];

        $sum = 0;

        foreach ($visible as $key) {
            $sum += $weights[$key] ?? 10;
        }

        $weight = $weights[$column] ?? 10;
        $percent = $sum > 0 ? ($weight / $sum) * 100 : 0;

        return number_format($percent, 2, '.', '').'%';
    }
}
