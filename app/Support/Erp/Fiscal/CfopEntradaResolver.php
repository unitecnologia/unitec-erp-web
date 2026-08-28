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
    /** CFOP de saída tipicamente usados em substituição tributária. */
    private const CFOP_SAIDA_ST = [
        '5401', '5402', '5403', '5405',
        '6401', '6402', '6403', '6404',
    ];

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

    public static function isCfopSaidaSt(string|int|null $cfop): bool
    {
        $digits = preg_replace('/\D/', '', (string) $cfop) ?? '';

        if (strlen($digits) > 4) {
            $digits = substr($digits, -4);
        }

        return strlen($digits) === 4 && in_array($digits, self::CFOP_SAIDA_ST, true);
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

    /**
     * Resolve CFOP de entrada para um item, respeitando ST:
     * item ST não cai no fallback genérico (1102) só porque o convertido não está cadastrado.
     */
    public function resolveParaItem(string|int|null $cfop, ?string $fallback = null, bool $temSt = false): string
    {
        if (! $temSt) {
            return $this->resolve($cfop, $fallback);
        }

        $entrada = $this->toEntrada($cfop);

        if ($this->isEntrada($entrada) && $this->existsEntrada($entrada)) {
            return $entrada;
        }

        // Mantém o convertido ST mesmo sem cadastro (evita 1102).
        if ($this->isEntrada($entrada) && strlen($entrada) === 4) {
            return $entrada;
        }

        $saidaNorm = $this->normalize($cfop);
        $interestadual = strlen($saidaNorm) === 4 && in_array($saidaNorm[0], ['6', '2'], true);
        $stFallback = $interestadual ? '2403' : '1403';

        if ($this->existsEntrada($stFallback)) {
            return $stFallback;
        }

        if ($this->isEntrada($stFallback)) {
            return $stFallback;
        }

        return $this->resolve($cfop, $fallback);
    }
}
