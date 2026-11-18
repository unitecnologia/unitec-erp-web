@if ($this->ajusteModalOpen)
    @php
        $funcionarios = $this->vinculoFuncionarioOptions();
        $tipos = \App\Models\RhEscalaAjuste::tipos();
    @endphp
    <div class="erp-lookup-modal erp-contador-form-modal erp-rh-escala-ajuste-modal" wire:keydown.escape.window="closeAjusteModal" wire:keydown.f5.window.prevent="saveAjusteDia">
        <div class="erp-lookup-modal__backdrop" wire:click="closeAjusteModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-contador-form-modal__window--sm" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Ajuste pontual do dia</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeAjusteModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <div class="erp-pcad-form" data-erp-form autocomplete="off">
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-a-func">Funcionário *</label>
                        <select id="rh-esc-a-func" wire:model="ajusteForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                            <option value="">Selecione…</option>
                            @foreach ($funcionarios as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-esc-a-data">Data *</label>
                        <input id="rh-esc-a-data" type="date" wire:model="ajusteForm.data_ref" class="erp-pcad-form__input erp-pcad-form__input--date">
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-a-tipo">Tipo</label>
                        <select id="rh-esc-a-tipo" wire:model="ajusteForm.tipo" class="erp-pcad-form__select">
                            @foreach ($tipos as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-esc-a-ini">Início</label>
                        <input id="rh-esc-a-ini" type="time" wire:model="ajusteForm.hora_inicio" class="erp-pcad-form__input erp-pcad-form__input--date">
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-a-fim">Fim</label>
                        <input id="rh-esc-a-fim" type="time" wire:model="ajusteForm.hora_fim" class="erp-pcad-form__input erp-pcad-form__input--date">
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-a-mot">Motivo</label>
                        <input id="rh-esc-a-mot" type="text" wire:model="ajusteForm.motivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="saveAjusteDia" class="erp-pcad-actions__btn"><kbd>F5</kbd> | Gravar</button>
                <button type="button" wire:click="closeAjusteModal" class="erp-pcad-actions__btn"><kbd>ESC</kbd> | Sair</button>
            </div>
        </div>
    </div>
@endif
