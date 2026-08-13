document.addEventListener('DOMContentLoaded', initErpDevVendaForm);
document.addEventListener('livewire:navigated', initErpDevVendaForm);

const ERP_DEVVENDA_FORM_ACTIONS = {
    F2: 'gravarDevolucao',
    F3: 'finalizarDevolucao',
    Escape: 'handleDevolucaoFormEscape',
};

function initErpDevVendaForm() {
    const page = document.querySelector('.erp-devolucao-venda-form-page');

    if (! page) {
        return;
    }

    bindErpDevVendaFormKeys();
}

function getErpDevVendaComponent() {
    const root = document.querySelector('.erp-devolucao-venda-form-page');

    if (! root) {
        return null;
    }

    const componentEl = root.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpDevVendaFormKeys() {
    if (window.__erpDevVendaFormKeysBound) {
        return;
    }

    window.__erpDevVendaFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-devolucao-venda-form-page')) {
            return;
        }

        const method = ERP_DEVVENDA_FORM_ACTIONS[event.key];

        if (! method) {
            return;
        }

        const component = getErpDevVendaComponent();

        if (! component) {
            return;
        }

        event.preventDefault();
        component.call(method);
    });
}
