<?php

namespace App\Support\Erp;

use App\Models\Person;

final class DocumentoBrasileiroValidator
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValidCpf(string $value): bool
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($length = 9; $length < 11; $length++) {
            $sum = 0;

            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * (($length + 1) - $index);
            }

            $check = ((10 * $sum) % 11) % 10;

            if ((int) $digits[$length] !== $check) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $value): bool
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($round = 0; $round < 2; $round++) {
            $sum = 0;
            $weights = $round === 0 ? $weightsFirst : $weightsSecond;
            $limit = $round === 0 ? 12 : 13;

            for ($index = 0; $index < $limit; $index++) {
                $sum += (int) $digits[$index] * $weights[$index];
            }

            $remainder = $sum % 11;
            $check = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $digits[12 + $round] !== $check) {
                return false;
            }
        }

        return true;
    }

    public static function mensagemCpf(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) !== 11) {
            return 'Informe um CPF válido com 11 dígitos.';
        }

        if (! self::isValidCpf($digits)) {
            return 'CPF inválido. Verifique os números digitados.';
        }

        return null;
    }

    public static function mensagemCnpj(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === '') {
            return null;
        }

        if ($digits === '00000000000000') {
            return null;
        }

        if (strlen($digits) !== 14) {
            return 'Informe um CNPJ válido com 14 dígitos.';
        }

        if (! self::isValidCnpj($digits)) {
            return 'CNPJ inválido. Verifique os números digitados.';
        }

        return null;
    }

    public static function mensagemCpfCnpj(?string $value, ?string $pessoaTipo = null): ?string
    {
        $digits = self::digits($value);

        if ($digits === '') {
            return null;
        }

        $tipo = mb_strtolower(trim((string) $pessoaTipo), 'UTF-8');

        if ($tipo === Person::PESSOA_FISICA || $tipo === 'fisica') {
            return self::mensagemCpf($digits);
        }

        if ($tipo === Person::PESSOA_JURIDICA || $tipo === 'juridica') {
            return self::mensagemCnpj($digits);
        }

        if (strlen($digits) <= 11) {
            return self::mensagemCpf($digits);
        }

        return self::mensagemCnpj($digits);
    }
}
