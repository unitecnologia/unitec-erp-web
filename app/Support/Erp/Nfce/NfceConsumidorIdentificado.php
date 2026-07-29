<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\DocumentoExibicao;

/**
 * Resolve o consumidor identificado da NFC-e (cadastro ou CPF na nota).
 * Nome e endereço vão para XML (produção) e cupom; CPF completo só no XML —
 * na exibição o CPF segue máscara LGPD.
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
        $cpf = trim((string) ($venda->cpf_nota ?? ''));

        if ($cpf === '') {
            return null;
        }

        return DocumentoExibicao::mascararCpf($cpf);
    }

    public static function cpfDigits(?string $cpf): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }
}
