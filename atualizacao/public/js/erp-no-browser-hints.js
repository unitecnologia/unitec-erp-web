/**
 * Bloqueia autofill, histórico de valores e popups do Chrome/Edge em todo o ERP.
 *
 * O popup preto (1,00 / 10,00…) é histórico nativo do navegador — não é tela do sistema.
 * Estratégia: autocomplete one-time-code + name aleatório + readonly até interação.
 *
 * Opt-out pontual: data-erp-allow-browser-hints="1" no campo ou ancestral.
 */
(function initErpNoBrowserHints() {
    const VERSION = 'v3-global';

    if (window.__erpNoBrowserHintsVersion === VERSION) {
        return;
    }

    window.__erpNoBrowserHintsVersion = VERSION;

    const IGNORE_INPUT_TYPES = new Set([
        'hidden', 'submit', 'button', 'reset', 'image', 'checkbox', 'radio', 'file', 'range', 'color',
    ]);

    function randomToken() {
        return 'erp-' + Math.random().toString(36).slice(2, 10);
    }

    function allowsBrowserHints(element) {
        return Boolean(element?.closest?.('[data-erp-allow-browser-hints="1"]'));
    }

    function fieldSignature(element) {
        return [
            element.name || '',
            element.id || '',
            element.getAttribute('wire:model') || '',
            element.getAttribute('wire:model.live') || '',
            element.getAttribute('wire:model.blur') || '',
            element.getAttribute('wire:model.live.debounce.200ms') || '',
            element.getAttribute('wire:model.live.debounce.250ms') || '',
            element.getAttribute('placeholder') || '',
            element.className || '',
            element.closest('label')?.textContent || '',
        ].join(' ');
    }

    function isProductNameSearch(element) {
        if (! (element instanceof HTMLInputElement)) {
            return false;
        }

        const signature = fieldSignature(element);

        return /nome|descricao|descri[cç][aã]o|produto|fv-tv-barcode|erp-fv-tv__input--barcode/i.test(signature)
            || element.id === 'fv-tv-barcode'
            || element.classList.contains('erp-fv-tv__input--barcode');
    }

    function hardenForm(element) {
        if (! element?.matches?.('form')) {
            return;
        }

        element.setAttribute('autocomplete', 'off');
        element.setAttribute('data-erp-no-autofill', 'true');
        element.setAttribute('data-lpignore', 'true');
        element.setAttribute('data-1p-ignore', 'true');
        element.setAttribute('data-bwignore', 'true');
    }

    function hardenField(element) {
        if (! element || element.nodeType !== 1 || allowsBrowserHints(element)) {
            return;
        }

        if (element.matches('form')) {
            hardenForm(element);

            return;
        }

        if (! element.matches('input, textarea, select')) {
            return;
        }

        if (element instanceof HTMLInputElement && IGNORE_INPUT_TYPES.has(element.type)) {
            return;
        }

        if (element instanceof HTMLSelectElement) {
            element.setAttribute('autocomplete', 'off');
            element.setAttribute('data-erp-no-autofill', '1');

            return;
        }

        if (element instanceof HTMLInputElement && element.type === 'email') {
            element.type = 'text';
            element.setAttribute('inputmode', 'email');
        }

        const isFocused = document.activeElement === element;
        const productSearch = isProductNameSearch(element);

        // one-time-code: Chrome/Edge respeitam melhor que "off" para histórico de valores.
        element.setAttribute('autocomplete', 'one-time-code');
        element.setAttribute('autocorrect', 'off');
        element.setAttribute('autocapitalize', productSearch ? 'characters' : 'off');
        element.setAttribute('spellcheck', 'false');
        element.setAttribute('data-lpignore', 'true');
        element.setAttribute('data-1p-ignore', 'true');
        element.setAttribute('data-form-type', 'other');
        element.setAttribute('data-bwignore', 'true');
        element.setAttribute('data-google-password-manager', 'ignore');
        element.setAttribute('data-erp-no-autofill', '1');

        if (element.dataset.erpNoAutofillName !== '1') {
            element.setAttribute('name', randomToken());
            element.dataset.erpNoAutofillName = '1';
        }

        if (element instanceof HTMLInputElement) {
            const mask = element.dataset?.mask || '';

            if (mask === 'money-br' || mask === 'percent-br' || mask === 'qty-br') {
                element.setAttribute('inputmode', 'decimal');
            } else if (mask === 'integer') {
                element.setAttribute('inputmode', 'numeric');
            } else if (productSearch) {
                element.removeAttribute('inputmode');

                if (element.getAttribute('role') !== 'combobox') {
                    element.setAttribute('role', 'combobox');
                }
            } else if (! element.hasAttribute('inputmode')) {
                element.setAttribute('role', 'presentation');
            }
        }

        if (element.dataset.erpNoAutofillBound === '1') {
            if (isFocused) {
                element.removeAttribute('readonly');
            }

            return;
        }

        element.dataset.erpNoAutofillBound = '1';

        if (! element.hasAttribute('readonly') && ! isFocused) {
            element.setAttribute('readonly', 'readonly');
        }

        const unlock = () => {
            element.removeAttribute('readonly');
        };

        element.addEventListener('keydown', unlock, { passive: true });
        element.addEventListener('pointerdown', unlock, { passive: true });
        element.addEventListener('mousedown', unlock);
        element.addEventListener('touchstart', unlock, { passive: true });
        element.addEventListener('paste', unlock);
        element.addEventListener('focus', unlock);
    }

    function scan(root) {
        if (! root || root.nodeType !== 1) {
            return;
        }

        if (root.matches('form, input, textarea, select')) {
            hardenField(root);
        }

        root.querySelectorAll('form, input, textarea, select').forEach(hardenField);
    }

    function run() {
        scan(document.documentElement);
    }

    let scheduled = false;

    function scheduleScan(root) {
        if (root && root !== document.documentElement) {
            scan(root);
        }

        if (scheduled) {
            return;
        }

        scheduled = true;

        window.requestAnimationFrame(() => {
            scheduled = false;
            run();
        });
    }

    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('livewire:navigated', run);

    document.addEventListener('livewire:init', () => {
        if (! window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('morph.updated', ({ el }) => {
            scheduleScan(el);
        });

        window.Livewire.hook('element.updated', ({ el }) => {
            scheduleScan(el);
        });
    });

    if (! window.__erpNoBrowserHintsObserver) {
        window.__erpNoBrowserHintsObserver = true;

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        scheduleScan(node);
                    }
                }
            }
        });

        const startObserver = () => {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
                run();
            }
        };

        if (document.body) {
            startObserver();
        } else {
            document.addEventListener('DOMContentLoaded', startObserver);
        }
    }

    run();
})();
