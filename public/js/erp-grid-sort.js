/**
 * Ordenação client-side de grades (sem requisição ao servidor).
 *
 * Opt-in por atributos:
 *   [data-erp-csort="chave-da-grade"]      container (ou a própria <table>)
 *   [data-erp-csort-scope="42"]            escopo do localStorage (ex.: usuário)
 *   [data-erp-csort-stripe="fi-striped"]   classe de zebra a recalcular após ordenar
 *   th[data-erp-csort-key][data-erp-csort-type="text|number|money|date|time|datetime"]
 *
 * A ordem original vem do servidor; após cada morph do Livewire a ordem salva
 * é reaplicada sobre as linhas novas.
 */
(function () {
    'use strict';

    if (window.ErpGridSort) {
        return;
    }

    const STORAGE_PREFIX = 'erp.gridSort.v1:';
    const IDX_ATTR = 'data-erp-csort-idx';
    const ACTIVE_CLASS = 'erp-csort-active';
    const EMPTY_VALUES = new Set(['', '—', '-', '–']);

    const collator = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' });

    function storageKey(grid) {
        const scope = grid.getAttribute('data-erp-csort-scope') || '0';

        return STORAGE_PREFIX + scope + ':' + grid.getAttribute('data-erp-csort');
    }

    function readState(grid) {
        try {
            const raw = window.localStorage.getItem(storageKey(grid));
            const state = raw ? JSON.parse(raw) : null;

            if (state && typeof state.key === 'string' && (state.dir === 'asc' || state.dir === 'desc')) {
                return state;
            }
        } catch (e) {
            // localStorage indisponível ou valor corrompido: segue sem ordenação.
        }

        return null;
    }

    function writeState(grid, state) {
        try {
            window.localStorage.setItem(storageKey(grid), JSON.stringify(state));
        } catch (e) {
            // Sem persistência; a ordenação continua valendo na sessão atual.
        }
    }

    function findTable(grid) {
        if (grid.tagName === 'TABLE') {
            return grid;
        }

        for (const table of grid.querySelectorAll('table')) {
            if (table.querySelector('thead th[data-erp-csort-key]')) {
                return table;
            }
        }

        return null;
    }

    function headerCells(table) {
        const row = table.tHead ? table.tHead.rows[table.tHead.rows.length - 1] : null;

        return row ? Array.from(row.cells) : [];
    }

    function bodyRows(table) {
        const tbody = table.tBodies[0];

        if (! tbody) {
            return [];
        }

        return Array.from(tbody.rows).filter((tr) => ! tr.querySelector('td[colspan]'));
    }

    function stampOriginalOrder(table, force) {
        const rows = bodyRows(table);
        let next = 0;

        if (! force) {
            rows.forEach((tr) => {
                const idx = Number(tr.getAttribute(IDX_ATTR));

                if (Number.isFinite(idx) && idx >= next) {
                    next = idx + 1;
                }
            });
        }

        rows.forEach((tr) => {
            if (force || ! tr.hasAttribute(IDX_ATTR)) {
                tr.setAttribute(IDX_ATTR, String(next++));
            }
        });
    }

    function cellText(td) {
        return td ? td.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function parseNumber(text) {
        const cleaned = text.replace(/[^\d,.\-]/g, '').replace(/\./g, '').replace(',', '.');
        const value = parseFloat(cleaned);

        return Number.isFinite(value) ? value : null;
    }

    function parseDate(text) {
        const m = text.match(/(\d{2})\/(\d{2})\/(\d{4})/);

        return m ? Number(m[3]) * 10000 + Number(m[2]) * 100 + Number(m[1]) : null;
    }

    function parseTime(text) {
        const m = text.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);

        return m ? Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3] || 0) : null;
    }

    function sortValue(text, type) {
        if (EMPTY_VALUES.has(text)) {
            return null;
        }

        switch (type) {
            case 'number':
            case 'money':
                return parseNumber(text);
            case 'date':
                return parseDate(text);
            case 'time':
                return parseTime(text);
            case 'datetime': {
                const date = parseDate(text);

                return date === null ? null : date * 100000 + (parseTime(text) ?? 0);
            }
            default:
                return text;
        }
    }

    function compareValues(a, b, type) {
        if (type === 'text') {
            return collator.compare(a, b);
        }

        return a - b;
    }

    function restripe(grid, rows) {
        const stripeClass = grid.getAttribute('data-erp-csort-stripe');

        if (! stripeClass) {
            return;
        }

        rows.forEach((tr, i) => tr.classList.toggle(stripeClass, i % 2 === 1));
    }

    function paintHeader(table, state) {
        headerCells(table).forEach((th) => {
            const active = !! state && th.getAttribute('data-erp-csort-key') === state.key;

            th.classList.toggle(ACTIVE_CLASS, active);

            if (active) {
                th.setAttribute('data-erp-csort-dir', state.dir);
                th.setAttribute('aria-sort', state.dir === 'asc' ? 'ascending' : 'descending');
            } else {
                th.removeAttribute('data-erp-csort-dir');

                if (th.hasAttribute('data-erp-csort-key')) {
                    th.removeAttribute('aria-sort');
                }
            }
        });
    }

    function apply(grid) {
        const table = findTable(grid);

        if (! table) {
            return;
        }

        const state = readState(grid);
        const headers = headerCells(table);
        const colIndex = state
            ? headers.findIndex((th) => th.getAttribute('data-erp-csort-key') === state.key)
            : -1;

        paintHeader(table, colIndex >= 0 ? state : null);

        if (colIndex < 0) {
            return;
        }

        stampOriginalOrder(table, false);

        const type = headers[colIndex].getAttribute('data-erp-csort-type') || 'text';
        const dir = state.dir === 'desc' ? -1 : 1;
        const rows = bodyRows(table);

        const entries = rows.map((tr) => ({
            tr,
            idx: Number(tr.getAttribute(IDX_ATTR)) || 0,
            value: sortValue(cellText(tr.cells[colIndex]), type),
        }));

        entries.sort((a, b) => {
            if (a.value === null || b.value === null) {
                if (a.value === b.value) {
                    return a.idx - b.idx;
                }

                return a.value === null ? 1 : -1;
            }

            return (dir * compareValues(a.value, b.value, type)) || (a.idx - b.idx);
        });

        const changed = entries.some((entry, i) => entry.tr !== rows[i]);

        if (changed) {
            const tbody = table.tBodies[0];
            const focused = document.activeElement;
            const fragment = document.createDocumentFragment();

            entries.forEach((entry) => fragment.appendChild(entry.tr));
            tbody.appendChild(fragment);

            // Mover o nó tira o foco do elemento dentro da linha; os campos fora da grade nem são tocados.
            if (focused instanceof HTMLElement && focused !== document.activeElement && tbody.contains(focused)) {
                focused.focus({ preventScroll: true });
            }
        }

        restripe(grid, entries.map((entry) => entry.tr));
    }

    function grids(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const found = Array.from(scope.querySelectorAll('[data-erp-csort]'));

        if (scope instanceof Element && scope.matches('[data-erp-csort]')) {
            found.unshift(scope);
        }

        return found;
    }

    function applyAll(root) {
        grids(root).forEach(apply);
    }

    function onHeaderClick(event) {
        const th = event.target instanceof Element
            ? event.target.closest('th[data-erp-csort-key]')
            : null;
        const grid = th ? th.closest('[data-erp-csort]') : null;

        if (! th || ! grid || event.button !== 0) {
            return;
        }

        const key = th.getAttribute('data-erp-csort-key');
        const current = readState(grid);
        const dir = current && current.key === key && current.dir === 'asc' ? 'desc' : 'asc';

        writeState(grid, { key, dir });
        apply(grid);
    }

    /** Linhas re-renderizadas voltam na ordem do servidor: re-marca e reordena. */
    function onMorphed({ el }) {
        if (! (el instanceof Element)) {
            return;
        }

        grids(el).forEach((grid) => {
            if (grid.closest('[wire\\:id]') !== el) {
                return;
            }

            const table = findTable(grid);

            if (table) {
                stampOriginalOrder(table, true);
            }

            apply(grid);
        });
    }

    let hooked = false;

    function hookLivewire() {
        if (hooked || ! window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        hooked = true;
        window.Livewire.hook('morphed', onMorphed);
    }

    document.addEventListener('click', onHeaderClick);
    document.addEventListener('livewire:init', () => {
        hookLivewire();
        applyAll(document);
    });
    document.addEventListener('livewire:navigated', () => applyAll(document));
    document.addEventListener('DOMContentLoaded', () => applyAll(document));

    hookLivewire();

    if (document.readyState !== 'loading') {
        applyAll(document);
    }

    window.ErpGridSort = { apply, applyAll };
})();
