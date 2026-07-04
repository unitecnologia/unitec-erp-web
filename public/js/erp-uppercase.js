window.ErpUppercase = {
    selectors: [
        '[data-erp-uppercase]',
        '.erp-pcad-form__input[type="text"]:not([readonly]):not([data-mask])',
        '.erp-pcad-form__textarea:not([readonly])',
        '.erp-produtos__input:not([readonly])',
        '.erp-orcamentos__input:not([readonly])',
        '.erp-vendas__input:not([readonly])',
        '.erp-caixa__input:not([readonly])',
        '.erp-compras__input:not([readonly])',
        '.erp-receber__input:not([readonly])',
        '.erp-pagar__input:not([readonly])',
        '.erp-vendedores__input:not([readonly])',
        '.erp-entregadores__input:not([readonly])',
        '.erp-contadores__input:not([readonly])',
        '.erp-grupos__input:not([readonly])',
        '.erp-marcas__input:not([readonly])',
        '.erp-unidades__input:not([readonly])',
        '.erp-ajustes-estoque__input:not([readonly])',
        '.erp-ajusta-precos__input:not([readonly])',
        '.erp-impressao-etiquetas__input:not([readonly])',
        '.erp-pdv-form__input:not([data-mask]):not(.erp-pdv-form__input--money)',
    ],

    shouldSkip(input) {
        if (input.readOnly || input.disabled) {
            return true;
        }

        const type = (input.type || 'text').toLowerCase();

        if (type === 'email' || type === 'password') {
            return true;
        }

        if (input.hasAttribute('data-mask')) {
            return true;
        }

        if (input.classList.contains('erp-pdv-form__input--money')) {
            return true;
        }

        return false;
    },

    bindInput(input) {
        if (input.dataset.erpUppercaseBound === '1' || this.shouldSkip(input)) {
            return;
        }

        input.dataset.erpUppercaseBound = '1';
        input.setAttribute('data-erp-uppercase', '');

        input.addEventListener('input', () => {
            const upper = input.value.toLocaleUpperCase('pt-BR');

            if (input.value === upper) {
                return;
            }

            const start = input.selectionStart;
            const end = input.selectionEnd;

            input.value = upper;

            if (start !== null && end !== null) {
                input.setSelectionRange(start, end);
            }

            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    },

    bind(root = document) {
        const seen = new Set();

        this.selectors.forEach((selector) => {
            root.querySelectorAll(selector).forEach((input) => {
                if (seen.has(input)) {
                    return;
                }

                seen.add(input);
                this.bindInput(input);
            });
        });
    },
};

function initErpUppercase() {
    window.ErpUppercase?.bind(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initErpUppercase);
} else {
    initErpUppercase();
}

document.addEventListener('livewire:navigated', initErpUppercase);

if (window.Livewire) {
    window.Livewire.hook('morph.updated', ({ el }) => {
        window.ErpUppercase?.bind(el);
    });
}
