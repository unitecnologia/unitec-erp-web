/**
 * Desativa dicas/autofill do navegador (Google Password Manager, endereços, 1Password, etc.)
 * em todos os inputs do ERP — inclusive campos type="password".
 *
 * Importante: NÃO usar autocomplete="new-password" em senhas de certificado/API/etc.
 * Esse valor faz o Chrome oferecer "senha forte".
 */
(function initErpNoBrowserHints() {
    const IGNORE_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image', 'checkbox', 'radio', 'file', 'range', 'color']);

    const ADDRESS_OR_PROFILE_RE = /email|user|login|senha|password|pass|api.?key|remetente|certificado|pfx|nome|name|endereco|address|rua|street|bairro|cidade|city|estado|uf|state|cep|zip|postal|logradouro|complemento|numero|phone|tel|whats|contato|cargo|ctps|pis|rg|cpf|barras|barcode|ean|gtin|codigo_barras|referencia/i;

    // Campos que são só código de barras (histórico/numérico). Não aplicar em busca por nome.
    const BARCODE_OR_HISTORY_RE = /barras|barcode|ean|gtin|codigo_barras|pprod-barras/i;
    const PRODUCT_NAME_SEARCH_RE = /nome|descricao|descri[cç][aã]o|produto|fv-tv-barcode|erp-fv-tv__input--barcode/i;

    function randomToken() {
        return 'erp-' + Math.random().toString(36).slice(2, 10);
    }

    function fieldSignature(element) {
        return [
            element.name || '',
            element.id || '',
            element.getAttribute('wire:model') || '',
            element.getAttribute('wire:model.live.debounce.200ms') || '',
            element.getAttribute('wire:model.live.debounce.250ms') || '',
            element.getAttribute('placeholder') || '',
            element.className || '',
            element.getAttribute('for') || '',
            element.previousElementSibling?.textContent || '',
            element.closest('label')?.textContent || '',
        ].join(' ');
    }

    function hardenElement(element) {
        if (! element || element.nodeType !== 1) {
            return;
        }

        if (element.matches('form') || element.matches('[data-erp-form], .erp-pcad-form, .erp-produtos-form, .erp-produtos-window, .erp-vendedor-form-modal, .erp-usuario-form-modal, .erp-lookup-modal__window')) {
            element.setAttribute('autocomplete', 'off');
            element.setAttribute('data-erp-no-autofill', 'true');
            element.setAttribute('data-lpignore', 'true');
            element.setAttribute('data-1p-ignore', 'true');
            element.setAttribute('data-bwignore', 'true');
        }

        if (! element.matches('input, textarea, select')) {
            return;
        }

        if (element instanceof HTMLInputElement && IGNORE_TYPES.has(element.type)) {
            return;
        }

        // Chrome ignora autocomplete="off" em campos type="email" e dispara sugestões de endereço.
        if (element instanceof HTMLInputElement && element.type === 'email') {
            element.type = 'text';
            element.setAttribute('inputmode', 'email');
        }

        const isPassword = element instanceof HTMLInputElement && element.type === 'password';
        const signature = fieldSignature(element);
        const looksLikeCredentialOrAddress = isPassword || ADDRESS_OR_PROFILE_RE.test(signature);
        const allowsProductNameSearch = PRODUCT_NAME_SEARCH_RE.test(signature)
            || element.id === 'fv-tv-barcode'
            || element.classList.contains('erp-fv-tv__input--barcode');
        // Só força teclado numérico em campos exclusivos de barras — nunca na busca código/nome.
        const looksLikeBarcodeHistory = BARCODE_OR_HISTORY_RE.test(signature) && ! allowsProductNameSearch;
        const isFocused = document.activeElement === element;

        // Valor aleatório: Chrome costuma respeitar mais que "off" em endereço/perfil/histórico.
        // one-time-code reduz o histórico de valores digitados no Código de Barras.
        element.setAttribute(
            'autocomplete',
            looksLikeBarcodeHistory ? 'one-time-code' : (allowsProductNameSearch ? 'off' : randomToken()),
        );
        element.setAttribute('autocapitalize', looksLikeBarcodeHistory ? 'off' : 'characters');
        element.setAttribute('autocorrect', 'off');
        element.setAttribute('spellcheck', 'false');
        element.setAttribute('data-lpignore', 'true');
        element.setAttribute('data-1p-ignore', 'true');
        element.setAttribute('data-form-type', 'other');
        element.setAttribute('data-bwignore', 'true');
        element.setAttribute('data-google-password-manager', 'ignore');
        element.setAttribute('data-erp-no-autofill', '1');

        if (looksLikeCredentialOrAddress || looksLikeBarcodeHistory) {
            const currentName = element.getAttribute('name') || '';

            if (! currentName || ADDRESS_OR_PROFILE_RE.test(currentName) || BARCODE_OR_HISTORY_RE.test(currentName)) {
                element.setAttribute('name', randomToken());
            }
        }

        if (looksLikeBarcodeHistory && element instanceof HTMLInputElement) {
            element.setAttribute('inputmode', 'numeric');
            element.setAttribute('role', 'presentation');
        } else if (allowsProductNameSearch && element instanceof HTMLInputElement) {
            // Busca por nome: teclado texto e combobox acessível.
            element.removeAttribute('inputmode');
            if (element.getAttribute('role') === 'presentation') {
                element.setAttribute('role', 'combobox');
            }
        }

        if (element.dataset.erpNoAutofillBound === '1') {
            // Remorph do Livewire: não recoloca readonly se o usuário está digitando.
            if (isFocused) {
                element.removeAttribute('readonly');
            }

            return;
        }

        element.dataset.erpNoAutofillBound = '1';

        if (! (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
            return;
        }

        // Não trava o campo enquanto ele já tem foco (digitação / Livewire morph).
        if (! element.hasAttribute('readonly') && ! isFocused) {
            element.setAttribute('readonly', 'readonly');
        }

        const unlock = () => {
            element.removeAttribute('readonly');
        };

        // Desbloqueia só na interação — reduz popup de endereço/senha/histórico do Chrome.
        element.addEventListener('keydown', unlock, { once: false });
        element.addEventListener('pointerdown', unlock, { once: false });
        element.addEventListener('mousedown', unlock, { once: false });
        element.addEventListener('touchstart', unlock, { passive: true, once: false });
        element.addEventListener('paste', unlock, { once: false });

        if (! looksLikeCredentialOrAddress || looksLikeBarcodeHistory || allowsProductNameSearch) {
            element.addEventListener('focus', unlock, { once: false });
        }
    }

    function scan(root) {
        if (! root || root.nodeType !== 1) {
            return;
        }

        if (root.matches('form, input, textarea, select, [data-erp-form], .erp-pcad-form, .erp-produtos-form, .erp-produtos-window, .erp-vendedor-form-modal, .erp-usuario-form-modal, .erp-lookup-modal__window')) {
            hardenElement(root);
        }

        root.querySelectorAll('form, input, textarea, select, [data-erp-form], .erp-pcad-form, .erp-produtos-form, .erp-produtos-window, .erp-vendedor-form-modal, .erp-usuario-form-modal, .erp-lookup-modal__window').forEach(hardenElement);
    }

    function run() {
        scan(document.documentElement);
    }

    let scheduled = false;

    function scheduleScan(root) {
        if (root && root !== document.documentElement) {
            scan(root);
        }

        if (scheduled) {
            return;
        }

        scheduled = true;

        window.requestAnimationFrame(() => {
            scheduled = false;
            run();
        });
    }

    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('livewire:navigated', run);

    document.addEventListener('livewire:init', () => {
        if (! window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('morph.updated', ({ el }) => {
            scheduleScan(el);
        });

        window.Livewire.hook('element.updated', ({ el }) => {
            scheduleScan(el);
        });
    });

    if (! window.__erpNoBrowserHintsObserver) {
        window.__erpNoBrowserHintsObserver = true;

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        scheduleScan(node);
                    }
                }
            }
        });

        const startObserver = () => {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
                run();
            }
        };

        if (document.body) {
            startObserver();
        } else {
            document.addEventListener('DOMContentLoaded', startObserver);
        }
    }

    run();
})();
