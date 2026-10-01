<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Rules\PersonDocumentoUnico;
use App\Support\Erp\PersonCpfCnpjUnicidade;
use App\Support\Erp\PersonDocumentoDuplicadoException;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class PersonCpfCnpjUnicidadeTest extends TestCase
{
    use MigratesSqliteMemory;

    private const CPF = '52998224725';

    private const CPF_MASK = '529.982.247-25';

    private const CNPJ = '11222333000181';

    private const CNPJ_MASK = '11.222.333/0001-81';

    public function test_permite_cpf_novo(): void
    {
        $this->assertNull(app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF));
        app(PersonCpfCnpjUnicidade::class)->assertDisponivel(self::CPF);
        $this->assertTrue(true);
    }

    public function test_bloqueia_mesmo_cpf_com_mascara_diferente(): void
    {
        $this->criarPessoa(['cpf_cnpj' => self::CPF_MASK, 'pessoa_tipo' => Person::PESSOA_FISICA]);

        $msg = app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('Já existe um cadastro com este CPF', $msg);
        $this->assertStringContainsString('código', $msg);

        $this->expectException(PersonDocumentoDuplicadoException::class);
        app(PersonCpfCnpjUnicidade::class)->assertDisponivel(self::CPF);
    }

    public function test_permite_cnpj_novo(): void
    {
        $this->assertNull(app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CNPJ));
    }

    public function test_bloqueia_mesmo_cnpj_com_mascara_diferente(): void
    {
        $this->criarPessoa(['cpf_cnpj' => self::CNPJ, 'pessoa_tipo' => Person::PESSOA_JURIDICA]);

        $msg = app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CNPJ_MASK);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('Já existe um cadastro com este CNPJ', $msg);
    }

    public function test_edicao_sem_mudar_documento_permite(): void
    {
        $person = $this->criarPessoa(['cpf_cnpj' => self::CPF_MASK]);

        $this->assertNull(
            app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF, $person->id),
        );
    }

    public function test_edicao_trocando_para_documento_de_outra_bloqueia(): void
    {
        $this->criarPessoa(['cpf_cnpj' => self::CPF, 'nome_razao' => 'PRIMEIRA']);
        $segunda = $this->criarPessoa([
            'cpf_cnpj' => self::CNPJ,
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'SEGUNDA',
        ]);

        $msg = app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF_MASK, $segunda->id);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('CPF', $msg);
    }

    public function test_documento_de_pessoa_inativa_bloqueia(): void
    {
        $this->criarPessoa(['cpf_cnpj' => self::CPF, 'ativo' => false]);

        $msg = app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF_MASK);
        $this->assertNotNull($msg);
    }

    public function test_vazio_e_null_permitem_multiplos(): void
    {
        $this->criarPessoa(['cpf_cnpj' => null]);
        $this->criarPessoa(['cpf_cnpj' => '']);
        $this->criarPessoa(['cpf_cnpj' => null]);

        $this->assertNull(app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(null));
        $this->assertNull(app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(''));
        $this->assertSame(3, Person::query()->where(function ($q) {
            $q->whereNull('cpf_cnpj')->orWhere('cpf_cnpj', '');
        })->count());
    }

    public function test_rule_integra_com_validacao_de_dv(): void
    {
        $this->criarPessoa(['cpf_cnpj' => self::CPF]);

        $failDv = Validator::make(
            ['cpf_cnpj' => '111.111.111-11'],
            ['cpf_cnpj' => [new DocumentoBrasileiroValido(Person::PESSOA_FISICA), new PersonDocumentoUnico]],
        );
        $this->assertTrue($failDv->fails());
        $this->assertStringContainsString('CPF inválido', $failDv->errors()->first('cpf_cnpj'));

        $failDup = Validator::make(
            ['cpf_cnpj' => self::CPF_MASK],
            ['cpf_cnpj' => [new DocumentoBrasileiroValido(Person::PESSOA_FISICA), new PersonDocumentoUnico]],
        );
        $this->assertTrue($failDup->fails());
        $this->assertStringContainsString('Já existe um cadastro com este CPF', $failDup->errors()->first('cpf_cnpj'));

        $ok = Validator::make(
            ['cpf_cnpj' => self::CNPJ_MASK],
            ['cpf_cnpj' => [new DocumentoBrasileiroValido(Person::PESSOA_JURIDICA), new PersonDocumentoUnico]],
        );
        $this->assertFalse($ok->fails());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function criarPessoa(array $extra = []): Person
    {
        return Person::query()->create(array_merge([
            'codigo' => Person::nextCodigo(),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'PESSOA TESTE '.uniqid(),
            'is_cliente' => true,
            'ativo' => true,
        ], $extra));
    }
}
