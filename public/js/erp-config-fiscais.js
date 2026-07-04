document.addEventListener('DOMContentLoaded', initErpConfigFiscais);
document.addEventListener('livewire:navigated', initErpConfigFiscais);

function initErpConfigFiscais() {
    if (! document.querySelector('.erp-config-fiscais-page')) {
        return;
    }

    bindErpConfigFiscaisKeys();
    bindErpConfigFiscaisPasswordToggles();
}

function bindErpConfigFiscaisPasswordToggles() {
    if (window.__erpConfigFiscaisPasswordToggleBound) {
        return;
    }

    window.__erpConfigFiscaisPasswordToggleBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-password-toggle]');

        if (! button || ! document.querySelector('.erp-config-fiscais-page')) {
            return;
        }

        const inputId = button.getAttribute('data-erp-password-toggle');

        if (! inputId) {
            return;
        }

        const input = document.getElementById(inputId);

        if (! input) {
            return;
        }

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.classList.toggle('is-visible', showPassword);
        button.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
        button.setAttribute('title', showPassword ? 'Ocultar senha' : 'Mostrar senha');
    });
}

function getErpConfigFiscaisComponent() {
    const page = document.querySelector('.erp-config-fiscais-page');

    if (! page) {
        return null;
    }

    const componentEl = page.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpConfigFiscaisKeys() {
    if (window.__erpConfigFiscaisKeysBound) {
        return;
    }

    window.__erpConfigFiscaisKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-config-fiscais-page')) {
            return;
        }

        const component = getErpConfigFiscaisComponent();

        if (! component) {
            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            component.call('saveConfig');

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('closeScreen');
        }
    });
}
