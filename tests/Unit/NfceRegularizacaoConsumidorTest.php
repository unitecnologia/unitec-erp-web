<?php

namespace Tests\Unit;

use App\Support\Erp\Nfce\NfceRegularizacaoService;
use DomainException;
use PHPUnit\Framework\TestCase;

class NfceRegularizacaoConsumidorTest extends TestCase
{
    public function test_nome_e_cpf_digitados_sem_cadastro(): void
    {
        $consumidor = NfceRegularizacaoService::normalizarConsumidor([
            'person_id' => null,
            'nome' => '  joão   da silva ',
            'cpf' => '529.982.247-25',
        ]);

        $this->assertSame(['person_id' => null, 'nome' => 'JOÃO DA SILVA', 'cpf' => '52998224725'], $consumidor);
    }

    public function test_tudo_vazio_e_consumidor_nao_identificado(): void
    {
        $this->assertSame(
            ['person_id' => null, 'nome' => '', 'cpf' => ''],
            NfceRegularizacaoService::normalizarConsumidor(['nome' => '', 'cpf' => '']),
        );
    }

    public function test_recusa_cnpj(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('NFC-e não aceita CNPJ');

        NfceRegularizacaoService::normalizarConsumidor(['nome' => 'EMPRESA', 'cpf' => '11.222.333/0001-81']);
    }

    public function test_recusa_cpf_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('CPF inválido');

        NfceRegularizacaoService::normalizarConsumidor(['nome' => 'FULANO', 'cpf' => '529.982.247-26']);
    }

    public function test_nome_sem_cpf_nao_identifica(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Informe o CPF');

        NfceRegularizacaoService::normalizarConsumidor(['nome' => 'FULANO', 'cpf' => '']);
    }
}
