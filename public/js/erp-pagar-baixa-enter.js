/**
 * ENTER = próximo campo no modal Baixar — Contas a Pagar.
 * Capture para foco imediato; não usa stopImmediatePropagation (blur Livewire).
 * ENTER em Pago em não grava, não limpa a data e não muda o foco.
 * Salvar: F5 / botão Salvar.
 */
(function () {
    const VERSION = 'v6-pagar-baixa-enter-pago-em';

    if (window.__erpPagarBaixaEnterVersion === VERSION) {
        return;
    }

    if (typeof window.__erpPagarBaixaEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpPagarBaixaEnterKeydown, true);
    }

    window.__erpPagarBaixaEnterVersion = VERSION;

    function modalAberto(modal) {
        if (! modal) {
            return false;
        }

        if (modal.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        const display = modal.style?.display || '';
        if (display === 'none') {
            return false;
        }

        return true;
    }

    function unlock(el) {
        if (! (el instanceof HTMLElement)) {
            return;
        }

        if (el.disabled || el.classList.contains('erp-pagar-baixa-modal__input--ro')) {
            return;
        }

        el.removeAttribute('readonly');
    }

    function isVisible(el) {
        if (! (el instanceof HTMLElement)) {
            return false;
        }

        if (el.type === 'hidden') {
            return false;
        }

        if (el.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        if (el.offsetParent === null && el.getClientRects().length === 0) {
            return false;
        }

        return true;
    }

    function isNavigable(el) {
        if (! (el instanceof HTMLInputElement) && ! (el instanceof HTMLSelectElement)) {
            return false;
        }

        if (el.disabled || el.getAttribute('tabindex') === '-1') {
            return false;
        }

        if (el.classList.contains('erp-pagar-baixa-modal__input--ro')) {
            return false;
        }

        return isVisible(el);
    }

    function isDateField(el) {
        return el instanceof HTMLInputElement
            && (el.type === 'date' || el.getAttribute('data-erp-pagar-baixa-field') === 'pago-em');
    }

    function navigableFields(modal) {
        return Array.from(modal.querySelectorAll('input, select')).filter(isNavigable);
    }

    function fieldKey(el) {
        if (! (el instanceof HTMLElement)) {
            return '';
        }

        return el.getAttribute('data-erp-pagar-baixa-field') || '';
    }

    function restorePending() {
        const pending = window.__erpPagarBaixaEnterPending;
        if (! pending || pending.save || ! pending.field) {
            return;
        }

        const modal = document.querySelector('.erp-pagar-baixa-modal');
        if (! modalAberto(modal)) {
            window.__erpPagarBaixaEnterPending = null;
            return;
        }

        const next = modal.querySelector('[data-erp-pagar-baixa-field="' + pending.field + '"]');
        if (! (next instanceof HTMLElement) || ! isNavigable(next)) {
            return;
        }

        unlock(next);
        next.focus();

        if (typeof next.select === 'function' && next instanceof HTMLInputElement && next.type !== 'date' && next.type !== 'number') {
            next.select();
        }

        if (! pending.sticky) {
            window.__erpPagarBaixaEnterPending = null;
        }
    }

    window.__erpPagarBaixaEnterRestore = restorePending;

    function bindLivewireRestore() {
        if (window.__erpPagarBaixaEnterHook) {
            return;
        }

        if (! window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        window.__erpPagarBaixaEnterHook = true;

        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                queueMicrotask(() => {
                    if (typeof window.__erpPagarBaixaEnterRestore === 'function') {
                        window.__erpPagarBaixaEnterRestore();
                    }
                });
            });
        });
    }

    function commitField(el) {
        if (! (el instanceof HTMLElement) || isDateField(el)) {
            return;
        }

        unlock(el);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
        el.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
    }

    function scheduleFocus(next) {
        const key = fieldKey(next);
        window.__erpPagarBaixaEnterPending = { field: key };

        if (isDateField(next)) {
            window.__erpPagarBaixaEnterPending = { field: key, sticky: true };
            [200, 400, 650].forEach((ms) => {
                window.setTimeout(() => {
                    const pending = window.__erpPagarBaixaEnterPending;
                    if (! pending || pending.field !== key) {
                        return;
                    }

                    restorePending();

                    if (ms >= 650) {
                        window.__erpPagarBaixaEnterPending = null;
                    }
                }, ms);
            });

            return;
        }

        unlock(next);
        next.focus();

        if (typeof next.select === 'function' && next instanceof HTMLInputElement && next.type !== 'number') {
            next.select();
        }
    }

    function focusSibling(modal, current, direction) {
        const fields = navigableFields(modal);
        const index = fields.indexOf(current);

        if (index < 0) {
            return false;
        }

        const next = fields[index + direction];
        if (! next) {
            return false;
        }

        commitField(current);
        scheduleFocus(next);

        return true;
    }

    function onKeydown(event) {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        if (event.isComposing || event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }

        const modal = target.closest('.erp-pagar-baixa-modal');
        if (! modalAberto(modal)) {
            return;
        }

        if (target instanceof HTMLButtonElement) {
            return;
        }

        if (! (target instanceof HTMLInputElement) && ! (target instanceof HTMLSelectElement)) {
            return;
        }

        unlock(target);

        if (! isNavigable(target)) {
            return;
        }

        event.preventDefault();

        if (isDateField(target)) {
            return;
        }

        if (event.shiftKey) {
            focusSibling(modal, target, -1);
            return;
        }

        focusSibling(modal, target, 1);
    }

    window.__erpPagarBaixaEnterKeydown = onKeydown;
    document.addEventListener('keydown', onKeydown, true);

    if (window.Livewire) {
        bindLivewireRestore();
    } else {
        document.addEventListener('livewire:init', bindLivewireRestore, { once: true });
    }
})();
