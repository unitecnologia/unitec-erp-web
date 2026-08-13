<?php

namespace App\Support\Erp\Fiscal;

use App\Models\Cfop;

/**
 * Converte CFOP de saída (XML do fornecedor) para CFOP de entrada (compra)
 * e valida contra o cadastro local (tipo E — grupos 1/2/3).
 *
 * Mapeamento clássico do dígito inicial:
 * 5xxx (saída interna) → 1xxx
 * 6xxx (saída interestadual) → 2xxx
 * 7xxx (saída exterior) → 3xxx
 */
final class CfopEntradaResolver
{
    public function normalize(string|int|null $cfop): string
    {
        $digits = preg_replace('/\D/', '', (string) $cfop) ?? '';

        if (strlen($digits) > 4) {
            $digits = substr($digits, -4);
        }

        if (strlen($digits) < 4) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Converte saída → entrada; se já for entrada (1/2/3), mantém.
     */
    public function toEntrada(string|int|null $cfop): string
    {
        $codigo = $this->normalize($cfop);

        if (strlen($codigo) !== 4) {
            return $codigo;
        }

        $primeiro = $codigo[0];

        $mapa = [
            '5' => '1',
            '6' => '2',
            '7' => '3',
        ];

        if (isset($mapa[$primeiro])) {
            return $mapa[$primeiro].substr($codigo, 1);
        }

        return $codigo;
    }

    public function isEntrada(string|int|null $cfop): bool
    {
        $codigo = $this->normalize($cfop);

        if (strlen($codigo) !== 4) {
            return false;
        }

        return in_array($codigo[0], ['1', '2', '3'], true);
    }

    public function existsEntrada(string|int|null $cfop): bool
    {
        $codigo = $this->toEntrada($cfop);

        if (! $this->isEntrada($codigo)) {
            return false;
        }

        return Cfop::query()
            ->where('codigo', (int) $codigo)
            ->where('tipo', Cfop::TIPO_ENTRADA)
            ->where('ativo', true)
            ->exists();
    }

    /**
     * Resolve CFOP de entrada para a tela de importação:
     * converte saída→entrada e, se o código convertido não existir no cadastro,
     * usa o fallback (ex.: param_imp_cfop_compra) quando informado.
     */
    public function resolve(string|int|null $cfop, ?string $fallback = null): string
    {
        $entrada = $this->toEntrada($cfop);
        $fallbackNorm = $this->toEntrada($fallback ?? '');

        if ($this->isEntrada($entrada) && $this->existsEntrada($entrada)) {
            return $entrada;
        }

        if ($this->isEntrada($fallbackNorm) && $this->existsEntrada($fallbackNorm)) {
            return $fallbackNorm;
        }

        if ($this->isEntrada($fallbackNorm)) {
            return $fallbackNorm;
        }

        if ($this->isEntrada($entrada)) {
            return $entrada;
        }

        return $entrada !== '' ? $entrada : $fallbackNorm;
    }
}
