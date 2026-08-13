{{--
    Select de Grupo com busca + flags App / Balança.
    @var string $id
--}}
@php
    $id = $id ?? 'pprod-grupo';
    $rows = $this->grupoSelectRows;
    $selected = (string) ($this->data['grupo'] ?? '');
    $selectedLabel = $selected !== '' ? \App\Models\Grupo::displayNome($selected) : '';
@endphp

<div
    wire:key="grupo-select-{{ md5($selected.'|'.json_encode($rows)) }}"
    class="erp-prod-compact-select erp-prod-grupo-select"
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
                minWidth: Math.max(Math.round(r.width), 220) + 'px',
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
            $wire.set('data.grupo', value);
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
            placeholder="Digite para pesquisar…"
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
            class="erp-prod-compact-select__menu erp-prod-grupo-select__menu erp-prod-compact-select__menu--portal"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.75ms
            :style="menuStyle"
            role="listbox"
            aria-labelledby="{{ $id }}"
        >
            <li class="erp-prod-grupo-select__head" role="presentation">
                <span class="erp-prod-grupo-select__head-name">Grupo</span>
                <span class="erp-prod-grupo-select__head-flag" title="Mostrar no App">App</span>
                <span class="erp-prod-grupo-select__head-flag" title="Grupo de balança">Bal.</span>
            </li>

            <li role="presentation" x-show="showingAll()" x-bind:hidden="! showingAll()">
                <div class="erp-prod-grupo-select__row @if ($selected === '') is-selected @endif">
                    <button
                        type="button"
                        role="option"
                        data-option
                        aria-selected="{{ $selected === '' ? 'true' : 'false' }}"
                        class="erp-prod-grupo-select__pick"
                        @click="pick('', '')"
                    >&nbsp;</button>
                    <span class="erp-prod-grupo-select__flag" aria-hidden="true" style="visibility:hidden">App</span>
                    <span class="erp-prod-grupo-select__flag" aria-hidden="true" style="visibility:hidden">Bal.</span>
                </div>
            </li>

            @foreach ($rows as $row)
                @php
                    $value = (string) $row['nome'];
                    $label = (string) $row['label'];
                    $isSelected = $selected === $value;
                @endphp
                <li
                    role="presentation"
                    wire:key="grupo-select-{{ $row['id'] }}"
                    x-show="match(@js($label))"
                    x-bind:hidden="! match(@js($label))"
                >
                    <div @class([
                        'erp-prod-grupo-select__row',
                        'is-selected' => $isSelected,
                    ])>
                        <button
                            type="button"
                            role="option"
                            data-option
                            aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                            class="erp-prod-grupo-select__pick"
                            @click="pick(@js($value), @js($label))"
                        >{{ $label }}</button>

                        <button
                            type="button"
                            class="erp-prod-grupo-select__flag @if ($row['mostrar_no_app']) is-on @endif"
                            wire:click.stop="toggleGrupoFlag(@js($value), 'mostrar_no_app')"
                            @click.stop
                            title="{{ $row['mostrar_no_app'] ? 'Visível no app' : 'Oculto no app' }}"
                            aria-pressed="{{ $row['mostrar_no_app'] ? 'true' : 'false' }}"
                            aria-label="Mostrar no App"
                        >App</button>

                        <button
                            type="button"
                            class="erp-prod-grupo-select__flag @if ($row['balanca_marcado']) is-on @endif"
                            wire:click.stop="toggleGrupoFlag(@js($value), 'balanca_marcado')"
                            @click.stop
                            title="{{ $row['balanca_marcado'] ? 'Grupo de balança' : 'Não é grupo de balança' }}"
                            aria-pressed="{{ $row['balanca_marcado'] ? 'true' : 'false' }}"
                            aria-label="Balança"
                        >Bal.</button>
                    </div>
                </li>
            @endforeach
        </ul>
    </template>
</div>
