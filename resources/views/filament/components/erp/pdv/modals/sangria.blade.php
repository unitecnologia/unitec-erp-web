@if ($this->activeModal === 'sangria')
    <div class="erp-pdv-modal" role="dialog" aria-labelledby="erp-pdv-sangria-title">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-pdv-sangria-title">Sangria</h2>
            </header>

            <div class="erp-pdv-modal__body">
                <div class="erp-pdv-form erp-pdv-form--modal">
                    <label class="erp-pdv-form__field erp-pdv-form__field--full">
                        <span>Histórico</span>
                        <input type="text" wire:model="sangriaForm.historico" class="erp-pdv-form__input">
                    </label>

                    <label class="erp-pdv-form__field">
                        <span>Valor</span>
                        <input type="text" wire:model="sangriaForm.valor" class="erp-pdv-form__input erp-pdv-form__input--money">
                    </label>

                    <label class="erp-pdv-form__field">
                        <span>Tipo de Contas</span>
                        <select wire:model="sangriaForm.tipo_conta" class="erp-pdv-form__select">
                            <option value=""></option>
                            @foreach ($this->tipoContaOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-pdv-form__field erp-pdv-form__field--full">
                        <span>Destino do Lançamento</span>
                        <select wire:model="sangriaForm.destino" class="erp-pdv-form__select">
                            @foreach ($this->sangriaDestinoOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="gravarSangria" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">
                    <kbd>F10</kbd> Gravar
                </button>
                <button type="button" wire:click="closePdvModal" class="erp-pdv-modal__btn">
                    <kbd>Esc</kbd> Cancelar
                </button>
            </footer>
        </div>
    </div>
@endif
