{{-- Sempre no DOM (x-show): abre após F10 com skipRender, sem remorph de 160KB. --}}
<div
    class="erp-aviso-modal erp-aviso-modal--info erp-fv-imprimir-pos-venda"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-fv-imprimir-pos-venda-title"
    data-erp-fv-imprimir-pos-venda="1"
    x-data
    x-show="$wire.fvImprimirPosVendaOpen"
    x-cloak
    x-bind:aria-hidden="$wire.fvImprimirPosVendaOpen ? 'false' : 'true'"
    x-effect="
        if ($wire.fvImprimirPosVendaOpen) {
            $nextTick(() => {
                requestAnimationFrame(() => {
                    window.erpFvImprimirPosVendaFocusDefault?.();
                });
            });
        }
    "
>
    <div class="erp-aviso-modal__backdrop" wire:click="dispensarImprimirPosVenda"></div>

    <div class="erp-aviso-modal__box">
        <div class="erp-aviso-modal__icon" aria-hidden="true">✓</div>

        <h2 class="erp-aviso-modal__title" id="erp-fv-imprimir-pos-venda-title">
            Venda concluída com sucesso!
        </h2>

        <div class="erp-aviso-modal__body">
            <p class="erp-aviso-modal__text" x-show="!!$wire.fvImprimirPosVendaPopupBloqueado" x-cloak>
                O navegador bloqueou a abertura automática do comprovante.
                Use o botão abaixo para abrir a impressão desta venda — o faturamento já foi concluído.
            </p>
            <p class="erp-aviso-modal__text" x-show="!$wire.fvImprimirPosVendaPopupBloqueado" x-cloak>
                Deseja imprimir o comprovante?
            </p>
        </div>

        <div class="erp-aviso-modal__actions" data-erp-fv-imprimir-actions>
            <button
                type="button"
                id="erp-fv-imprimir-pos-venda-manual"
                class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                data-erp-fv-imprimir-btn="manual"
                x-show="!!$wire.fvImprimirPosVendaPopupBloqueado"
                x-cloak
                x-on:click.prevent="window.erpFvImprimirPosVendaAbrirAgora?.($wire, 'manual')"
                wire:loading.attr="disabled"
                wire:target="abrirComprovantePosVendaManual,dispensarImprimirPosVenda,confirmarImprimirPosVenda,finalizarAposPerguntaImprimir,irAoMonitorAposImprimirPosVenda"
                x-bind:disabled="!!$wire.fvImprimirPosVendaProcessando"
            >Abrir comprovante</button>

            <button
                type="button"
                id="erp-fv-imprimir-pos-venda-monitor"
                class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                data-erp-fv-imprimir-btn="nao"
                x-show="!!$wire.fvImprimirPosVendaPopupBloqueado"
                x-cloak
                wire:click="finalizarAposPerguntaImprimir"
                wire:loading.attr="disabled"
                wire:target="abrirComprovantePosVendaManual,dispensarImprimirPosVenda,confirmarImprimirPosVenda,finalizarAposPerguntaImprimir,irAoMonitorAposImprimirPosVenda"
                x-bind:disabled="!!$wire.fvImprimirPosVendaProcessando"
                x-text="$wire.fvAposConcluirPermanecer ? 'Continuar na venda' : 'Ir ao Monitor'"
            ></button>

            {{-- Open síncrono no clique (URL já em $wire): não espera remorph Livewire da TV. --}}
            <button
                type="button"
                id="erp-fv-imprimir-pos-venda-sim"
                class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                data-erp-fv-imprimir-btn="sim"
                x-show="!$wire.fvImprimirPosVendaPopupBloqueado"
                x-cloak
                x-on:click.prevent="window.erpFvImprimirPosVendaAbrirAgora?.($wire, 'sim')"
                wire:loading.attr="disabled"
                wire:target="confirmarImprimirPosVenda,dispensarImprimirPosVenda,finalizarAposPerguntaImprimir"
                x-bind:disabled="!!$wire.fvImprimirPosVendaProcessando"
            >Sim, imprimir</button>

            <button
                type="button"
                id="erp-fv-imprimir-pos-venda-nao"
                class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                data-erp-fv-imprimir-btn="nao"
                x-show="!$wire.fvImprimirPosVendaPopupBloqueado"
                x-cloak
                wire:click="dispensarImprimirPosVenda"
                wire:loading.attr="disabled"
                wire:target="confirmarImprimirPosVenda,dispensarImprimirPosVenda,finalizarAposPerguntaImprimir"
                x-bind:disabled="!!$wire.fvImprimirPosVendaProcessando"
            >Não</button>
        </div>

        <p
            class="erp-aviso-modal__hint"
            x-text="$wire.fvAposConcluirPermanecer
                ? '← → ou Tab alterna · Enter no botão focado · Esc continua sem imprimir'
                : '← → ou Tab alterna · Enter no botão focado · Esc vai ao Monitor sem imprimir'"
        ></p>
    </div>
</div>
