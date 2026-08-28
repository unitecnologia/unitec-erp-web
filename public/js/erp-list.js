window.__erpListConfigs = window.__erpListConfigs || [];
window.__erpListConfigByClass = window.__erpListConfigByClass || {};

document.addEventListener('DOMContentLoaded', () => window.initErpListPages());
document.addEventListener('livewire:navigated', () => {
    // Navigate pode disparar antes do script inline empilhar a config — adia um tick.
    queueMicrotask(() => window.initErpListPages());
    setTimeout(() => window.initErpListPages(), 0);
});

/**
 * Chamado pelo list-scripts (e pela fila) para registrar e ligar a lista na hora.
 */
window.registerErpListConfig = function registerErpListConfig(config) {
    if (! config || ! config.pageClass) {
        return;
    }

    window.__erpListConfigByClass[config.pageClass] = config;
    initErpListPage(config);
};

window.initErpListPages = function initErpListPages() {
    const pending = window.__erpListConfigs || [];

    pending.forEach((config) => {
        window.registerErpListConfig(config);
    });

    window.__erpListConfigs = [];

    // Re-liga páginas já conhecidas (ex.: navigate trocou o DOM e o dataset sumiu).
    Object.values(window.__erpListConfigByClass || {}).forEach((config) => {
        initErpListPage(config);
    });
};

function erpListClassTokens(pageClass) {
    return String(pageClass || '').trim().split(/\s+/).filter(Boolean);
}

function erpListPageSelector(pageClass) {
    const tokens = erpListClassTokens(pageClass);

    if (tokens.length === 0) {
        return '';
    }

    return '.' + tokens.join('.');
}

function erpListPageHasClass(page, pageClass) {
    const tokens = erpListClassTokens(pageClass);

    return tokens.length > 0 && tokens.every((token) => page?.classList?.contains(token));
}

function initErpListPage(config) {
    const pageClass = config.pageClass;

    if (! pageClass) {
        return;
    }

    const page = document.querySelector(erpListPageSelector(pageClass));

    if (! page) {
        return;
    }

    bindRowSelection(page, config);
    ensureKeyboardShortcutsBound();

    if (typeof initErpDatepickers === 'function') {
        initErpDatepickers(page);
    }
}

function bindRowSelection(page, config) {
    page.dataset.erpListDblEdit = config.doubleClickEdit === false ? '0' : '1';
    page.dataset.erpListPageClass = config.pageClass || '';

    if (page.dataset.erpListDblBound === '1') {
        return;
    }

    page.dataset.erpListDblBound = '1';

    // Destaque visual no cliente (highlightRecord usa skipRender e não remonta a grade).
    // Não competir com wire:click do olho/botões (capture dispara highlightRecord em paralelo).
    page.addEventListener('click', (event) => {
        if (isErpListInteractiveTarget(event.target)) {
            return;
        }

        const row = event.target.closest('.fi-ta-row');

        if (! isDataRow(page, row)) {
            return;
        }

        selectErpListRow(page, row);
        syncHighlightToLivewire(page, row);
    }, true);

    page.addEventListener('dblclick', (event) => {
        if (page.dataset.erpListDblEdit === '0') {
            return;
        }

        if (isErpListInteractiveTarget(event.target)) {
            return;
        }

        const row = event.target.closest('.fi-ta-row');

        if (! isDataRow(page, row)) {
            return;
        }

        const pageConfig = resolvePageConfig(page);

        if (! pageConfig?.edit) {
            return;
        }

        const component = getLivewireComponent(page);

        if (! component) {
            return;
        }

        selectErpListRow(page, row);

        const recordKey = getRecordKeyFromRow(row);

        if (recordKey) {
            Promise.resolve(component.call('highlightRecord', recordKey))
                .then(() => component.call(pageConfig.edit))
                .catch(() => component.call(pageConfig.edit));

            return;
        }

        component.call(pageConfig.edit);
    });
}

function isErpListInteractiveTarget(target) {
    if (! (target instanceof Element)) {
        return false;
    }

    // Controles reais (olho, actions, inputs). Célula/linha Filament NÃO é interativa:
    // button.fi-ta-col e wire:click de highlightRecord/mountTableAction são a seleção da linha.
    const el = target.closest([
        '.erp-compras__eye-btn',
        '.erp-orcamentos__eye-btn',
        '.erp-vendas__eye-btn',
        '.erp-caixa__eye-btn',
        '.fi-ta-actions',
        'input',
        'select',
        'textarea',
        'a[href]',
        'button',
        '[role="button"]',
        '[wire\\:click]',
    ].join(', '));

    if (! el) {
        return false;
    }

    if (el.classList.contains('fi-ta-col') || el.matches('.fi-ta-col, button.fi-ta-col')) {
        return false;
    }

    const wireClick = el.getAttribute('wire:click') || '';
    if (
        wireClick.includes('mountTableAction')
        || wireClick.includes('highlightRecord')
        || el.classList.contains('fi-ta-record-content')
    ) {
        return false;
    }

    return true;
}

function syncHighlightToLivewire(page, row) {
    const recordKey = getRecordKeyFromRow(row);

    if (! recordKey) {
        return;
    }

    const component = getLivewireComponent(page);

    if (! component) {
        return;
    }

    try {
        component.call('highlightRecord', recordKey);
    } catch (_) {
        // Filament recordAction ainda pode completar o highlight.
    }
}

function selectErpListRow(page, row) {
    page.querySelectorAll('.fi-ta-row.erp-row-selected').forEach((selected) => {
        selected.classList.remove('erp-row-selected');
    });

    row.classList.add('erp-row-selected');
}

function getRecordKeyFromRow(row) {
    if (! row) {
        return null;
    }

    const wireKey = row.getAttribute('wire:key') || '';
    const match = wireKey.match(/\.table\.records\.(.+)$/)
        || wireKey.match(/records\.([^.]+)$/)
        || wireKey.match(/record-([^-]+(?:-[^-]+)*)-/);

    if (match?.[1]) {
        return match[1];
    }

    const recordKey = row.getAttribute('data-record-key')
        || row.dataset?.recordKey
        || row.getAttribute('x-sortable-item');

    return recordKey || null;
}

function resolvePageConfig(page) {
    const pageClass = page?.dataset?.erpListPageClass
        || Object.keys(window.__erpListConfigByClass || {}).find((cls) => erpListPageHasClass(page, cls));

    if (! pageClass) {
        return null;
    }

    return window.__erpListConfigByClass[pageClass] || null;
}

function ensureKeyboardShortcutsBound() {
    if (window.__erpListKeysBound === true) {
        return;
    }

    window.__erpListKeysBound = true;

    document.addEventListener('keydown', (event) => {
        const activePage = document.querySelector('.erp-list-page');

        if (! activePage) {
            return;
        }

        const config = resolvePageConfig(activePage)
            || Object.values(window.__erpListConfigByClass || {}).find((cfg) => {
                return cfg.pageClass && erpListPageHasClass(activePage, cfg.pageClass);
            });

        if (! config) {
            return;
        }

        // Modal de lançamento / confirmação da NF-e e cadastro de Contador: atalhos da lista não devem rodar.
        if (document.querySelector('.erp-nfe-lancamento-modal, .erp-nfe-item-delete-modal, .erp-contador-form-modal, .erp-pagar-form-modal, .erp-receber-form-modal, .erp-aviso-modal, .erp-devcompra-lancamento-modal')) {
            return;
        }

        if (event.target.matches('input, textarea, select, [contenteditable="true"]')) {
            const searchFocusKey = config.searchFocusKey ?? 'F6';

            if (event.key !== searchFocusKey) {
                return;
            }
        }

        const component = getLivewireComponent(activePage);

        if (! component) {
            return;
        }

        if (event.key === 'Delete' && config.delete) {
            event.preventDefault();
            component.call(config.delete);

            return;
        }

        const searchFocusKey = config.searchFocusKey ?? 'F6';

        if (event.key === searchFocusKey && config.searchInput) {
            event.preventDefault();
            activePage.querySelector(config.searchInput)?.focus();

            return;
        }

        const extra = config.extraKeys?.[event.key];

        const defaultKeys = {
            F2: config.create,
            F3: config.edit,
            F5: config.refresh,
        };

        if (event.key === 'F5') {
            const printButton = activePage.querySelector('[data-erp-print-nota]');

            if (printButton) {
                event.preventDefault();
                printButton.click();

                return;
            }
        }

        const method = extra?.method
            ?? defaultKeys[event.key];

        if (! method) {
            return;
        }

        event.preventDefault();

        if (extra?.params) {
            component.call(method, ...extra.params);
        } else {
            component.call(method);
        }
    });
}

function isDataRow(page, row) {
    if (! row || ! page.contains(row)) {
        return false;
    }

    return ! row.classList.contains('fi-ta-row-not-reorderable')
        && ! row.classList.contains('fi-ta-group-header-row')
        && ! row.classList.contains('fi-ta-summary-row');
}

function getLivewireComponent(page) {
    const componentEl = page.closest('[wire\\:id]');

    if (! componentEl || ! window.Livewire) {
        return null;
    }

    return window.Livewire.find(componentEl.getAttribute('wire:id')) ?? null;
}

// Se este arquivo carregar DEPOIS do push inline (navigate a partir do dashboard),
// processa a fila imediatamente.
window.initErpListPages();
