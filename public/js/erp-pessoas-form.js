document.addEventListener('DOMContentLoaded', initErpPessoasForm);
document.addEventListener('livewire:navigated', initErpPessoasForm);

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });
});

function initErpPessoasForm() {
    const page = document.querySelector('.erp-pessoas-form-page');

    if (! page) {
        return;
    }

    initErpMasks(page);
    bindErpPessoasFormKeys(page);
    bindSearchPessoaJuridica(page);
}

function bindSearchPessoaJuridica(page) {
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

        const input = document.getElementById('pcad-cpf');
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

        component.call('searchPessoaJuridica', input?.value ?? '');
    });
}

function bindErpPessoasFormKeys(page) {
    if (page.dataset.erpFormKeysBound === '1') {
        return;
    }

    page.dataset.erpFormKeysBound = '1';

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-pessoas-form-page')) {
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
