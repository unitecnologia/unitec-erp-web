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

    public static function extrairCpfParaNota(?string $cpfCnpj, ?string $pessoaTipo = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $cpfCnpj) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($pessoaTipo === Person::PESSOA_JURIDICA || strlen($digits) === 14) {
            return null;
        }

        return strlen($digits) === 11 ? (string) $cpfCnpj : null;
    }
}
