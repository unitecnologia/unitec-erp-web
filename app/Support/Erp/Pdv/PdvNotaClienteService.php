<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\DocumentoBrasileiroValidator;
use Carbon\Carbon;

final class PdvNotaClienteService
{
    public function __construct(
        private readonly PdvConfig $config,
    ) {}

    public function validaCpfNota(?int $personId, string $cpfNota, ?int $ignoreVendaId = null): ?string
    {
        if ($msg = $this->validaDocumentoCpfNota($cpfNota)) {
            return $msg;
        }

        $cpf = preg_replace('/\D/', '', $cpfNota) ?? '';

        if ($cpf === '') {
            return null;
        }

        $prazoDias = (int) $this->config->prazoMaxNotaCliente();

        if ($prazoDias <= 0) {
            return null;
        }

        $desde = Carbon::today()->subDays($prazoDias);

        $query = PdvVenda::query()
            ->where('created_at', '>=', $desde->startOfDay())
            ->where(function ($q) use ($cpf, $personId): void {
                $q->where('cpf_nota', 'like', '%' . $cpf . '%');

                if ($personId) {
                    $q->orWhere('person_id', $personId);
                }
            });

        if ($ignoreVendaId) {
            $query->where('id', '!=', $ignoreVendaId);
        }

        if ($query->exists()) {
            return 'CPF/cliente já utilizado em venda nos últimos ' . $prazoDias . ' dia(s).';
        }

        return null;
    }

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
