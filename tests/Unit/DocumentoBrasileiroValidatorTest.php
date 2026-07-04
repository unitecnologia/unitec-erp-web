<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\Erp\DocumentoBrasileiroValidator;
use Tests\TestCase;

class DocumentoBrasileiroValidatorTest extends TestCase
{
    public function test_it_validates_cpf_check_digits(): void
    {
        $this->assertTrue(DocumentoBrasileiroValidator::isValidCpf('52998224725'));
        $this->assertFalse(DocumentoBrasileiroValidator::isValidCpf('11111111111'));
        $this->assertFalse(DocumentoBrasileiroValidator::isValidCpf('12345678900'));
    }

    public function test_it_validates_cnpj_check_digits(): void
    {
        $this->assertTrue(DocumentoBrasileiroValidator::isValidCnpj('11222333000181'));
        $this->assertFalse(DocumentoBrasileiroValidator::isValidCnpj('00000000000000'));
    }

    public function test_it_returns_messages_for_invalid_documents(): void
    {
        $this->assertNull(DocumentoBrasileiroValidator::mensagemCpf(''));
        $this->assertSame(
            'CPF inválido. Verifique os números digitados.',
            DocumentoBrasileiroValidator::mensagemCpf('111.111.111-11'),
        );
        $this->assertSame(
            'Informe um CNPJ válido com 14 dígitos.',
            DocumentoBrasileiroValidator::mensagemCnpj('12.345.678/0001'),
        );
        $this->assertNull(
            DocumentoBrasileiroValidator::mensagemCpfCnpj('529.982.247-25', Person::PESSOA_FISICA),
        );
    }
}
