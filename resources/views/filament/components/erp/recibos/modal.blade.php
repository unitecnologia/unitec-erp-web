@if ($this->showForm)
    <div
        class="erp-fpgto-modal erp-recibo-modal"
        x-data
        x-init="
            $nextTick(() => {
                const el = $refs.reciboValor;
                if (!el) return;
                el.removeAttribute('readonly');
                el.focus();
            });
        "
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleRecibosEscape(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.saveRecibo(); }
        "
        x-on:erp-recibo-focus-referente.window="
            $nextTick(() => {
                const next = $refs.reciboReferente;
                if (!next) return;
                next.removeAttribute('readonly');
                next.focus();
            });
        "
    >
        <div class="erp-fpgto-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-fpgto-modal__dialog erp-recibo-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <div class="erp-recibo-modal__title-wrap">
                    <span class="erp-recibo-modal__eyebrow">Financeiro</span>
                    <span>Cadastro de Recibo</span>
                </div>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body erp-recibo-modal__body">
                <div class="erp-recibo-modal__grid">
                    <label class="erp-fpgto-field erp-recibo-modal__code">
                        <span class="erp-fpgto-field__label">Código</span>
                        <input
                            type="text"
                            inputmode="numeric"
                            wire:model="form.codigo"
                            class="erp-fpgto-field__input erp-fpgto-field__input--code"
                            readonly
                            tabindex="-1"
                        >
                    </label>

                    <label class="erp-fpgto-field erp-recibo-modal__emissao">
                        <span class="erp-fpgto-field__label">Emissão</span>
                        <input
                            type="date"
                            wire:model="form.emissao"
                            class="erp-fpgto-field__input"
                            x-on:keydown.enter.prevent="
                                $refs.reciboValor?.removeAttribute('readonly');
                                $refs.reciboValor?.focus();
                            "
                        >
                    </label>

                    <label class="erp-fpgto-field erp-recibo-modal__valor">
                        <span class="erp-fpgto-field__label">Valor (R$)</span>
                        <input
                            type="text"
                            inputmode="decimal"
                            wire:model="form.valor"
                            wire:blur="syncExtensoFromValor"
                            x-ref="reciboValor"
                            class="erp-fpgto-field__input erp-fpgto-field__input--money"
                            placeholder="0,00"
                            x-on:keydown.enter.prevent="
                                const v = $event.target.value;
                                $event.target.removeAttribute('readonly');
                                $wire.set('form.valor', v).then(() => $wire.syncExtensoFromValor()).then(() => {
                                    const next = document.getElementById('recibo-recebi-de');
                                    if (!next) return;
                                    next.removeAttribute('readonly');
                                    next.focus();
                                });
                            "
                        >
                    </label>

                    <label class="erp-fpgto-field erp-recibo-modal__extenso">
                        <span class="erp-fpgto-field__label">Extenso</span>
                        <input
                            type="text"
                            wire:model="form.extenso"
                            maxlength="500"
                            x-ref="reciboExtenso"
                            class="erp-fpgto-field__input"
                            placeholder="PREENCHIDO AUTOMATICAMENTE PELO VALOR"
                            data-erp-uppercase
                            style="text-transform: uppercase;"
                            x-on:keydown.enter.prevent="
                                const next = document.getElementById('recibo-recebi-de');
                                if (!next) return;
                                next.removeAttribute('readonly');
                                next.focus();
                            "
                        >
                    </label>

                    <div class="erp-fpgto-field erp-recibo-modal__recebi">
                        <span class="erp-fpgto-field__label">Recebi de</span>
                        <div
                            class="erp-recibo-recebi-lookup"
                            x-data="{ selected: {{ (int) ($this->selectedRecebiIndex ?? 0) }} }"
                            x-on:keydown.capture="
                                if ($event.target.id !== 'recibo-recebi-de') return;

                                if ($event.key === 'Enter') {
                                    $event.preventDefault();
                                    $event.stopImmediatePropagation();
                                    const total = $el.querySelector('.erp-recibo-recebi-lookup__list')?.children?.length ?? 0;
                                    if (total > 0) {
                                        $wire.selectRecebiResult(selected);
                                    } else {
                                        $wire.handleRecebiEnter();
                                    }
                                    return;
                                }

                                if ($event.key !== 'ArrowDown' && $event.key !== 'ArrowUp') return;
                                $event.preventDefault();
                                $event.stopImmediatePropagation();

                                const list = $el.querySelector('.erp-recibo-recebi-lookup__list');
                                const total = list?.children?.length ?? 0;
                                if (total < 1) return;

                                selected = Math.max(0, Math.min(total - 1, selected + ($event.key === 'ArrowDown' ? 1 : -1)));
                                list.children[selected]?.scrollIntoView({ block: 'nearest' });
                            "
                        >
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="form.recebi_de"
                                wire:focus="openRecebiLookup"
                                maxlength="200"
                                x-ref="reciboRecebi"
                                id="recibo-recebi-de"
                                class="erp-fpgto-field__input"
                                data-erp-uppercase
                                style="text-transform: uppercase;"
                                autocapitalize="characters"
                                autocomplete="off"
                                placeholder="DIGITE O NOME PARA BUSCAR NO CADASTRO"
                            >
                            @if ($this->recebiLookupOpen && filled(trim((string) ($this->form['recebi_de'] ?? ''))))
                                @if ($this->recebiResults !== [])
                                    <div class="erp-recibo-recebi-lookup__panel" role="listbox" aria-label="Clientes encontrados">
                                        <ul class="erp-recibo-recebi-lookup__list">
                                            @foreach ($this->recebiResults as $index => $row)
                                                <li
                                                    wire:key="recibo-recebi-{{ $row['id'] }}"
                                                    wire:mousedown.prevent="selectRecebiResult({{ $index }})"
                                                    data-recebi-idx="{{ $index }}"
                                                    role="option"
                                                    class="erp-recibo-recebi-lookup__item"
                                                    :class="{ 'erp-recibo-recebi-lookup__item--active': selected === {{ $index }} }"
                                                >
                                                    <span class="erp-recibo-recebi-lookup__nome">{{ $row['nome'] }}</span>
                                                    @if (filled($row['cpf_cnpj']))
                                                        <span class="erp-recibo-recebi-lookup__doc">{{ $row['cpf_cnpj'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <div class="erp-recibo-recebi-lookup__panel erp-recibo-recebi-lookup__panel--empty">
                                        Não encontrado no cadastro — será gravado no recibo como digitado.
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="erp-fpgto-field erp-recibo-modal__referente">
                        <span class="erp-fpgto-field__label">Referente a</span>
                        <div
                            class="erp-recibo-produto-lookup"
                            x-data="{ selected: {{ (int) ($this->selectedReferenteIndex ?? 0) }} }"
                            x-on:keydown.capture="
                                if ($event.target !== $refs.reciboReferente) return;

                                if ($event.key === 'Enter') {
                                    const total = $el.querySelector('.erp-recibo-produto-lookup__list')?.children?.length ?? 0;
                                    if (total > 0) {
                                        $event.preventDefault();
                                        $event.stopImmediatePropagation();
                                        $wire.selectReferenteResult(selected);
                                    }
                                    return;
                                }

                                if ($event.key !== 'ArrowDown' && $event.key !== 'ArrowUp') return;

                                const list = $el.querySelector('.erp-recibo-produto-lookup__list');
                                const total = list?.children?.length ?? 0;
                                if (total < 1) return;

                                $event.preventDefault();
                                $event.stopImmediatePropagation();
                                selected = Math.max(0, Math.min(total - 1, selected + ($event.key === 'ArrowDown' ? 1 : -1)));
                                list.children[selected]?.scrollIntoView({ block: 'nearest' });
                            "
                        >
                            <textarea
                                wire:model.live.debounce.250ms="form.referente_a"
                                wire:focus="openReferenteLookup"
                                rows="2"
                                maxlength="2000"
                                x-ref="reciboReferente"
                                id="recibo-referente-a"
                                class="erp-fpgto-field__input erp-recibo-modal__textarea"
                                data-erp-uppercase
                                style="text-transform: uppercase;"
                                autocapitalize="characters"
                                autocomplete="off"
                                placeholder="DIGITE PARA BUSCAR PRODUTO OU TEXTO LIVRE (NÃO BAIXA ESTOQUE)"
                            ></textarea>
                            @php
                                $referenteLines = preg_split("/\r\n|\n|\r/", (string) ($this->form['referente_a'] ?? '')) ?: [''];
                                $referenteTerm = trim((string) $referenteLines[array_key_last($referenteLines)]);
                            @endphp
                            @if ($this->referenteLookupOpen && mb_strlen($referenteTerm) >= 2)
                                @if ($this->referenteResults !== [])
                                    <div class="erp-recibo-produto-lookup__panel" role="listbox" aria-label="Produtos encontrados">
                                        <ul class="erp-recibo-produto-lookup__list">
                                            @foreach ($this->referenteResults as $index => $row)
                                                <li
                                                    wire:key="recibo-produto-{{ $row['id'] }}"
                                                    wire:mousedown.prevent="selectReferenteResult({{ $index }})"
                                                    role="option"
                                                    class="erp-recibo-produto-lookup__item"
                                                    :class="{ 'erp-recibo-produto-lookup__item--active': selected === {{ $index }} }"
                                                >
                                                    <span class="erp-recibo-produto-lookup__codigo">{{ $row['codigo'] }}</span>
                                                    <span class="erp-recibo-produto-lookup__nome">{{ $row['descricao'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="erp-recibo-produto-lookup__hint">
                                            Só inclui o texto no recibo — não movimenta estoque.
                                        </div>
                                    </div>
                                @else
                                    <div class="erp-recibo-produto-lookup__panel erp-recibo-produto-lookup__panel--empty">
                                        Nenhum produto encontrado — texto livre (não baixa estoque).
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                @error('form.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.emissao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.valor') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.extenso') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.recebi_de') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.referente_a') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save" wire:click="saveRecibo">
                    <span class="erp-fpgto-modal__btn-icon">✓</span> <kbd>F5</kbd> Salvar
                </button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel" wire:click="closeForm">
                    <span class="erp-fpgto-modal__btn-icon">✕</span> <kbd>ESC</kbd> Sair
                </button>
            </div>
        </div>
    </div>
@endif
