@if ($this->observacaoPedidoModalOpen)
    <div
        class="erp-pdv-modal erp-fv-tv-obs"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-obs-pedido-title"
        x-data="{ temTexto: @js(trim($this->observacoes) !== '') }"
    >
        <div
            class="erp-pdv-modal__backdrop"
            x-on:click="$wire.fecharObservacaoPedido(document.getElementById('erp-fv-obs-pedido')?.value ?? '')"
        ></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-obs-pedido-title">Observação do pedido</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <textarea
                    id="erp-fv-obs-pedido"
                    class="erp-pdv-modal__input erp-fv-tv-obs__text"
                    rows="8"
                    wire:model="observacoes"
                    placeholder="Digite a observação do pedido…"
                    x-on:input.capture="
                        const el = $event.target;
                        const up = el.value.toLocaleUpperCase('pt-BR');
                        if (el.value !== up) {
                            const start = el.selectionStart;
                            const end = el.selectionEnd;
                            el.value = up;
                            el.setSelectionRange(start, end);
                        }
                        temTexto = el.value.trim() !== '';
                    "
                ></textarea>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    x-text="temTexto ? 'Salvar' : 'Sair'"
                    x-on:click="$wire.fecharObservacaoPedido(document.getElementById('erp-fv-obs-pedido')?.value ?? '')"
                >Sair</button>
            </footer>
        </div>
    </div>
@endif

@if ($this->observacaoClienteModalOpen)
    <div class="erp-pdv-modal erp-fv-tv-obs" role="dialog" aria-modal="true" aria-labelledby="erp-fv-obs-cliente-title">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharObservacaoCliente"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-obs-cliente-title">Observação do cliente</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <textarea
                    id="erp-fv-obs-cliente"
                    class="erp-pdv-modal__input erp-fv-tv-obs__text"
                    rows="8"
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                    data-erp-locked="1"
                >{{ $this->clienteObservacoes }}</textarea>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="fecharObservacaoCliente" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Fechar</button>
            </footer>
        </div>
    </div>
@endif
