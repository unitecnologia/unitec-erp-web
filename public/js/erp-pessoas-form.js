document.addEventListener('DOMContentLoaded', initErpPessoasForm);
document.addEventListener('livewire:navigated', initErpPessoasForm);
// wire:navigate pode injetar este script DEPOIS de livewire:navigated —
// sem o boot imediato, o 1º cadastro fica sem o clique de Pesquisar Pessoa Jurídica.
if (document.readyState !== 'loading') {
    queueMicrotask(() => initErpPessoasForm());
}

bindSearchPessoaJuridica();
bootErpPessoasLivewire();
document.addEventListener('livewire:init', bootErpPessoasLivewire);

function bootErpPessoasLivewire() {
    if (window.__erpPessoasLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpPessoasLivewireBound = true;

    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }
    });

    window.Livewire.on('erp-pessoa-focus-email', () => {
        scheduleErpPessoaFocus('pcad-email');
    });

    window.Livewire.on('erp-pessoa-focus-cpf', () => {
        scheduleErpPessoaFocus('pcad-cpf');
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-pessoas-form-page');

        if (page) {
            initErpMasks(page);
        }

        refocusErpPessoaAfterMorph();
    });
}

function initErpPessoasForm() {
    const page = document.querySelector('.erp-pessoas-form-page');

    if (! page) {
        return;
    }

    initErpMasks(page);
    bindErpPessoasFormKeys(page);
    bindSearchPessoaJuridica();
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

    const caretPosicionado = document.activeElement === el
        && typeof el.selectionStart === 'number'
        && el.selectionStart === el.selectionEnd;

    if (caretPosicionado) {
        return true;
    }

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

        if (! document.getElementById('pcad-cidade-sugestoes')) {
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

function erpPessoasModalAberto() {
    return Array.from(document.querySelectorAll(
        '.erp-aviso-modal, .erp-pessoa-credito-modal, .erp-pessoas-foto__modal',
    )).some((el) => {
        const style = window.getComputedStyle(el);

        return style.display !== 'none' && style.visibility !== 'hidden';
    });
}

function notifyErpPessoaCnpj(body, type = 'warning') {
    if (typeof notifyErp === 'function') {
        notifyErp(body, type === 'danger' ? 'danger' : 'info', 'Consulta de CNPJ');

        return;
    }

    if (typeof FilamentNotification !== 'undefined') {
        const notification = new FilamentNotification().title('Consulta de CNPJ').body(body);

        if (type === 'danger') {
            notification.danger();
        } else {
            notification.warning();
        }

        notification.send();

        return;
    }

    window.alert(body);
}

function bindSearchPessoaJuridica() {
    if (window.__erpPessoasSearchPjBound) {
        return;
    }

    window.__erpPessoasSearchPjBound = true;

    // Listener no document: sobrevive a morph, troca de cadastro e script injetado tarde.
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-erp-search-pj]')
            : null;

        if (! button) {
            return;
        }

        const page = document.querySelector('.erp-pessoas-form-page');

        if (! page || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        if (button.disabled || window.__erpPessoasCnpjBusy) {
            return;
        }

        const input = page.querySelector('#pcad-cpf') || document.getElementById('pcad-cpf');

        // Só formata o valor visível — wire.set aqui corre com a consulta e o clique some.
        if (input && window.ErpMasks) {
            window.ErpMasks.apply(input, { sync: false });
        }

        const cnpj = String(input?.value ?? '').trim();
        const componentEl = button.closest('[wire\\:id]')
            || page.closest('[wire\\:id]')
            || (page.hasAttribute('wire:id') ? page : null);
        const component = componentEl
            ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
            : getErpPessoasLivewireComponent();

        if (! component || typeof component.call !== 'function') {
            notifyErpPessoaCnpj('Não foi possível iniciar a consulta. Recarregue a tela e tente novamente.', 'danger');

            return;
        }

        window.__erpPessoasCnpjBusy = true;
        button.disabled = true;

        Promise.resolve(component.call('searchPessoaJuridica', cnpj))
            .catch(() => {
                notifyErpPessoaCnpj('Não foi possível consultar o CNPJ agora. Tente novamente.', 'danger');
            })
            .finally(() => {
                window.__erpPessoasCnpjBusy = false;

                if (button.isConnected) {
                    button.disabled = false;
                }
            });
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
            if (erpPessoasModalAberto()) {
                return;
            }

            if (document.getElementById('pcad-cidade-sugestoes')) {
                if (event.target?.id !== 'pcad-cidade-nome') {
                    event.preventDefault();
                    component.call('fecharPessoaCidadeSugestoes');
                }

                return;
            }

            event.preventDefault();
            component.call('cancelForm');
        }
    });
}
