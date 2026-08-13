<?php

namespace App\Support\Erp\Balanca;

/**
 * Layouts de etiqueta/código de barras EAN de balança (modelos 01–05).
 * Paridade com param_pdv_modelo_balanca do PDV Delphi (01–04) + 05 (6 dígitos + total)
 * observado em etiquetas reais de açougue/balança.
 */
final class BalancaEtiquetaLayout
{
    public const DEFAULT_MODELO = 4;

    public const DEFAULT_PREFIXO = '2';

    public const DEFAULT_DIGITOS = 6;

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            1 => '01 — 4 dígitos + peso',
            2 => '02 — 5 dígitos + peso',
            3 => '03 — 5 dígitos + total',
            4 => '04 — 6 dígitos + peso',
            5 => '05 — 6 dígitos + total',
        ];
    }

    public static function normalizeModelo(mixed $modelo): int
    {
        $modelo = (int) $modelo;

        return in_array($modelo, [1, 2, 3, 4, 5], true) ? $modelo : self::DEFAULT_MODELO;
    }

    public static function normalizePrefixo(mixed $prefixo): string
    {
        $prefixo = preg_replace('/\D/', '', (string) $prefixo) ?? '';

        if ($prefixo === '') {
            return self::DEFAULT_PREFIXO;
        }

        return substr($prefixo, 0, 2);
    }

    public static function normalizeDigitos(mixed $digitos): int
    {
        $digitos = (int) $digitos;

        return in_array($digitos, [4, 5, 6], true) ? $digitos : self::DEFAULT_DIGITOS;
    }

    /**
     * Digitos padrão do código de produto conforme o modelo de layout.
     */
    public static function digitosForModelo(int $modelo): int
    {
        return match (self::normalizeModelo($modelo)) {
            1 => 4,
            2, 3 => 5,
            default => 6,
        };
    }

    /**
     * Zeros de preenchimento entre código do produto e peso/total.
     */
    public static function fillerLength(int $modelo): int
    {
        return match (self::normalizeModelo($modelo)) {
            1 => 1, // "0"
            default => 0,
        };
    }

    /**
     * Quantidade de dígitos do segmento peso (5) ou total/preço (5 ou 6).
     */
    public static function valorLength(int $modelo): int
    {
        return self::normalizeModelo($modelo) === 3 ? 6 : 5;
    }

    public static function isTotalPrice(int $modelo): bool
    {
        return in_array(self::normalizeModelo($modelo), [3, 5], true);
    }

    /**
     * Comprimento do início do EAN usado para localizar o produto (prefixo + código).
     */
    public static function productKeyLength(string $prefixo, int $digitos): int
    {
        return strlen(self::normalizePrefixo($prefixo)) + self::normalizeDigitos($digitos);
    }

    /**
     * Extrai o código do produto (PLU) do EAN, já com o tamanho configurado.
     */
    public static function productCodeFromBarcode(string $barcode, string $prefixo, int $digitos, int $modelo = 4): string
    {
        $barcode = preg_replace('/\D/', '', $barcode) ?? '';
        $prefixo = self::normalizePrefixo($prefixo);
        $digitos = self::normalizeDigitos($digitos);
        $start = strlen($prefixo);

        return str_pad(substr($barcode, $start, $digitos), $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Normaliza o código/PLU do produto para o mesmo formato do trecho do EAN.
     */
    public static function normalizeProductCode(string $productCode, string $eanPrefix, int $digitos): string
    {
        $raw = preg_replace('/\D/', '', $productCode) ?? '';
        $eanPrefix = self::normalizePrefixo($eanPrefix);
        $digitos = self::normalizeDigitos($digitos);

        if ($raw === '') {
            return str_repeat('0', $digitos);
        }

        if (str_starts_with($raw, $eanPrefix) && strlen($raw) > strlen($eanPrefix)) {
            $raw = substr($raw, strlen($eanPrefix));
        }

        if (strlen($raw) > $digitos) {
            $raw = substr($raw, -$digitos);
        }

        return str_pad($raw, $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Diagramas compactos dos layouts (guia visual).
     *
     * @return list<array{
     *     modelo: int,
     *     title: string,
     *     digitos: int,
     *     valor: string,
     *     parts: list<array{v: string, role: string, cap: string}>
     * }>
     */
    public static function diagrams(): array
    {
        return [
            [
                'modelo' => 1,
                'title' => '01',
                'digitos' => 4,
                'valor' => 'peso',
                'parts' => [
                    ['v' => '2', 'role' => 'prefix', 'cap' => 'Pref.'],
                    ['v' => 'CCCC', 'role' => 'prod', 'cap' => 'Produto'],
                    ['v' => '0', 'role' => 'fill', 'cap' => ''],
                    ['v' => 'PPPPP', 'role' => 'peso', 'cap' => 'Peso'],
                    ['v' => 'D', 'role' => 'dv', 'cap' => 'DV'],
                ],
            ],
            [
                'modelo' => 2,
                'title' => '02',
                'digitos' => 5,
                'valor' => 'peso',
                'parts' => [
                    ['v' => '2', 'role' => 'prefix', 'cap' => 'Pref.'],
                    ['v' => 'CCCCC', 'role' => 'prod', 'cap' => 'Produto'],
                    ['v' => 'PPPPP', 'role' => 'peso', 'cap' => 'Peso'],
                    ['v' => 'D', 'role' => 'dv', 'cap' => 'DV'],
                ],
            ],
            [
                'modelo' => 3,
                'title' => '03',
                'digitos' => 5,
                'valor' => 'total',
                'parts' => [
                    ['v' => '2', 'role' => 'prefix', 'cap' => 'Pref.'],
                    ['v' => 'CCCCC', 'role' => 'prod', 'cap' => 'Produto'],
                    ['v' => 'TTTTTT', 'role' => 'total', 'cap' => 'Total'],
                    ['v' => 'D', 'role' => 'dv', 'cap' => 'DV'],
                ],
            ],
            [
                'modelo' => 4,
                'title' => '04',
                'digitos' => 6,
                'valor' => 'peso',
                'parts' => [
                    ['v' => '2', 'role' => 'prefix', 'cap' => 'Pref.'],
                    ['v' => 'CCCCCC', 'role' => 'prod', 'cap' => 'Produto'],
                    ['v' => 'PPPPP', 'role' => 'peso', 'cap' => 'Peso'],
                    ['v' => 'D', 'role' => 'dv', 'cap' => 'DV'],
                ],
            ],
            [
                'modelo' => 5,
                'title' => '05',
                'digitos' => 6,
                'valor' => 'total',
                'parts' => [
                    ['v' => '2', 'role' => 'prefix', 'cap' => 'Pref.'],
                    ['v' => 'CCCCCC', 'role' => 'prod', 'cap' => 'Produto'],
                    ['v' => 'TTTTT', 'role' => 'total', 'cap' => 'Total'],
                    ['v' => 'D', 'role' => 'dv', 'cap' => 'DV'],
                ],
            ],
        ];
    }
}
