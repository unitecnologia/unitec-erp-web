@if ($this->lookupOpen)
    @php
        $lookup = $this->lookupViewState;
    @endphp

    <div
        class="erp-lookup-modal"
        wire:keydown.escape="handleLookupEscape"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeProductLookup"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-lookup-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-lookup-title">{{ $lookup['title'] }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeProductLookup" title="Fechar">✕</button>
            </div>

            @if ($lookup['panel'] === 'list')
                <div class="erp-lookup-modal__body">
                    <fieldset class="erp-lookup-modal__search-box">
                        <legend class="erp-lookup-modal__search-legend">
                            F6 | Localizar &lt;&lt;{{ $lookup['searchLabel'] }}&gt;&gt;
                        </legend>
                        <input
                            id="erp-lookup-search"
                            type="text"
                            wire:model.live.debounce.200ms="lookupSearch"
                            class="erp-pcad-form__input erp-lookup-modal__search-input"
                        >
                    </fieldset>

                    <p class="erp-lookup-modal__hint">
                        Você pode mudar a pesquisa clicando no título do campo a ser pesquisado.
                    </p>

                    <div class="erp-lookup-modal__grid-wrap">
                        <table class="erp-lookup-modal__grid">
                            <thead>
                                <tr>
                                    @foreach ($lookup['columns'] as $columnKey => $columnLabel)
                                        <th
                                            scope="col"
                                            wire:click="setLookupSearchColumn('{{ $columnKey }}')"
                                            @class([
                                                'erp-lookup-modal__grid-head',
                                                'erp-lookup-modal__grid-head--active' => $lookup['searchColumn'] === $columnKey,
                                            ])
                                        >
                                            @if ($lookup['searchColumn'] === $columnKey)
                                                &gt;&gt;{{ $columnLabel }}
                                            @else
                                                {{ $columnLabel }}
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lookup['records'] as $record)
                                    <tr
                                        wire:key="lookup-row-{{ $record['id'] }}"
                                        data-record-id="{{ $record['id'] }}"
                                        wire:click="highlightLookupRecord({{ $record['id'] }})"
                                        wire:dblclick="confirmProductLookup({{ $record['id'] }})"
                                        @class([
                                            'erp-lookup-modal__row',
                                            'erp-lookup-modal__row--selected' => $lookup['highlightedId'] === $record['id'],
                                        ])
                                    >
                                        @foreach ($lookup['columns'] as $columnKey => $columnLabel)
                                            <td>{{ $record['values'][$columnKey] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($lookup['columns']) }}" class="erp-lookup-modal__empty">
                                            Nenhum registro encontrado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="startLookupCreate" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon">+</span>
                        <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Novo</span>
                    </button>
                    <button type="button" wire:click="startLookupEdit" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon">✎</span>
                        <span class="erp-pcad-actions__label"><kbd>F3</kbd> | Alterar</span>
                    </button>
                    <button type="button" wire:click="modulePending('Imprimir')" class="erp-pcad-actions__btn erp-lookup-modal__btn--disabled" title="Em implementação">
                        <span class="erp-pcad-actions__icon">🖨</span>
                        <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Imprimir</span>
                    </button>
                    <button type="button" wire:click="closeProductLookup" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                    </button>
                </div>
            @else
                <div class="erp-lookup-modal__body erp-lookup-modal__body--form">
                    <fieldset class="erp-lookup-modal__form-box">
                        <legend class="erp-lookup-modal__form-legend">
                            {{ $lookup['editing'] ? 'Alterar' : 'Novo' }} — {{ $lookup['title'] }}
                        </legend>

                        @foreach ($lookup['formFields'] as $fieldKey => $fieldLabel)
                            <label class="erp-lookup-modal__form-field" for="erp-lookup-field-{{ $fieldKey }}">
                                <span>{{ $fieldLabel }}</span>
                                <input
                                    id="erp-lookup-field-{{ $fieldKey }}"
                                    type="text"
                                    wire:model="lookupForm.{{ $fieldKey }}"
                                    class="erp-pcad-form__input"
                                >
                            </label>
                        @endforeach
                    </fieldset>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions erp-lookup-modal__actions--form">
                    <button type="button" wire:click="saveLookupRecord" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Salvar</span>
                    </button>
                    <button type="button" wire:click="cancelLookupForm" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">↩</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Voltar</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
