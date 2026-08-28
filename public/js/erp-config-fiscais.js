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

            // Blur do campo ativo para flush de wire:model.blur / commit do valor digitado.
            if (document.activeElement instanceof HTMLElement) {
                document.activeElement.blur();
            }

            // Preferir o botão wire:click para o Livewire sincronizar wire:model
            // deferred (ex.: ID Token / série) antes de gravar.
            const saveBtn = document.querySelector(
                '.erp-config-fiscais-page [wire\\:click="saveConfig"], .erp-config-fiscais-page [wire\\:click=\'saveConfig\']'
            );

            if (saveBtn instanceof HTMLElement) {
                saveBtn.click();

                return;
            }

            const wire = component;
            if (typeof wire?.$commit === 'function') {
                wire.$commit().then(() => wire.call('saveConfig'));
            } else {
                wire.call('saveConfig');
            }

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('closeScreen');
        }
    });
}
