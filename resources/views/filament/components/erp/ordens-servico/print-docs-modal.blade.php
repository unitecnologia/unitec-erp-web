@if ($this->printDocsModalOpen)
    <div
        class="erp-lookup-modal erp-orc-print-modal erp-os-print-docs"
        wire:key="os-print-docs-{{ $this->highlightedRecordId }}"
        wire:keydown.escape.window="closePrintDocsModal"
        x-data
        x-init="$nextTick(() => $el.querySelector('.erp-os-print-docs__btn--print')?.focus())"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closePrintDocsModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-os-print-docs-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-print-docs-title">Documentos para imprimir</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closePrintDocsModal" title="Fechar">✕</button>
            </div>

            <form class="erp-os-print-docs__body" wire:submit.prevent="imprimirDocumentosSelecionados">
                <ul class="erp-os-print-docs__list">
                    <li class="erp-os-print-docs__item">
                        <label class="erp-os-print-docs__check">
                            <input type="checkbox" value="os" wire:model="printDocsMarcados">
                            <span>Ordem de Serviço nº {{ $this->printDocsOsNumero }}</span>
                        </label>
                        <div class="erp-os-print-docs__modelo" role="radiogroup" aria-label="Modelo da OS">
                            <label @class(['is-active' => ! $this->printDocsOsTecnica])>
                                <input type="radio" value="0" wire:model.live="printDocsOsTecnica"> Completa
                            </label>
                            <label @class(['is-active' => $this->printDocsOsTecnica])>
                                <input type="radio" value="1" wire:model.live="printDocsOsTecnica"> Técnica
                            </label>
                        </div>
                    </li>
                    @foreach ($this->printDocsDisponiveis as $chave => $documento)
                        <li class="erp-os-print-docs__item" wire:key="os-print-doc-{{ $chave }}">
                            <label class="erp-os-print-docs__check">
                                <input type="checkbox" value="{{ $chave }}" wire:model="printDocsMarcados">
                                <span>{{ $documento['rotulo'] }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <div class="erp-os-print-docs__actions">
                    <button
                        type="submit"
                        class="erp-orc-print-modal__option erp-os-print-docs__btn--print"
                        wire:loading.attr="disabled"
                        wire:target="imprimirDocumentosSelecionados"
                    >🖨 Imprimir selecionados</button>
                    <button type="button" class="erp-orc-print-modal__option erp-orc-print-modal__option--exit" wire:click="closePrintDocsModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
