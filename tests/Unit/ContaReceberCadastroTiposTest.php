<?php

namespace Tests\Unit;

use App\Models\ContaReceber;
use App\Support\Erp\Financeiro\ContaReceberCadastroService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContaReceberCadastroTiposTest extends TestCase
{
    public function test_tipos_avulso_tem_seis_opcoes_sem_deposito(): void
    {
        $tipos = ContaReceberCadastroService::tiposAvulso();

        $this->assertSame([
            ContaReceber::FORMA_CARTEIRA,
            ContaReceber::FORMA_CHEQUE,
            ContaReceber::FORMA_CARTAO,
            ContaReceber::FORMA_BOLETO,
            ContaReceber::FORMA_PIX,
            'dinheiro',
        ], array_keys($tipos));
        $this->assertArrayNotHasKey('deposito', $tipos);
    }

    public function test_forma_invalida_e_rejeitada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContaReceberCadastroService())->normalizarForma('deposito');
    }
}
