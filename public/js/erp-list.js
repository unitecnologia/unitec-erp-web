window.__erpListConfigs = window.__erpListConfigs || [];

document.addEventListener('DOMContentLoaded', initErpListPages);
document.addEventListener('livewire:navigated', initErpListPages);

function initErpListPages() {
    const pending = window.__erpListConfigs || [];

    pending.forEach((config) => {
        initErpListPage(config);
    });

    window.__erpListConfigs = [];
}

function initErpListPage(config) {
    const pageClass = config.pageClass;

    if (! pageClass) {
        return;
    }

    const page = document.querySelector('.' + pageClass);

    if (! page) {
        return;
    }

    bindDoubleClickEdit(page, config);
    bindKeyboardShortcuts(page, config);

    if (typeof initErpDatepickers === 'function') {
        initErpDatepickers(page);
    }
}

function bindDoubleClickEdit(page, config) {
    if (page.dataset.erpListDblBound === '1') {
        return;
    }

    page.dataset.erpListDblBound = '1';

    page.addEventListener('dblclick', (event) => {
        const row = event.target.closest('.fi-ta-row');

        if (! isDataRow(page, row)) {
            return;
        }

        if (config.edit) {
            getLivewireComponent(page)?.call(config.edit);
        }
    });
}

function bindKeyboardShortcuts(page, config) {
    if (page.dataset.erpListKeysBound === '1') {
        return;
    }

    page.dataset.erpListKeysBound = '1';

    document.addEventListener('keydown', (event) => {
        const activePage = config.pageClass
            ? document.querySelector('.' + config.pageClass)
            : null;

        if (! activePage) {
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
