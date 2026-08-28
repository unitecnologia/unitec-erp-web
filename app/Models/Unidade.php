<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sigla', 'descricao', 'ativo'])]
class Unidade extends Model
{
    /**
     * Descrições canônicas (exibição em caixa alta no select).
     *
     * @return array<string, string>
     */
    public static function descricoesCanonicas(): array
    {
        return [
            'UN' => 'UNIDADE',
            'PC' => 'PEÇA',
            'KG' => 'QUILOGRAMA',
            'CX' => 'CAIXA',
            'LT' => 'LITRO',
            'MT' => 'METRO',
            'M2' => 'METRO QUADRADO',
            'M3' => 'METRO CÚBICO',
            'PAR' => 'PAR',
            'SC' => 'SACO',
            'KIT' => 'KIT',
            'G' => 'GRAMA',
            'ML' => 'MILILITRO',
            'DZ' => 'DÚZIA',
            'FD' => 'FARDO',
            'PCT' => 'PACOTE',
            'RL' => 'ROLO',
            'CJ' => 'CONJUNTO',
        ];
    }

    public static function normalizeDescricao(string $sigla, ?string $descricao = null): string
    {
        $sigla = strtoupper(trim($sigla));
        $canon = self::descricoesCanonicas()[$sigla] ?? null;

        if ($canon !== null) {
            return $canon;
        }

        $raw = trim((string) $descricao);

        if ($raw === '' || strcasecmp($raw, $sigla) === 0) {
            return $sigla;
        }

        return mb_strtoupper($raw, 'UTF-8');
    }

    /** Rótulo do select: "KG — QUILOGRAMA". */
    public static function optionLabel(string $sigla, ?string $descricao = null): string
    {
        $sigla = strtoupper(trim($sigla));
        $label = self::normalizeDescricao($sigla, $descricao);

        if ($label === $sigla) {
            return $sigla;
        }

        return $sigla.' — '.$label;
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
