@if (filled($this->pdvFiscalOverlayTipo))
    @php
        $sucesso = $this->pdvFiscalOverlayTipo === 'sucesso';
        $variantClass = $sucesso ? 'erp-pdv-naoencontrado--sucesso' : 'erp-pdv-naoencontrado--erro';
    @endphp
    <div
        class="erp-pdv-naoencontrado erp-pdv-fiscal-overlay {{ $variantClass }}"
        role="alertdialog"
        aria-labelledby="erp-pdv-fiscal-overlay-title"
        aria-live="assertive"
    >
        <div class="erp-pdv-naoencontrado__box">
            <div class="erp-pdv-naoencontrado__icon" aria-hidden="true">{{ $sucesso ? '✓' : '!' }}</div>
            <h2 id="erp-pdv-fiscal-overlay-title" class="erp-pdv-naoencontrado__title">
                {{ $this->pdvFiscalOverlayTitulo }}
            </h2>
            @if (filled($this->pdvFiscalOverlayDetalhe))
                <p class="erp-pdv-naoencontrado__codigo">{!! nl2br(e($this->pdvFiscalOverlayDetalhe)) !!}</p>
            @endif
            @if (filled($this->pdvFiscalOverlayMensagem))
                <div class="erp-pdv-fiscal-overlay__text">
                    {!! nl2br(e($this->pdvFiscalOverlayMensagem)) !!}
                </div>
            @endif
            @if ($sucesso)
                <p class="erp-pdv-fiscal-overlay__pergunta">Deseja imprimir o protocolo de cancelamento?</p>
                <div class="erp-pdv-naoencontrado__actions">
                    <button
                        type="button"
                        wire:click="imprimirProtocoloCancelamentoNfce"
                        class="erp-pdv-naoencontrado__btn"
                        id="erp-pdv-fiscal-overlay-imprimir"
                    >Imprimir</button>
                    <button
                        type="button"
                        wire:click="sairPdvFiscalOverlay"
                        class="erp-pdv-naoencontrado__btn erp-pdv-naoencontrado__btn--secondary"
                        id="erp-pdv-fiscal-overlay-sair"
                    >Sair</button>
                </div>
                <p class="erp-pdv-naoencontrado__hint">Clique em Sair para voltar ao PDV.</p>
            @else
                <p class="erp-pdv-fiscal-overlay__origem">Esta é uma mensagem da SEFAZ - SC.</p>
                <button
                    type="button"
                    wire:click="sairPdvFiscalOverlay"
                    class="erp-pdv-naoencontrado__btn"
                    id="erp-pdv-fiscal-overlay-entendido"
                >Entendido</button>
                <p class="erp-pdv-naoencontrado__hint">Clique em Entendido para voltar ao PDV.</p>
            @endif
        </div>
    </div>
@endif
