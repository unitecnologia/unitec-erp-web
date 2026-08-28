<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;
use Tests\TestCase;

class NfceConsumidorIdentificadoTest extends TestCase
{
    public function test_resolve_person_pela_venda_quando_cliente_cadastrado(): void
    {
        $person = new Person([
            'codigo' => 'CLI001',
            'nome_razao' => 'MARIA SILVA',
            'endereco' => 'RUA DAS PALMEIRAS',
            'numero' => '120',
            'bairro' => 'CENTRO',
            'cidade_nome' => 'FLORIANOPOLIS',
            'uf' => 'SC',
        ]);
        $person->id = 10;

        $venda = new PdvVenda([
            'person_id' => 10,
            'cpf_nota' => '045.333.239-01',
        ]);
        $venda->setRelation('person', $person);

        $this->assertSame($person, NfceConsumidorIdentificado::resolvePerson($venda));
        $this->assertSame('MARIA SILVA', NfceConsumidorIdentificado::nome($person));
        $this->assertStringContainsString('RUA DAS PALMEIRAS', (string) NfceConsumidorIdentificado::endereco($person));
        $this->assertSame('045.***.***-01', NfceConsumidorIdentificado::cpfMascarado($venda));
        $this->assertSame('045.333.239-01', NfceConsumidorIdentificado::cpfFormatado($venda));
    }

    public function test_nao_considera_consumidor_final_como_identificado(): void
    {
        $cf = new Person([
            'codigo' => 'CF',
            'nome_razao' => 'CONSUMIDOR FINAL',
            'endereco' => 'RUA X',
        ]);

        $this->assertFalse(NfceConsumidorIdentificado::ehClienteIdentificado($cf));
        $this->assertNull(NfceConsumidorIdentificado::nome($cf));
        $this->assertNull(NfceConsumidorIdentificado::endereco($cf));

        $legado = new Person([
            'codigo' => '000001',
            'nome_razao' => 'CONSUMIDOR FINAL',
        ]);
        $this->assertFalse(NfceConsumidorIdentificado::ehClienteIdentificado($legado));
    }

    public function test_cpf_digits_mascarado_e_formatado(): void
    {
        $this->assertSame('04533323901', NfceConsumidorIdentificado::cpfDigits('045.333.239-01'));
        $this->assertSame('', NfceConsumidorIdentificado::cpfDigits('123'));

        $venda = new PdvVenda(['cpf_nota' => '04533323901']);
        $this->assertSame('045.***.***-01', NfceConsumidorIdentificado::cpfMascarado($venda));
        $this->assertSame('045.333.239-01', NfceConsumidorIdentificado::cpfFormatado($venda));
        $this->assertNull(NfceConsumidorIdentificado::cpfMascarado(new PdvVenda(['cpf_nota' => null])));
        $this->assertNull(NfceConsumidorIdentificado::cpfFormatado(new PdvVenda(['cpf_nota' => null])));
    }
}
