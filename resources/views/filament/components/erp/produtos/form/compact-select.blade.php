{{--
    Select compacto (substitui <select> nativo — opções com altura reduzida).
    @var string $id
    @var string $field          chave em data.* (ex.: grupo)
    @var list<string>|array<string, string> $options
    @var bool $grow             default false
    @var bool $allowEmpty       default true
--}}
@php
    $id = $id ?? '';
    $field = $field ?? '';
    $options = $options ?? [];
    $grow = $grow ?? false;
    $allowEmpty = $allowEmpty ?? true;
    $selected = (string) ($this->data[$field] ?? '');
    $selectedLabel = $selected;

    foreach ($options as $optionKey => $optionValue) {
        $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
        if ($value === $selected) {
            $selectedLabel = (string) $optionValue;
            break;
        }
    }
@endphp

<div
    @class([
        'erp-prod-compact-select',
        'erp-prod-compact-select--grow' => $grow,
    ])
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        id="{{ $id }}"
        class="erp-pcad-form__select erp-prod-compact-select__trigger"
        @click="open = ! open"
        @keydown.arrow-down.prevent="open = true"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        aria-controls="{{ $id }}-list"
    >
        <span class="erp-prod-compact-select__value">{{ $selected !== '' ? $selectedLabel : '' }}</span>
        <span class="erp-prod-compact-select__caret" aria-hidden="true">▾</span>
    </button>

    <ul
        id="{{ $id }}-list"
        class="erp-prod-compact-select__menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.75ms
        role="listbox"
        aria-labelledby="{{ $id }}"
    >
        @if ($allowEmpty)
            <li role="presentation">
                <button
                    type="button"
                    role="option"
                    aria-selected="{{ $selected === '' ? 'true' : 'false' }}"
                    class="erp-prod-compact-select__item @if ($selected === '') is-selected @endif"
                    wire:click="$set('data.{{ $field }}', '')"
                    @click="open = false"
                >&nbsp;</button>
            </li>
        @endif
        @foreach ($options as $optionKey => $optionValue)
            @php
                $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
                $label = (string) $optionValue;
                $isSelected = $selected === $value;
            @endphp
            <li role="presentation" wire:key="compact-select-{{ $id }}-{{ $value }}">
                <button
                    type="button"
                    role="option"
                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                    class="erp-prod-compact-select__item @if ($isSelected) is-selected @endif"
                    wire:click="$set('data.{{ $field }}', @js($value))"
                    @click="open = false"
                >{{ $label }}</button>
            </li>
        @endforeach
    </ul>
</div>
