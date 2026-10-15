<?php

namespace App\Support\Erp\Nfe;

use App\Models\Person;
use App\Support\Erp\CepLookupService;

/**
 * Campos obrigatórios do cliente para emitir NF-e.
 * Espelha NfeVendaMercadoriaService::assertClienteAptoParaNfe e exige IE
 * quando o destinatário é contribuinte (indIEDest = 1).
 */
final class NfeClienteCadastro
{
    /**
     * @return list<string>
     */
    public static function camposFaltantesDoCliente(?Person $cliente): array
    {
        if ($cliente === null) {
            return ['cliente'];
        }

        if (Person::isCodigoConsumidorFinal($cliente->codigo)) {
            return ['cadastro completo (consumidor final não emite NF-e)'];
        }

        $faltando = [];
        $nome = mb_strtoupper(trim((string) ($cliente->nome_razao ?? '')), 'UTF-8');

        if ($nome === '' || $nome === 'CONSUMIDOR FINAL') {
            $faltando[] = 'nome/razão social';
        }

        $digits = preg_replace('/\D/', '', (string) ($cliente->cpf_cnpj ?? '')) ?? '';

        if (strlen($digits) !== 11 && strlen($digits) !== 14) {
            $faltando[] = 'CPF/CNPJ';
        }

        if (trim((string) ($cliente->endereco ?? '')) === '') {
            $faltando[] = 'endereço';
        }

        if (trim((string) ($cliente->bairro ?? '')) === '') {
            $faltando[] = 'bairro';
        }

        if (trim((string) ($cliente->uf ?? '')) === '') {
            $faltando[] = 'UF';
        }

        $cep = preg_replace('/\D/', '', (string) ($cliente->cep ?? '')) ?? '';

        if (strlen($cep) !== 8) {
            $faltando[] = 'CEP';
        }

        if (trim((string) ($cliente->cidade_nome ?? '')) === '') {
            $faltando[] = 'cidade';
        }

        if (! CepLookupService::isValidIbgeCode($cliente->cidade_codigo ?? null)) {
            $faltando[] = 'código IBGE da cidade';
        }

        if (self::exigeIe($cliente)) {
            $ie = trim((string) ($cliente->rg_ie ?? ''));
            $ieDigits = preg_replace('/\D/', '', $ie) ?? '';

            if ($ie === '' || strtoupper($ie) === 'ISENTO' || $ieDigits === '') {
                $faltando[] = 'IE (inscrição estadual)';
            }
        }

        return $faltando;
    }

    /**
     * IE obrigatória para PJ contribuinte (indIEDest = 1 no payload da NF-e).
     */
    public static function exigeIe(Person $cliente): bool
    {
        if ($cliente->isPessoaFisica()) {
            return false;
        }

        return strtolower(trim((string) ($cliente->tipo_contribuinte ?? ''))) === 'contribuinte';
    }

    /**
     * Mesmas regras fiscais do destinatário, com rótulos de fornecedor (devolução de compra).
     *
     * @return list<string>
     */
    public static function camposFaltantesDoFornecedor(?Person $fornecedor): array
    {
        $faltando = self::camposFaltantesDoCliente($fornecedor);

        return array_map(static function (string $campo): string {
            return match ($campo) {
                'cliente' => 'fornecedor',
                'cadastro completo (consumidor final não emite NF-e)' => 'cadastro completo (fornecedor inválido para NF-e)',
                default => $campo,
            };
        }, $faltando);
    }
}
