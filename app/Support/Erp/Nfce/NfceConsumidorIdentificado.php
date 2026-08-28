<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\DocumentoExibicao;

/**
 * Resolve o consumidor identificado da NFC-e (cadastro ou CPF na nota).
 * Nome, endereço e CPF formatado vão para o cupom; o XML fiscal usa CPF completo.
 * cpfMascarado permanece disponível para telas com máscara LGPD.
 */
final class NfceConsumidorIdentificado
{
    public static function resolvePerson(PdvVenda $venda): ?Person
    {
        $venda->loadMissing('person');

        $person = $venda->person;
        if (self::ehClienteIdentificado($person)) {
            return $person;
        }

        return self::findByCpf($venda->cpf_nota);
    }

    public static function findByCpf(?string $cpf): ?Person
    {
        $digits = self::cpfDigits($cpf);

        if ($digits === '') {
            return null;
        }

        return Person::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf_cnpj, ''), '.', ''), '-', ''), '/', ''), ' ', '') = ?",
                [$digits],
            )
            ->orderBy('id')
            ->get()
            ->first(fn (Person $p): bool => self::ehClienteIdentificado($p));
    }

    public static function ehClienteIdentificado(?Person $person): bool
    {
        if ($person === null) {
            return false;
        }

        if (Person::isCodigoConsumidorFinal($person->codigo ?? null)) {
            return false;
        }

        return filled($person->nome_razao);
    }

    public static function nome(?Person $person): ?string
    {
        if (! self::ehClienteIdentificado($person)) {
            return null;
        }

        $nome = trim((string) ($person->nome_razao ?: $person->apelido_fantasia ?: ''));

        return $nome !== '' ? $nome : null;
    }

    public static function endereco(?Person $person): ?string
    {
        if (! self::ehClienteIdentificado($person)) {
            return null;
        }

        $endereco = trim((string) ($person->endereco_lista ?? ''));

        return $endereco !== '' ? $endereco : null;
    }

    public static function cpfMascarado(PdvVenda $venda): ?string
    {
        $digits = self::cpfDigitsDaVenda($venda);

        if ($digits === '') {
            return null;
        }

        return DocumentoExibicao::mascararCpf($digits);
    }

    /**
     * CPF completo formatado para o cupom DANFE (identificação do consumidor).
     */
    public static function cpfFormatado(PdvVenda $venda): ?string
    {
        $digits = self::cpfDigitsDaVenda($venda);

        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    /**
     * Preferência: cpf_nota da venda; senão CPF 11 dígitos do cliente vinculado.
     */
    public static function cpfDigitsDaVenda(PdvVenda $venda): string
    {
        $digits = self::cpfDigits($venda->cpf_nota);

        if ($digits !== '') {
            return $digits;
        }

        $person = self::resolvePerson($venda);

        return self::cpfDigits($person?->cpf_cnpj);
    }

    public static function cpfDigits(?string $cpf): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }
}
