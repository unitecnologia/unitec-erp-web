document.addEventListener('DOMContentLoaded', initErpNfeLancamento);
document.addEventListener('livewire:navigated', initErpNfeLancamento);

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-nfe-focus-item-codigo', () => {
        focusNfeLancamentoInput('nfe-item-codigo');
    });

    window.Livewire.on('erp-nfe-focus-item-produto', () => {
        focusNfeLancamentoInput('nfe-item-produto');
        requestAnimationFrame(positionNfeProdutoLookup);
    });

    window.Livewire.on('erp-nfe-focus-item-cfop', () => {
        focusNfeLancamentoInput('nfe-item-cfop');
    });

    window.Livewire.on('erp-nfe-focus-item-cst', () => {
        focusNfeLancamentoInput('nfe-item-cst');
    });

    window.Livewire.on('erp-nfe-focus-item-preco', () => {
        focusNfeLancamentoInput('nfe-item-preco');
    });

    window.Livewire.on('erp-nfe-focus-item-quantidade', () => {
        focusNfeLancamentoInput('nfe-item-quantidade');
    });

    window.Livewire.on('erp-nfe-focus-item-unidade', () => {
        focusNfeLancamentoInput('nfe-item-unidade');
    });

    window.Livewire.on('erp-nfe-scroll-produto-selection', () => {
        scrollNfeProdutoSelectionIntoView();
        positionNfeProdutoLookup();
    });

    window.Livewire.hook('morph.updated', () => {
        requestAnimationFrame(positionNfeProdutoLookup);
    });
});

function initErpNfeLancamento() {
    bindErpNfeLancamentoKeys();
    bindNfeProdutoLookupFloating();
    requestAnimationFrame(positionNfeProdutoLookup);
}

function bindNfeProdutoLookupFloating() {
    if (window.__erpNfeProdutoLookupFloatingBound) {
        return;
    }

    window.__erpNfeProdutoLookupFloatingBound = true;

    window.addEventListener('resize', positionNfeProdutoLookup);
    document.addEventListener('scroll', positionNfeProdutoLookup, true);
}

function getNfeProdutoLookupDropdown() {
    const field = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-field');

    if (! field) {
        return null;
    }

    return field.querySelector('.erp-nfe-produto-lookup-panel')
        ?? field.querySelector('.erp-nfe-produto-lookup--empty');
}

function resetNfeProdutoLookupPosition(dropdown) {
    dropdown.classList.remove('is-floating');
    dropdown.style.position = '';
    dropdown.style.top = '';
    dropdown.style.left = '';
    dropdown.style.width = '';
    dropdown.style.maxHeight = '';
    dropdown.style.zIndex = '';
}

function positionNfeProdutoLookup() {
    const modal = document.querySelector('.erp-nfe-lancamento-modal__window');
    const input = document.getElementById('nfe-item-produto');
    const dropdown = getNfeProdutoLookupDropdown();

    document.querySelectorAll('.erp-nfe-produto-lookup-panel.is-floating, .erp-nfe-produto-lookup--empty.is-floating')
        .forEach((node) => {
            if (node !== dropdown) {
                resetNfeProdutoLookupPosition(node);
            }
        });

    if (! modal || ! input || ! dropdown) {
        return;
    }

    const rootFont = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const inputRect = input.getBoundingClientRect();
    const modalRect = modal.getBoundingClientRect();
    const gap = 2;
    const width = Math.max(
        26 * rootFont,
        Math.min(
            52 * rootFont,
            modalRect.right - inputRect.left - 8,
            window.innerWidth - inputRect.left - 8,
        ),
    );
    const maxHeight = Math.min(
        22 * rootFont,
        window.innerHeight - inputRect.bottom - gap - 12,
    );

    dropdown.classList.add('is-floating');
    dropdown.style.position = 'fixed';
    dropdown.style.top = `${inputRect.bottom + gap}px`;
    dropdown.style.left = `${inputRect.left}px`;
    dropdown.style.width = `${width}px`;
    dropdown.style.maxHeight = `${Math.max(8 * rootFont, maxHeight)}px`;
    dropdown.style.zIndex = '400';
}

function getErpNfeLancamentoComponent() {
    const modal = document.querySelector('.erp-nfe-lancamento-modal');

    if (! modal) {
        return null;
    }

    const componentEl = modal.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpNfeLancamentoKeys() {
    if (window.__erpNfeLancamentoKeysBound) {
        return;
    }

    window.__erpNfeLancamentoKeysBound = true;

    document.addEventListener('keydown', handleErpNfeLancamentoKeydown);
}

function handleErpNfeLancamentoKeydown(event) {
    if (! document.querySelector('.erp-nfe-lancamento-modal')) {
        return;
    }

    const component = getErpNfeLancamentoComponent();

    if (! component) {
        return;
    }

    const produtoFocused = document.activeElement?.id === 'nfe-item-produto';

    if (produtoFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        const lookup = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-lookup-panel');

        if (! lookup) {
            return;
        }

        event.preventDefault();

        const delta = event.key === 'ArrowDown' ? 1 : -1;

        component.call('moveNfeProdutoSelection', delta);

        return;
    }

    if (event.key !== 'Delete' || ! event.ctrlKey) {
        return;
    }

    if (isNfeEditableTarget(event.target)) {
        return;
    }

    event.preventDefault();
    component.call('deleteNfeSelectedItem');
}

function isNfeEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}

function focusNfeLancamentoInput(id, retries = 10) {
    const input = document.getElementById(id);

    if (input && ! input.disabled && ! input.readOnly) {
        input.focus();

        if (typeof input.select === 'function') {
            input.select();
        }

        return;
    }

    if (retries <= 0) {
        return;
    }

    requestAnimationFrame(() => {
        focusNfeLancamentoInput(id, retries - 1);
    });
}

function scrollNfeProdutoSelectionIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-nfe-produto-lookup-panel__list .erp-nfe-produto-lookup__row--active')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
}
