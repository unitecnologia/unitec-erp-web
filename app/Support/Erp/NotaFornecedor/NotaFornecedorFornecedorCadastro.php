<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\Person;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\PersonCpfCnpjUnicidade;
use Illuminate\Support\Str;

/**
 * Garante cadastro do emitente do XML como fornecedor (sem interação).
 *
 * Pessoa nova: cadastro completo com os dados do emitente.
 * Pessoa existente: só completa campos vazios; nome, documento, endereço preenchido
 * e status ativo/inativo nunca são alterados.
 */
final class NotaFornecedorFornecedorCadastro
{
    /** Gravados sempre em bloco, para não misturar o endereço cadastrado com o do XML. */
    private const CAMPOS_ENDERECO = [
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cep',
        'cidade_codigo',
        'cidade_nome',
        'uf',
    ];

    /** Telefones já cadastrados: o fone do XML não é gravado se repetir algum deles. */
    private const CAMPOS_TELEFONE = ['fone1', 'fone2', 'celular1', 'celular2', 'whatsapp'];

    /**
     * Só consulta. O cadastro do fornecedor fica para o Finalizar da importação.
     *
     * @param  array<string, mixed>  $emitente
     * @return array{
     *     person: ?Person,
     *     status: 'existente'|'automatico'|'sem_documento',
     *     label: string
     * }
     */
    public function preview(array $emitente): array
    {
        $digits = $this->documentoDigits($emitente);

        if ($digits === null) {
            return $this->semDocumento();
        }

        $person = $this->findByDocumento($digits);

        if ($person && $person->is_fornecedor) {
            return [
                'person' => $person,
                'status' => 'existente',
                'label' => $person->ativo ? 'Fornecedor já cadastrado' : 'Fornecedor cadastrado (inativo)',
            ];
        }

        if ($person) {
            return [
                'person' => null,
                'status' => 'automatico',
                'label' => 'Cadastro existente será fornecedor',
            ];
        }

        return [
            'person' => null,
            'status' => 'automatico',
            'label' => 'Fornecedor será cadastrado ao finalizar',
        ];
    }

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
        $digits = $this->documentoDigits($emitente);

        if ($digits === null) {
            return $this->semDocumento();
        }

        $dados = $this->dadosDoEmitente($emitente, $digits);
        $person = $this->findByDocumento($digits);

        if ($person) {
            $jaEraFornecedor = (bool) $person->is_fornecedor;
            $this->completarExistente($person, $dados);

            return [
                'person' => $person->fresh() ?? $person,
                'status' => $jaEraFornecedor ? 'existente' : 'automatico',
                'label' => $jaEraFornecedor
                    ? 'Fornecedor já cadastrado'
                    : 'Cadastro existente vinculado como fornecedor',
            ];
        }

        $person = Person::query()->create(array_merge(
            $dados,
            [
                'nome_razao' => $dados['nome_razao'] ?? 'FORNECEDOR '.$digits,
                'tipo_contribuinte' => $dados['tipo_contribuinte'] ?? 'nao_contribuinte',
                'codigo' => Person::nextCodigo(),
                'is_fornecedor' => true,
                'is_cliente' => false,
                'ativo' => true,
            ],
        ));

        return [
            'person' => $person,
            'status' => 'automatico',
            'label' => 'Fornecedor cadastrado automaticamente',
        ];
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function completarExistente(Person $person, array $dados): void
    {
        $updates = [];

        foreach (['pessoa_tipo', 'nome_razao', 'apelido_fantasia', 'regime_tributario'] as $campo) {
            if (blank($person->{$campo}) && isset($dados[$campo])) {
                $updates[$campo] = $dados[$campo];
            }
        }

        if (isset($dados['fone1']) && blank($person->fone1) && ! $this->telefoneJaCadastrado($person, $dados['fone1'])) {
            $updates['fone1'] = $dados['fone1'];
        }

        $ieCompletada = false;
        if (isset($dados['rg_ie']) && blank($person->rg_ie)) {
            $updates['rg_ie'] = $dados['rg_ie'];
            $ieCompletada = true;
        }

        if (isset($dados['tipo_contribuinte'])) {
            $tipoAtual = strtolower(trim((string) ($person->tipo_contribuinte ?? '')));
            $pessoaTipo = $person->pessoa_tipo ?: ($dados['pessoa_tipo'] ?? null);

            // Mesma regra do Cadastro de Pessoas: IE informada em PJ define o contribuinte.
            $derivarDaIe = $ieCompletada
                && $tipoAtual === 'nao_contribuinte'
                && $pessoaTipo !== Person::PESSOA_FISICA;

            if ($tipoAtual === '' || $derivarDaIe) {
                $updates['tipo_contribuinte'] = $dados['tipo_contribuinte'];
            }
        }

        if ($this->enderecoVazio($person, $dados)) {
            foreach (self::CAMPOS_ENDERECO as $campo) {
                if (isset($dados[$campo])) {
                    $updates[$campo] = $dados[$campo];
                }
            }
        } elseif (
            isset($dados['cidade_codigo'])
            && ! CepLookupService::isValidIbgeCode($person->cidade_codigo)
            && $this->mesmaCidade($person, $dados)
        ) {
            $updates['cidade_codigo'] = $dados['cidade_codigo'];
        }

        if (! $person->is_fornecedor) {
            $updates['is_fornecedor'] = true;
        }

        if ($updates !== []) {
            $person->forceFill($updates)->save();
        }
    }

    /**
     * Sem endereço cadastrado. UF igual à do XML não conta como endereço (só UF não identifica local).
     *
     * @param  array<string, string>  $dados
     */
    private function enderecoVazio(Person $person, array $dados): bool
    {
        foreach (self::CAMPOS_ENDERECO as $campo) {
            if ($campo === 'uf') {
                continue;
            }

            if (filled($person->{$campo})) {
                return false;
            }
        }

        $ufAtual = mb_strtoupper(trim((string) ($person->uf ?? '')), 'UTF-8');

        return $ufAtual === '' || $ufAtual === ($dados['uf'] ?? null);
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function mesmaCidade(Person $person, array $dados): bool
    {
        $ufAtual = mb_strtoupper(trim((string) ($person->uf ?? '')), 'UTF-8');
        $cidadeAtual = $this->nomeComparavel((string) ($person->cidade_nome ?? ''));

        return $ufAtual !== ''
            && $cidadeAtual !== ''
            && $ufAtual === ($dados['uf'] ?? null)
            && $cidadeAtual === $this->nomeComparavel($dados['cidade_nome'] ?? '');
    }

    private function nomeComparavel(string $nome): string
    {
        $nome = mb_strtoupper(Str::ascii(trim($nome)), 'UTF-8');

        return preg_replace('/\s+/', ' ', $nome) ?? $nome;
    }

    private function telefoneJaCadastrado(Person $person, string $fone): bool
    {
        $digits = preg_replace('/\D/', '', $fone) ?? '';

        foreach (self::CAMPOS_TELEFONE as $campo) {
            if ($digits !== '' && (preg_replace('/\D/', '', (string) ($person->{$campo} ?? '')) ?? '') === $digits) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dados do emitente já no formato do Cadastro de Pessoas (sem chaves vazias).
     *
     * @param  array<string, mixed>  $emitente
     * @return array<string, string>
     */
    private function dadosDoEmitente(array $emitente, string $digits): array
    {
        $formatador = app(CnpjLookupService::class);
        $pessoaFisica = strlen($digits) === 11;
        $ie = mb_strtoupper(trim((string) ($emitente['ie'] ?? '')), 'UTF-8');
        $ieIsento = in_array($ie, ['ISENTO', 'ISENTA'], true);
        $cep = preg_replace('/\D/', '', (string) ($emitente['cep'] ?? '')) ?? '';
        $ibge = preg_replace('/\D/', '', (string) ($emitente['municipio_codigo'] ?? '')) ?? '';
        $uf = mb_strtoupper(trim((string) ($emitente['uf'] ?? '')), 'UTF-8');

        $dados = [
            'pessoa_tipo' => $pessoaFisica ? Person::PESSOA_FISICA : Person::PESSOA_JURIDICA,
            'cpf_cnpj' => $pessoaFisica ? $this->formatCpf($digits) : $formatador->formatCnpj($digits),
            'nome_razao' => $this->texto($emitente['nome'] ?? null),
            'apelido_fantasia' => $this->texto($emitente['fantasia'] ?? null),
            'rg_ie' => $ie !== '' && ! $ieIsento ? $ie : null,
            'fone1' => $this->telefone($formatador, $emitente['telefone'] ?? null),
            'regime_tributario' => $this->regimePorCrt($emitente['crt'] ?? null),
            // Sem a chave "ie" o XML completo não estava disponível: não deduzir contribuinte.
            'tipo_contribuinte' => array_key_exists('ie', $emitente)
                ? $this->tipoContribuinte($pessoaFisica, $ie)
                : null,
            'endereco' => $this->texto($emitente['logradouro'] ?? null),
            'numero' => $this->texto($emitente['numero'] ?? null),
            'complemento' => $this->texto($emitente['complemento'] ?? null),
            'bairro' => $this->texto($emitente['bairro'] ?? null),
            'cep' => strlen($cep) === 8 ? $formatador->formatCep($cep) : null,
            'cidade_codigo' => CepLookupService::isValidIbgeCode($ibge) ? $ibge : null,
            'cidade_nome' => $this->texto($emitente['municipio'] ?? null),
            'uf' => preg_match('/^[A-Z]{2}$/', $uf) === 1 ? $uf : null,
        ];

        return array_filter($dados, static fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * CRT 1/2 = Simples Nacional, 4 = MEI (Simples). CRT 3 (regime normal) não diz se é
     * Presumido ou Real: fica sem regime.
     */
    private function regimePorCrt(mixed $crt): ?string
    {
        return match (trim((string) $crt)) {
            '1', '2', '4' => 'simples',
            default => null,
        };
    }

    private function tipoContribuinte(bool $pessoaFisica, string $ie): string
    {
        if ($pessoaFisica || $ie === '' || $ie === '-') {
            return 'nao_contribuinte';
        }

        if (in_array($ie, ['ISENTO', 'ISENTA'], true)) {
            return 'isento';
        }

        return 'contribuinte';
    }

    private function telefone(CnpjLookupService $formatador, mixed $fone): ?string
    {
        $digits = ltrim(preg_replace('/\D/', '', (string) ($fone ?? '')) ?? '', '0');

        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $formatador->formatApiPhone($digits);
    }

    private function formatCpf(string $digits): string
    {
        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    private function texto(mixed $value): ?string
    {
        $texto = preg_replace('/\s+/', ' ', trim((string) ($value ?? ''))) ?? '';

        return $texto !== '' ? mb_strtoupper($texto, 'UTF-8') : null;
    }

    /**
     * @param  array<string, mixed>  $emitente
     */
    private function documentoDigits(array $emitente): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($emitente['cnpj'] ?? '')) ?? '';

        return in_array(strlen($digits), [11, 14], true) ? $digits : null;
    }

    /**
     * @return array{person: null, status: 'sem_documento', label: string}
     */
    private function semDocumento(): array
    {
        return [
            'person' => null,
            'status' => 'sem_documento',
            'label' => 'Sem CNPJ/CPF no XML',
        ];
    }

    private function findByDocumento(string $digits): ?Person
    {
        return app(PersonCpfCnpjUnicidade::class)->encontrar($digits);
    }
}
