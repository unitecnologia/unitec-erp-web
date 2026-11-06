<?php

namespace App\Support\Erp\Pdv;

use App\Models\Person;
use App\Support\Erp\DocumentoBrasileiroValidator;

final class PdvNotaClienteService
{
    /**
     * NFC-e (SC): campo "CPF na Nota" aceita somente CPF de pessoa física (11 dígitos).
     */
    public function validaDocumentoCpfNota(string $cpfNota): ?string
    {
        $digits = preg_replace('/\D/', '', $cpfNota) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 14) {
            return $this->mensagemCnpjNaoPermitidoNfce();
        }

        return DocumentoBrasileiroValidator::mensagemCpf($cpfNota);
    }

    public function mensagemCnpjNaoPermitidoNfce(): string
    {
        return 'Conforme a legislação de SC, CNPJ não pode constar na NFC-e. Este campo aceita apenas CPF de pessoa física.';
    }

    /**
     * Extrai CPF formatado (000.000.000-00) para o campo "CPF na Nota".
     * CNPJ, documento inválido ou incompleto → null (campo deve ficar vazio).
     */
    public static function extrairCpfParaNota(?string $cpfCnpj, ?string $pessoaTipo = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $cpfCnpj) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($pessoaTipo === Person::PESSOA_JURIDICA || strlen($digits) === 14) {
            return null;
        }

        if (strlen($digits) !== 11 || ! DocumentoBrasileiroValidator::isValidCpf($digits)) {
            return null;
        }

        return self::formatarCpfNota($digits);
    }

    public static function formatarCpfNota(string $digitsOrMasked): string
    {
        $digits = preg_replace('/\D/', '', $digitsOrMasked) ?? '';

        if (strlen($digits) !== 11) {
            return '';
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }
}
