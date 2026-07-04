document.addEventListener('DOMContentLoaded', initErpEmpresasForm);
document.addEventListener('livewire:navigated', initErpEmpresasForm);

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-empresas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-empresas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });
});

function initErpEmpresasForm() {
    const page = document.querySelector('.erp-empresas-form-page');

    if (! page) {
        return;
    }

    initErpMasks(page);
    bindErpEmpresasFormKeys(page);
    bindSearchEmpresaCnpj(page);
}

function bindSearchEmpresaCnpj(page) {
    if (page.dataset.erpSearchPjBound === '1') {
        return;
    }

    page.dataset.erpSearchPjBound = '1';

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-search-pj]');

        if (! button || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        const input = document.getElementById('emp-cnpj');
        const componentEl = page.closest('[wire\\:id]');
        const component = componentEl
            ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
            : null;

        if (! component) {
            return;
        }

        if (input && window.ErpMasks) {
            window.ErpMasks.apply(input);
        }

        component.call('searchEmpresaCnpj', input?.value ?? '');
    });
}

function bindErpEmpresasFormKeys(page) {
    if (page.dataset.erpFormKeysBound === '1') {
        return;
    }

    page.dataset.erpFormKeysBound = '1';

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-empresas-form-page')) {
            return;
        }

        const componentEl = page.closest('[wire\\:id]');
        const component = componentEl
            ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
            : null;

        if (! component) {
            return;
        }

        if (event.key === 'F5') {
            event.preventDefault();
            component.call('saveForm');

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelForm');
        }
    });
}
