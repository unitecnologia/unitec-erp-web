<div
    class="erp-orcamentos-window"
    x-data
    x-on:orc-focus-cliente.window="$nextTick(() => { const el = document.getElementById('orc-cliente'); el?.focus(); el?.select?.(); })"
    x-on:orc-focus-cliente-end.window="$nextTick(() => {
        const place = () => {
            const el = document.getElementById('orc-cliente');
            if (!el || el.disabled) return;
            el.removeAttribute('readonly');
            el.focus();
            const len = (el.value || '').length;
            try { el.setSelectionRange(len, len); } catch (e) {}
        };
        place();
        requestAnimationFrame(place);
        setTimeout(place, 40);
    })"
    x-on:orc-focus-field.window="$nextTick(() => {
        const id = $event.detail?.id ?? $event.detail?.[0];
        if (!id) return;
        const focusEl = () => {
            const el = document.getElementById(id);
            if (!el || el.disabled) return;
            el.removeAttribute('readonly');
            el.focus();
            if (typeof el.select === 'function' && el.tagName === 'INPUT') {
                el.select();
            }
        };
        focusEl();
        requestAnimationFrame(focusEl);
        setTimeout(focusEl, 50);
        setTimeout(focusEl, 150);
    })"
    x-on:orc-focus-barcode.window="$nextTick(() => { const el = document.getElementById('orc-prod-barcode'); el?.focus(); el?.select?.(); })"
    x-on:orc-focus-qtd.window="$nextTick(() => { const el = document.getElementById('orc-prod-qtd'); el?.focus(); el?.select?.(); })"
    x-on:orc-focus-preco.window="$nextTick(() => { const el = document.getElementById('orc-prod-preco'); el?.focus(); el?.select?.(); })"
    x-on:keydown.window="
        if ($event.ctrlKey && ($event.key === 'd' || $event.key === 'D') && !$wire.descontoModalOpen) {
            const t = $event.target;
            const inField = t?.closest?.('input, textarea, select');
            if (!inField) {
                $event.preventDefault();
                $wire.abrirModalDescontoItem();
            }
        }
    "
>
    <header class="erp-orcamentos-window__titlebar">
        <span class="erp-orcamentos-window__title">Lançamento de Orçamento</span>
        <button
            type="button"
            class="erp-orcamentos-window__close"
            wire:click="handleOrcamentoFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-orcamentos-window__body">
        @include('filament.components.erp.orcamentos.form.shell')
        @include('filament.components.erp.orcamentos.form.totals')
        @include('filament.components.erp.orcamentos.form.action-bar')
    </div>

    @if ($this->overlayProductOpen)
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Produtos',
            'iframeUrl' => $this->productOverlayUrl,
            'closeAction' => 'closeProductOverlay',
        ])
    @endif

    @if ($this->overlayPersonOpen)
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Clientes',
            'iframeUrl' => $this->personOverlayUrl,
            'closeAction' => 'closePersonOverlay',
        ])
    @endif

    @include('filament.components.erp.orcamentos.form.post-save-prompt')
    @include('filament.components.erp.orcamentos.form.item-delete-confirm')
    @include('filament.components.erp.orcamentos.form.desconto-item')
</div>
