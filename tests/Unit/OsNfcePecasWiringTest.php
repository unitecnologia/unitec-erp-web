<?php

namespace Tests\Unit;

use App\Support\Erp\Nfce\NfceOrdemServicoService;
use Tests\TestCase;

/**
 * Monitor de OS — F11 NFC-e Peças: wiring sem DB e sem SEFAZ.
 */
class OsNfcePecasWiringTest extends TestCase
{
    public function test_action_bar_liga_f11_ao_emitir_nfce_pecas(): void
    {
        $blade = file_get_contents(resource_path('views/filament/components/erp/ordens-servico/action-bar.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('wire:click="emitirNfcePecasDaOs"', $blade);
        $this->assertStringContainsString('data-erp-key="F11"', $blade);
        $this->assertStringContainsString('data-erp-enable-on-row="erp-os-row--nfce-pendente"', $blade);
        $this->assertStringContainsString('NFC-e Peças', $blade);
        $this->assertStringContainsString('@disabled(! $this->osSelecionadaPodeEmitirNfcePecas())', $blade);
    }

    public function test_listagem_mapeia_f11_e_usa_trait_de_nfce_pecas(): void
    {
        $page = file_get_contents(app_path('Filament/Resources/OrdemServicoResource/Pages/ListOrdensServico.php'));

        $this->assertIsString($page);
        $this->assertStringContainsString('ManagesOrdemServicoNfcePecas', $page);
        $this->assertStringContainsString("'F11' => ['method' => 'emitirNfcePecasDaOs']", $page);
        $this->assertStringContainsString('function osSelecionadaPodeEmitirNfcePecas(): bool', $page);
        $this->assertStringContainsString("selectedOsMeta['nfce_pendente']", $page);
    }

    public function test_trait_abre_modal_e_confirma_via_nfce_ordem_servico_service(): void
    {
        $trait = file_get_contents(app_path(
            'Filament/Resources/OrdemServicoResource/Pages/Concerns/ManagesOrdemServicoNfcePecas.php'
        ));

        $this->assertIsString($trait);
        $this->assertStringContainsString('function emitirNfcePecasDaOs(): void', $trait);
        $this->assertStringContainsString('function confirmarOsNfceEmitirModal(): void', $trait);
        $this->assertStringContainsString('NfceOrdemServicoService', $trait);
        $this->assertStringContainsString('->emitir($ordem, $consumidor, $observacoes)', $trait);
        $this->assertStringContainsString('->conferirPecas($ordem)', $trait);
        $this->assertStringContainsString('osNfceEmitirConferencia', $trait);
        $this->assertStringContainsString("ErpAccess::authorizeOrNotify(Auth::user(), 'nfce.access')", $trait);
        $this->assertStringContainsString('motivoBloqueioEmissao', $trait);
        $this->assertStringContainsString('nfceOsRetransmissivel', $trait);
        $this->assertStringContainsString("dispatch('erp-sefaz-resultado'", $trait);
        $this->assertStringContainsString('resetTable()', $trait);
        $this->assertStringContainsString('NFC-e não aceita CNPJ', $trait);
    }

    public function test_modal_exibe_grade_readonly_e_totais_antes_de_transmitir(): void
    {
        $blade = file_get_contents(resource_path(
            'views/filament/components/erp/ordens-servico/os-nfce-emitir-modal.blade.php'
        ));

        $this->assertIsString($blade);
        $this->assertStringContainsString('osNfceEmitirConferencia', $blade);
        $this->assertStringContainsString('Peças (somente leitura)', $blade);
        $this->assertStringContainsString('erp-os-nfce-emitir-modal__grid', $blade);
        $this->assertStringContainsString('Descrição', $blade);
        $this->assertStringContainsString('Unitário', $blade);
        $this->assertStringContainsString('Desconto', $blade);
        $this->assertStringContainsString('Total NFC-e', $blade);
        $this->assertStringContainsString('Descontos das peças', $blade);
        $this->assertStringContainsString('wire:model="osNfceEmitirCpf"', $blade);
        $this->assertStringContainsString('wire:model="osNfceEmitirNome"', $blade);
        $this->assertStringContainsString('wire:model="osNfceEmitirObservacoes"', $blade);
        $this->assertStringContainsString('confirmarOsNfceEmitirModal', $blade);
        $this->assertStringContainsString('cancelarOsNfceEmitirModal', $blade);
        $this->assertStringNotContainsString('wire:model="osNfceEmitirConferencia', $blade);
    }

    public function test_servico_e_somente_fiscal_e_filtra_pecas(): void
    {
        $src = file_get_contents((new \ReflectionClass(NfceOrdemServicoService::class))->getFileName());

        $this->assertIsString($src);
        $this->assertStringContainsString('Não toca estoque, caixa, contas a receber nem situação da OS', $src);
        $this->assertStringContainsString("ORIGEM_OS_NFCE_PECAS", $src);
        $this->assertStringContainsString("!== 'P'", $src);
        $this->assertStringContainsString('OsFiscalLock::executar', $src);
        $this->assertStringContainsString('STATUS_RETRANSMITIVEIS', $src);
        $this->assertStringContainsString('possuiNfePecasEmitida', $src);
        // Fecha vNF quando round(preço×qtd,2) diverge do total da OS (decimal 12,2).
        $this->assertStringContainsString("max(0.0, -\$diff)", $src);
        $this->assertStringContainsString("'acrescimo' => \$acrescimo", $src);
        $this->assertStringContainsString('function conferirPecas(OrdemServico $ordem): array', $src);
        $this->assertStringContainsString('function montarEspelhoPecas(OrdemServico $ordem): array', $src);
    }

    public function test_row_formatter_habilita_nfce_pendente_sem_cnpj_e_sem_documento(): void
    {
        $src = file_get_contents(app_path('Support/Erp/OsListRowFormatter.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('erp-os-row--nfce-pendente', $src);
        $this->assertStringContainsString('possuiNfceAutorizada', $src);
        $this->assertStringContainsString('osClienteTemCnpj', $src);
        $this->assertStringContainsString('STATUS_DENEGADA', $src);
    }
}
