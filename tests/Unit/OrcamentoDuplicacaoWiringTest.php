<?php

namespace Tests\Unit;

use Tests\TestCase;

class OrcamentoDuplicacaoWiringTest extends TestCase
{
    public function test_action_bar_tem_botao_duplicar_sem_atalho(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/filament/components/erp/orcamentos/action-bar.blade.php')
        );

        $this->assertStringContainsString('wire:click="duplicarOrcamento"', $src);

        $this->assertSame(1, preg_match(
            '/wire:click="duplicarOrcamento"[\s\S]*?<\/button>/',
            $src,
            $match
        ));
        $this->assertStringContainsString('>Duplicar</span>', $match[0]);
        $this->assertStringNotContainsString('<kbd>', $match[0]);
        $this->assertStringNotContainsString('data-erp-key=', $match[0]);
    }

    public function test_listagem_e_create_usam_fluxo_de_duplicacao(): void
    {
        $list = (string) file_get_contents(
            app_path('Filament/Resources/OrcamentoResource/Pages/ListOrcamentos.php')
        );
        $create = (string) file_get_contents(
            app_path('Filament/Resources/OrcamentoResource/Pages/CreateOrcamento.php')
        );
        $form = (string) file_get_contents(
            app_path('Filament/Resources/OrcamentoResource/Pages/Concerns/ErpOrcamentoFormPage.php')
        );

        $this->assertStringContainsString('function duplicarOrcamento', $list);
        $this->assertStringContainsString('?duplicar=', $list);
        $this->assertStringContainsString("'duplicar' => 'um orçamento para duplicar'", $list);

        $this->assertStringContainsString('aplicarDuplicacaoSeSolicitada', $create);
        $this->assertStringContainsString('function aplicarDuplicacaoSeSolicitada', $form);
        $this->assertStringContainsString("request()->query('duplicar'", $form);
        $this->assertStringContainsString("\$row['id'] = null", $form);
        $this->assertStringContainsString('Não copia cliente, pagamentos', $form);
    }
}
