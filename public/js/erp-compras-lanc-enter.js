/**
 * Enter na grade do lançamento de compras — foco imediato no próximo campo.
 * Também navega Frete → Seguro → Outras nos totais.
 * Carregado pela página de compras (head-assets). Independente do Alpine.
 */
(function () {
    const VERSION = 'v9-qtd-alpine';
    const TOTAIS_ORDER = ['frete', 'seguro', 'outras'];

    if (window.__erpLancGridEnterVersion === VERSION) {
        return;
    }

    if (typeof window.__erpLancGridEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpLancGridEnterKeydown, true);
    }

    window.__erpLancGridEnterVersion = VERSION;
    window.__erpLancGridEnterV5 = true;
    window.__erpLancGridEnterV4 = true;
    window.__erpLancFocusUntil = 0;

    function findInput(modal, col, index) {
        return modal.querySelector(
            'input[data-erp-lanc-enter="' + col + '"][data-row-index="' + String(index) + '"]'
        );
    }

    function findTotaisInput(modal, key) {
        return modal.querySelector('input[data-erp-lanc-totais="' + key + '"]');
    }

    function proximo(modal, col, rowIndex) {
        const existe = (c, i) => !! findInput(modal, c, i);

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
    }

    function focar(modal, col, index) {
        const input = findInput(modal, col, index);

        if (! input || input.disabled) {
            return false;
        }

        input.removeAttribute('readonly');

        const tr = input.closest('tr');
        const body = tr && tr.closest('tbody');

        if (body) {
            body.querySelectorAll('.erp-compras-lancamento-modal__row--selected').forEach((row) => {
                row.classList.remove('erp-compras-lancamento-modal__row--selected');
            });
            tr.classList.add('erp-compras-lancamento-modal__row--selected');
        }

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

        return true;
    }

    function focarTotais(modal, key) {
        const input = findTotaisInput(modal, key);

        if (! input || input.disabled) {
            return false;
        }

        input.removeAttribute('readonly');

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

        return true;
    }

    function finalizeMask(el) {
        if (! (window.ErpMasks && el.dataset && el.dataset.mask)) {
            return;
        }

        try {
            el.value = window.ErpMasks.finalizeMaskValue(el);
            window.ErpMasks.apply(el, {
                sync: false,
                allowEmptySync: true,
                thousands: window.ErpMasks.isBrDecimalMask(el.dataset.mask),
            });
        } catch (error) {
            // ignore
        }
    }

    function onKeydown(event) {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        const el = event.target;

        if (! (el instanceof HTMLInputElement)) {
            return;
        }

        const modal = el.closest('.erp-compras-lancamento-modal');

        if (! modal || el.disabled) {
            return;
        }

        // Totais: Frete → Seguro → Outras
        if (el.hasAttribute('data-erp-lanc-totais')) {
            el.removeAttribute('readonly');
            event.preventDefault();

            const key = el.getAttribute('data-erp-lanc-totais');
            const idx = TOTAIS_ORDER.indexOf(key);

            if (idx < 0) {
                return;
            }

            window.__erpLancFocusUntil = Date.now() + 2000;
            finalizeMask(el);

            const nextKey = TOTAIS_ORDER[idx + 1];

            if (! nextKey) {
                return;
            }

            focarTotais(modal, nextKey);
            window.setTimeout(() => focarTotais(modal, nextKey), 0);
            window.setTimeout(() => focarTotais(modal, nextKey), 60);
            window.setTimeout(() => focarTotais(modal, nextKey), 160);
            window.setTimeout(() => focarTotais(modal, nextKey), 320);

            return;
        }

        if (! el.hasAttribute('data-erp-lanc-enter')) {
            return;
        }

        // autofill do ERP usa readonly até a 1ª tecla — Enter chega no capture
        // antes do unlock; remover readonly e navegar.
        el.removeAttribute('readonly');

        // preventDefault evita submit; NÃO usa stopImmediatePropagation
        // para o wire:keydown.enter do Livewire ainda gravar o valor.
        event.preventDefault();

        const col = el.getAttribute('data-erp-lanc-enter');
        const rowIndex = Number(el.getAttribute('data-row-index'));

        if (! col || Number.isNaN(rowIndex)) {
            return;
        }

        window.__erpLancFocusUntil = Date.now() + 2000;
        finalizeMask(el);

        const next = proximo(modal, col, rowIndex);

        // Qtd: o commit é no Alpine $wire do input (capture).
        // Aqui só marca navegação; o foco volta via erp-lanc-focus após remount.
        if (col === 'qtd') {
            return;
        }

        if (! next) {
            return;
        }

        focar(modal, next.col, next.index);
        window.setTimeout(() => focar(modal, next.col, next.index), 0);
        window.setTimeout(() => focar(modal, next.col, next.index), 60);
        window.setTimeout(() => focar(modal, next.col, next.index), 160);
        window.setTimeout(() => focar(modal, next.col, next.index), 320);
    }

    window.__erpLancGridEnterKeydown = onKeydown;
    document.addEventListener('keydown', onKeydown, true);

    window.ErpComprasLancEnter = window.ErpComprasLancEnter || {};
    window.ErpComprasLancEnter.isNavigating = function () {
        return Date.now() < (window.__erpLancFocusUntil || 0);
    };
    window.ErpComprasLancEnter.version = VERSION;
})();
