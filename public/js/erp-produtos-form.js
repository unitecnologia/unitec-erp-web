document.addEventListener('DOMContentLoaded', initErpProdutosForm);
document.addEventListener('livewire:navigated', initErpProdutosForm);

const ERP_PRODUCT_LOOKUP_FIELDS = {
    'pprod-marca': 'marca',
    'pprod-grupo': 'grupo',
    'pprod-unidade': 'unidade',
    'pprod-ncm': 'ncm',
    'pprod-ncm-desc': 'ncm',
};

/** Próximo campo a focar após morph do Livewire (Enter na precificação). */
let erpPrecifPendingFocusId = null;

document.addEventListener('livewire:init', () => {
    initErpProdutosForm();

    window.Livewire.on('erp-masks-refresh', () => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (page) {
            initErpProdutosFormInputs(page);
        }
    });

    window.Livewire.on('erp-lookup-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-lookup-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-lookup-form-opened', () => {
        window.setTimeout(() => {
            document.querySelector('.erp-lookup-modal__body--form input')?.focus();
        }, 50);
    });

    // Foco da precificação: só erp-precif-enter-v5.js (evita double select).
    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (page) {
            initErpProdutosFormInputs(page);
        }
    });
});

function focusPrecificacaoInputById(fieldId) {
    if (! fieldId) {
        return;
    }

    if (typeof window.__erpPrecifFocusById === 'function') {
        window.__erpPrecifFocusById(fieldId, { select: true });
    }
}

/**
 * Cadastro novo: cursor em Descrição.
 * Só na abertura da tela e cancelado na primeira ação do usuário, para nunca
 * roubar o foco de quem já está navegando com Enter.
 */
let erpDescricaoFocusTimer = null;

function cancelProdutoDescricaoFocus() {
    if (erpDescricaoFocusTimer !== null) {
        window.clearTimeout(erpDescricaoFocusTimer);
        erpDescricaoFocusTimer = null;
    }
}

function bindProdutoDescricaoFocusCancel() {
    if (window.__erpProdutoDescricaoFocusCancelBound) {
        return;
    }

    window.__erpProdutoDescricaoFocusCancelBound = true;

    // Só interação real do usuário cancela — eventos programáticos da carga não.
    const cancelIfLeftDescricao = (event) => {
        if (event.target?.id !== 'pprod-descricao') {
            cancelProdutoDescricaoFocus();
        }
    };

    document.addEventListener('keydown', cancelIfLeftDescricao, true);
    document.addEventListener('pointerdown', cancelIfLeftDescricao, true);
}

function focusProdutoDescricaoOnCreate(page) {
    if (! page || ! page.classList.contains('erp-produtos-form-page--create')) {
        return;
    }

    bindProdutoDescricaoFocusCancel();
    cancelProdutoDescricaoFocus();

    const attempts = [0, 50, 150];

    const tryFocus = (index) => {
        erpDescricaoFocusTimer = null;

        const input = document.getElementById('pprod-descricao');

        if (input && ! input.disabled) {
            input.removeAttribute('readonly');
            input.focus();

            if (document.activeElement === input) {
                return;
            }
        }

        const next = index + 1;

        if (next < attempts.length) {
            erpDescricaoFocusTimer = window.setTimeout(() => tryFocus(next), attempts[next]);
        }
    };

    erpDescricaoFocusTimer = window.setTimeout(() => tryFocus(0), attempts[0]);
}

function initErpProdutosForm() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page) {
        return;
    }

    initErpProdutosFormInputs(page);
    bindErpProdutosFormKeys();
    bindErpProdutosSaveButtons(page);
    bindErpProdutosLocFields(page);
    bindSearchCodigoBarras(page);
    bindProductCadastroLookup(page);
    focusProdutoDescricaoOnCreate(page);
}

function initErpProdutosFormInputs(page) {
    initErpMasks(page);

    if (window.ErpDatepicker) {
        window.ErpDatepicker.init(page);
    }
}

function commitErpProdutosFormInputs(page) {
    page.querySelectorAll('[data-mask]').forEach((input) => {
        if (input.dataset.mask === 'date-br') {
            return;
        }

        if (window.ErpMasks) {
            if (
                window.ErpMasks.isBrDecimalMask(input.dataset.mask)
                || input.dataset.mask === 'integer'
            ) {
                input.value = window.ErpMasks.finalizeMaskValue(input);
            }

            window.ErpMasks.apply(input, {
                allowEmptySync: true,
                live: true,
                thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
            });
        }
    });

    if (window.ErpDatepicker) {
        window.ErpDatepicker.commitAllIn(page);
    }
}

function getErpProdutosComponent() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page || ! window.Livewire) {
        return null;
    }

    const wireRoot = page.hasAttribute('wire:id') ? page : page.closest('[wire\\:id]');
    const wireId = wireRoot?.getAttribute('wire:id');

    return wireId ? window.Livewire.find(wireId) : null;
}

function sanitizeErpProdutosLocInput(input) {
    if (! input) {
        return '';
    }

    const sanitized = String(input.value ?? '').replace(/\D/g, '').slice(0, 2);
    input.value = sanitized;

    return sanitized;
}

function formatErpProdutosLocalizacao(loc) {
    const segments = [];

    const add = (label, value) => {
        const digits = String(value ?? '').replace(/\D/g, '').slice(0, 2);

        if (digits && parseInt(digits, 10) > 0) {
            segments.push(`${label}:${digits}`);
        }
    };

    add('C', loc.corredor);
    add('M', loc.modulo);
    add('P', loc.prateleira);
    add('G', loc.gaveta);

    return segments.join('/');
}

function readErpProdutosLocValues() {
    return {
        corredor: sanitizeErpProdutosLocInput(document.getElementById('pprod-loc-corredor')),
        modulo: sanitizeErpProdutosLocInput(document.getElementById('pprod-loc-modulo')),
        prateleira: sanitizeErpProdutosLocInput(document.getElementById('pprod-loc-prateleira')),
        gaveta: sanitizeErpProdutosLocInput(document.getElementById('pprod-loc-gaveta')),
    };
}

window.pushErpProdutosLocToLivewire = async function pushErpProdutosLocToLivewire() {
    // Só sincroniza se os campos estiverem na tela (aba Localizações).
    if (! document.querySelector('[data-erp-loc-fields]')) {
        return;
    }

    const component = getErpProdutosComponent();

    if (! component) {
        return;
    }

    const loc = readErpProdutosLocValues();

    await component.call(
        'applyLocalizacaoInputParts',
        loc.corredor,
        loc.modulo,
        loc.prateleira,
        loc.gaveta,
    );
};

function bindErpProdutosLocFields(page) {
    if (page.dataset.erpLocBound === '1') {
        return;
    }

    page.dataset.erpLocBound = '1';

    // Delegação no page: sobrevive à recriação do bloco wire:ignore nas abas.
    page.addEventListener(
        'focusout',
        (event) => {
            if (! event.target.matches('[data-erp-loc-fields] .erp-produtos-loc__input')) {
                return;
            }

            sanitizeErpProdutosLocInput(event.target);
            // Inclui troca de aba: o bloco some do DOM (wire:key), então o state
            // Livewire precisa receber as partes antes do próximo save.
            window.pushErpProdutosLocToLivewire();
        },
        true,
    );
}

window.commitErpProdutosFormBeforeSave = function commitErpProdutosFormBeforeSave() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (page) {
        commitErpProdutosFormInputs(page);
    }
};

window.saveErpProdutosForm = async function saveErpProdutosForm() {
    const page = document.querySelector('.erp-produtos-form-page');

    if (! page) {
        return;
    }

    commitErpProdutosFormInputs(page);

    const component = getErpProdutosComponent();

    if (! component) {
        return;
    }

    // Sem o bloco Localizações no DOM (outra subaba), não envia '' — isso apagava
    // a localização gravada. O Livewire já mantém o valor em $data.
    if (! document.querySelector('[data-erp-loc-fields]')) {
        await component.call('saveForm');

        return;
    }

    const loc = readErpProdutosLocValues();
    const localizacao = formatErpProdutosLocalizacao(loc);

    await component.call('saveForm', localizacao);
};

function bindProductCadastroLookup(page) {
    if (page.dataset.erpLookupBound === '1') {
        return;
    }

    page.dataset.erpLookupBound = '1';

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-open-lookup]');

        if (! button || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        const type = button.getAttribute('data-erp-open-lookup');
        const component = getErpProdutosComponent();

        if (! type || ! component) {
            return;
        }

        component.call('openProductLookup', type);
    });
}

function bindSearchCodigoBarras(page) {
    if (page.dataset.erpSearchBarcodeBound === '1') {
        return;
    }

    page.dataset.erpSearchBarcodeBound = '1';

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-search-barcode]');

        if (! button || ! page.contains(button)) {
            return;
        }

        event.preventDefault();

        const input = document.getElementById('pprod-barras');
        const component = getErpProdutosComponent();

        if (! component) {
            return;
        }

        component.call('searchCodigoBarras', input?.value ?? '');
    });
}

function getLookupModal() {
    // Exclui outros modais que reutilizam a classe visual erp-lookup-modal
    // (precificação, confirmação de NCM, etc.).
    return document.querySelector(
        '.erp-lookup-modal:not(.erp-prod-precificacao-modal):not(.erp-ncm-confirm-modal):not(.erp-duplicate-modal)',
    );
}

function getPrecificacaoModal() {
    return document.querySelector('.erp-prod-precificacao-modal');
}

function getPrecificacaoEditableFields(modal) {
    if (! modal) {
        return [];
    }

    return Array.from(modal.querySelectorAll(
        '.erp-prod-precificacao-modal__body input.erp-pcad-form__input:not([readonly]):not([disabled])'
    )).filter((input) => Boolean(input.id));
}

function focusPrecificacaoFieldById(fieldId, clearPending = false) {
    if (! fieldId) {
        return false;
    }

    const root = getPrecificacaoModal();
    const next = root?.querySelector(`#${CSS.escape(fieldId)}`);

    if (! next || next.readOnly || next.disabled) {
        return false;
    }

    next.focus({ preventScroll: true });
    next.select();

    const ok = document.activeElement === next;

    if (ok && clearPending && erpPrecifPendingFocusId === fieldId) {
        erpPrecifPendingFocusId = null;
    }

    return ok;
}

function focusNextPrecificacaoField(current) {
    const modal = getPrecificacaoModal();
    const fields = getPrecificacaoEditableFields(modal);
    const currentId = current?.id || '';
    const index = fields.findIndex((field) => field.id === currentId);
    const nextId = index >= 0 ? (fields[index + 1]?.id || null) : null;

    if (window.ErpMasks && current?.dataset?.mask) {
        current.value = window.ErpMasks.finalizeMaskValue(current);
        window.ErpMasks.apply(current, {
            allowEmptySync: true,
            live: true,
            thousands: window.ErpMasks.isBrDecimalMask(current.dataset.mask),
        });
    }

    if (! nextId) {
        erpPrecifPendingFocusId = null;
        current?.blur();

        return;
    }

    erpPrecifPendingFocusId = nextId;

    const tryFocus = (attempt = 0) => {
        if (focusPrecificacaoFieldById(nextId, true) || attempt >= 12) {
            return;
        }

        window.setTimeout(() => tryFocus(attempt + 1), 30 + (attempt * 25));
    };

    // Igual Tab: foca o próximo imediatamente e retenta se o Livewire remorphar.
    tryFocus(0);
}

window.focusNextPrecificacaoField = focusNextPrecificacaoField;

function handlePrecificacaoModalKeydown(event) {
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }

    const target = event.target;

    if (! (target instanceof HTMLInputElement) || target.readOnly || target.disabled) {
        return;
    }

    const modal = getPrecificacaoModal();

    if (! modal || ! modal.contains(target)) {
        return;
    }

    // Enter = Tab (avança campo), sem submeter nada.
    event.preventDefault();
    event.stopPropagation();

    if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
    }

    focusNextPrecificacaoField(target);
}

function getLookupRows() {
    return Array.from(document.querySelectorAll('.erp-lookup-modal__row'));
}

function moveLookupSelection(direction) {
    const rows = getLookupRows();

    if (rows.length === 0) {
        return;
    }

    const component = getErpProdutosComponent();

    if (! component) {
        return;
    }

    const selectedIndex = rows.findIndex((row) => row.classList.contains('erp-lookup-modal__row--selected'));
    let nextIndex = selectedIndex;

    if (selectedIndex === -1) {
        nextIndex = direction > 0 ? 0 : rows.length - 1;
    } else {
        nextIndex = Math.max(0, Math.min(rows.length - 1, selectedIndex + direction));
    }

    const recordId = Number.parseInt(rows[nextIndex]?.dataset.recordId ?? '', 10);

    if (Number.isNaN(recordId)) {
        return;
    }

    component.call('highlightLookupRecord', recordId);
    rows[nextIndex]?.scrollIntoView({ block: 'nearest' });
}

function bindErpProdutosSaveButtons(page) {
    if (page.dataset.erpSaveButtonsBound === '1') {
        return;
    }

    page.dataset.erpSaveButtonsBound = '1';

    page.addEventListener(
        'mousedown',
        (event) => {
            const button = event.target.closest('.erp-pcad-actions [data-erp-key="F5"]');

            if (! button || ! page.contains(button)) {
                return;
            }

            commitErpProdutosFormInputs(page);
        },
        true,
    );
}

function bindErpProdutosFormKeys() {
    if (window.__erpProdutosFormKeysBoundV9) {
        return;
    }

    window.__erpProdutosFormKeysBoundV9 = true;

    document.addEventListener('keydown', (event) => {
        const page = document.querySelector('.erp-produtos-form-page');

        if (! page) {
            return;
        }

        // Precificação: Enter tratado por erp-precif-enter-v5.js (capture).
        // Aqui só evitamos F5/ESC do formulário por baixo da modal.
        if (document.querySelector('.erp-prod-precificacao-modal')) {
            if (event.key === 'Enter') {
                return;
            }

            const component = getErpProdutosComponent();

            if (event.key === 'F5' && component) {
                event.preventDefault();
                event.stopPropagation();
                component.call('aplicarProductPrecificacao');
            }

            return;
        }

        const component = getErpProdutosComponent();

        if (! component) {
            return;
        }

        const lookupModal = getLookupModal();

        if (lookupModal) {
            handleLookupModalKeydown(event, component, lookupModal);

            return;
        }

        if (event.key === 'F2') {
            const fieldId = document.activeElement?.id ?? '';
            const lookupType = ERP_PRODUCT_LOOKUP_FIELDS[fieldId];

            if (lookupType) {
                event.preventDefault();
                component.call('openProductLookup', lookupType);

                return;
            }
        }

        if (event.key === 'F5') {
            event.preventDefault();
            window.saveErpProdutosForm();

            return;
        }

        // Enter no botão Salvar encerra o ciclo do formulário gravando.
        if (
            event.key === 'Enter'
            && document.activeElement?.closest('.erp-pcad-actions__btn--primary[data-erp-key="F5"]')
        ) {
            event.preventDefault();
            window.saveErpProdutosForm();

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelForm');

            return;
        }

        if (event.target.matches('input, textarea, select, [contenteditable="true"]')) {
            return;
        }
    }, true);
}

function handleLookupModalKeydown(event, component, lookupModal) {
    const isFormPanel = lookupModal.querySelector('.erp-lookup-modal__body--form') !== null;

    if (event.key === 'Escape') {
        event.preventDefault();
        component.call('handleLookupEscape');

        return;
    }

    if (isFormPanel) {
        if (event.key === 'F5') {
            event.preventDefault();
            component.call('saveLookupRecord');
        }

        return;
    }

    if (event.key === 'F6') {
        event.preventDefault();
        document.getElementById('erp-lookup-search')?.focus();

        return;
    }

    if (event.key === 'F2') {
        event.preventDefault();
        component.call('startLookupCreate');

        return;
    }

    if (event.key === 'F3') {
        event.preventDefault();
        component.call('startLookupEdit');

        return;
    }

    if (event.key === 'Enter') {
        const selectedRow = lookupModal.querySelector('.erp-lookup-modal__row--selected');

        if (selectedRow) {
            event.preventDefault();
            component.call('confirmProductLookup');
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveLookupSelection(1);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveLookupSelection(-1);
    }
}
