document.addEventListener('DOMContentLoaded', initErpPessoasForm);
document.addEventListener('livewire:navigated', initErpPessoasForm);

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });

    window.Livewire.on('erp-pessoa-focus-email', () => {
        scheduleErpPessoaFocus('pcad-email');
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }

        refocusErpPessoaAfterMorph();
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
    bindErpPessoaCidadeEnter();
}

function getErpPessoasLivewireComponent() {
    const page = document.querySelector('.erp-pessoas-form-page');

    if (! page) {
        return null;
    }

    const componentEl = page.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function focusErpPessoaField(id) {
    const el = document.getElementById(id);

    if (! el || el.disabled) {
        return false;
    }

    el.removeAttribute('readonly');

    try {
        el.focus({ preventScroll: true });

        if (typeof el.select === 'function' && el instanceof HTMLInputElement) {
            el.select();
        }
    } catch (error) {
        try {
            el.focus();

            if (typeof el.select === 'function' && el instanceof HTMLInputElement) {
                el.select();
            }
        } catch (ignored) {
            return false;
        }
    }

    return true;
}

function scheduleErpPessoaFocus(id) {
    if (! id) {
        return;
    }

    window.__erpPessoaFocusKey = id;
    window.__erpPessoaFocusUntil = Date.now() + 2500;

    focusErpPessoaField(id);
    window.setTimeout(() => focusErpPessoaField(id), 0);
    window.setTimeout(() => focusErpPessoaField(id), 60);
    window.setTimeout(() => focusErpPessoaField(id), 160);
    window.setTimeout(() => focusErpPessoaField(id), 320);
    window.setTimeout(() => focusErpPessoaField(id), 500);
    window.setTimeout(() => focusErpPessoaField(id), 800);
}

function refocusErpPessoaAfterMorph() {
    if (Date.now() >= (window.__erpPessoaFocusUntil || 0)) {
        return;
    }

    const key = window.__erpPessoaFocusKey;

    if (! key) {
        return;
    }

    focusErpPessoaField(key);
}

function bindErpPessoaCidadeEnter() {
    if (window.__erpPessoaCidadeEnterBound) {
        return;
    }

    window.__erpPessoaCidadeEnterBound = true;
    window.__erpPessoaFocusUntil = 0;
    window.__erpPessoaFocusKey = null;

    // Capture: roda antes do Alpine e libera readonly anti-autofill.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        const el = event.target;

        if (! (el instanceof HTMLInputElement) || el.id !== 'pcad-cidade-nome') {
            return;
        }

        if (! document.querySelector('.erp-pessoas-form-page')) {
            return;
        }

        el.removeAttribute('readonly');
        // preventDefault evita submit; NÃO usa stopImmediatePropagation
        // para o wire:keydown.enter do Livewire ainda gravar.
        event.preventDefault();

        scheduleErpPessoaFocus('pcad-email');

        const component = getErpPessoasLivewireComponent();

        if (component) {
            component.call('confirmarPessoaCidadeSugestao');
        }
    }, true);
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
        const component = getErpPessoasLivewireComponent();

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

        const component = getErpPessoasLivewireComponent();

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
