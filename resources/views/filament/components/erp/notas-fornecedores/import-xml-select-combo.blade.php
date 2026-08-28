@props([
    'value' => '',
    'itemIndex' => 0,
    'field' => 'grupo',
    'options' => [],
    'title' => '',
    'emptyLabel' => '—',
    'panelWidth' => 180,
    'enterNav' => false,
    'center' => false,
])

@php
    $current = trim((string) $value);
    $display = $current !== '' ? $current : $emptyLabel;
    $optionList = [];
    $comboUid = $field.'-'.(int) $itemIndex;

    foreach ($options as $key => $label) {
        $codigo = is_int($key) ? (string) $label : (string) $key;
        $texto = (string) $label;
        $optionList[] = ['value' => $codigo, 'label' => $texto];
    }

    if ($current !== '' && ! collect($optionList)->contains(fn (array $o): bool => $o['value'] === $current)) {
        array_unshift($optionList, ['value' => $current, 'label' => $current]);
    }
@endphp

<div
    class="erp-nf-xml-combo {{ $center ? 'erp-nf-xml-combo--center' : '' }}"
    x-data="{
        open: false,
        uid: @js($comboUid),
        panelStyle: {},
        announceOpen() {
            window.dispatchEvent(new CustomEvent('erp-nf-combo-open', { detail: { uid: this.uid } }));
        },
        toggle() {
            if (this.open) {
                this.open = false;
                return;
            }

            this.announceOpen();
            this.open = true;

            this.$nextTick(() => {
                const btn = this.$refs.btn;
                const panel = this.$refs.panel;
                if (! btn || ! panel) {
                    return;
                }

                const r = btn.getBoundingClientRect();
                const width = Math.max({{ (int) $panelWidth }}, Math.round(r.width));
                let left = r.left;
                let top = r.bottom + 1;
                const panelHeight = Math.min(280, panel.scrollHeight || 280);

                if (left + width > window.innerWidth - 8) {
                    left = Math.max(8, window.innerWidth - width - 8);
                }

                if (top + panelHeight > window.innerHeight - 8) {
                    top = Math.max(8, r.top - panelHeight - 1);
                }

                this.panelStyle = {
                    position: 'fixed',
                    top: top + 'px',
                    left: left + 'px',
                    width: width + 'px',
                    zIndex: 700,
                };

                const selected = panel.querySelector('[data-selected]');
                selected?.scrollIntoView({ block: 'nearest' });
            });
        },
        pick(valor) {
            this.open = false;
            $wire.selecionarCampoItemXml(@js($field), String(valor), {{ (int) $itemIndex }});
        },
    }"
    @keydown.escape.window="open = false"
    @erp-nf-combo-open.window="if ($event.detail.uid !== uid) open = false"
    @click.outside="open = false"
    @click.stop
>
    <button
        type="button"
        x-ref="btn"
        class="erp-nf-xml-combo__trigger erp-nf-forn-import-xml-modal__cell-input"
        @click="toggle()"
        @if ($enterNav) data-erp-xml-enter @endif
        title="{{ $title !== '' ? $title : 'Selecionar' }}"
    >
        <span class="erp-nf-xml-combo__value">{{ $display }}</span>
        <span class="erp-nf-xml-combo__chevron" aria-hidden="true"></span>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        class="erp-nf-xml-combo__panel"
        :style="panelStyle"
        @click.stop
    >
        <button
            type="button"
            class="erp-nf-xml-combo__option {{ $current === '' ? 'is-selected' : '' }}"
            @if ($current === '') data-selected @endif
            @click="pick('')"
        >{{ $emptyLabel }}</button>

        @foreach ($optionList as $option)
            <button
                type="button"
                class="erp-nf-xml-combo__option {{ $option['value'] === $current ? 'is-selected' : '' }}"
                @if ($option['value'] === $current) data-selected @endif
                @click="pick(@js($option['value']))"
                title="{{ $option['label'] }}"
            >{{ $option['label'] }}</button>
        @endforeach
    </div>
</div>
