document.addEventListener('DOMContentLoaded', initErpOsForm);
document.addEventListener('livewire:navigated', initErpOsForm);

const ERP_OS_FORM_ACTIONS = {
    F2: 'gravarOs',
    F3: 'finalizarOs',
    F8: 'openProdutosCadastro',
    F9: 'openPessoasCadastro',
    Escape: 'handleOsFormEscape',
};

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-os-form-page');

        if (page && window.ErpMasks) {
            window.ErpMasks.init(page);
        }
    });
});

function initErpOsForm() {
    const page = document.querySelector('.erp-os-form-page');

    if (! page) {
        return;
    }

    if (window.ErpMasks) {
        window.ErpMasks.init(page);
    }

    bindErpOsFormKeys();
}

function getErpOsComponent() {
    const root = document.querySelector('.erp-os-form-page');

    if (! root) {
        return null;
    }

    const componentEl = root.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpOsFormKeys() {
    if (window.__erpOsFormKeysBound) {
        return;
    }

    window.__erpOsFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-os-form-page')) {
            return;
        }

        const component = getErpOsComponent();

        if (! component) {
            return;
        }

        if (document.querySelector('.erp-orc-item-delete-modal')) {
            if (event.key === 'Enter') {
                event.preventDefault();
                component.call('confirmDeleteItem');
            }

            return;
        }

        const method = ERP_OS_FORM_ACTIONS[event.key];

        if (method) {
            if (document.querySelector('.erp-form-overlay') && (event.key === 'F8' || event.key === 'F9')) {
                return;
            }

            event.preventDefault();
            component.call(method);

            return;
        }

        if (event.key === 'F11') {
            event.preventDefault();
            document.getElementById('os-barcode')?.focus();

            return;
        }

        if (event.ctrlKey && event.key === 'Delete' && ! isOsEditableTarget(event.target)) {
            event.preventDefault();
            component.call('deleteSelectedItem');
        }
    });
}

function isOsEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}
