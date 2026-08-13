@php
    $legado = filled($this->data['loc_legado'] ?? null) ? (string) $this->data['loc_legado'] : null;
    $locCorredor = (string) ($this->data['loc_corredor'] ?? '');
    $locModulo = (string) ($this->data['loc_modulo'] ?? '');
    $locPrateleira = (string) ($this->data['loc_prateleira'] ?? '');
    $locGaveta = (string) ($this->data['loc_gaveta'] ?? '');
@endphp

{{--
  wire:ignore evita o morph apagar a digitação.
  A troca de aba (Trocas/ANP) remove o bloco porque o painel pai tem wire:key por subaba.
--}}
<div
    wire:ignore
    class="erp-produtos-loc"
    data-erp-loc-fields
    x-data="{
        rev: 0,
        sanitizeLocInput(event) {
            const input = event.target;
            input.value = String(input.value ?? '').replace(/\D/g, '').slice(0, 2);
            this.rev++;
        },
        onLocEnter(event) {
            event.preventDefault();
            event.stopPropagation();
            this.sanitizeLocInput(event);

            const order = [
                this.$refs.corredor,
                this.$refs.modulo,
                this.$refs.prateleira,
                this.$refs.gaveta,
            ].filter(Boolean);

            const index = order.indexOf(event.target);
            const next = index >= 0 ? order[index + 1] : null;

            if (next) {
                next.focus({ preventScroll: true });
                if (typeof next.select === 'function') {
                    next.select();
                }
                return;
            }

            if (typeof window.pushErpProdutosLocToLivewire === 'function') {
                window.pushErpProdutosLocToLivewire();
            }
        },
        formatLocPart(label, value) {
            const digits = String(value ?? '').replace(/\D/g, '');

            if (! digits || parseInt(digits, 10) <= 0) {
                return null;
            }

            return label + ':' + digits;
        },
        preview() {
            this.rev;

            const parts = [
                this.formatLocPart('C', $refs.corredor?.value),
                this.formatLocPart('M', $refs.modulo?.value),
                this.formatLocPart('P', $refs.prateleira?.value),
                this.formatLocPart('G', $refs.gaveta?.value),
            ].filter(Boolean);

            return parts.length ? parts.join('/') : null;
        },
    }"
>
    <span class="erp-produtos-loc__title">Localização</span>

    <div class="erp-produtos-loc__fields" role="group" aria-label="Localização do produto">
        <label class="erp-produtos-loc__field">
            <span>Corredor</span>
            <input
                id="pprod-loc-corredor"
                x-ref="corredor"
                type="text"
                value="{{ $locCorredor }}"
                maxlength="2"
                @input="sanitizeLocInput($event)"
                @keydown.enter="onLocEnter($event)"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="1"
                class="erp-pcad-form__input erp-produtos-loc__input"
                title="Corredor"
                autocomplete="off"
            >
        </label>
        <label class="erp-produtos-loc__field">
            <span>Módulo</span>
            <input
                id="pprod-loc-modulo"
                x-ref="modulo"
                type="text"
                value="{{ $locModulo }}"
                maxlength="2"
                @input="sanitizeLocInput($event)"
                @keydown.enter="onLocEnter($event)"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="2"
                class="erp-pcad-form__input erp-produtos-loc__input"
                title="Módulo"
                autocomplete="off"
            >
        </label>
        <label class="erp-produtos-loc__field">
            <span>Prateleira</span>
            <input
                id="pprod-loc-prateleira"
                x-ref="prateleira"
                type="text"
                value="{{ $locPrateleira }}"
                maxlength="2"
                @input="sanitizeLocInput($event)"
                @keydown.enter="onLocEnter($event)"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="3"
                class="erp-pcad-form__input erp-produtos-loc__input"
                title="Prateleira"
                autocomplete="off"
            >
        </label>
        <label class="erp-produtos-loc__field">
            <span>Gaveta</span>
            <input
                id="pprod-loc-gaveta"
                x-ref="gaveta"
                type="text"
                value="{{ $locGaveta }}"
                maxlength="2"
                @input="sanitizeLocInput($event)"
                @keydown.enter="onLocEnter($event)"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="4"
                class="erp-pcad-form__input erp-produtos-loc__input"
                title="Gaveta"
                autocomplete="off"
            >
        </label>
    </div>

    <div class="erp-produtos-loc__preview" aria-live="polite">
        <span class="erp-produtos-loc__preview-label">Padrão:</span>
        <strong x-text="preview() || 'C:/M:/P:/G:'"></strong>
    </div>

    @if ($legado)
        <p class="erp-produtos-loc__legacy">
            Valor anterior: <strong>{{ $legado }}</strong>. Preencha os campos acima para padronizar.
        </p>
    @endif
</div>
