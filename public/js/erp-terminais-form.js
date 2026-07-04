document.addEventListener('DOMContentLoaded', initErpTerminaisForm);
document.addEventListener('livewire:navigated', initErpTerminaisForm);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-terminais-form-page');

        if (page) {
            initErpMasks(page);
        }
    });
});

function initErpTerminaisForm() {
    const page = document.querySelector('.erp-terminais-form-page');

    if (! page) {
        return;
    }

    initErpMasks(page);
    bindErpTerminaisFormKeys();
}

function getErpTerminaisComponent() {
    const page = document.querySelector('.erp-terminais-form-page');

    if (! page) {
        return null;
    }

    const componentEl = page.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpTerminaisFormKeys() {
    if (window.__erpTerminaisFormKeysBound) {
        return;
    }

    window.__erpTerminaisFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-terminais-form-page')) {
            return;
        }

        const component = getErpTerminaisComponent();

        if (! component) {
            return;
        }

        if (event.key === 'F4') {
            event.preventDefault();
            component.call('deleteTerminal');

            return;
        }

        if (event.key === 'F10') {
            event.preventDefault();
            component.call('saveTerminalForm');

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('closeScreen');
        }
    });
}
