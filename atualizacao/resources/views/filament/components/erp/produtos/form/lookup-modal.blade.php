@if ($this->lookupOpen)
    @php
        $lookup = $this->lookupViewState;
    @endphp

    <div
        @class([
            'erp-lookup-modal',
            'erp-lookup-modal--'.$lookup['type'] => filled($lookup['type'] ?? null),
            'erp-lookup-modal--compact' => in_array($lookup['type'] ?? null, ['grupo', 'marca', 'unidade'], true),
        ])
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

                    @if (count($lookup['columns']) > 1)
                        <p class="erp-lookup-modal__hint">
                            Clique no título da coluna para mudar o campo da pesquisa.
                        </p>
                    @endif

                    <div class="erp-lookup-modal__grid-wrap">
                        <table class="erp-lookup-modal__grid">
                            <thead>
                                <tr>
                                    @foreach ($lookup['columns'] as $columnKey => $columnLabel)
                                        @php
                                            $canSearchColumn = in_array($columnKey, $lookup['searchColumns'] ?? array_keys($lookup['columns']), true);
                                        @endphp
                                        <th
                                            scope="col"
                                            @if ($canSearchColumn)
                                                wire:click="setLookupSearchColumn('{{ $columnKey }}')"
                                            @endif
                                            @class([
                                                'erp-lookup-modal__grid-head',
                                                'erp-lookup-modal__grid-head--active' => $canSearchColumn && $lookup['searchColumn'] === $columnKey,
                                                'erp-lookup-modal__grid-head--static' => ! $canSearchColumn,
                                            ])
                                        >
                                            @if ($canSearchColumn && $lookup['searchColumn'] === $columnKey)
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
                                            @php
                                                $isBoolean = in_array($columnKey, $lookup['booleanFields'] ?? [], true);
                                                $flagOn = $isBoolean && ! empty($record['values'][$columnKey]);
                                            @endphp
                                            <td
                                                @class([
                                                    'erp-lookup-modal__cell--flag' => $isBoolean,
                                                ])
                                            >
                                                @if ($isBoolean)
                                                    <button
                                                        type="button"
                                                        class="erp-lookup-modal__flag @if ($flagOn) is-on @endif"
                                                        wire:click.stop="toggleLookupBoolean({{ $record['id'] }}, '{{ $columnKey }}')"
                                                        title="{{ $flagOn ? 'Visível no app força de vendas' : 'Oculto no app força de vendas' }}"
                                                        aria-pressed="{{ $flagOn ? 'true' : 'false' }}"
                                                        aria-label="{{ $columnLabel }}"
                                                    ></button>
                                                @else
                                                    {{ $record['values'][$columnKey] ?? '' }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($lookup['columns']) }}" class="erp-lookup-modal__empty">
                                            @if ($lookup['type'] === 'ncm')
                                                Nenhum NCM encontrado.
                                                <div class="erp-lookup-modal__empty-actions">
                                                    <button
                                                        type="button"
                                                        class="erp-pcad-form__btn"
                                                        wire:click="startLookupCreate"
                                                    >
                                                        Cadastrar NCM
                                                    </button>
                                                </div>
                                            @else
                                                Nenhum registro encontrado.
                                            @endif
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
                    @if (($lookup['type'] ?? null) !== 'grupo')
                        <button type="button" wire:click="modulePending('Imprimir')" class="erp-pcad-actions__btn erp-lookup-modal__btn--disabled" title="Em implementação">
                            <span class="erp-pcad-actions__icon">🖨</span>
                            <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Imprimir</span>
                        </button>
                    @endif
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
                            @if (in_array($fieldKey, $lookup['booleanFields'] ?? [], true))
                                <label class="erp-lookup-modal__form-check" for="erp-lookup-field-{{ $fieldKey }}">
                                    <input
                                        id="erp-lookup-field-{{ $fieldKey }}"
                                        type="checkbox"
                                        wire:model.boolean="lookupForm.{{ $fieldKey }}"
                                        class="erp-lookup-modal__form-check-input"
                                    >
                                    <span>{{ $fieldLabel }} — mostrar no app força de vendas</span>
                                </label>
                            @else
                                <label class="erp-lookup-modal__form-field" for="erp-lookup-field-{{ $fieldKey }}">
                                    <span>{{ $fieldLabel }}</span>
                                    <input
                                        id="erp-lookup-field-{{ $fieldKey }}"
                                        type="text"
                                        wire:model="lookupForm.{{ $fieldKey }}"
                                        class="erp-pcad-form__input"
                                    >
                                </label>
                            @endif
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
