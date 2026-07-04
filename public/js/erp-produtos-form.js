document.addEventListener('DOMContentLoaded', initErpProdutosForm);
document.addEventListener('livewire:navigated', initErpProdutosForm);

const ERP_PRODUCT_LOOKUP_FIELDS = {
    'pprod-marca': 'marca',
    'pprod-grupo': 'grupo',
    'pprod-unidade': 'unidade',
    'pprod-ncm': 'ncm',
    'pprod-ncm-desc': 'ncm',
};

document.addEventListener('livewire:init', () => {
    initErpProdutosForm();

    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (page) {
            initErpProdutosFormInputs(page);
        }
    });

    window.Livewire.on('erp-lookup-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-lookup-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-lookup-form-opened', () => {
        window.setTimeout(() => {
            document.querySelector('.erp-lookup-modal__body--form input')?.focus();
        }, 50);
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (page) {
            initErpProdutosFormInputs(page);
        }
    });
});

function initErpProdutosForm() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page) {
        return;
    }

    initErpProdutosFormInputs(page);
    bindErpProdutosFormKeys();
    bindErpProdutosSaveButtons(page);
    bindSearchCodigoBarras(page);
    bindProductCadastroLookup(page);
}

function initErpProdutosFormInputs(page) {
    initErpMasks(page);

    if (window.ErpDatepicker) {
        window.ErpDatepicker.init(page);
    }
}

function commitErpProdutosFormInputs(page) {
    page.querySelectorAll('[data-mask]').forEach((input) => {
        if (input.dataset.mask === 'date-br') {
            return;
        }

        if (window.ErpMasks) {
            if (
                window.ErpMasks.isBrDecimalMask(input.dataset.mask)
                || input.dataset.mask === 'integer'
            ) {
                input.value = window.ErpMasks.finalizeMaskValue(input);
            }

            window.ErpMasks.apply(input, { allowEmptySync: true, live: true });
        }
    });

    if (window.ErpDatepicker) {
        window.ErpDatepicker.commitAllIn(page);
    }
}

function getErpProdutosComponent() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page || ! window.Livewire) {
        return null;
    }

    const wireRoot = page.hasAttribute('wire:id') ? page : page.closest('[wire\\:id]');
    const wireId = wireRoot?.getAttribute('wire:id');

    return wireId ? window.Livewire.find(wireId) : null;
}

window.commitErpProdutosFormBeforeSave = function commitErpProdutosFormBeforeSave() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (page) {
        commitErpProdutosFormInputs(page);
    }
};

window.saveErpProdutosForm = function saveErpProdutosForm() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page) {
        return;
    }

    commitErpProdutosFormInputs(page);

    window.setTimeout(() => {
        getErpProdutosComponent()?.call('saveForm');
    }, 50);
};

function bindProductCadastroLookup(page) {
    if (page.dataset.erpLookupBound === '1') {
        return;
    }

    page.dataset.erpLookupBound = '1';

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-open-lookup]');

        if (! button || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        const type = button.getAttribute('data-erp-open-lookup');
        const component = getErpProdutosComponent();

        if (! type || ! component) {
            return;
        }

        component.call('openProductLookup', type);
    });
}

function bindSearchCodigoBarras(page) {
    if (page.dataset.erpSearchBarcodeBound === '1') {
        return;
    }

    page.dataset.erpSearchBarcodeBound = '1';

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-search-barcode]');

        if (! button || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        const input = document.getElementById('pprod-barras');
        const component = getErpProdutosComponent();

        if (! component) {
            return;
        }

        component.call('searchCodigoBarras', input?.value ?? '');
    });
}

function getLookupModal() {
    return document.querySelector('.erp-lookup-modal');
}

function getLookupRows() {
    return Array.from(document.querySelectorAll('.erp-lookup-modal__row'));
}

function moveLookupSelection(direction) {
    const rows = getLookupRows();

    if (rows.length === 0) {
        return;
    }

    const component = getErpProdutosComponent();

    if (! component) {
        return;
    }

    const selectedIndex = rows.findIndex((row) => row.classList.contains('erp-lookup-modal__row--selected'));
    let nextIndex = selectedIndex;

    if (selectedIndex === -1) {
        nextIndex = direction > 0 ? 0 : rows.length - 1;
    } else {
        nextIndex = Math.max(0, Math.min(rows.length - 1, selectedIndex + direction));
    }

    const recordId = Number.parseInt(rows[nextIndex]?.dataset.recordId ?? '', 10);

    if (Number.isNaN(recordId)) {
        return;
    }

    component.call('highlightLookupRecord', recordId);
    rows[nextIndex]?.scrollIntoView({ block: 'nearest' });
}

function bindErpProdutosSaveButtons(page) {
    if (page.dataset.erpSaveButtonsBound === '1') {
        return;
    }

    page.dataset.erpSaveButtonsBound = '1';

    page.addEventListener(
        'mousedown',
        (event) => {
            const button = event.target.closest('.erp-pcad-actions [data-erp-key="F5"]');

            if (! button || ! page.contains(button)) {
                return;
            }

            commitErpProdutosFormInputs(page);
        },
        true,
    );
}

function bindErpProdutosFormKeys() {
    if (window.__erpProdutosFormKeysBound) {
        return;
    }

    window.__erpProdutosFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (! page) {
            return;
        }

        const component = getErpProdutosComponent();

        if (! component) {
            return;
        }

        const lookupModal = getLookupModal();

        if (lookupModal) {
            handleLookupModalKeydown(event, component, lookupModal);

            return;
        }

        if (event.key === 'F2') {
            const fieldId = document.activeElement?.id ?? '';
            const lookupType = ERP_PRODUCT_LOOKUP_FIELDS[fieldId];

            if (lookupType) {
                event.preventDefault();
                component.call('openProductLookup', lookupType);

                return;
            }
        }

        if (event.key === 'F5') {
            event.preventDefault();
            window.saveErpProdutosForm();

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelForm');

            return;
        }

        if (event.target.matches('input, textarea, select, [contenteditable="true"]')) {
            return;
        }
    });
}

function handleLookupModalKeydown(event, component, lookupModal) {
    const isFormPanel = lookupModal.querySelector('.erp-lookup-modal__body--form') !== null;

    if (event.key === 'Escape') {
        event.preventDefault();
        component.call('handleLookupEscape');

        return;
    }

    if (isFormPanel) {
        if (event.key === 'F5') {
            event.preventDefault();
            component.call('saveLookupRecord');
        }

        return;
    }

    if (event.key === 'F6') {
        event.preventDefault();
        document.getElementById('erp-lookup-search')?.focus();

        return;
    }

    if (event.key === 'F2') {
        event.preventDefault();
        component.call('startLookupCreate');

        return;
    }

    if (event.key === 'F3') {
        event.preventDefault();
        component.call('startLookupEdit');

        return;
    }

    if (event.key === 'Enter') {
        const selectedRow = lookupModal.querySelector('.erp-lookup-modal__row--selected');

        if (selectedRow) {
            event.preventDefault();
            component.call('confirmProductLookup');
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveLookupSelection(1);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveLookupSelection(-1);
    }
}
