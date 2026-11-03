@php
    $ehBancaria = (bool) ($this->form['eh_bancaria'] ?? false);
    $tipos = $ehBancaria
        ? [\App\Models\CaixaConta::TIPO_BANCO => 'BANCO']
        : \App\Models\CaixaConta::tipoLabelsOperacionais();
    $bancos = \App\Support\Erp\EmpresaParametros::boletoBancoOptions();
    $tiposBancarios = \App\Models\CaixaConta::contaTipoBancariaLabels();
    $isEdit = filled($this->formId);
@endphp

@if ($this->showForm)
    <div class="erp-contas-caixa-modal" x-data
         x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleContasCaixaEscape(); }
            if ($event.key === 'F2') { $event.preventDefault(); $wire.saveContaCaixa(); }
         ">
        <div class="erp-contas-caixa-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-contas-caixa-modal__dialog {{ $ehBancaria ? 'erp-contas-caixa-modal__dialog--banco' : '' }}" role="dialog" aria-modal="true" aria-label="Cadastro de conta caixa">
            <div class="erp-contas-caixa-modal__titlebar">
                <strong>{{ $isEdit ? 'Alterar conta caixa' : 'Nova conta caixa' }}</strong>
                <button type="button" class="erp-contas-caixa-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-contas-caixa-modal__body">
                <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--code">
                    <span>Código</span>
                    <input type="number" min="1" wire:model="form.codigo">
                </label>
                @error('form.codigo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Descrição</span>
                    <input type="text" wire:model="form.nome" maxlength="120" autofocus data-erp-uppercase>
                </label>
                @error('form.nome') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__check">
                    <input type="checkbox" wire:model.live="form.eh_bancaria">
                    É conta bancária
                </label>

                <label class="erp-contas-caixa-modal__field">
                    <span>Tipo</span>
                    <select wire:model.live="form.tipo" @disabled($ehBancaria)>
                        @foreach ($tipos as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @error('form.tipo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                @if ($ehBancaria)
                    <div class="erp-contas-caixa-modal__banco-grid">
                        @php
                            $bancoSelecionado = (string) ($this->form['banco_codigo'] ?? '');
                            $bancoLabel = $bancoSelecionado !== '' && isset($bancos[$bancoSelecionado])
                                ? $bancoSelecionado.' — '.$bancos[$bancoSelecionado]
                                : '';
                        @endphp
                        <div class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--full">
                            <span>Banco</span>
                            <div
                                class="erp-contas-caixa-banco-select"
                                wire:key="caixa-banco-select-{{ $bancoSelecionado }}"
                                x-data="{
                                    open: false,
                                    q: @js($bancoLabel),
                                    selectedLabel: @js($bancoLabel),
                                    menuStyle: {},
                                    activeIndex: -1,
                                    blurTimer: null,
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
                                    visibleOptions() {
                                        const menu = this.$refs.menu;
                                        if (! menu) return [];
                                        return Array.from(menu.querySelectorAll('[data-option]')).filter((el) => {
                                            const li = el.closest('li');
                                            if (! li) return true;
                                            if (li.hidden) return false;
                                            return window.getComputedStyle(li).display !== 'none';
                                        });
                                    },
                                    clearActive() {
                                        this.$refs.menu?.querySelectorAll('.is-active').forEach((el) => el.classList.remove('is-active'));
                                    },
                                    syncActive() {
                                        this.clearActive();
                                        const opts = this.visibleOptions();
                                        if (this.activeIndex < 0 || this.activeIndex >= opts.length) return;
                                        const el = opts[this.activeIndex];
                                        el.classList.add('is-active');
                                        el.scrollIntoView({ block: 'nearest' });
                                    },
                                    move(delta) {
                                        if (! this.open) {
                                            this.show();
                                            this.$nextTick(() => {
                                                const opts = this.visibleOptions();
                                                this.activeIndex = opts.length ? 0 : -1;
                                                this.syncActive();
                                            });
                                            return;
                                        }
                                        const opts = this.visibleOptions();
                                        if (! opts.length) return;
                                        if (this.activeIndex < 0) {
                                            this.activeIndex = delta > 0 ? 0 : opts.length - 1;
                                        } else {
                                            this.activeIndex = Math.max(0, Math.min(opts.length - 1, this.activeIndex + delta));
                                        }
                                        this.syncActive();
                                    },
                                    pickActive() {
                                        if (! this.open) return;
                                        const opts = this.visibleOptions();
                                        if (! opts.length) return;
                                        (this.activeIndex >= 0 ? opts[this.activeIndex] : opts[0])?.click();
                                    },
                                    placeMenu() {
                                        const t = this.$refs.trigger;
                                        if (! t) return;
                                        const r = t.getBoundingClientRect();
                                        this.menuStyle = {
                                            position: 'fixed',
                                            top: Math.round(r.bottom + 2) + 'px',
                                            left: Math.round(r.left) + 'px',
                                            width: Math.max(Math.round(r.width), 260) + 'px',
                                            maxHeight: '16rem',
                                            overflowY: 'auto',
                                            zIndex: '5200',
                                        };
                                    },
                                    portalStyle() {
                                        return Object.assign({}, this.menuStyle, { display: this.open ? 'block' : 'none' });
                                    },
                                    show() {
                                        if (this.blurTimer) { clearTimeout(this.blurTimer); this.blurTimer = null; }
                                        this.open = true;
                                        this.activeIndex = -1;
                                        this.$nextTick(() => {
                                            this.clearActive();
                                            this.placeMenu();
                                            requestAnimationFrame(() => this.placeMenu());
                                        });
                                    },
                                    showAndSelect() {
                                        this.show();
                                        this.$nextTick(() => this.$refs.input?.select?.());
                                    },
                                    closeAndRestore() {
                                        if (this.blurTimer) { clearTimeout(this.blurTimer); this.blurTimer = null; }
                                        this.open = false;
                                        this.activeIndex = -1;
                                        this.clearActive();
                                        this.q = this.selectedLabel;
                                    },
                                    onBlur(event) {
                                        const next = event.relatedTarget;
                                        if (this.$refs.menu?.contains(next) || this.$refs.trigger?.contains(next)) return;
                                        this.blurTimer = window.setTimeout(() => {
                                            const active = document.activeElement;
                                            if (this.$refs.menu?.contains(active) || this.$refs.trigger?.contains(active)) return;
                                            if (this.open) this.closeAndRestore();
                                        }, 120);
                                    },
                                    pick(value, label) {
                                        this.selectedLabel = label;
                                        this.q = label;
                                        this.open = false;
                                        this.activeIndex = -1;
                                        $wire.set('form.banco_codigo', value);
                                    }
                                }"
                                :class="{ 'is-open': open }"
                                @keydown.escape.window="if (open) closeAndRestore()"
                                @click.outside="if (open && ! $refs.menu?.contains($event.target)) closeAndRestore()"
                                @resize.window="if (open) placeMenu()"
                                @scroll.window.capture="if (open) placeMenu()"
                            >
                                <div x-ref="trigger" class="erp-contas-caixa-banco-select__trigger">
                                    <input
                                        type="text"
                                        class="erp-contas-caixa-banco-select__input"
                                        x-ref="input"
                                        x-model="q"
                                        @focus="showAndSelect()"
                                        @blur="onBlur($event)"
                                        @input="show()"
                                        @keydown.tab="closeAndRestore()"
                                        @keydown.arrow-down.prevent="move(1)"
                                        @keydown.arrow-up.prevent="move(-1)"
                                        @keydown.enter.prevent="pickActive()"
                                        :aria-expanded="open.toString()"
                                        aria-autocomplete="list"
                                        aria-controls="caixa-banco-list"
                                        aria-haspopup="listbox"
                                        autocomplete="off"
                                        spellcheck="false"
                                        placeholder="Digite código ou nome do banco…"
                                    >
                                    <button
                                        type="button"
                                        class="erp-contas-caixa-banco-select__caret"
                                        tabindex="-1"
                                        @click.prevent="open ? closeAndRestore() : (show(), $refs.input?.focus())"
                                        aria-label="Abrir lista de bancos"
                                    >▾</button>
                                </div>

                                <template x-teleport="body">
                                    <ul
                                        id="caixa-banco-list"
                                        x-ref="menu"
                                        class="erp-contas-caixa-banco-select__menu"
                                        x-cloak
                                        :style="portalStyle()"
                                        role="listbox"
                                    >
                                        <li role="presentation" x-show="showingAll()" x-bind:hidden="! showingAll()">
                                            <button
                                                type="button"
                                                role="option"
                                                data-option
                                                class="erp-contas-caixa-banco-select__item @if ($bancoSelecionado === '') is-selected @endif"
                                                @click="pick('', '')"
                                            >— Selecione…</button>
                                        </li>
                                        @foreach ($bancos as $codigo => $nome)
                                            @php $label = $codigo.' — '.$nome; @endphp
                                            <li
                                                role="presentation"
                                                wire:key="caixa-banco-opt-{{ $codigo }}"
                                                x-show="match(@js($label))"
                                                x-bind:hidden="! match(@js($label))"
                                            >
                                                <button
                                                    type="button"
                                                    role="option"
                                                    data-option
                                                    @class([
                                                        'erp-contas-caixa-banco-select__item',
                                                        'is-selected' => $bancoSelecionado === (string) $codigo,
                                                    ])
                                                    @click="pick(@js((string) $codigo), @js($label))"
                                                >
                                                    <span class="erp-contas-caixa-banco-select__code">{{ $codigo }}</span>
                                                    <span class="erp-contas-caixa-banco-select__sep" aria-hidden="true">—</span>
                                                    <span>{{ $nome }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </template>
                            </div>
                        </div>
                        @error('form.banco_codigo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-contas-caixa-modal__field">
                            <span>Agência</span>
                            <input type="text" wire:model="form.agencia" maxlength="20" inputmode="numeric">
                        </label>
                        @error('form.agencia') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--dv">
                            <span>DV</span>
                            <input type="text" wire:model="form.agencia_dv" maxlength="4">
                        </label>

                        <label class="erp-contas-caixa-modal__field">
                            <span>Conta</span>
                            <input type="text" wire:model="form.conta_numero" maxlength="40" inputmode="numeric">
                        </label>
                        @error('form.conta_numero') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--dv">
                            <span>DV</span>
                            <input type="text" wire:model="form.conta_dv" maxlength="4">
                        </label>

                        <label class="erp-contas-caixa-modal__field">
                            <span>Tipo de conta</span>
                            <select wire:model="form.conta_tipo_bancaria">
                                @foreach ($tiposBancarios as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('form.conta_tipo_bancaria') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--full">
                            <span>Titular</span>
                            <input type="text" wire:model="form.titular_nome" maxlength="120" data-erp-uppercase>
                        </label>
                        @error('form.titular_nome') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--full">
                            <span>CPF/CNPJ do titular</span>
                            <input type="text" wire:model="form.titular_documento" maxlength="20" inputmode="numeric">
                        </label>
                        @error('form.titular_documento') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <label class="erp-contas-caixa-modal__check">
                    <input type="checkbox" wire:model="form.ativo">
                    Conta ativa
                </label>
            </div>

            <div class="erp-contas-caixa-modal__footer">
                <button type="button" class="is-cancel" wire:click="closeForm">Cancelar</button>
                <button type="button" class="is-save" wire:click="saveContaCaixa">Gravar</button>
            </div>
        </div>
    </div>
@endif
