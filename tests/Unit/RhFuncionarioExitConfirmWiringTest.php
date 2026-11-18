<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Garante que o modal de funcionário não fecha no clique fora e que Sair/X/Esc
 * passam pela confirmação de alterações não salvas.
 */
class RhFuncionarioExitConfirmWiringTest extends TestCase
{
    public function test_backdrop_nao_fecha_e_sair_usa_request_close(): void
    {
        $blade = (string) file_get_contents(resource_path('views/filament/components/erp/rh-funcionarios/form-modal.blade.php'));

        $this->assertStringContainsString('wire:keydown.escape.window="requestCloseRhFuncionarioModal"', $blade);
        $this->assertStringContainsString('wire:keydown.f5.window.prevent="saveRhFuncionario"', $blade);
        $this->assertStringContainsString('wire:click="requestCloseRhFuncionarioModal"', $blade);
        $this->assertStringContainsString('wire:click="saveRhFuncionario"', $blade);
        $this->assertStringNotContainsString('wire:click="closeRhFuncionarioModal"', $blade);
        $this->assertStringContainsString('erp-lookup-modal__backdrop', $blade);
        $this->assertStringNotContainsString('erp-lookup-modal__backdrop" wire:click', $blade);
        $this->assertStringContainsString('exit-confirm-modal', $blade);
        $this->assertStringContainsString('erp-rh-func-focus-form', $blade);
        $this->assertStringContainsString('id="erp-rh-func-nome"', $blade);
    }

    public function test_confirmacao_usa_aviso_modal_com_acoes_corretas(): void
    {
        $blade = (string) file_get_contents(resource_path('views/filament/components/erp/rh-funcionarios/exit-confirm-modal.blade.php'));

        $this->assertStringContainsString('rhFuncionarioExitConfirmOpen', $blade);
        $this->assertStringContainsString('Existem alterações não salvas. Deseja realmente sair e descartar os dados?', $blade);
        $this->assertStringContainsString('confirmRhFuncionarioExitWithoutSaving', $blade);
        $this->assertStringContainsString('dismissRhFuncionarioExitConfirm', $blade);
        $this->assertStringContainsString('Sair sem salvar', $blade);
        $this->assertStringContainsString('Continuar editando', $blade);
    }

    public function test_trait_tem_fluxo_de_confirmacao_e_baseline(): void
    {
        $src = (string) file_get_contents(app_path('Filament/Resources/RhFuncionarioResource/Pages/Concerns/ManagesRhFuncionarioFormModal.php'));

        $this->assertStringContainsString('function requestCloseRhFuncionarioModal', $src);
        $this->assertStringContainsString('function confirmRhFuncionarioExitWithoutSaving', $src);
        $this->assertStringContainsString('function dismissRhFuncionarioExitConfirm', $src);
        $this->assertStringContainsString('captureRhFuncionarioFormBaseline', $src);
        $this->assertStringContainsString('RhFuncionarioFormSnapshot', $src);
        $this->assertStringContainsString('rhFuncionarioFormHasUnsavedChanges', $src);
        // Gravar limpa estado sem abrir confirmação.
        $this->assertStringContainsString('rhFuncionarioExitConfirmOpen = false', $src);
        $this->assertStringContainsString('rhFuncionarioFormBaselineHash = null', $src);
    }

    public function test_abas_do_cadastro_permanecem(): void
    {
        $blade = (string) file_get_contents(resource_path('views/filament/components/erp/rh-funcionarios/form-modal.blade.php'));

        foreach (['Identificação', 'Endereço', 'Contato', 'Trabalhistas', 'Operador'] as $aba) {
            $this->assertStringContainsString($aba, $blade);
        }
    }
}
