<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\Erp\Import\FirebirdPersonImportService;
use PHPUnit\Framework\TestCase;

class FirebirdPersonImportServiceTest extends TestCase
{
    public function test_maps_firebird_person_row_with_flags_and_enums(): void
    {
        $mapped = (new FirebirdPersonImportService())->mapFirebirdRow([
            'CODIGO' => 42,
            'RAZAO' => 'Empresa Teste Ltda',
            'FANTASIA' => 'Teste',
            'CNPJ' => '01.704.088/0001-70',
            'TIPO' => 'JURIDICA',
            'CLI' => 'S',
            'FORN' => 'N',
            'FAB' => 'S',
            'TRAN' => 'S',
            'CCF' => 'S',
            'SPC' => 'N',
            'ISENTO' => 'N',
            'IE' => '123456789',
            'REGIME_TRIBUTARIO' => 'SIMPLES',
            'TIPO_RECEBIMENTO' => 'PIX',
            'LIMITE' => '1.500,50',
            'DIA_PGTO' => 10,
            'ECIVIL' => 'CASADO',
            'SEXO' => 'MASCULINO',
            'EMAIL1' => 'a@teste.com',
            'EMAIL2' => 'b@teste.com',
            'ATIVO' => 'S',
        ]);

        $this->assertSame('42', $mapped['codigo']);
        $this->assertSame('EMPRESA TESTE LTDA', $mapped['nome_razao']);
        $this->assertSame(Person::PESSOA_JURIDICA, $mapped['pessoa_tipo']);
        $this->assertTrue($mapped['is_cliente']);
        $this->assertFalse($mapped['is_fornecedor']);
        $this->assertTrue($mapped['is_fabricante']);
        $this->assertTrue($mapped['is_transportadora']);
        $this->assertTrue($mapped['is_ccf_spc']);
        $this->assertSame('contribuinte', $mapped['tipo_contribuinte']);
        $this->assertSame('simples', $mapped['regime_tributario']);
        $this->assertSame('pix', $mapped['tipo_recebimento']);
        $this->assertSame(1500.5, $mapped['limite_credito']);
        $this->assertSame(10, $mapped['dia_pgto']);
        $this->assertSame('casado', $mapped['estado_civil']);
        $this->assertSame('masculino', $mapped['sexo']);
        $this->assertSame('a@teste.com', $mapped['email']);
        $this->assertSame('b@teste.com', $mapped['email2']);
    }

    public function test_maps_isento_contribuinte_from_firebird_flag(): void
    {
        $mapped = (new FirebirdPersonImportService())->mapFirebirdRow([
            'CODIGO' => 1,
            'RAZAO' => 'Cliente PF',
            'CNPJ' => '123.456.789-00',
            'TIPO' => 'FISICA',
            'ISENTO' => 'S',
            'IE' => '',
        ]);

        $this->assertSame('isento', $mapped['tipo_contribuinte']);
        $this->assertSame(Person::PESSOA_FISICA, $mapped['pessoa_tipo']);
    }
}
