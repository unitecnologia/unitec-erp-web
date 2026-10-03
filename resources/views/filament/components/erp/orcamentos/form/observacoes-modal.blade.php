@if ($this->observacaoOrcamentoModalOpen)
    <div
        class="erp-pdv-modal erp-fv-tv-obs erp-orc-obs-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-orc-obs-orcamento-title"
        x-data="{ temTexto: @js(trim($this->observacoes) !== ''), somenteLeitura: @js($this->orcamentoReadOnly()) }"
    >
        <div
            class="erp-pdv-modal__backdrop"
            x-on:click="$wire.fecharObservacaoOrcamento(document.getElementById('erp-orc-obs-orcamento')?.value ?? '')"
        ></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-orc-obs-orcamento-title">Observação do orçamento</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <textarea
                    id="erp-orc-obs-orcamento"
                    class="erp-pdv-modal__input erp-fv-tv-obs__text"
                    rows="8"
                    wire:model="observacoes"
                    placeholder="Digite a observação do orçamento…"
                    @readonly($this->orcamentoReadOnly())
                    @if ($this->orcamentoReadOnly()) tabindex="-1" aria-readonly="true" data-erp-locked="1" @endif
                    x-on:input.capture="
                        if (somenteLeitura) return;
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
                    x-text="somenteLeitura ? 'Fechar' : (temTexto ? 'Salvar' : 'Sair')"
                    x-on:click="$wire.fecharObservacaoOrcamento(document.getElementById('erp-orc-obs-orcamento')?.value ?? '')"
                >Sair</button>
            </footer>
        </div>
    </div>
@endif

@if ($this->observacaoClienteModalOpen)
    <div class="erp-pdv-modal erp-fv-tv-obs erp-orc-obs-modal" role="dialog" aria-modal="true" aria-labelledby="erp-orc-obs-cliente-title">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharObservacaoCliente"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-orc-obs-cliente-title">Observação do cliente</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <textarea
                    id="erp-orc-obs-cliente"
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
