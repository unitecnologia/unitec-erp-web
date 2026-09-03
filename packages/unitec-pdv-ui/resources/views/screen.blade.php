{{--
    Tela completa do PDV (miolo + modais sob demanda).
    FONTE ÚNICA: ERP e PDV offline incluem este arquivo.
    Contratos de $this-> seguem o PDV do ERP (activeModal, overlays, finalizar…).
    Hot path do bip: não renderizar modais fechados (includeWhen).
--}}
@include('pdvui::partials.main')

@php
    $modal = $this->activeModal ?? null;
@endphp

@includeWhen($modal === 'options', 'pdvui::modals.options')
@includeWhen($modal === 'resumo', 'pdvui::modals.resumo-caixa')
@includeWhen($modal === 'sangria', 'pdvui::modals.sangria')
@includeWhen($modal === 'suprimento', 'pdvui::modals.suprimento')
@includeWhen(in_array($modal, ['abrir_caixa', 'fechar_caixa'], true), 'pdvui::modals.caixa')
@includeWhen($modal === 'finalizar', 'pdvui::modals.finalizar')
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

@includeWhen($modal === 'excluir_item', 'pdvui::modals.excluir-item')
@includeWhen($this->pdvConfirmCancelarVenda ?? false, 'pdvui::modals.cancelar-venda')
@includeWhen($modal === 'vendedor', 'pdvui::modals.vendedor')
@includeWhen($modal === 'desconto_item', 'pdvui::modals.desconto-item')
@includeWhen($modal === 'grade', 'pdvui::modals.grade')
@includeWhen($modal === 'serial', 'pdvui::modals.serial')
@includeWhen($modal === 'busca_avancada', 'pdvui::modals.busca-avancada')
@includeWhen($modal === 'remover_itens', 'pdvui::modals.remover-itens')
@includeWhen($modal === 'autorizacao', 'pdvui::modals.autorizacao')
@includeWhen($modal === 'busca_preco', 'pdvui::modals.busca-preco')
@includeWhen($modal === 'importar_menu', 'pdvui::modals.importar-menu')
@includeWhen($modal === 'importar_pedido', 'pdvui::modals.importar-pedido')
@includeWhen($modal === 'importar', 'pdvui::modals.importar')
@includeWhen($modal === 'receber', 'pdvui::modals.receber')
@includeWhen($modal === 'reimprimir', 'pdvui::modals.reimprimir')
@includeWhen($modal === 'consulta_venda', 'pdvui::modals.consulta-venda')
@includeWhen($modal === 'estorno_venda', 'pdvui::modals.estorno-venda')
@includeWhen(filled($this->pdvFiscalOverlayTipo ?? null), 'pdvui::modals.fiscal-mensagem')
@includeWhen($modal === 'tabela_preco', 'pdvui::modals.tabela-preco')
@includeWhen($modal === 'bloqueio' || ($this->pdvBloqueado ?? false), 'pdvui::modals.bloqueio')
@includeWhen($modal === 'sair', 'pdvui::modals.sair')
@includeWhen(($this->produtoNaoEncontradoCodigo ?? null) !== null, 'pdvui::modals.produto-nao-encontrado')

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
