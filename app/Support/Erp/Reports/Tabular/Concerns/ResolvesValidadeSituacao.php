<?php

namespace App\Support\Erp\Reports\Tabular\Concerns;

trait ResolvesValidadeSituacao
{
    /**
     * @return array<string, string>
     */
    protected static function situacaoOptions(): array
    {
        return [
            'todos' => 'Todos',
            'vencido' => 'Vencido',
            'critico' => 'Crítico (≤7 dias)',
            'atencao' => 'Atenção',
            'ok' => 'OK',
        ];
    }

    /**
     * @return 'vencido'|'critico'|'atencao'|'ok'
     */
    protected static function situacaoFromDias(?int $dias, int $diasAlerta = 30): string
    {
        if ($dias === null) {
            return 'ok';
        }

        if ($dias < 0) {
            return 'vencido';
        }

        if ($dias <= 7) {
            return 'critico';
        }

        if ($dias <= max(7, $diasAlerta)) {
            return 'atencao';
        }

        return 'ok';
    }

    protected static function situacaoLabel(string $situacao): string
    {
        return match ($situacao) {
            'vencido' => 'Vencido',
            'critico' => 'Crítico',
            'atencao' => 'Atenção',
            default => 'OK',
        };
    }

    protected static function parseDiasAlerta(mixed $value, int $default = 30): int
    {
        $n = (int) preg_replace('/\D/', '', (string) ($value ?? '')) ?: 0;

        if ($n <= 0) {
            return $default;
        }

        return min(3650, $n);
    }
}
