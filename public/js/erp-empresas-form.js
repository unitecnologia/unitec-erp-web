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
    bindErpEmpresasFormKeys();
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

function bindErpEmpresasFormKeys() {
    // Um único listener global — evita empilhar F5/ESC a cada navigated/morph.
    if (window.__erpEmpresasFormKeysBound) {
        return;
    }

    window.__erpEmpresasFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        const page = document.querySelector('.erp-empresas-form-page');

        if (! page) {
            return;
        }

        if (event.key !== 'F5' && event.key !== 'Escape') {
            return;
        }

        const componentEl = page.closest('[wire\\:id]');
        const component = componentEl
            ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
            : null;

        if (! component) {
            return;
        }

        // Evita disparar várias gravações enquanto uma ainda está em andamento.
        if (componentEl.classList.contains('wire-loading') || componentEl.getAttribute('wire:loading') === 'true') {
            event.preventDefault();

            return;
        }

        if (event.key === 'F5') {
            event.preventDefault();

            if (window.__erpEmpresasSaving) {
                return;
            }

            window.__erpEmpresasSaving = true;

            Promise.resolve(component.call('saveForm'))
                .catch(() => {})
                .finally(() => {
                    window.setTimeout(() => {
                        window.__erpEmpresasSaving = false;
                    }, 800);
                });

            return;
        }

        event.preventDefault();
        component.call('cancelForm');
    });
}
