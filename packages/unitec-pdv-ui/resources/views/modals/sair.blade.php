{{-- Sempre no DOM: abertura/fechamento via JS + skipRender (sem re-render do PDV). --}}
{{-- Classe is-open (não só [hidden]): .erp-pdv-naoencontrado usa display:grid e anula [hidden]. --}}
<div
    id="erp-pdv-sair-overlay"
    class="erp-pdv-naoencontrado erp-pdv-confirm-overlay{{ ($this->activeModal ?? null) === 'sair' ? ' is-open' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-pdv-sair-title"
    aria-live="assertive"
    @if (($this->activeModal ?? null) !== 'sair') hidden @endif
>
    <div class="erp-pdv-naoencontrado__box">
        <div class="erp-pdv-naoencontrado__icon" aria-hidden="true">!</div>
        <h2 id="erp-pdv-sair-title" class="erp-pdv-naoencontrado__title">SAIR DO PDV</h2>
        <p class="erp-pdv-naoencontrado__codigo">Confirma sair do PDV?</p>
        <div class="erp-pdv-naoencontrado__actions">
            <button
                type="button"
                wire:click="confirmSairPdv"
                onclick="typeof hidePdvSairOverlay === 'function' && hidePdvSairOverlay()"
                class="erp-pdv-naoencontrado__btn"
                id="erp-pdv-sair-sim"
            >Sim</button>
            <button
                type="button"
                wire:click="closePdvModal"
                class="erp-pdv-naoencontrado__btn erp-pdv-naoencontrado__btn--secondary"
                id="erp-pdv-sair-nao"
            >Não</button>
        </div>
        <p class="erp-pdv-naoencontrado__hint">Pressione Enter para confirmar ou Esc para cancelar.</p>
    </div>
</div>
