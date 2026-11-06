<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\FormaPagamentoAtalho;
use Tests\TestCase;

class FormaPagamentoAtalhoTest extends TestCase
{
    public function test_normaliza_letras_e_teclas_f(): void
    {
        $this->assertSame('A', FormaPagamentoAtalho::normalizar(' a '));
        $this->assertSame('F4', FormaPagamentoAtalho::normalizar('f4'));
        $this->assertSame('', FormaPagamentoAtalho::normalizar(null));
        $this->assertSame('', FormaPagamentoAtalho::normalizar(''));
    }

    public function test_opcoes_detalhadas_incluem_letras_e_sem_atalho(): void
    {
        $options = FormaPagamentoAtalho::opcoesSelectDetalhadas();
        $byValue = collect($options)->keyBy('value');

        $this->assertTrue($byValue->has(''));
        $this->assertSame('Sem atalho', $byValue->get('')['label']);
        $this->assertFalse($byValue->get('')['disabled']);
        $this->assertTrue($byValue->has('A'));
        $this->assertTrue($byValue->has('Z'));
        $this->assertFalse($byValue->has('F3'));
        $this->assertFalse($byValue->has('F4'));
        $this->assertFalse($byValue->has('F8'));
        $this->assertFalse($byValue->has('F9'));
    }

    public function test_concluir_aparece_desabilitado_nas_opcoes_do_meio(): void
    {
        $concluir = EmpresaParametros::atalhoConcluirVenda();
        $byValue = collect(FormaPagamentoAtalho::opcoesSelectDetalhadas())->keyBy('value');

        $this->assertTrue($byValue->has($concluir));
        $this->assertTrue($byValue->get($concluir)['disabled']);
        $this->assertStringContainsString('Concluir Venda', $byValue->get($concluir)['label']);
        $this->assertStringContainsString('em uso', $byValue->get($concluir)['label']);
    }

    public function test_proprio_atalho_permanece_habilitado_mesmo_se_mapa_tivesse_ocupacao(): void
    {
        // Simula edição: valor atual = A; ignoreId remove o próprio registro do mapa.
        // Sem meios no banco, A fica livre; com meios, A do próprio id é ignorado.
        $byValue = collect(FormaPagamentoAtalho::opcoesSelectDetalhadas('A', 999999))->keyBy('value');

        $this->assertTrue($byValue->has('A'));
        $this->assertFalse($byValue->get('A')['disabled']);
        $this->assertSame('A', $byValue->get('A')['label']);
    }

    public function test_opcoes_concluir_desabilitam_tecla_de_meio(): void
    {
        $options = FormaPagamentoAtalho::opcoesSelectConcluirDetalhadas('F10');
        $this->assertCount(3, $options);

        foreach ($options as $opt) {
            $this->assertArrayHasKey('disabled', $opt);
            $this->assertContains($opt['value'], ['F7', 'F9', 'F10']);
        }
    }

    public function test_valor_legado_invalido_aparece_selecionavel(): void
    {
        $byValue = collect(FormaPagamentoAtalho::opcoesSelectDetalhadas('F4'))->keyBy('value');

        $this->assertTrue($byValue->has('F4'));
        $this->assertFalse($byValue->get('F4')['disabled']);
        $this->assertStringContainsString('inválido', $byValue->get('F4')['label']);
    }

    public function test_is_permitido_rejeita_reservadas(): void
    {
        $this->assertFalse(FormaPagamentoAtalho::isPermitido('F3'));
        $this->assertFalse(FormaPagamentoAtalho::isPermitido('F4'));
        $this->assertFalse(FormaPagamentoAtalho::isPermitido('F7'));
        $this->assertFalse(FormaPagamentoAtalho::isPermitido('F8'));
        $this->assertFalse(FormaPagamentoAtalho::isPermitido('F9'));
        $this->assertTrue(FormaPagamentoAtalho::isPermitido('A'));
        $this->assertTrue(FormaPagamentoAtalho::isPermitido(''));
    }
}
