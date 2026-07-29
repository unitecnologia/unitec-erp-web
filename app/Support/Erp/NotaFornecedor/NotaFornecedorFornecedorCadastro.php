<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\Person;

/**
 * Garante cadastro do emitente do XML como fornecedor (sem interação).
 */
final class NotaFornecedorFornecedorCadastro
{
    /**
     * @param  array<string, mixed>  $emitente
     * @return array{
     *     person: ?Person,
     *     status: 'existente'|'automatico'|'sem_documento',
     *     label: string
     * }
     */
    public function ensure(array $emitente): array
    {
        $digits = preg_replace('/\D/', '', (string) ($emitente['cnpj'] ?? '')) ?? '';

        if (strlen($digits) < 11) {
            return [
                'person' => null,
                'status' => 'sem_documento',
                'label' => 'Sem CNPJ/CPF no XML',
            ];
        }

        $person = $this->findByDocumento($digits);

        if ($person && $person->is_fornecedor) {
            return [
                'person' => $person,
                'status' => 'existente',
                'label' => 'Fornecedor já cadastrado',
            ];
        }

        if ($person) {
            $person->forceFill(array_merge(
                $this->payloadFromEmitente($emitente, $digits, forUpdate: true),
                ['is_fornecedor' => true, 'ativo' => true],
            ))->save();

            return [
                'person' => $person->fresh() ?? $person,
                'status' => 'automatico',
                'label' => 'Fornecedor cadastrado automaticamente',
            ];
        }

        $person = Person::query()->create(array_merge(
            $this->payloadFromEmitente($emitente, $digits, forUpdate: false),
            [
                'codigo' => Person::nextCodigo(),
                'is_fornecedor' => true,
                'is_cliente' => false,
                'ativo' => true,
                'regime_tributario' => 'simples',
                'tipo_contribuinte' => filled($emitente['ie'] ?? null) ? 'contribuinte' : 'nao_contribuinte',
            ],
        ));

        return [
            'person' => $person,
            'status' => 'automatico',
            'label' => 'Fornecedor cadastrado automaticamente',
        ];
    }

    private function findByDocumento(string $digits): ?Person
    {
        return Person::query()
            ->where(function ($query) use ($digits): void {
                $query->where('cpf_cnpj', $digits)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', ''), ' ', '') = ?",
                        [$digits],
                    );
            })
            ->orderByDesc('is_fornecedor')
            ->orderByDesc('ativo')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $emitente
     * @return array<string, mixed>
     */
    private function payloadFromEmitente(array $emitente, string $digits, bool $forUpdate): array
    {
        $nome = mb_strtoupper(trim((string) ($emitente['nome'] ?? '')), 'UTF-8');
        $fantasia = mb_strtoupper(trim((string) ($emitente['fantasia'] ?? '')), 'UTF-8');
        $uf = mb_strtoupper(trim((string) ($emitente['uf'] ?? '')), 'UTF-8');
        $cep = preg_replace('/\D/', '', (string) ($emitente['cep'] ?? '')) ?? '';
        $fone = preg_replace('/\D/', '', (string) ($emitente['telefone'] ?? '')) ?? '';
        $ie = trim((string) ($emitente['ie'] ?? ''));

        $payload = [
            'pessoa_tipo' => strlen($digits) === 11 ? Person::PESSOA_FISICA : Person::PESSOA_JURIDICA,
            'cpf_cnpj' => $digits,
        ];

        if ($nome !== '') {
            $payload['nome_razao'] = $nome;
        } elseif (! $forUpdate) {
            $payload['nome_razao'] = 'FORNECEDOR '.$digits;
        }

        if ($fantasia !== '') {
            $payload['apelido_fantasia'] = $fantasia;
        }

        $logradouro = trim((string) ($emitente['logradouro'] ?? ''));
        if ($logradouro !== '') {
            $payload['endereco'] = $logradouro;
        }

        $numero = trim((string) ($emitente['numero'] ?? ''));
        if ($numero !== '') {
            $payload['numero'] = $numero;
        }

        $complemento = trim((string) ($emitente['complemento'] ?? ''));
        if ($complemento !== '') {
            $payload['complemento'] = $complemento;
        }

        $bairro = trim((string) ($emitente['bairro'] ?? ''));
        if ($bairro !== '') {
            $payload['bairro'] = $bairro;
        }

        $municipio = trim((string) ($emitente['municipio'] ?? ''));
        if ($municipio !== '') {
            $payload['cidade_nome'] = $municipio;
        }

        $municipioCodigo = trim((string) ($emitente['municipio_codigo'] ?? ''));
        if ($municipioCodigo !== '') {
            $payload['cidade_codigo'] = $municipioCodigo;
        }

        if ($uf !== '') {
            $payload['uf'] = $uf;
        }

        if (strlen($cep) === 8) {
            $payload['cep'] = $cep;
        }

        if ($fone !== '') {
            $payload['fone1'] = $fone;
        }

        if ($ie !== '' && strtoupper($ie) !== 'ISENTO') {
            $payload['rg_ie'] = $ie;
        }

        if ($forUpdate) {
            return array_filter(
                $payload,
                static fn (mixed $value, string $key): bool => $key === 'cpf_cnpj'
                    || $key === 'pessoa_tipo'
                    || ($value !== null && $value !== ''),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return $payload;
    }
}
