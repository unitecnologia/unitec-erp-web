{{--
    Dropdown compacto do campo de pesquisa (padrão Produtos).
    @var array<string, string> $fields
    @var string $searchColumn
    @var list<string>|null $markedFields  se informado, marca vários campos
    @var string|null $buttonLabel
    @var string|null $wireMethod   default: setSearchColumn (ignorado se $wireProperty)
    @var string|null $wireProperty Livewire $set('prop', value)
    @var bool $showFlag            default true
    @var bool $closeOnSelect       default true
    @var string|null $ariaLabel
    @var string|null $btnClass     classes extras no botão
--}}
@php
    $fields = $fields ?? [];
    $searchColumn = $searchColumn ?? '';
    $markedFields = $markedFields ?? null;
    $buttonLabel = $buttonLabel ?? ($fields[$searchColumn] ?? 'CAMPO');
    $wireMethod = $wireMethod ?? 'setSearchColumn';
    $wireProperty = $wireProperty ?? null;
    $showFlag = $showFlag ?? true;
    $closeOnSelect = $closeOnSelect ?? true;
    $ariaLabel = $ariaLabel ?? 'Campo de pesquisa';
    $btnClass = $btnClass ?? '';
@endphp

<div
    class="erp-field-dd"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="erp-field-dd__btn {{ $btnClass }}"
        @click="open = !open"
        :aria-expanded="open.toString()"
    >
        <span>{{ $buttonLabel }}</span>
        <span class="erp-field-dd__caret" aria-hidden="true">▾</span>
    </button>
    <ul class="erp-field-dd__menu" x-show="open" x-cloak x-transition.opacity.duration.75ms role="listbox" aria-label="{{ $ariaLabel }}">
        @foreach ($fields as $value => $label)
            @php
                $isMarked = is_array($markedFields)
                    ? in_array((string) $value, array_map('strval', $markedFields), true)
                    : (string) $searchColumn === (string) $value;
            @endphp
            <li role="option" aria-selected="{{ $isMarked ? 'true' : 'false' }}">
                <button
                    type="button"
                    class="erp-field-dd__item @if ($isMarked) is-active @endif"
                    @if ($wireProperty)
                        wire:click="$set('{{ $wireProperty }}', '{{ $value }}')"
                    @else
                        wire:click="{{ $wireMethod }}('{{ $value }}')"
                    @endif
                    @if ($closeOnSelect)
                        @click="open = false"
                    @endif
                >
                    @if ($showFlag)
                        <span
                            class="erp-field-dd__flag @if ($isMarked) is-on @endif"
                            aria-hidden="true"
                        ></span>
                    @endif
                    <span class="erp-field-dd__label">{{ $label }}</span>
                </button>
            </li>
        @endforeach
    </ul>
</div>
