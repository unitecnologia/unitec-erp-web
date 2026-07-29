<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Util\ChaveAcesso;

final class ChaveAcessoTest extends TestCase
{
    public function test_gera_chave_com_digito_verificador(): void
    {
        $chave = ChaveAcesso::gerar(
            uf: 'SC',
            emissao: new \DateTimeImmutable('2026-07-06 14:30:00'),
            cnpj: '22469772000100',
            modelo: '65',
            serie: 1,
            numero: 1,
            tpEmis: 1,
            cNf: 12345678,
        );

        $this->assertSame(44, strlen($chave));
        $this->assertSame('42', substr($chave, 0, 2));
        $this->assertSame('65', substr($chave, 20, 2));

        $base43 = substr($chave, 0, 43);
        $dv = (int) substr($chave, 43, 1);
        $this->assertSame($dv, ChaveAcesso::digitoVerificador($base43));
    }
}
