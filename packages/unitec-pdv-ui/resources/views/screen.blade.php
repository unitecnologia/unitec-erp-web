{{--
    Tela completa do PDV (miolo + TODOS os modais).
    FONTE ÚNICA: ERP e PDV offline incluem este arquivo.
    Contratos de $this-> seguem o PDV do ERP (activeModal, overlays, finalizar…).
--}}
@include('pdvui::partials.main')

@include('pdvui::modals.options')
@include('pdvui::modals.resumo-caixa')
@include('pdvui::modals.sangria')
@include('pdvui::modals.suprimento')
@include('pdvui::modals.caixa')
@include('pdvui::modals.finalizar')
@include('pdvui::fiscal-progress')

@if ($this->pdvConfirmImprimirPosVenda ?? false)
    <div class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-imprimir-pos-venda" role="dialog" aria-labelledby="erp-pdv-imprimir-pos-venda-title">
        <div class="erp-pdv-modal__backdrop" wire:click="confirmImprimirPosVenda(false)"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-pdv-imprimir-pos-venda-title">Impressão</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__confirm-text">
                    NFC-e autorizada. Deseja imprimir o documento?
                </p>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmImprimirPosVenda(true)" class="erp-pdv-modal__btn" id="erp-pdv-imprimir-pos-venda-sim">Sim</button>
                <button type="button" wire:click="confirmImprimirPosVenda(false)" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" id="erp-pdv-imprimir-pos-venda-nao">Não</button>
            </footer>
        </div>
    </div>
@endif

@include('pdvui::modals.excluir-item')
@include('pdvui::modals.cancelar-venda')
@include('pdvui::modals.vendedor')
@include('pdvui::modals.desconto-item')
@include('pdvui::modals.grade')
@include('pdvui::modals.serial')
@include('pdvui::modals.busca-avancada')
@include('pdvui::modals.remover-itens')
@include('pdvui::modals.autorizacao')
@include('pdvui::modals.busca-preco')
@include('pdvui::modals.importar-menu')
@include('pdvui::modals.importar-pedido')
@include('pdvui::modals.importar')
@include('pdvui::modals.receber')
@include('pdvui::modals.reimprimir')
@include('pdvui::modals.consulta-venda')
@include('pdvui::modals.estorno-venda')
@include('pdvui::modals.fiscal-mensagem')
@include('pdvui::modals.tabela-preco')
@include('pdvui::modals.bloqueio')
@include('pdvui::modals.sair')
@include('pdvui::modals.produto-nao-encontrado')

{{-- Cadastros: ERP usa iframe Filament; offline pode injetar corpo local via slots/flags. --}}
@if ($this->overlayProductOpen ?? false)
    @if (! empty($this->productOverlayUrl ?? null))
        @include('pdvui::overlays.iframe', [
            'title' => 'Cadastro de Produtos',
            'iframeUrl' => $this->productOverlayUrl,
            'type' => 'product',
        ])
    @elseif (view()->exists('livewire.pdv.cad-produto-body'))
        @include('livewire.pdv.cad-produto-body')
    @endif
@endif

@if ($this->overlayPersonOpen ?? false)
    @if (! empty($this->personOverlayUrl ?? null))
        @include('pdvui::overlays.iframe', [
            'title' => 'Cadastro de Clientes',
            'iframeUrl' => $this->personOverlayUrl,
            'type' => 'person',
        ])
    @elseif (view()->exists('livewire.pdv.cad-cliente-body'))
        @include('livewire.pdv.cad-cliente-body')
    @endif
@endif
