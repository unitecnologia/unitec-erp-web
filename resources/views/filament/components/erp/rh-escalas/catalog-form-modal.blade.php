@if ($this->escalaCatalogModalOpen)
    @php
        $tipos = \App\Models\RhEscala::tipos();
        $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $trabalho = $this->escalaCatalogForm['dias_trabalho'] ?? [];
        $folga = $this->escalaCatalogForm['dias_folga'] ?? [];
        $tipoAtual = (string) ($this->escalaCatalogForm['tipo'] ?? '');
        $mostraDias = $tipoAtual !== \App\Models\RhEscala::TIPO_12X36;
    @endphp
    <div class="erp-lookup-modal erp-contador-form-modal erp-rh-escala-catalog-modal" wire:keydown.escape.window="closeEscalaCatalogModal" wire:keydown.f5.window.prevent="saveEscalaCatalog">
        <div class="erp-lookup-modal__backdrop" wire:click="closeEscalaCatalogModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->escalaCatalogRecordId ? 'Alterar escala (modelo)' : 'Nova escala (modelo)' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeEscalaCatalogModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form" data-erp-form autocomplete="off">
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-esc-c-cod">Código</label>
                        <input id="rh-esc-c-cod" type="text" wire:model="escalaCatalogForm.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" @if($this->escalaCatalogRecordId) readonly @endif>
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-c-tipo">Tipo</label>
                        <select id="rh-esc-c-tipo" wire:model.live="escalaCatalogForm.tipo" class="erp-pcad-form__select erp-pcad-form__input--grow">
                            @foreach ($tipos as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-c-nome">Nome *</label>
                        <input id="rh-esc-c-nome" type="text" wire:model.blur="escalaCatalogForm.nome" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="120" data-erp-uppercase autofocus>
                        @error('escalaCatalogForm.nome') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-esc-c-ent">Entrada</label>
                        <input id="rh-esc-c-ent" type="time" wire:model="escalaCatalogForm.hora_entrada" class="erp-pcad-form__input erp-pcad-form__input--date">
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-c-sai">Saída</label>
                        <input id="rh-esc-c-sai" type="time" wire:model="escalaCatalogForm.hora_saida" class="erp-pcad-form__input erp-pcad-form__input--date">
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-c-int">Intervalo (min)</label>
                        <input id="rh-esc-c-int" type="number" min="0" max="600" wire:model="escalaCatalogForm.intervalo_minutos" class="erp-pcad-form__input erp-pcad-form__input--xs">
                    </div>
                    @if ($tipoAtual === \App\Models\RhEscala::TIPO_12X36)
                        <p class="erp-rh-escalas__hint">12×36: ciclo alternado a partir do início do vínculo (ímpar = trabalho, par = folga).</p>
                    @endif
                    @if ($mostraDias)
                        <div class="erp-rh-escalas__dias-grid">
                            <span class="erp-rh-escalas__dias-title">Dias de trabalho</span>
                            <div class="erp-rh-escalas__dias-chips">
                                @foreach ($diasSemana as $dow => $nome)
                                    <button type="button" class="erp-rh-escalas__dia-chip {{ in_array($dow, $trabalho, true) ? 'erp-rh-escalas__dia-chip--on' : '' }}" wire:click="toggleEscalaDiaTrabalho({{ $dow }})">{{ $nome }}</button>
                                @endforeach
                            </div>
                            <span class="erp-rh-escalas__dias-title">Dias de folga</span>
                            <div class="erp-rh-escalas__dias-chips">
                                @foreach ($diasSemana as $dow => $nome)
                                    <button type="button" class="erp-rh-escalas__dia-chip erp-rh-escalas__dia-chip--folga {{ in_array($dow, $folga, true) ? 'erp-rh-escalas__dia-chip--on' : '' }}" wire:click="toggleEscalaDiaFolga({{ $dow }})">{{ $nome }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($this->escalaCatalogRecordId)
                        <label class="erp-rh-escalas__check">
                            <input type="checkbox" wire:model="escalaCatalogForm.ativo"> Modelo ativo
                        </label>
                    @endif
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="saveEscalaCatalog" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeEscalaCatalogModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
