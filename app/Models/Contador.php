<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'codigo',
    'nome',
    'cnpj_cpf',
    'crc',
    'cep',
    'endereco',
    'numero',
    'bairro',
    'cidade',
    'uf',
    'email',
    'fone',
])]
class Contador extends Model
{
    protected $table = 'contadores';

    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public static function formatCnpjCpf(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 11) {
            return self::formatCpf($digits);
        }

        return self::formatCnpj($digits);
    }

    public static function formatCep(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 8) {
            return $value;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    public static function formatFone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
        }

        return $value;
    }

    private static function formatCpf(string $digits): string
    {
        $digits = substr($digits, 0, 11);

        if (strlen($digits) <= 3) {
            return $digits;
        }

        if (strlen($digits) <= 6) {
            return substr($digits, 0, 3).'.'.substr($digits, 3);
        }

        if (strlen($digits) <= 9) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6);
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9);
    }

    private static function formatCnpj(string $digits): string
    {
        $digits = substr($digits, 0, 14);

        if (strlen($digits) <= 2) {
            return $digits;
        }

        if (strlen($digits) <= 5) {
            return substr($digits, 0, 2).'.'.substr($digits, 2);
        }

        if (strlen($digits) <= 8) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5);
        }

        if (strlen($digits) <= 12) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8);
        }

        return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12);
    }
}
