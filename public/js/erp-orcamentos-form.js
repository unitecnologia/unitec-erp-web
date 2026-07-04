document.addEventListener('DOMContentLoaded', initErpOrcamentosForm);
document.addEventListener('livewire:navigated', initErpOrcamentosForm);

const ERP_ORC_FORM_ACTIONS = {
    F2: 'gravarOrcamento',
    F3: 'finalizarOrcamento',
    F8: 'openProdutosCadastro',
    F9: 'openPessoasCadastro',
    Escape: 'handleOrcamentoFormEscape',
};

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-orcamento-focus-barcode', () => {
        focusOrcFormInput('orc-barcode');
    });

    window.Livewire.on('erp-orcamento-focus-item-codigo', () => {
        focusOrcFormInput('orc-item-codigo');
    });

    window.Livewire.on('erp-orcamento-focus-item-descricao', () => {
        focusOrcFormInput('orc-item-descricao', { selectAll: false });
        requestAnimationFrame(positionOrcProdutoLookup);
    });

    window.Livewire.on('erp-orcamento-focus-item-quantidade', () => {
        focusOrcFormInput('orc-item-quantidade');
    });

    window.Livewire.on('erp-orcamento-focus-item-preco', () => {
        focusOrcFormInput('orc-item-preco');
    });

    window.Livewire.on('erp-orcamento-masks-refresh', () => {
        const page = document.querySelector('.erp-orcamentos-form-page');

        if (page && window.ErpMasks) {
            window.ErpMasks.init(page);
        }
    });

    window.Livewire.on('erp-orcamento-post-save-prompt-opened', () => {
        document.querySelector('.erp-orc-post-save-modal [wire\\:click="sairAposGravarOrcamento"]')?.focus();
    });

    window.Livewire.on('erp-orcamento-item-delete-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-orc-item-delete-sim')?.focus();
        }, 50);
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-orcamentos-form-page');

        if (page && window.ErpMasks) {
            window.ErpMasks.init(page);
        }

        requestAnimationFrame(positionOrcProdutoLookup);
    });
});

window.addEventListener('message', (event) => {
    if (event.data?.type !== 'erp-orcamento-overlay-close') {
        return;
    }

    const component = getErpOrcamentosComponent();

    if (! component) {
        return;
    }

    const produtoCodigo = event.data.produtoCodigo;

    if (typeof produtoCodigo === 'string' && produtoCodigo.trim() !== '') {
        component.call('applyOverlayProdutoSaved', produtoCodigo);

        return;
    }

    const clienteId = Number.parseInt(String(event.data.clienteId ?? ''), 10);

    if (! Number.isNaN(clienteId) && clienteId > 0) {
        component.call('applyOverlayPersonSaved', clienteId);

        return;
    }

    component.call('closeProductOverlay');
    component.call('closePersonOverlay');
});

function initErpOrcamentosForm() {
    const page = document.querySelector('.erp-orcamentos-form-page');

    if (! page) {
        return;
    }

    if (window.ErpMasks) {
        window.ErpMasks.init(page);
    }

    bindErpOrcamentosFormKeys();
    bindOrcProdutoLookupFloating();
    requestAnimationFrame(positionOrcProdutoLookup);
}

function bindOrcProdutoLookupFloating() {
    if (window.__erpOrcProdutoLookupFloatingBound) {
        return;
    }

    window.__erpOrcProdutoLookupFloatingBound = true;

    window.addEventListener('resize', positionOrcProdutoLookup);
    document.addEventListener('scroll', positionOrcProdutoLookup, true);
}

function getOrcProdutoLookupDropdown() {
    const field = document.querySelector('.erp-orcamentos-form-page .erp-orc-produto-field');

    if (! field) {
        return null;
    }

    return field.querySelector('.erp-orc-produto-lookup')
        ?? field.querySelector('.erp-orc-produto-lookup--empty');
}

function resetOrcProdutoLookupPosition(dropdown) {
    dropdown.classList.remove('is-floating');
    dropdown.style.position = '';
    dropdown.style.top = '';
    dropdown.style.left = '';
    dropdown.style.width = '';
    dropdown.style.maxHeight = '';
    dropdown.style.zIndex = '';
}

function positionOrcProdutoLookup() {
    const codigoInput = document.getElementById('orc-item-codigo');
    const descricaoInput = document.getElementById('orc-item-descricao');
    const dropdown = getOrcProdutoLookupDropdown();

    document.querySelectorAll('.erp-orc-produto-lookup.is-floating, .erp-orc-produto-lookup--empty.is-floating')
        .forEach((node) => {
            if (node !== dropdown) {
                resetOrcProdutoLookupPosition(node);
            }
        });

    if (! codigoInput || ! descricaoInput || ! dropdown) {
        return;
    }

    const rootFont = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const codigoRect = codigoInput.getBoundingClientRect();
    const descricaoRect = descricaoInput.getBoundingClientRect();
    const gap = 2;
    const width = Math.max(
        22 * rootFont,
        Math.min(36 * rootFont, descricaoRect.right - codigoRect.left, window.innerWidth - codigoRect.left - 8),
    );
    const maxHeight = Math.min(
        14 * rootFont,
        window.innerHeight - descricaoRect.bottom - gap - 12,
    );

    dropdown.classList.add('is-floating');
    dropdown.style.position = 'fixed';
    dropdown.style.top = `${descricaoRect.bottom + gap}px`;
    dropdown.style.left = `${codigoRect.left}px`;
    dropdown.style.width = `${width}px`;
    dropdown.style.maxHeight = `${Math.max(6 * rootFont, maxHeight)}px`;
    dropdown.style.zIndex = '400';
}

function getErpOrcamentosComponent() {
    const root = document.querySelector('.erp-orcamentos-form-page');

    if (! root) {
        return null;
    }

    const componentEl = root.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpOrcamentosFormKeys() {
    if (window.__erpOrcFormKeysBound) {
        return;
    }

    window.__erpOrcFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-orcamentos-form-page')) {
            return;
        }

        const component = getErpOrcamentosComponent();

        if (! component) {
            return;
        }

        if (document.querySelector('.erp-orc-post-save-modal')) {
            return;
        }

        if (document.querySelector('.erp-orc-item-delete-modal')) {
            if (event.key === 'Enter') {
                event.preventDefault();
                component.call('confirmDeleteItem');
            }

            return;
        }

        const method = ERP_ORC_FORM_ACTIONS[event.key];

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
            document.getElementById('orc-barcode')?.focus();

            return;
        }

        if (event.key === 'Delete' && ! isEditableTarget(event.target)) {
            event.preventDefault();
            component.call('deleteSelectedItem');
        }
    });
}

function isEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}

function focusOrcFormInput(id, options = {}) {
    const input = document.getElementById(id);

    if (! input || input.disabled) {
        return;
    }

    const selectAll = options.selectAll !== false;

    if (document.activeElement !== input) {
        input.focus();
    }

    if (selectAll) {
        input.select();
    }
}
