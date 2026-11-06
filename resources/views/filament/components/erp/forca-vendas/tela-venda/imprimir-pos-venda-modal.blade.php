{{-- Mesmo padrão visual de sair-confirm / aviso-modal (UNI). Sem confirm() nativo. --}}
@if ($this->fvImprimirPosVendaOpen)
    @php
        $permanecerTv = (bool) $this->fvAposConcluirPermanecer;
        $popupBloqueado = (bool) $this->fvImprimirPosVendaPopupBloqueado;
        $hintEsc = $permanecerTv
            ? '← → ou Tab alterna · Enter no botão focado · Esc continua sem imprimir'
            : '← → ou Tab alterna · Enter no botão focado · Esc vai ao Monitor sem imprimir';
        $labelContinuar = $permanecerTv ? 'Continuar na venda' : 'Ir ao Monitor';
    @endphp
    <div
        class="erp-aviso-modal erp-aviso-modal--info erp-fv-imprimir-pos-venda"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-imprimir-pos-venda-title"
        data-erp-fv-imprimir-pos-venda="1"
        x-data
        x-init="
            $nextTick(() => {
                requestAnimationFrame(() => {
                    window.erpFvImprimirPosVendaFocusDefault?.();
                });
            });
        "
    >
        <div class="erp-aviso-modal__backdrop" wire:click="dispensarImprimirPosVenda"></div>

        <div class="erp-aviso-modal__box">
            <div class="erp-aviso-modal__icon" aria-hidden="true">✓</div>

            <h2 class="erp-aviso-modal__title" id="erp-fv-imprimir-pos-venda-title">
                Venda concluída com sucesso!
            </h2>

            <div class="erp-aviso-modal__body">
                @if ($popupBloqueado)
                    <p class="erp-aviso-modal__text">
                        O navegador bloqueou a abertura automática do comprovante.
                        Use o botão abaixo para abrir a impressão desta venda — o faturamento já foi concluído.
                    </p>
                @else
                    <p class="erp-aviso-modal__text">
                        Deseja imprimir o comprovante?
                    </p>
                @endif
            </div>

            <div class="erp-aviso-modal__actions" data-erp-fv-imprimir-actions>
                @if ($popupBloqueado)
                    <button
                        type="button"
                        id="erp-fv-imprimir-pos-venda-manual"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                        data-erp-fv-imprimir-btn="manual"
                        wire:click="abrirComprovantePosVendaManual"
                        wire:loading.attr="disabled"
                        wire:target="abrirComprovantePosVendaManual,dispensarImprimirPosVenda,confirmarImprimirPosVenda,finalizarAposPerguntaImprimir,irAoMonitorAposImprimirPosVenda"
                    >Abrir comprovante</button>

                    <button
                        type="button"
                        id="erp-fv-imprimir-pos-venda-monitor"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                        data-erp-fv-imprimir-btn="nao"
                        wire:click="finalizarAposPerguntaImprimir"
                        wire:loading.attr="disabled"
                        wire:target="abrirComprovantePosVendaManual,dispensarImprimirPosVenda,confirmarImprimirPosVenda,finalizarAposPerguntaImprimir,irAoMonitorAposImprimirPosVenda"
                    >{{ $labelContinuar }}</button>
                @else
                    <button
                        type="button"
                        id="erp-fv-imprimir-pos-venda-sim"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                        data-erp-fv-imprimir-btn="sim"
                        wire:click="confirmarImprimirPosVenda"
                        wire:loading.attr="disabled"
                        wire:target="confirmarImprimirPosVenda,dispensarImprimirPosVenda"
                        @disabled($this->fvImprimirPosVendaProcessando)
                    >Sim, imprimir</button>

                    <button
                        type="button"
                        id="erp-fv-imprimir-pos-venda-nao"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                        data-erp-fv-imprimir-btn="nao"
                        wire:click="dispensarImprimirPosVenda"
                        wire:loading.attr="disabled"
                        wire:target="confirmarImprimirPosVenda,dispensarImprimirPosVenda"
                        @disabled($this->fvImprimirPosVendaProcessando)
                    >Não</button>
                @endif
            </div>

            <p class="erp-aviso-modal__hint">{{ $hintEsc }}</p>
        </div>
    </div>
@endif
