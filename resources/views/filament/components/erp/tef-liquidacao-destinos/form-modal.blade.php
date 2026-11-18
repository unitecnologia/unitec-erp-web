@if ($this->destinoModalOpen)
    @php
        $terminais = $this->destinoTerminalOptions();
        $contas = $this->destinoContaOptions();
        $provedores = $this->destinoProvedorOptions();
        $isEdit = filled($this->destinoModalRecordId);
        $provedorUnico = count($provedores) === 1;
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-tef-liq-dest-form-modal"
        wire:keydown.escape.window="closeDestinoModal"
        wire:keydown.f5.window.prevent="saveDestino"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeDestinoModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-tef-liq-dest-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-tef-liq-dest-form-title">Destino de liquidação TEF</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeDestinoModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Destino previsto (não liquida)</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="tef-liq-terminal">Terminal *</label>
                                <select
                                    id="tef-liq-terminal"
                                    wire:model="destinoForm.terminal_id"
                                    class="erp-pcad-form__select erp-pcad-form__input--grow"
                                    autofocus
                                >
                                    <option value="">Selecione…</option>
                                    @foreach ($terminais as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('destinoForm.terminal_id')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="tef-liq-provedor">Provedor *</label>
                                @if ($provedorUnico)
                                    @php $unicoKey = array_key_first($provedores); @endphp
                                    <input type="hidden" wire:model="destinoForm.provedor">
                                    <span class="erp-pcad-form__input erp-pcad-form__input--grow" style="display:flex;align-items:center;">{{ $provedores[$unicoKey] ?? 'SuperTEF' }}</span>
                                @else
                                    <select
                                        id="tef-liq-provedor"
                                        wire:model="destinoForm.provedor"
                                        class="erp-pcad-form__select erp-pcad-form__input--grow"
                                    >
                                        @foreach ($provedores as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('destinoForm.provedor')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="tef-liq-cnpj">CNPJ adquirente</label>
                                <input
                                    id="tef-liq-cnpj"
                                    type="text"
                                    wire:model.blur="destinoForm.acquirer_cnpj"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    maxlength="18"
                                    inputmode="numeric"
                                    placeholder="Vazio = padrão do terminal"
                                >
                                <span class="erp-tef-liq-dest-form-modal__hint">Opcional. Sem CNPJ = destino padrão do terminal.</span>
                                @error('destinoForm.acquirer_cnpj')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="tef-liq-conta">Conta BANCO *</label>
                                <select
                                    id="tef-liq-conta"
                                    wire:model="destinoForm.caixa_conta_id"
                                    class="erp-pcad-form__select erp-pcad-form__input--grow"
                                >
                                    <option value="">Selecione…</option>
                                    @foreach ($contas as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('destinoForm.caixa_conta_id')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="tef-liq-ativo">Ativo</label>
                                <input
                                    id="tef-liq-ativo"
                                    type="checkbox"
                                    wire:model="destinoForm.ativo"
                                    class="erp-pcad-form__checkbox"
                                >
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="closeDestinoModal">
                    Cancelar
                </button>
                <button type="button" class="erp-contador-form-modal__btn" wire:click="saveDestino">
                    {{ $isEdit ? 'Salvar (F5)' : 'Incluir (F5)' }}
                </button>
            </div>
        </div>
    </div>
@endif
