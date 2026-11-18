/**
 * Enter = próximo campo / Shift+Enter = anterior no modal Contas a Pagar.
 * Lookup do fornecedor: ENTER confirma a opção; depois o foco vai para Vencimento.
 * Capture para foco imediato; não usa stopImmediatePropagation (máscara month-year).
 */
(function () {
    const VERSION = 'v3-pagar-form-enter-vencimento';

    if (window.__erpPagarFormEnterVersion === VERSION) {
        return;
    }

    if (typeof window.__erpPagarFormEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpPagarFormEnterKeydown, true);
    }

    if (typeof window.__erpPagarFormEnterOffConfirmado === 'function') {
        window.__erpPagarFormEnterOffConfirmado();
        window.__erpPagarFormEnterOffConfirmado = null;
    }

    window.__erpPagarFormEnterVersion = VERSION;

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

    function isLookupOpen(modal) {
        return !! modal.querySelector('.erp-pagar-form-modal__fornecedor-lookup');
    }

    function isFornecedorBusca(el) {
        return el instanceof HTMLInputElement
            && el.hasAttribute('data-erp-pagar-fornecedor-busca');
    }

    function unlock(el) {
        if (! (el instanceof HTMLElement)) {
            return;
        }

        if (el.disabled || el.classList.contains('erp-pagar-form-modal__input--ro')) {
            return;
        }

        el.removeAttribute('readonly');
    }

    function finalizeMask(el) {
        if (! (el instanceof HTMLInputElement) || ! el.dataset.mask || ! window.ErpMasks) {
            return;
        }

        if (typeof window.ErpMasks.finalizeMaskValue === 'function') {
            el.value = window.ErpMasks.finalizeMaskValue(el);
        }

        window.ErpMasks.apply(el, {
            allowEmptySync: true,
            live: true,
            thousands: window.ErpMasks.isBrDecimalMask?.(el.dataset.mask) === true,
        });
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

        if (el.classList.contains('erp-pagar-form-modal__input--ro')) {
            return false;
        }

        return isVisible(el);
    }

    function navigableFields(modal) {
        return Array.from(modal.querySelectorAll('input, select')).filter(isNavigable);
    }

    function resolveControl(el) {
        if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement) {
            return el;
        }

        if (! (el instanceof Element)) {
            return el;
        }

        const dateWrap = el.closest('.erp-date-field');
        const dateInput = dateWrap?.querySelector('input');
        if (dateInput instanceof HTMLInputElement) {
            return dateInput;
        }

        return el;
    }

    function dateInputFromOpenCalendar(modal) {
        if (! modal) {
            return null;
        }

        const dates = modal.querySelectorAll('input.erp-date-input, input[type="date"]');
        for (let i = 0; i < dates.length; i += 1) {
            const input = dates[i];
            if (input._flatpickr?.isOpen) {
                return input;
            }
        }

        return null;
    }

    function focusSibling(modal, current, direction) {
        const fields = navigableFields(modal);
        const resolved = resolveControl(current);
        const index = fields.indexOf(resolved);

        if (index < 0) {
            return;
        }

        const next = fields[index + direction];
        if (! next) {
            return;
        }

        unlock(next);
        next.focus();

        if (typeof next.select === 'function' && next instanceof HTMLInputElement && next.type !== 'date' && next.type !== 'number') {
            next.select();
        }
    }

    function focusAposFornecedor() {
        const modal = document.getElementById('erp-pagar-form-modal');
        if (! modalAberto(modal)) {
            return;
        }

        const busca = modal.querySelector('[data-erp-pagar-fornecedor-busca]');
        if (! busca) {
            return;
        }

        focusSibling(modal, busca, 1);
    }

    function onKeydown(event) {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        if (event.isComposing || event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        let el = event.target;
        if (! (el instanceof Element)) {
            return;
        }

        let modal = el.closest('.erp-pagar-form-modal');
        if (! modalAberto(modal) && el.closest('.flatpickr-calendar')) {
            modal = document.getElementById('erp-pagar-form-modal');
            el = dateInputFromOpenCalendar(modal) || el;
        }

        if (! modalAberto(modal)) {
            return;
        }

        el = resolveControl(el);

        if (el instanceof HTMLButtonElement) {
            return;
        }

        if (! (el instanceof HTMLInputElement) && ! (el instanceof HTMLSelectElement)) {
            return;
        }

        // Lookup aberto no campo Fornecedor: ENTER confirma (wire:keydown.enter).
        if (! event.shiftKey && isFornecedorBusca(el) && isLookupOpen(modal)) {
            return;
        }

        unlock(el);
        event.preventDefault();

        if (el._flatpickr?.isOpen) {
            el._flatpickr.close();
        }

        finalizeMask(el);

        if (event.shiftKey) {
            focusSibling(modal, el, -1);
        } else {
            focusSibling(modal, el, 1);
        }
    }

    function bindConfirmado() {
        if (! window.Livewire || typeof window.Livewire.on !== 'function') {
            return;
        }

        window.__erpPagarFormEnterOffConfirmado = window.Livewire.on('erp-pagar-fornecedor-confirmado', () => {
            requestAnimationFrame(focusAposFornecedor);
        });
    }

    window.__erpPagarFormEnterKeydown = onKeydown;
    document.addEventListener('keydown', onKeydown, true);

    if (window.Livewire) {
        bindConfirmado();
    } else {
        document.addEventListener('livewire:init', bindConfirmado, { once: true });
    }
})();
