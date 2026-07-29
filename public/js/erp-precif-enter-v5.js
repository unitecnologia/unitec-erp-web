/**
 * Precificação: Enter = Tab (próximo campo) + commit/cálculo no Enter e no blur.
 * Sem wire:model.blur — evita corrida que recalcula só na 1ª vez.
 */
(function () {
    const VERSION = 'v8-calc-commit';

    if (window.__erpPrecifEnterVersion === VERSION) {
        return;
    }

    window.__erpPrecifEnterVersion = VERSION;

    const ORDER = [
        'precif-compra',
        'precif-pct-custos',
        'precif-custos-rs',
        'precif-frete',
        'precif-frete-rs',
        'precif-outras-pct',
        'precif-outras',
        'precif-varejo-comissao',
        'precif-varejo-comissao-rs',
        'precif-varejo-desconto',
        'precif-varejo-desconto-rs',
        'precif-varejo-margem',
        'precif-varejo-praticado',
        'precif-atacado-comissao',
        'precif-atacado-comissao-rs',
        'precif-atacado-desconto',
        'precif-atacado-desconto-rs',
        'precif-atacado-margem',
        'precif-atacado-praticado',
        'precif-especial-comissao',
        'precif-especial-comissao-rs',
        'precif-especial-desconto',
        'precif-especial-desconto-rs',
        'precif-especial-margem',
        'precif-especial-praticado',
    ];

    function getModal() {
        return document.querySelector('.erp-prod-precificacao-modal');
    }

    function getWire() {
        if (! window.Livewire) {
            return null;
        }

        const modal = getModal();
        const fromModal = modal?.closest('[wire\\:id]');

        if (fromModal) {
            return window.Livewire.find(fromModal.getAttribute('wire:id'));
        }

        const page = document.querySelector('.erp-produtos-form-page');
        let root = page;

        while (root && ! root.getAttribute('wire:id')) {
            root = root.parentElement;
        }

        const wireId = root?.getAttribute('wire:id');

        return wireId ? window.Livewire.find(wireId) : null;
    }

    function clearFocusLock() {
        window.__erpPrecifFocusId = null;
        window.__erpPrecifFocusUntil = 0;

        if (window.__erpPrecifFocusTimer) {
            clearInterval(window.__erpPrecifFocusTimer);
            window.__erpPrecifFocusTimer = null;
        }
    }

    function armTypingGuard(el) {
        if (! el || el.dataset.erpPrecifTypingGuard === '1') {
            return;
        }

        el.dataset.erpPrecifTypingGuard = '1';

        const stop = () => {
            clearFocusLock();
            el.dataset.erpPrecifTypingGuard = '0';
            el.removeEventListener('keydown', stop);
            el.removeEventListener('input', stop);
            el.removeEventListener('beforeinput', stop);
        };

        el.addEventListener('keydown', stop);
        el.addEventListener('input', stop);
        el.addEventListener('beforeinput', stop);
    }

    function finalizeMask(input) {
        if (! window.ErpMasks || ! input?.dataset?.mask) {
            return input?.value ?? '';
        }

        input.value = window.ErpMasks.finalizeMaskValue(input);
        window.ErpMasks.apply(input, {
            allowEmptySync: true,
            live: true,
            thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
        });

        return input.value;
    }

    function skipBlurFor(fieldId, ms) {
        window.__erpPrecifSkipBlurFor = fieldId;
        window.__erpPrecifSkipBlurUntil = Date.now() + (ms || 1500);
    }

    function shouldSkipBlur(fieldId) {
        return window.__erpPrecifSkipBlurFor === fieldId
            && Date.now() < (window.__erpPrecifSkipBlurUntil || 0);
    }

    /**
     * Foco suave: select só 1x. Se o usuário digitar, cancela qualquer re-foco/re-select.
     */
    function focusById(fieldId, options = {}) {
        if (! fieldId) {
            return false;
        }

        const allowSelect = options.select !== false;

        clearFocusLock();
        window.__erpPrecifFocusId = fieldId;
        window.__erpPrecifFocusUntil = Date.now() + 600;

        let selectedOnce = false;

        const run = (doSelect) => {
            if (window.__erpPrecifFocusId !== fieldId) {
                return false;
            }

            const root = getModal();
            const el = root ? root.querySelector('#' + CSS.escape(fieldId)) : null;

            if (! el || el.disabled) {
                return false;
            }

            if (el.readOnly) {
                el.removeAttribute('readonly');
            }

            el.focus({ preventScroll: true });

            if (doSelect && allowSelect && ! selectedOnce && typeof el.select === 'function') {
                el.select();
                selectedOnce = true;
                armTypingGuard(el);
            }

            return document.activeElement === el;
        };

        run(true);

        // Poucos retries só se o morph roubar o foco — sem select de novo.
        [50, 120, 250].forEach((ms) => {
            setTimeout(() => {
                if (window.__erpPrecifFocusId !== fieldId) {
                    return;
                }

                if (document.activeElement?.id === fieldId) {
                    return;
                }

                run(false);
            }, ms);
        });

        return true;
    }

    window.__erpPrecifFocusById = focusById;

    function onKeyDown(event) {
        if (event.key !== 'Enter' || event.isComposing || event.repeat) {
            return;
        }

        const modal = getModal();

        if (! modal) {
            return;
        }

        const target = event.target;

        if (! (target instanceof HTMLInputElement) || ! modal.contains(target)) {
            return;
        }

        if (target.disabled) {
            return;
        }

        const fieldId = target.id || '';
        const index = ORDER.indexOf(fieldId);

        if (index < 0) {
            return;
        }

        if (target.readOnly) {
            target.removeAttribute('readonly');
        }

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        const value = finalizeMask(target);
        const nextId = ORDER[index + 1] || null;
        const wire = getWire();

        // Enter já comita/calcula — ignora o focusout que vem ao mudar o foco.
        skipBlurFor(fieldId, 1500);

        if (wire && typeof wire.call === 'function') {
            wire.call('precificacaoEnter', fieldId, value);
        } else if (nextId) {
            focusById(nextId, { select: true });
        }
    }

    function onFocusOut(event) {
        const modal = getModal();

        if (! modal) {
            return;
        }

        const target = event.target;

        if (! (target instanceof HTMLInputElement) || ! modal.contains(target)) {
            return;
        }

        if (target.disabled || target.readOnly) {
            return;
        }

        const fieldId = target.id || '';

        if (ORDER.indexOf(fieldId) < 0) {
            return;
        }

        if (shouldSkipBlur(fieldId)) {
            return;
        }

        const value = finalizeMask(target);
        const wire = getWire();

        if (wire && typeof wire.call === 'function') {
            wire.call('precificacaoCommitField', fieldId, value);
        }
    }

    document.addEventListener('keydown', onKeyDown, true);
    document.addEventListener('focusout', onFocusOut, true);

    document.addEventListener('livewire:init', () => {
        window.Livewire.on('erp-precif-focus', (event) => {
            const id = event?.id
                ?? (Array.isArray(event) ? event[0]?.id : null)
                ?? (typeof event === 'string' ? event : null);

            if (id) {
                focusById(id, { select: true });
            }
        });

        window.Livewire.hook('morph.updated', () => {
            const want = window.__erpPrecifFocusId;

            if (! want || Date.now() > (window.__erpPrecifFocusUntil || 0)) {
                return;
            }

            // Já digitando: não mexe.
            if (document.activeElement?.id === want) {
                return;
            }

            const root = getModal();
            const el = root ? root.querySelector('#' + CSS.escape(want)) : null;

            if (! el || el.disabled) {
                return;
            }

            if (el.readOnly) {
                el.removeAttribute('readonly');
            }

            el.focus({ preventScroll: true });
            // sem select() aqui — evita apagar o que o usuário começou a digitar
        });
    });
})();
