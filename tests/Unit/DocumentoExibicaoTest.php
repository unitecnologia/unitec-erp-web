<?php

namespace Tests\Unit;

use App\Support\Erp\DocumentoExibicao;
use Tests\TestCase;

class DocumentoExibicaoTest extends TestCase
{
    public function test_mascara_cpf_mantendo_inicio_e_fim(): void
    {
        $this->assertSame('045.***.***-01', DocumentoExibicao::mascararCpf('045.333.239-01'));
        $this->assertSame('045.***.***-01', DocumentoExibicao::mascararCpf('04533323901'));
    }

    public function test_retorna_original_quando_cpf_invalido(): void
    {
        $this->assertSame('123', DocumentoExibicao::mascararCpf('123'));
        $this->assertSame('', DocumentoExibicao::mascararCpf(null));
    }
}
