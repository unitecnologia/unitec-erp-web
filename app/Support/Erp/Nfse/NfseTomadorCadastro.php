<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Person;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\ErpContext;

/**
 * Campos obrigatórios do tomador/cliente para emitir NFS-e.
 * Espelha NfsePage (nome + CPF/CNPJ) e NfseIpmXmlGerador (endereço no provedor IPM).
 */
final class NfseTomadorCadastro
{
    public static function provedorExigeEndereco(?Empresa $empresa = null): bool
    {
        $empresa ??= ErpContext::currentEmpresa();

        return strtolower(trim((string) ($empresa?->nfse_provedor ?? ''))) === 'ipm';
    }

    /**
     * @return list<string>
     */
    public static function camposFaltantesDoCliente(?Person $cliente, ?Empresa $empresa = null): array
    {
        if ($cliente === null) {
            return ['cliente'];
        }

        return self::camposFaltantes([
            'nome' => $cliente->nome_razao,
            'cpf_cnpj' => $cliente->cpf_cnpj,
            'endereco' => $cliente->endereco,
            'bairro' => $cliente->bairro,
            'cidade_codigo' => $cliente->cidade_codigo,
            'uf' => $cliente->uf,
            'cep' => $cliente->cep,
        ], self::provedorExigeEndereco($empresa));
    }

    /**
     * @param  array{
     *     nome?: mixed,
     *     cpf_cnpj?: mixed,
     *     endereco?: mixed,
     *     bairro?: mixed,
     *     cidade_codigo?: mixed,
     *     uf?: mixed,
     *     cep?: mixed
     * }  $tomador
     * @return list<string>
     */
    public static function camposFaltantes(array $tomador, bool $enderecoObrigatorio = false): array
    {
        $faltando = [];

        if (trim((string) ($tomador['nome'] ?? '')) === '') {
            $faltando[] = 'nome/razão social';
        }

        $documento = preg_replace('/\D/', '', (string) ($tomador['cpf_cnpj'] ?? '')) ?? '';

        if (strlen($documento) !== 11 && strlen($documento) !== 14) {
            $faltando[] = 'CPF/CNPJ';
        }

        if ($enderecoObrigatorio) {
            $faltando = array_merge($faltando, self::camposFaltantesEndereco($tomador));
        }

        return $faltando;
    }

    /**
     * Mesmas regras de NfseIpmXmlGerador::endereco quando o tomador está identificado.
     *
     * @param  array{
     *     endereco?: mixed,
     *     bairro?: mixed,
     *     cidade_codigo?: mixed,
     *     uf?: mixed,
     *     cep?: mixed
     * }  $tomador
     * @return list<string>
     */
    public static function camposFaltantesEndereco(array $tomador): array
    {
        $faltando = [];

        if (trim((string) ($tomador['endereco'] ?? '')) === '') {
            $faltando[] = 'endereço';
        }

        if (trim((string) ($tomador['bairro'] ?? '')) === '') {
            $faltando[] = 'bairro';
        }

        if (! CepLookupService::isValidIbgeCode($tomador['cidade_codigo'] ?? null)) {
            $faltando[] = 'município (código IBGE)';
        }

        if (strlen(strtoupper(trim((string) ($tomador['uf'] ?? '')))) !== 2) {
            $faltando[] = 'UF';
        }

        $cep = preg_replace('/\D/', '', (string) ($tomador['cep'] ?? '')) ?? '';

        if (strlen($cep) !== 8) {
            $faltando[] = 'CEP';
        }

        return $faltando;
    }

    /**
     * Mensagem estável do XML IPM quando o endereço obrigatório está incompleto.
     *
     * @param  array{
     *     endereco?: mixed,
     *     bairro?: mixed,
     *     cidade_codigo?: mixed,
     *     uf?: mixed,
     *     cep?: mixed
     * }  $tomador
     */
    public static function motivoEnderecoIncompleto(array $tomador): ?string
    {
        if (self::camposFaltantesEndereco($tomador) === []) {
            return null;
        }

        return 'Complete o endereço do tomador: logradouro, bairro, município, UF e CEP com 8 dígitos.';
    }
}
