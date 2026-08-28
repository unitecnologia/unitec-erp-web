/**
 * Enter = Tab no lançamento avulso de Contas a Receber.
 * Capture para foco imediato; não usa stopImmediatePropagation.
 */
(function () {
    const VERSION = 'v1-receber-form-enter';

    if (window.__erpReceberFormEnterVersion === VERSION) {
        return;
    }

    if (typeof window.__erpReceberFormEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpReceberFormEnterKeydown, true);
    }

    window.__erpReceberFormEnterVersion = VERSION;

    function isLookupOpen(modal) {
        return !! modal.querySelector('.erp-receber-form-modal__cliente-lookup');
    }

    function unlock(el) {
        if (el instanceof HTMLElement) {
            el.removeAttribute('readonly');
        }
    }

    function finalizeMask(el) {
        if (! (el instanceof HTMLInputElement) || ! el.dataset.mask || ! window.ErpMasks) {
            return;
        }

        el.value = window.ErpMasks.finalizeMaskValue(el);
        window.ErpMasks.apply(el, {
            allowEmptySync: true,
            live: true,
            thousands: window.ErpMasks.isBrDecimalMask(el.dataset.mask),
        });
    }

    function isNavigable(el) {
        if (! (el instanceof HTMLElement)) {
            return false;
        }

        if (el.disabled || el.getAttribute('tabindex') === '-1') {
            return false;
        }

        if (el instanceof HTMLButtonElement) {
            return true;
        }

        return el instanceof HTMLInputElement || el instanceof HTMLSelectElement;
    }

    function navigableFields(modal) {
        return Array.from(modal.querySelectorAll('input, select, button')).filter(isNavigable);
    }

    function focusNext(modal, current) {
        const fields = navigableFields(modal);
        const index = fields.indexOf(current);
        const next = index >= 0 ? fields[index + 1] : fields[0];

        if (! next) {
            return;
        }

        unlock(next);
        next.focus();

        if (typeof next.select === 'function' && next instanceof HTMLInputElement && next.type !== 'date') {
            next.select();
        }
    }

    function onKeydown(event) {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        if (event.isComposing) {
            return;
        }

        const el = event.target;

        if (! (el instanceof HTMLElement)) {
            return;
        }

        const modal = el.closest('.erp-receber-form-modal');

        if (! modal) {
            return;
        }

        if (el instanceof HTMLButtonElement) {
            return;
        }

        if (isLookupOpen(modal)) {
            return;
        }

        unlock(el);
        event.preventDefault();
        finalizeMask(el);
        focusNext(modal, el);
    }

    window.__erpReceberFormEnterKeydown = onKeydown;
    document.addEventListener('keydown', onKeydown, true);
})();
