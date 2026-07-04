<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\Terminais\TerminalFormOptions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\TerminalFormMergerHarness;
use Tests\TestCase;

class TerminalFormDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_persists_operacao_and_preview_in_impressora_extra(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $merged = (new TerminalFormMergerHarness)->merge([
            'nome' => 'CAIXA-1',
            'tipo_operacao_padrao' => 'nfce_transmitir',
            'preview_impressao' => true,
            'tipo_fechamento' => '0',
        ]);

        $this->assertSame($empresa->id, $merged['empresa_id']);
        $this->assertSame('CAIXA-1', $merged['nome']);
        $this->assertArrayNotHasKey('tipo_operacao_padrao', $merged);
        $this->assertArrayNotHasKey('preview_impressao', $merged);
        $this->assertSame([
            'tipo_operacao_padrao' => 'NFCE_TRANSMITIR',
            'preview_impressao' => true,
        ], $merged['impressora_extra']);
    }

    public function test_it_clears_meia_folha_when_fechamento_is_not_a4_padrao(): void
    {
        session(['erp_empresa_id' => 1]);

        $merged = (new TerminalFormMergerHarness)->merge([
            'nome' => 'CAIXA-1',
            'tipo_fechamento' => '2',
            'meia_folha' => true,
        ]);

        $this->assertFalse($merged['meia_folha']);
    }

    public function test_default_terminal_form_data_uses_pedido_a4(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $defaults = (new TerminalFormMergerHarness)->defaultFormData();

        $this->assertSame('0', $defaults['tipo_impressora']);
        $this->assertSame($empresa->id, $defaults['empresa_id']);
        $this->assertTrue($defaults['exibe_f3']);
        $this->assertTrue($defaults['pdv']);
    }

    public function test_it_normalizes_legacy_tipo_operacao_padrao_values(): void
    {
        $this->assertSame('nfce_transmitir', TerminalFormOptions::normalizeTipoOperacaoPadrao('NFCE'));
        $this->assertSame('pedido_nao_fiscal', TerminalFormOptions::normalizeTipoOperacaoPadrao('ORCAMENTO'));
        $this->assertSame('pedido_nao_fiscal', TerminalFormOptions::normalizeTipoOperacaoPadrao('ECF_FISCAL_FINALIZAR'));
    }
}
