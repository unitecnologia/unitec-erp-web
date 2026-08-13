window.ErpComprasPrint = {
    openDanfe(url) {
        if (! url) {
            return;
        }

        document.getElementById('erp-compras-print-frame')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'erp-compras-print-frame';
        iframe.src = url;
        iframe.title = 'Impressão DANFE';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = [
            'position: fixed',
            'width: 0',
            'height: 0',
            'border: 0',
            'opacity: 0',
            'pointer-events: none',
            'left: -9999px',
            'top: -9999px',
        ].join(';');

        let cleanedUp = false;

        const cleanup = () => {
            if (cleanedUp) {
                return;
            }

            cleanedUp = true;
            iframe.remove();
            window.removeEventListener('message', onMessage);
        };

        const onMessage = (event) => {
            if (event.source !== iframe.contentWindow) {
                return;
            }

            if (event.data?.type === 'erp-compras-danfe-print-done') {
                cleanup();
            }
        };

        const fallbackToNewTab = () => {
            cleanup();

            const separator = url.includes('?') ? '&' : '?';
            window.open(`${url}${separator}auto=1`, '_blank', 'noopener');
        };

        const printFrame = () => {
            const frameWindow = iframe.contentWindow;

            if (! frameWindow) {
                fallbackToNewTab();

                return;
            }

            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (error) {
                fallbackToNewTab();
            }
        };

        window.addEventListener('message', onMessage);

        iframe.addEventListener('load', () => {
            window.setTimeout(printFrame, 150);
            window.setTimeout(cleanup, 120000);
        }, { once: true });

        iframe.addEventListener('error', fallbackToNewTab, { once: true });

        document.body.appendChild(iframe);
    },
};

/**
 * Enter no lançamento de compras.
 * - Foco imediato no próximo campo (sem esperar Livewire)
 * - Bloqueia blur/wire:model durante a navegação (evita remount que “prende” o Enter)
 * - Funciona via document capture OU onkeydown nativo no input
 */
window.ErpComprasLancEnter = {
    version: 'v3-enter-fix',
    navigateUntil: 0,
    pendingFocus: null,
    lastCommitKey: '',

    isNavigating() {
        return Date.now() < (this.navigateUntil || 0);
    },

    armNavigate(ms = 1500) {
        this.navigateUntil = Date.now() + ms;
    },

    parseBr(value) {
        const raw = String(value ?? '').trim();

        if (raw === '') {
            return 0;
        }

        if (raw.includes(',')) {
            return Number.parseFloat(raw.replace(/\./g, '').replace(',', '.')) || 0;
        }

        return Number.parseFloat(raw) || 0;
    },

    formatBr(num, decimals = 2) {
        const n = Number.isFinite(num) ? num : 0;
        const fixed = n.toFixed(decimals);
        const parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return parts.join(',');
    },

    findInput(col, index) {
        const modal = document.querySelector('.erp-compras-lancamento-modal');

        if (! modal) {
            return null;
        }

        return modal.querySelector(
            'input[data-erp-lanc-enter="' + col + '"][data-row-index="' + String(index) + '"]'
        );
    },

    proximo(col, rowIndex) {
        const existe = (c, i) => !! this.findInput(c, i);

        if (col === 'qtd') {
            return { col: 'mg_venda', index: rowIndex };
        }

        if (col === 'mg_venda') {
            return { col: 'venda', index: rowIndex };
        }

        if (col === 'venda') {
            if (existe('mg_venda', rowIndex + 1)) {
                return { col: 'mg_venda', index: rowIndex + 1 };
            }

            return existe('mg_atacado', 0) ? { col: 'mg_atacado', index: 0 } : null;
        }

        if (col === 'mg_atacado') {
            return { col: 'atacado', index: rowIndex };
        }

        if (col === 'atacado') {
            if (existe('mg_atacado', rowIndex + 1)) {
                return { col: 'mg_atacado', index: rowIndex + 1 };
            }

            return existe('mg_especial', 0) ? { col: 'mg_especial', index: 0 } : null;
        }

        if (col === 'mg_especial') {
            return { col: 'especial', index: rowIndex };
        }

        if (col === 'especial') {
            return existe('mg_especial', rowIndex + 1)
                ? { col: 'mg_especial', index: rowIndex + 1 }
                : null;
        }

        return null;
    },

    marcarLinha(input) {
        const tr = input && input.closest('tr');
        const body = tr && tr.closest('tbody');

        if (! body) {
            return;
        }

        body.querySelectorAll('.erp-compras-lancamento-modal__row--selected').forEach((row) => {
            row.classList.remove('erp-compras-lancamento-modal__row--selected');
        });
        tr.classList.add('erp-compras-lancamento-modal__row--selected');
    },

    setMaskedValue(input, formatted) {
        if (! input) {
            return;
        }

        input.value = formatted;

        if (! window.ErpMasks || ! input.dataset?.mask) {
            return;
        }

        try {
            window.ErpMasks.apply(input, {
                sync: false,
                allowEmptySync: true,
                thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
            });
        } catch (error) {
            // ignore
        }
    },

    /** Atualiza preço no DOM na hora (skipRender no servidor não remonta o input). */
    syncPrecoFromMargem(col, rowIndex, pctRaw) {
        const pct = this.parseBr(pctRaw);
        let base = 0;
        let targetCol = null;

        if (col === 'mg_venda') {
            targetCol = 'venda';
            const tr = this.findInput(col, rowIndex)?.closest('tr');
            const custoEl = tr?.querySelector('[data-erp-lanc-custo]');
            base = this.parseBr(custoEl?.getAttribute('data-erp-lanc-custo') ?? custoEl?.textContent);
        } else if (col === 'mg_atacado') {
            targetCol = 'atacado';
            base = this.parseBr(this.findInput('venda', rowIndex)?.value);
        } else if (col === 'mg_especial') {
            targetCol = 'especial';
            base = this.parseBr(this.findInput('venda', rowIndex)?.value);
        } else {
            return;
        }

        const preco = Math.round((base * (1 + (pct / 100))) * 100) / 100;
        this.setMaskedValue(this.findInput(targetCol, rowIndex), this.formatBr(preco));
    },

    focar(col, index) {
        const input = this.findInput(col, index);

        if (! input || input.disabled || input.readOnly) {
            return false;
        }

        this.marcarLinha(input);

        try {
            input.focus({ preventScroll: true });
            input.select();
        } catch (error) {
            try {
                input.focus();
                input.select();
            } catch (ignored) {
                return false;
            }
        }

        return document.activeElement === input;
    },

    finalizar(input) {
        if (! window.ErpMasks || ! input || ! input.dataset || ! input.dataset.mask) {
            return;
        }

        try {
            input.value = window.ErpMasks.finalizeMaskValue(input);
            window.ErpMasks.apply(input, {
                sync: false,
                allowEmptySync: true,
                thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
            });
        } catch (error) {
            // ignore
        }
    },

    livewire(el) {
        const root = el.closest('[wire\\:id]');

        if (! root || ! window.Livewire) {
            return null;
        }

        try {
            return window.Livewire.find(root.getAttribute('wire:id'));
        } catch (error) {
            return null;
        }
    },

    commit(el, col, rowIndex, value, next) {
        const key = [col, rowIndex, value, next?.col, next?.index].join('|');

        if (this.lastCommitKey === key && this.isNavigating()) {
            return;
        }

        this.lastCommitKey = key;

        const component = this.livewire(el);

        if (! component) {
            return;
        }

        try {
            if (col === 'qtd') {
                component.call('commitQtdAndGoNext', rowIndex, value);

                return;
            }

            component.call(
                'commitLancamentoPrecoAndGoNext',
                rowIndex,
                col,
                value,
                next ? next.col : col,
                next ? next.index : rowIndex,
            );
        } catch (error) {
            // foco já aplicado
        }
    },

    handleEnter(el) {
        if (! (el instanceof HTMLInputElement)) {
            return false;
        }

        if (! el.hasAttribute('data-erp-lanc-enter')) {
            return false;
        }

        if (! el.closest('.erp-compras-lancamento-modal')) {
            return false;
        }

        if (el.disabled) {
            return false;
        }

        el.removeAttribute('readonly');

        const col = el.getAttribute('data-erp-lanc-enter');
        const rowIndex = Number(el.getAttribute('data-row-index'));

        if (! col || Number.isNaN(rowIndex)) {
            return false;
        }

        // Armar ANTES de mudar o foco — bloqueia blur/Livewire
        this.armNavigate(1500);
        this.finalizar(el);

        const value = el.value;
        const next = this.proximo(col, rowIndex);

        if (col.startsWith('mg_')) {
            this.syncPrecoFromMargem(col, rowIndex, value);
        }

        this.pendingFocus = next;

        if (next) {
            this.focar(next.col, next.index);
            window.setTimeout(() => this.focar(next.col, next.index), 0);
            window.setTimeout(() => {
                if (this.pendingFocus && this.pendingFocus.col === next.col && this.pendingFocus.index === next.index) {
                    this.focar(next.col, next.index);
                }
            }, 40);
            window.setTimeout(() => {
                if (this.pendingFocus && this.pendingFocus.col === next.col && this.pendingFocus.index === next.index) {
                    this.focar(next.col, next.index);
                }
            }, 120);
        } else {
            try {
                el.select();
            } catch (error) {
                // ignore
            }
        }

        this.commit(el, col, rowIndex, value, next);

        return true;
    },

    onKeydown(event) {
        // v4: o foco no Enter fica no script inline da tela de compras;
        // o wire:keydown.enter grava no Livewire. Não interceptar aqui.
        if (window.__erpLancGridEnterV4) {
            return;
        }

        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        const el = event.target;

        if (! (el instanceof HTMLInputElement) || ! el.hasAttribute('data-erp-lanc-enter')) {
            return;
        }

        if (! el.closest('.erp-compras-lancamento-modal')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        this.handleEnter(el);
    },

    /** Blur manual (sem wire:model.blur) — grava se o usuário saiu sem Enter. */
    onFocusOut(event) {
        const el = event.target;

        if (! (el instanceof HTMLInputElement) || ! el.hasAttribute('data-erp-lanc-enter')) {
            return;
        }

        if (! el.closest('.erp-compras-lancamento-modal')) {
            return;
        }

        if (this.isNavigating()) {
            return;
        }

        if (el.disabled || el.readOnly) {
            return;
        }

        this.finalizar(el);

        const col = el.getAttribute('data-erp-lanc-enter');
        const rowIndex = Number(el.getAttribute('data-row-index'));

        if (! col || Number.isNaN(rowIndex)) {
            return;
        }

        if (col.startsWith('mg_')) {
            this.syncPrecoFromMargem(col, rowIndex, el.value);
        }

        this.commit(el, col, rowIndex, el.value, { col, index: rowIndex });
    },
};

function refreshErpComprasMasks(root) {
    if (! window.ErpMasks) {
        return;
    }

    const scope = root?.querySelector?.('.erp-compras-lancamento-modal, .erp-compras-page, .erp-compras')
        ?? document.querySelector('.erp-compras-lancamento-modal, .erp-compras-page, .erp-compras')
        ?? root
        ?? document;

    window.ErpMasks.init(scope);
}

function bindErpComprasLivewireEvents() {
    if (window.__erpComprasLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpComprasLivewireBound = true;

    window.Livewire.on('erp-compras-open-danfe', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.ErpComprasPrint.openDanfe(url);
        }
    });

    window.Livewire.on('erp-masks-refresh', () => {
        refreshErpComprasMasks(document);
    });

    window.Livewire.hook('morph.updated', ({ el }) => {
        if (el?.closest?.('.erp-compras-lancamento-modal, .erp-compras-page, .erp-compras')
            || el?.querySelector?.('.erp-compras-lancamento-modal, .erp-compras-page, .erp-compras')) {
            refreshErpComprasMasks(el);

            const pending = window.ErpComprasLancEnter.pendingFocus;

            if (pending && window.ErpComprasLancEnter.isNavigating()) {
                window.ErpComprasLancEnter.focar(pending.col, pending.index);
            }
        }
    });
}

function bindErpComprasLancEnter() {
    const version = window.ErpComprasLancEnter?.version || 'v0';

    if (window.__erpComprasLancEnterBound === version) {
        return;
    }

    if (typeof window.__erpComprasLancEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpComprasLancEnterKeydown, true);
    }

    if (typeof window.__erpComprasLancEnterFocusOut === 'function') {
        document.removeEventListener('focusout', window.__erpComprasLancEnterFocusOut, true);
    }

    window.__erpComprasLancEnterKeydown = (event) => {
        window.ErpComprasLancEnter.onKeydown(event);
    };

    window.__erpComprasLancEnterFocusOut = (event) => {
        window.ErpComprasLancEnter.onFocusOut(event);
    };

    document.addEventListener('keydown', window.__erpComprasLancEnterKeydown, true);
    document.addEventListener('focusout', window.__erpComprasLancEnterFocusOut, true);
    window.__erpComprasLancEnterBound = version;
}

document.addEventListener('livewire:init', bindErpComprasLivewireEvents);
document.addEventListener('DOMContentLoaded', () => {
    bindErpComprasLivewireEvents();
    bindErpComprasLancEnter();
    refreshErpComprasMasks(document);
});
document.addEventListener('livewire:navigated', () => {
    bindErpComprasLivewireEvents();
    bindErpComprasLancEnter();
    refreshErpComprasMasks(document);
});

bindErpComprasLancEnter();

if (window.Livewire) {
    bindErpComprasLivewireEvents();
}
