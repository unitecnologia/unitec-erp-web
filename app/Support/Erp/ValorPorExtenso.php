<?php

namespace App\Support\Erp;

/**
 * Converte valores monetários para extenso em português (BRL).
 */
final class ValorPorExtenso
{
    /** @var list<string> */
    private const UNIDADES = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    /** @var list<string> */
    private const DEZENAS = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa',
    ];

    /** @var list<string> */
    private const CENTENAS = [
        '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos',
    ];

    public static function fromMoney(mixed $valor): string
    {
        $number = self::normalize($valor);

        if ($number === null) {
            return '';
        }

        if ($number < 0) {
            return '';
        }

        $inteiro = (int) floor($number);
        $centavos = (int) round(($number - $inteiro) * 100);

        if ($centavos === 100) {
            $inteiro++;
            $centavos = 0;
        }

        if ($inteiro === 0 && $centavos === 0) {
            return 'zero reais';
        }

        $partes = [];

        if ($inteiro > 0) {
            $partes[] = self::integerPart($inteiro).($inteiro === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $partes[] = self::groupBelowThousand($centavos).($centavos === 1 ? ' centavo' : ' centavos');
        }

        return implode(' e ', $partes);
    }

    public static function normalize(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }

        $raw = trim((string) $valor);

        if ($raw === '') {
            return null;
        }

        $raw = str_replace(['R$', ' '], '', $raw);

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    protected static function integerPart(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }

        $grupos = [
            [1_000_000_000, 'bilhão', 'bilhões'],
            [1_000_000, 'milhão', 'milhões'],
            [1_000, 'mil', 'mil'],
            [1, '', ''],
        ];

        $restante = $n;
        $partes = [];

        foreach ($grupos as [$divisor, $singular, $plural]) {
            if ($restante < $divisor && $divisor > 1) {
                continue;
            }

            $qtd = intdiv($restante, $divisor);
            $restante %= $divisor;

            if ($qtd === 0) {
                continue;
            }

            $texto = self::groupBelowThousand($qtd);

            if ($divisor >= 1000) {
                if ($qtd === 1 && $divisor === 1000) {
                    $partes[] = 'mil';
                } else {
                    $partes[] = $texto.' '.($qtd === 1 ? $singular : $plural);
                }
            } else {
                $partes[] = $texto;
            }
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        $ultimo = array_pop($partes);
        $penultimo = $partes[array_key_last($partes)] ?? '';
        $join = ' e ';

        // Padrão BR: "mil duzentos..." (sem "e" quando o restante tem centenas).
        if (
            ($penultimo === 'mil' || str_ends_with($penultimo, ' milhão') || str_ends_with($penultimo, ' milhões')
                || str_ends_with($penultimo, ' bilhão') || str_ends_with($penultimo, ' bilhões'))
            && preg_match('/^(cento|duzentos|trezentos|quatrocentos|quinhentos|seiscentos|setecentos|oitocentos|novecentos)\b/u', $ultimo) === 1
        ) {
            $join = ' ';
        }

        return implode(', ', $partes).$join.$ultimo;
    }

    protected static function groupBelowThousand(int $n): string
    {
        if ($n < 20) {
            return self::UNIDADES[$n];
        }

        if ($n < 100) {
            $dez = intdiv($n, 10);
            $uni = $n % 10;

            return $uni === 0
                ? self::DEZENAS[$dez]
                : self::DEZENAS[$dez].' e '.self::UNIDADES[$uni];
        }

        if ($n === 100) {
            return 'cem';
        }

        $cen = intdiv($n, 100);
        $resto = $n % 100;

        if ($resto === 0) {
            return self::CENTENAS[$cen];
        }

        return self::CENTENAS[$cen].' e '.self::groupBelowThousand($resto);
    }
}
