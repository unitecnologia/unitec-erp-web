/**
 * Pesquisa da listagem de produtos.
 * Digitar rápido espera 220 ms e manda 1 request só para a grade.
 * Enter dispara na hora. Resposta antiga é abortada e não redesenha a tabela.
 */
(function () {
    const DEBOUNCE_MS = 220;
    const TABLE_NAME = 'erp.product-list-table';
    const PARENT_NAME = 'app.filament.resources.product-resource.pages.list-products';

    if (window.__erpProdutosSearchBound === true) {
        return;
    }

    window.__erpProdutosSearchBound = true;

    let timer = null;
    let lastSent = null;
    let latestSeq = 0;
    let abortCtrl = null;

    function productTable() {
        const named = window.Livewire?.getByName?.(TABLE_NAME) || [];

        if (named[0]) {
            return named[0];
        }

        const el = document.querySelector('.erp-list-grid-area [wire\\:id]');
        const id = el?.getAttribute?.('wire:id');

        if (!id || !window.Livewire?.find) {
            return null;
        }

        return window.Livewire.find(id);
    }

    function rememberInitialValue() {
        if (lastSent !== null) {
            return;
        }

        const input = document.querySelector('[data-erp-produtos-search]');

        if (input) {
            lastSent = input.value;
        }
    }

    function syncUrl(term) {
        try {
            const url = new URL(window.location.href);

            if (term) {
                url.searchParams.set('q', term);
            } else {
                url.searchParams.delete('q');
            }

            window.history.replaceState(window.history.state, '', url);
        } catch (e) {}
    }

    function clearHighlight() {
        const parents = window.Livewire?.getByName?.(PARENT_NAME) || [];
        const parent = parents[0] || null;

        if (!parent || !parent.highlightedRecordId) {
            return;
        }

        try {
            parent.set('highlightedRecordId', null, false);
        } catch (e) {}
    }

    function submit(input) {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        if (!input || !input.isConnected) {
            return;
        }

        const value = input.value;

        if (value === lastSent) {
            return;
        }

        lastSent = value;
        const seq = ++latestSeq;
        const wire = productTable();

        syncUrl(value);
        clearHighlight();

        if (abortCtrl) {
            const previous = abortCtrl;
            abortCtrl = null;
            previous.abort();
        }

        if (!wire || typeof wire.applyLocalSearch !== 'function') {
            lastSent = null;

            return;
        }

        wire.applyLocalSearch(value, seq);
    }

    function schedule(input) {
        if (timer !== null) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            timer = null;
            submit(input);
        }, DEBOUNCE_MS);
    }

    window.__erpProdutosSearchSync = function (value) {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        lastSent = value;
        const input = document.querySelector('[data-erp-produtos-search]');

        if (input) {
            input.value = value;
        }
    };

    function searchSeqFromPayload(payload) {
        if (typeof payload !== 'string' || !payload.includes('applyLocalSearch')) {
            return null;
        }

        try {
            const data = JSON.parse(payload);
            const components = data.components || [];

            for (let i = 0; i < components.length; i++) {
                const calls = components[i].calls || [];

                for (let j = 0; j < calls.length; j++) {
                    if (calls[j].method === 'applyLocalSearch') {
                        return Number((calls[j].params || [])[1] ?? 0);
                    }
                }
            }
        } catch (e) {
            return null;
        }

        return null;
    }

    function registerAbort() {
        if (window.__erpProdutosSearchAbort === true || !window.Livewire?.hook) {
            return;
        }

        window.__erpProdutosSearchAbort = true;

        window.Livewire.hook('request', ({ options, payload, fail }) => {
            const seq = searchSeqFromPayload(payload);

            if (seq === null) {
                return;
            }

            abortCtrl = new AbortController();
            options.signal = abortCtrl.signal;

            fail(() => {
                if (seq !== latestSeq) {
                    return;
                }

                lastSent = null;
            });
        });
    }

    document.addEventListener('input', (event) => {
        const input = event.target?.closest?.('[data-erp-produtos-search]');

        if (!input) {
            return;
        }

        schedule(input);
    });

    document.addEventListener('keydown', (event) => {
        const input = event.target?.closest?.('[data-erp-produtos-search]');

        if (!input || event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        submit(input);
    }, true);

    function boot() {
        rememberInitialValue();
        registerAbort();
    }

    if (window.Livewire?.hook) {
        boot();
    } else {
        document.addEventListener('livewire:init', boot, { once: true });
    }

    document.addEventListener('livewire:navigated', () => {
        lastSent = null;
        rememberInitialValue();
        registerAbort();
    });
})();
