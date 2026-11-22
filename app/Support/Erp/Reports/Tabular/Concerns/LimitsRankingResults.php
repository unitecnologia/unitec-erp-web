<?php

namespace App\Support\Erp\Reports\Tabular\Concerns;

use Illuminate\Http\Request;

/**
 * Limite de linhas exibidas em rankings de produtos (após calcular/ordenar).
 */
trait LimitsRankingResults
{
    public const RESULT_LIMIT_DEFAULT = 10;

    public const RESULT_LIMIT_MIN = 1;

    public const RESULT_LIMIT_MAX = 500;

    /**
     * Sugestões rápidas no campo (datalist). Qualquer inteiro válido também é aceito.
     *
     * @return list<int>
     */
    public static function resultLimitSuggestions(): array
    {
        return [10, 20, 30, 50];
    }

    public static function normalizeResultLimit(mixed $raw): int
    {
        if (is_array($raw)) {
            $raw = reset($raw);
        }

        if ($raw === null || $raw === '') {
            return self::RESULT_LIMIT_DEFAULT;
        }

        if (! is_numeric($raw)) {
            return self::RESULT_LIMIT_DEFAULT;
        }

        $n = (int) $raw;
        if ($n < self::RESULT_LIMIT_MIN) {
            return self::RESULT_LIMIT_DEFAULT;
        }

        return min($n, self::RESULT_LIMIT_MAX);
    }

    protected function resultLimitFromRequest(Request $request): int
    {
        return self::normalizeResultLimit($request->query('qtd_resultados', self::RESULT_LIMIT_DEFAULT));
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultLimitField(): array
    {
        return [
            'key' => 'qtd_resultados',
            'label' => 'Quantidade de resultados',
            'type' => 'number',
            'min' => self::RESULT_LIMIT_MIN,
            'max' => self::RESULT_LIMIT_MAX,
            'step' => 1,
            'placeholder' => '10',
            'list' => array_map(static fn (int $n): string => (string) $n, self::resultLimitSuggestions()),
        ];
    }

    /**
     * @template T
     *
     * @param  list<T>  $rows
     * @return list<T>
     */
    protected function applyResultLimit(array $rows, int $limit): array
    {
        if ($limit <= 0 || count($rows) <= $limit) {
            return $rows;
        }

        return array_values(array_slice($rows, 0, $limit));
    }
}
