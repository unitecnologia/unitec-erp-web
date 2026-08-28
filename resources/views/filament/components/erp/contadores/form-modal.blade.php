@if ($this->contadorModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal"
        wire:keydown.escape.window="handleContadorModalEscape"
        wire:keydown.f5.window.prevent="saveContador"
        @if ($this->contadorCidadeSugestoesOpen && $this->contadorCidadeSugestoes !== []) data-lookup-open="1" @endif
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeContadorModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-contador-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-contador-form-title">Contadores</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeContadorModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div
                    class="erp-pcad-form erp-contador-form-modal__form"
                    data-erp-form
                    autocomplete="off"
                    x-data
                    x-on:input.capture="
                        const t = $event.target;
                        if (!t || t.disabled) return;
                        if (t.dataset.mask) return;
                        if (!(t.matches('input[type=text], input:not([type]), textarea'))) return;
                        const u = String(t.value || '').toLocaleUpperCase('pt-BR');
                        if (t.value === u) return;
                        const s = t.selectionStart, e = t.selectionEnd;
                        t.value = u;
                        if (document.activeElement === t && s != null && e != null) {
                            try { t.setSelectionRange(s, e); } catch (_) {}
                        }
                    "
                >
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-codigo">Código</label>
                        <input
                            id="contador-codigo"
                            type="text"
                            wire:model="contadorForm.codigo"
                            class="erp-pcad-form__input erp-pcad-form__input--xs"
                            @if ($this->contadorModalRecordId) readonly @endif
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-nome">Nome *</label>
                        <input
                            id="contador-nome"
                            type="text"
                            wire:model="contadorForm.nome"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            autofocus
                        >
                    </div>
                    @error('contadorForm.nome')
                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
                    @enderror

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cnpj">CNPJ/CPF *</label>
                        <input
                            id="contador-cnpj"
                            type="text"
                            wire:model="contadorForm.cnpj_cpf"
                            data-mask="cpf-cnpj"
                            class="erp-pcad-form__input erp-pcad-form__input--doc"
                        >
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-crc">CRC</label>
                        <input
                            id="contador-crc"
                            type="text"
                            wire:model="contadorForm.crc"
                            class="erp-pcad-form__input erp-pcad-form__input--md"
                        >
                    </div>
                    @error('contadorForm.cnpj_cpf')
                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
                    @enderror

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cep">CEP</label>
                        <input
                            id="contador-cep"
                            type="text"
                            wire:model="contadorForm.cep"
                            data-mask="cep"
                            x-on:blur="$wire.buscarCepContador(true)"
                            class="erp-pcad-form__input erp-pcad-form__input--cep"
                        >
                        <button
                            type="button"
                            wire:click="buscarCepContador"
                            wire:loading.attr="disabled"
                            wire:target="buscarCepContador"
                            class="erp-pcad-form__btn"
                        >
                            <span class="erp-pcad-form__btn-icon" aria-hidden="true">🔍</span>
                            Pesquisar CEP
                        </button>
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-endereco">Endereço</label>
                        <input
                            id="contador-endereco"
                            type="text"
                            wire:model="contadorForm.endereco"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-numero">Número</label>
                        <input
                            id="contador-numero"
                            type="text"
                            wire:model="contadorForm.numero"
                            class="erp-pcad-form__input erp-pcad-form__input--xs"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-bairro">Bairro</label>
                        <input
                            id="contador-bairro"
                            type="text"
                            wire:model="contadorForm.bairro"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cidade">Cidade</label>
                        <div
                            class="erp-pcad-form__city-wrap"
                            @if ($this->contadorCidadeSugestoesOpen && $this->contadorCidadeSugestoes !== []) data-lookup-open="1" @endif
                        >
                            <input
                                id="contador-cidade"
                                type="text"
                                wire:model.live.debounce.250ms="contadorForm.cidade"
                                wire:keydown.enter.prevent="confirmarContadorCidadeSugestao"
                                wire:keydown.escape.prevent="fecharContadorCidadeSugestoes"
                                wire:keydown.arrow-up.prevent="moverContadorCidadeSugestao(-1)"
                                wire:keydown.arrow-down.prevent="moverContadorCidadeSugestao(1)"
                                class="erp-pcad-form__input erp-pcad-form__input--city"
                                autocomplete="off"
                                placeholder="Digite a cidade"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->contadorCidadeSugestoesOpen && $this->contadorCidadeSugestoes !== [] ? 'true' : 'false' }}"
                                aria-controls="contador-cidade-sugestoes"
                            >
                            @if ($this->contadorCidadeSugestoesOpen && $this->contadorCidadeSugestoes !== [])
                                <ul id="contador-cidade-sugestoes" class="erp-pcad-form__city-suggest" role="listbox" aria-label="Cidades encontradas">
                                    @foreach ($this->contadorCidadeSugestoes as $index => $sug)
                                        <li wire:key="contador-cid-sug-{{ $sug['codigo'] }}-{{ $index }}" role="presentation">
                                            <button
                                                type="button"
                                                role="option"
                                                aria-selected="{{ (int) $this->contadorCidadeSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                wire:mousedown.prevent="selecionarContadorCidade(@js($sug['nome']), '{{ $sug['uf'] }}')"
                                                @class(['is-selected' => (int) $this->contadorCidadeSugestaoIndex === (int) $index])
                                            >
                                                <span class="erp-pcad-form__city-suggest-code">{{ $sug['codigo'] }}</span>
                                                <span class="erp-pcad-form__city-suggest-nome">{{ $sug['nome'] }}</span>
                                                <span class="erp-pcad-form__city-suggest-uf">{{ $sug['uf'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-uf">UF</label>
                        <select id="contador-uf" wire:model.live="contadorForm.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
                            @foreach ($this->contadorUfOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-email">Email *</label>
                        <input
                            id="contador-email"
                            type="text"
                            wire:model="contadorForm.email"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            inputmode="email"
                            autocomplete="off"
                        >
                    </div>
                    @error('contadorForm.email')
                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
                    @enderror

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-fone">Fone *</label>
                        <input
                            id="contador-fone"
                            type="text"
                            wire:model="contadorForm.fone"
                            data-mask="phone"
                            class="erp-pcad-form__input erp-pcad-form__input--phone"
                        >
                    </div>
                    @error('contadorForm.fone')
                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="saveContador" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeContadorModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
