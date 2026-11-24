<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Filament Schema View injeta o método disabled() no escopo da Blade.
 * O botão compartilhado não pode tratar isso como flag true.
 */
class BtnCadastrarClienteDisabledLeakTest extends TestCase
{
    public function test_metodo_disabled_injetado_nao_desabilita_o_botao(): void
    {
        $html = view('filament.components.erp.shared.btn-cadastrar-cliente', [
            'title' => 'Cadastrar cliente',
            // Simula extractPublicMethods() do Filament ViewComponent.
            'disabled' => static fn (): bool => true,
        ])->render();

        $this->assertDoesNotMatchRegularExpression('/\sdisabled(=|\s|>)/', $html);
        $this->assertStringContainsString('Cadastrar Cliente', $html);
    }

    public function test_flag_bool_true_desabilita(): void
    {
        $html = view('filament.components.erp.shared.btn-cadastrar-cliente', [
            'title' => 'Cadastrar cliente',
            'disabled' => true,
        ])->render();

        $this->assertMatchesRegularExpression('/\sdisabled(=|\s|>)/', $html);
    }

    public function test_flag_bool_false_habilita(): void
    {
        $html = view('filament.components.erp.shared.btn-cadastrar-cliente', [
            'title' => 'Cadastrar cliente',
            'disabled' => false,
        ])->render();

        $this->assertDoesNotMatchRegularExpression('/\sdisabled(=|\s|>)/', $html);
    }
}
