@props([
    'value' => '',
    'itemIndex' => null,
    'options' => [],
    'compact' => false,
])

@php
    $current = preg_replace('/\D/', '', (string) $value) ?: '';
    $display = $current !== '' ? $current : '—';
    $itemIndexJs = $itemIndex === null ? 'null' : (int) $itemIndex;
    $comboUid = 'cfop-'.($itemIndex === null ? 'header' : (int) $itemIndex);
@endphp

<div
    class="erp-nf-cfop-combo {{ $compact ? 'erp-nf-cfop-combo--compact' : '' }}"
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
                const width = 76;
                let left = r.left;
                let top = r.bottom + 1;
                const panelHeight = Math.min(184, panel.scrollHeight || 184);

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
        pick(codigo) {
            this.open = false;
            $wire.selecionarCfopXml(String(codigo), {{ $itemIndexJs }});
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
        class="erp-nf-cfop-combo__trigger {{ $compact ? 'erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--center' : '' }}"
        @click="toggle()"
        title="Escolher CFOP de entrada"
    >
        <span class="erp-nf-cfop-combo__value">{{ $display }}</span>
        <span class="erp-nf-cfop-combo__chevron" aria-hidden="true"></span>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        class="erp-nf-cfop-combo__panel"
        :style="panelStyle"
        @click.stop
    >
        <div class="erp-nf-cfop-combo__head">CÓDIGO</div>
        <div class="erp-nf-cfop-combo__list" role="listbox" aria-label="CFOP de entrada">
            @forelse ($options as $codigo)
                <button
                    type="button"
                    class="erp-nf-cfop-combo__option {{ $codigo === $current ? 'is-selected' : '' }}"
                    @if ($codigo === $current) data-selected @endif
                    @click="pick(@js($codigo))"
                    role="option"
                    @if ($codigo === $current) aria-selected="true" @endif
                >{{ $codigo }}</button>
            @empty
                <div class="erp-nf-cfop-combo__empty">Sem CFOP de entrada</div>
            @endforelse
        </div>
    </div>
</div>
