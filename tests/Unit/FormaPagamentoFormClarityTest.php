<?php

namespace Tests\Unit;

use App\Filament\Resources\FormaPagamentoResource\Pages\ListFormasPagamento;
use App\Models\FormaPagamento;
use ReflectionClass;
use Tests\TestCase;

class FormaPagamentoFormClarityTest extends TestCase
{
    public function test_default_tipo_movimento_sugestoes(): void
    {
        $this->assertSame('caixa', FormaPagamento::defaultTipoMovimento('dinheiro'));
        $this->assertSame('caixa', FormaPagamento::defaultTipoMovimento('pix'));
        $this->assertSame('contas_receber', FormaPagamento::defaultTipoMovimento('boleto'));
        $this->assertSame('nenhum', FormaPagamento::defaultTipoMovimento(null));
    }

    public function test_tipo_movimento_hints_cobrem_todas_as_opcoes(): void
    {
        $labels = FormaPagamento::tipoMovimentoLabels();
        $hints = FormaPagamento::tipoMovimentoHints();

        foreach (array_keys($labels) as $key) {
            $this->assertArrayHasKey($key, $hints);
            $this->assertNotSame('', trim($hints[$key]));
        }
    }

    public function test_aviso_nenhum_e_combinacoes_suspeitas(): void
    {
        $page = $this->newFormPage();
        $page->form = [
            'tipo' => 'dinheiro',
            'tipo_movimento' => 'nenhum',
            'conta_destino_id' => null,
            'disponivel_mobile' => true,
        ];

        $avisos = $page->formaPagamentoAvisos();

        $this->assertNotEmpty($avisos);
        $this->assertTrue(collect($avisos)->contains(
            fn (string $a): bool => str_contains($a, 'não movimentará Caixa')
        ));
        $this->assertTrue(collect($avisos)->contains(
            fn (string $a): bool => str_contains($a, 'Dinheiro')
        ));
        $this->assertTrue(collect($avisos)->contains(
            fn (string $a): bool => str_contains($a, 'Mobile')
        ));
    }

    public function test_aviso_conta_destino_quando_movimento_caixa(): void
    {
        $page = $this->newFormPage();
        $page->form = [
            'tipo' => 'pix',
            'tipo_movimento' => 'caixa',
            'conta_destino_id' => 1,
            'disponivel_mobile' => false,
        ];

        $avisos = $page->formaPagamentoAvisos();

        $this->assertTrue(collect($avisos)->contains(
            fn (string $a): bool => str_contains($a, 'Conta de Destino')
        ));
    }

    public function test_sugestao_tipo_somente_na_criacao(): void
    {
        $page = $this->newFormPage();
        $page->formId = null;
        $page->tipoMovimentoManual = false;
        $page->form['tipo_movimento'] = 'nenhum';

        $page->updatedFormTipo('dinheiro');
        $this->assertSame('caixa', $page->form['tipo_movimento']);

        $page->formId = 10;
        $page->form['tipo_movimento'] = 'nenhum';
        $page->updatedFormTipo('pix');
        $this->assertSame('nenhum', $page->form['tipo_movimento']);
    }

    public function test_escolha_manual_nao_e_sobrescrita_na_criacao(): void
    {
        $page = $this->newFormPage();
        $page->formId = null;
        $page->tipoMovimentoManual = false;
        $page->form['tipo_movimento'] = 'nenhum';

        $page->updatedFormTipo('dinheiro');
        $this->assertSame('caixa', $page->form['tipo_movimento']);

        $page->updatedFormTipoMovimento('nenhum');
        $page->form['tipo_movimento'] = 'nenhum';
        $page->updatedFormTipo('boleto');

        $this->assertSame('nenhum', $page->form['tipo_movimento']);
        $this->assertTrue($page->tipoMovimentoManual);
    }

    public function test_ui_campos_por_tipo_dinheiro_oculta_cartao_e_parcelas(): void
    {
        $ui = FormaPagamento::uiCamposPorTipo('dinheiro');

        $this->assertTrue($ui['conta_destino']);
        $this->assertFalse($ui['taxa_cartao']);
        $this->assertFalse($ui['prazo_cartao']);
        $this->assertFalse($ui['max_parcelas']);
        $this->assertFalse($ui['intervalo_parcelas']);
        $this->assertFalse($ui['tabelas_prazo']);
        $this->assertFalse($ui['modo_prazo']);
        $this->assertFalse($ui['usa_tef']);
        $this->assertFalse($ui['usa_super_tef']);
        $this->assertFalse($ui['gerar_qrcode_pdv']);
        $this->assertFalse($ui['bandeiras']);
        $this->assertTrue($ui['nfce']);
    }

    public function test_ui_campos_por_tipo_pix_mostra_qrcode_sem_cartao(): void
    {
        $ui = FormaPagamento::uiCamposPorTipo('pix');

        $this->assertTrue($ui['gerar_qrcode_pdv']);
        $this->assertTrue($ui['conta_destino']);
        $this->assertFalse($ui['taxa_cartao']);
        $this->assertFalse($ui['tabelas_prazo']);
        $this->assertFalse($ui['modo_prazo']);
        $this->assertFalse($ui['usa_tef']);
    }

    public function test_ui_campos_por_tipo_cartao_e_boleto(): void
    {
        $cartao = FormaPagamento::uiCamposPorTipo('cartao_credito');
        $this->assertTrue($cartao['taxa_cartao']);
        $this->assertTrue($cartao['tabelas_prazo']);
        $this->assertTrue($cartao['usa_tef']);
        $this->assertFalse($cartao['modo_prazo']); // cartão/canhoto fora
        $this->assertFalse($cartao['gerar_qrcode_pdv']);

        $boleto = FormaPagamento::uiCamposPorTipo('boleto');
        $this->assertTrue($boleto['tabelas_prazo']);
        $this->assertTrue($boleto['max_parcelas']);
        $this->assertTrue($boleto['modo_prazo']);
        $this->assertFalse($boleto['taxa_cartao']);
        $this->assertFalse($boleto['gerar_qrcode_pdv']);
        $this->assertFalse($boleto['usa_tef']);
    }

    public function test_normalize_modo_prazo(): void
    {
        $this->assertSame('financeiro', FormaPagamento::normalizeModoPrazo('Financeiro'));
        $this->assertSame('tabela', FormaPagamento::normalizeModoPrazo('tabela'));
        $this->assertSame('tabela', FormaPagamento::normalizeModoPrazo(null));
        $this->assertSame('tabela', FormaPagamento::normalizeModoPrazo('xyz'));
    }

    private function newFormPage(): ListFormasPagamento
    {
        $ref = new ReflectionClass(ListFormasPagamento::class);

        /** @var ListFormasPagamento $page */
        $page = $ref->newInstanceWithoutConstructor();
        $page->formId = null;
        $page->tipoMovimentoManual = false;
        $page->form = [
            'tipo' => null,
            'tipo_movimento' => 'nenhum',
            'conta_destino_id' => null,
            'disponivel_mobile' => false,
        ];

        return $page;
    }
}
