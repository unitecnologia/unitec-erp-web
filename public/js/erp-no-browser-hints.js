(function initErpNoBrowserHints() {
    const IGNORE_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image']);

    function hardenElement(element) {
        if (! element || element.nodeType !== 1) {
            return;
        }

        if (element.matches('form')) {
            element.setAttribute('autocomplete', 'off');
            element.setAttribute('data-erp-no-autofill', 'true');
        }

        if (! element.matches('input, textarea, select')) {
            return;
        }

        if (element instanceof HTMLInputElement && IGNORE_TYPES.has(element.type)) {
            return;
        }

        if (element.dataset.erpNoAutofill === '1') {
            return;
        }

        element.dataset.erpNoAutofill = '1';
        element.setAttribute('autocomplete', 'off');
        element.setAttribute('autocapitalize', 'off');
        element.setAttribute('autocorrect', 'off');
        element.setAttribute('spellcheck', 'false');
        element.setAttribute('data-lpignore', 'true');
        element.setAttribute('data-1p-ignore', 'true');
        element.setAttribute('data-form-type', 'other');
        element.setAttribute('data-bwignore', 'true');

        if (element instanceof HTMLInputElement && element.type === 'password') {
            element.setAttribute('readonly', 'readonly');

            const unlock = () => {
                element.removeAttribute('readonly');
            };

            element.addEventListener('focus', unlock);
            element.addEventListener('mousedown', unlock);
            element.addEventListener('touchstart', unlock, { passive: true });
        }
    }

    function scan(root) {
        if (! root || root.nodeType !== 1) {
            return;
        }

        if (root.matches('form, input, textarea, select')) {
            hardenElement(root);
        }

        root.querySelectorAll('form, input, textarea, select').forEach(hardenElement);
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
})();
