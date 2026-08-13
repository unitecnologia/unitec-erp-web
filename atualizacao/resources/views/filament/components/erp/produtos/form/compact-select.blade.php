{{--
    Select compacto com busca por digitação.
    @var string $id
    @var string $field          chave em data.* (ex.: marca)
    @var list<string>|array<string, string> $options
    @var bool $grow             default false
    @var bool $allowEmpty       default true
    @var string|null $placeholder
--}}
@php
    $id = $id ?? '';
    $field = $field ?? '';
    $options = $options ?? [];
    $grow = $grow ?? false;
    $allowEmpty = $allowEmpty ?? true;
    $placeholder = $placeholder ?? 'Digite para pesquisar…';
    $selected = (string) ($this->data[$field] ?? '');
    $selectedLabel = $selected;

    foreach ($options as $optionKey => $optionValue) {
        $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
        if ($value === $selected) {
            $selectedLabel = (string) $optionValue;
            break;
        }
    }

    $items = [];
    $hasSplit = false;
    foreach ($options as $optionKey => $optionValue) {
        $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
        $label = (string) $optionValue;
        $code = '';
        $desc = '';
        if (preg_match('/^(.+?)\s+—\s+(.+)$/u', $label, $m)) {
            $code = $m[1];
            $desc = $m[2];
            $hasSplit = true;
        }
        $items[] = [
            'value' => $value,
            'label' => $label,
            'code' => $code,
            'desc' => $desc,
        ];
    }
@endphp

<div
    wire:key="compact-select-{{ $id }}-{{ md5($selected.'|'.$selectedLabel) }}"
    @class([
        'erp-prod-compact-select',
        'erp-prod-compact-select--grow' => $grow,
    ])
    x-data="{
        open: false,
        q: @js($selectedLabel),
        selectedLabel: @js($selectedLabel),
        menuStyle: {},
        norm(s) {
            return String(s || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        },
        match(label) {
            const qq = this.norm(this.q).trim();
            const selected = this.norm(this.selectedLabel).trim();
            if (! qq || qq === selected) return true;
            return this.norm(label).includes(qq);
        },
        showingAll() {
            const qq = this.norm(this.q).trim();
            return ! qq || qq === this.norm(this.selectedLabel).trim();
        },
        placeMenu() {
            const t = this.$refs.trigger;
            if (! t) return;
            const r = t.getBoundingClientRect();
            this.menuStyle = {
                position: 'fixed',
                top: Math.round(r.bottom + 2) + 'px',
                left: Math.round(r.left) + 'px',
                width: 'max-content',
                minWidth: Math.max(Math.round(r.width), 140) + 'px',
                maxHeight: 'none',
                overflow: 'visible',
                zIndex: '5000',
            };
        },
        show() {
            this.open = true;
            this.$nextTick(() => {
                this.placeMenu();
                requestAnimationFrame(() => this.placeMenu());
                this.$refs.input?.select?.();
            });
        },
        closeAndRestore() {
            this.open = false;
            this.q = this.selectedLabel;
        },
        pick(value, label) {
            this.selectedLabel = label;
            this.q = label;
            this.open = false;
            $wire.set('data.{{ $field }}', value);
        }
    }"
    :class="{ 'is-open': open }"
    @keydown.escape.window="if (open) { closeAndRestore(); }"
    @click.outside="if (open && ! $refs.menu?.contains($event.target)) { closeAndRestore(); }"
    @resize.window="if (open) placeMenu()"
    @scroll.window.capture="if (open) placeMenu()"
>
    <div
        x-ref="trigger"
        class="erp-pcad-form__select erp-prod-compact-select__trigger erp-prod-compact-select__trigger--input"
    >
        <input
            id="{{ $id }}"
            type="text"
            class="erp-prod-compact-select__input"
            x-ref="input"
            x-model="q"
            data-erp-prod-enter
            @focus="show()"
            @input="show()"
            @keydown.arrow-down.prevent="show()"
            @keydown.enter.prevent="
                const first = document.querySelector('#{{ $id }}-list [data-option]:not([hidden])');
                if (open && first) {
                    first.click();
                }
            "
            :aria-expanded="open.toString()"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-list"
            aria-haspopup="listbox"
            autocomplete="off"
            spellcheck="false"
            placeholder="{{ $placeholder }}"
        >
        <button
            type="button"
            class="erp-prod-compact-select__caret-btn"
            tabindex="-1"
            @click.prevent="open ? closeAndRestore() : (show(), $refs.input?.focus())"
            aria-label="Abrir lista"
        >
            <span class="erp-prod-compact-select__caret" aria-hidden="true">▾</span>
        </button>
    </div>

    <template x-teleport="body">
        <ul
            id="{{ $id }}-list"
            x-ref="menu"
            @class([
                'erp-prod-compact-select__menu',
                'erp-prod-compact-select__menu--portal',
                'erp-prod-compact-select__menu--aligned' => $hasSplit,
            ])
            x-show="open"
            x-cloak
            x-transition.opacity.duration.75ms
            :style="menuStyle"
            role="listbox"
            aria-labelledby="{{ $id }}"
        >
            @if ($allowEmpty)
                <li role="presentation" x-show="showingAll()" x-bind:hidden="! showingAll()">
                    <button
                        type="button"
                        role="option"
                        data-option
                        aria-selected="{{ $selected === '' ? 'true' : 'false' }}"
                        class="erp-prod-compact-select__item @if ($selected === '') is-selected @endif"
                        @click="pick('', '')"
                    >&nbsp;</button>
                </li>
            @endif
            @foreach ($items as $item)
                <li
                    role="presentation"
                    wire:key="compact-select-{{ $id }}-{{ $item['value'] }}"
                    x-show="match(@js($item['label']))"
                    x-bind:hidden="! match(@js($item['label']))"
                >
                    <button
                        type="button"
                        role="option"
                        data-option
                        aria-selected="{{ $selected === $item['value'] ? 'true' : 'false' }}"
                        @class([
                            'erp-prod-compact-select__item',
                            'erp-prod-compact-select__item--split' => $item['code'] !== '',
                            'is-selected' => $selected === $item['value'],
                        ])
                        @click="pick(@js($item['value']), @js($item['label']))"
                    >
                        @if ($item['code'] !== '')
                            <span class="erp-prod-compact-select__code">{{ $item['code'] }}</span>
                            <span class="erp-prod-compact-select__sep" aria-hidden="true">—</span>
                            <span class="erp-prod-compact-select__desc">{{ $item['desc'] }}</span>
                        @else
                            {{ $item['label'] }}
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </template>
</div>
