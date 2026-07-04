window.ErpMasks = {
    digits(value) {
        return String(value ?? '').replace(/\D/g, '');
    },

    getPessoaTipo(input) {
        if (input?.dataset?.maskPessoa === 'fisica' || input?.dataset?.maskPessoa === 'juridica') {
            return input.dataset.maskPessoa;
        }

        const root = input.closest('.erp-pcad')
            ?? input.closest('.erp-pessoas-form-page')
            ?? input.closest('.erp-empresas-form-page')
            ?? document;
        const select = root.querySelector('#pcad-pessoa') ?? root.querySelector('#emp-pessoa');

        if (select?.value === 'fisica') {
            return 'fisica';
        }

        return 'juridica';
    },

    formatCpf(digits) {
        if (digits.length === 0) {
            return '';
        }

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return `${digits.slice(0, 3)}.${digits.slice(3)}`;
        }

        if (digits.length <= 9) {
            return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
        }

        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9, 11)}`;
    },

    formatCnpj(digits) {
        if (digits.length === 0) {
            return '';
        }

        if (digits.length <= 2) {
            return digits;
        }

        if (digits.length <= 5) {
            return `${digits.slice(0, 2)}.${digits.slice(2)}`;
        }

        if (digits.length <= 8) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
        }

        if (digits.length <= 12) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
        }

        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12, 14)}`;
    },

    formatCpfCnpj(value, pessoaTipo = 'juridica') {
        const maxDigits = pessoaTipo === 'fisica' ? 11 : 14;
        const digits = this.digits(value).slice(0, maxDigits);

        return pessoaTipo === 'fisica'
            ? this.formatCpf(digits)
            : this.formatCnpj(digits);
    },

    isValidCpf(value) {
        const digits = this.digits(value);

        if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
            return false;
        }

        for (let length = 9; length < 11; length += 1) {
            let sum = 0;

            for (let index = 0; index < length; index += 1) {
                sum += Number(digits[index]) * ((length + 1) - index);
            }

            const check = ((10 * sum) % 11) % 10;

            if (Number(digits[length]) !== check) {
                return false;
            }
        }

        return true;
    },

    isValidCnpj(value) {
        const digits = this.digits(value);

        if (digits.length !== 14 || /^(\d)\1{13}$/.test(digits)) {
            return false;
        }

        const weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        const weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for (let round = 0; round < 2; round += 1) {
            let sum = 0;
            const weights = round === 0 ? weightsFirst : weightsSecond;
            const limit = round === 0 ? 12 : 13;

            for (let index = 0; index < limit; index += 1) {
                sum += Number(digits[index]) * weights[index];
            }

            const remainder = sum % 11;
            const check = remainder < 2 ? 0 : 11 - remainder;

            if (Number(digits[12 + round]) !== check) {
                return false;
            }
        }

        return true;
    },

    documentoValidationMessage(input) {
        const digits = this.digits(input.value);

        if (digits === '') {
            return null;
        }

        const pessoaTipo = this.getPessoaTipo(input);
        const cpfOnly = input.dataset.maskPessoa === 'fisica';
        const cnpjOnly = input.dataset.maskPessoa === 'juridica';

        if (cpfOnly || pessoaTipo === 'fisica') {
            if (digits.length !== 11) {
                return 'Informe um CPF válido com 11 dígitos.';
            }

            return this.isValidCpf(digits) ? null : 'CPF inválido. Verifique os números digitados.';
        }

        if (cnpjOnly || pessoaTipo === 'juridica') {
            if (digits.length !== 14) {
                return 'Informe um CNPJ válido com 14 dígitos.';
            }

            return this.isValidCnpj(digits) ? null : 'CNPJ inválido. Verifique os números digitados.';
        }

        if (digits.length <= 11) {
            if (digits.length !== 11) {
                return 'Informe um CPF válido com 11 dígitos.';
            }

            return this.isValidCpf(digits) ? null : 'CPF inválido. Verifique os números digitados.';
        }

        if (digits.length !== 14) {
            return 'Informe um CNPJ válido com 14 dígitos.';
        }

        return this.isValidCnpj(digits) ? null : 'CNPJ inválido. Verifique os números digitados.';
    },

    setDocumentoValidationState(input, message = null) {
        const resolved = message === undefined
            ? this.documentoValidationMessage(input)
            : message;

        input.classList.toggle('erp-mask-doc--invalid', resolved !== null);

        if (resolved) {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('title', resolved);
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('title');
        }

        return resolved === null;
    },

    validateDocumentoInput(input) {
        if (input?.dataset?.mask !== 'cpf-cnpj') {
            return true;
        }

        return this.setDocumentoValidationState(input);
    },

    formatCep(value) {
        const digits = this.digits(value).slice(0, 8);

        return digits.replace(/^(\d{5})(\d{1,3})$/, '$1-$2');
    },

    formatPhone(value) {
        const digits = this.digits(value).slice(0, 11);

        if (digits.length === 0) {
            return '';
        }

        if (digits.length <= 10) {
            return digits
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
        }

        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    },

    formatMobilePhone(value) {
        return this.formatPhone(this.digits(value).slice(0, 11));
    },

    mobileValidationMessage(input) {
        if (input?.dataset?.mask !== 'mobile-phone') {
            return null;
        }

        const digits = this.digits(input.value);

        if (digits === '') {
            return null;
        }

        if (digits.length !== 11) {
            return 'Informe o celular com DDD e 11 dígitos.';
        }

        return null;
    },

    setMobileValidationState(input, message = null) {
        const resolved = message === undefined
            ? this.mobileValidationMessage(input)
            : message;

        input.classList.toggle('erp-mask-doc--invalid', resolved !== null);

        if (resolved) {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('title', resolved);
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('title');
        }

        return resolved === null;
    },

    validateMobileInput(input) {
        if (input?.dataset?.mask !== 'mobile-phone') {
            return true;
        }

        return this.setMobileValidationState(input);
    },

    formatDecimalFromDigits(digits, decimals = 2) {
        if (! digits) {
            return '';
        }

        if (decimals === 0) {
            return String(parseInt(digits, 10) || 0);
        }

        const padded = digits.padStart(decimals + 1, '0');
        const intPart = padded.slice(0, -decimals).replace(/^0+(?=\d)/, '') || '0';
        const decPart = padded.slice(-decimals);

        return `${intPart},${decPart}`;
    },

    /** Valor monetário digitado normalmente (221 = R$ 221,00), não estilo caixa/PDV. */
    formatMoneyBr(value, maxDecimals = 2) {
        let raw = String(value ?? '').trim();

        if (raw === '') {
            return '';
        }

        if (raw.includes('.') && ! raw.includes(',')) {
            raw = raw.replace('.', ',');
        }

        raw = raw.replace(/[^\d,]/g, '');

        const commaIndex = raw.indexOf(',');
        let intPart;
        let decPart = '';

        if (commaIndex === -1) {
            intPart = raw;
        } else {
            intPart = raw.slice(0, commaIndex);
            decPart = raw.slice(commaIndex + 1).replace(/,/g, '');
        }

        intPart = intPart.replace(/^0+(?=\d)/, '');

        if (commaIndex === -1) {
            return intPart === '' ? '' : intPart;
        }

        decPart = decPart.slice(0, maxDecimals);

        return `${intPart === '' ? '0' : intPart},${decPart}`;
    },

    finalizeMoneyBr(value, maxDecimals = 2) {
        const formatted = this.formatMoneyBr(value, maxDecimals);

        if (formatted === '') {
            return `0,${'0'.repeat(maxDecimals)}`;
        }

        if (! formatted.includes(',')) {
            return `${formatted},${'0'.repeat(maxDecimals)}`;
        }

        const [intPart, decPart = ''] = formatted.split(',');

        return `${intPart || '0'},${decPart.padEnd(maxDecimals, '0').slice(0, maxDecimals)}`;
    },

    finalizeInteger(value) {
        const formatted = this.formatInteger(value);

        return formatted === '' ? '0' : formatted;
    },

    isBrDecimalMask(type) {
        return type === 'money-br' || type === 'percent-br';
    },

    finalizeMaskValue(input) {
        const type = input.dataset.mask;

        if (this.isBrDecimalMask(type)) {
            return this.finalizeMoneyBr(input.value);
        }

        if (type === 'integer') {
            return this.finalizeInteger(input.value);
        }

        return input.value;
    },

    formatMoney(value) {
        const digits = this.digits(value).slice(0, 13);

        return this.formatDecimalFromDigits(digits, 2);
    },

    formatPercent(value) {
        return this.formatMoney(value);
    },

    formatInteger(value) {
        const digits = this.digits(value).slice(0, 12);

        if (! digits) {
            return '';
        }

        return String(parseInt(digits, 10) || 0);
    },

    formatDecimal3(value) {
        const digits = this.digits(value).slice(0, 13);

        return this.formatDecimalFromDigits(digits, 3);
    },

    /** Quantidade digitada normalmente (1 = uma unidade), não estilo monetário. */
    formatQuantity3(value, maxDecimals = 3) {
        let raw = String(value ?? '').trim();

        if (raw === '') {
            return '';
        }

        if (raw.includes('.') && ! raw.includes(',')) {
            raw = raw.replace('.', ',');
        }

        raw = raw.replace(/[^\d,]/g, '');

        const commaIndex = raw.indexOf(',');
        let intPart;
        let decPart = '';

        if (commaIndex === -1) {
            intPart = raw;
        } else {
            intPart = raw.slice(0, commaIndex);
            decPart = raw.slice(commaIndex + 1).replace(/,/g, '');
        }

        intPart = intPart.replace(/^0+(?=\d)/, '');

        if (commaIndex === -1) {
            return intPart === '' ? '' : intPart;
        }

        decPart = decPart.slice(0, maxDecimals);

        return `${intPart === '' ? '0' : intPart},${decPart}`;
    },

    formatDateBr(value) {
        const digits = this.digits(value).slice(0, 8);

        if (! digits) {
            return '';
        }

        if (digits.length <= 2) {
            return digits;
        }

        if (digits.length <= 4) {
            return `${digits.slice(0, 2)}/${digits.slice(2)}`;
        }

        return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    },

    formatDigits(value, max = 14) {
        return this.digits(value).slice(0, max);
    },

    formatByType(type, value, input = null) {
        switch (type) {
            case 'cpf-cnpj':
                return this.formatCpfCnpj(value, input ? this.getPessoaTipo(input) : 'juridica');
            case 'cep':
                return this.formatCep(value);
            case 'phone':
                return this.formatPhone(value);
            case 'mobile-phone':
                return this.formatMobilePhone(value);
            case 'money':
                return this.formatMoney(value);
            case 'money-br':
                return this.formatMoneyBr(value);
            case 'percent':
                return this.formatPercent(value);
            case 'percent-br':
                return this.formatMoneyBr(value);
            case 'integer':
                return this.formatInteger(value);
            case 'decimal3':
                return this.formatDecimal3(value);
            case 'quantity3':
                return this.formatQuantity3(value);
            case 'date-br':
                return this.formatDateBr(value);
            case 'digits':
                return this.formatDigits(value, parseInt(input?.dataset.maxDigits ?? '14', 10));
            default:
                return value ?? '';
        }
    },

    getWireField(input) {
        if (input.dataset.wireField) {
            return input.dataset.wireField;
        }

        for (const attribute of input.attributes) {
            if (attribute.name.startsWith('wire:model')) {
                return attribute.value;
            }
        }

        return null;
    },

    getLivewireComponent(input) {
        const root = input.closest('[wire\\:id]');

        if (! root || ! window.Livewire) {
            return null;
        }

        return window.Livewire.find(root.getAttribute('wire:id'));
    },

    getWireApi(component) {
        if (! component) {
            return null;
        }

        // Livewire 3: use o componente retornado por Livewire.find().
        // component.$wire.call(...) pode disparar MethodNotFoundException ($wire).
        return component;
    },

    readWireValue(component, field) {
        const wire = this.getWireApi(component);

        if (! wire || typeof wire.get !== 'function') {
            return '';
        }

        const value = wire.get(field);

        return value === null || value === undefined ? '' : String(value);
    },

    syncLivewire(input, value, live = false) {
        const field = this.getWireField(input);

        if (! field || value === undefined || value === null) {
            return;
        }

        const component = this.getLivewireComponent(input);
        const wire = this.getWireApi(component);

        if (! wire || typeof wire.set !== 'function') {
            return;
        }

        if (input.dataset.erpMaskSynced === value) {
            return;
        }

        input.dataset.erpMaskSynced = value;
        wire.set(field, value, live);
    },

    apply(input, options = {}) {
        const type = input.dataset.mask;

        if (! type) {
            return;
        }

        const formatted = this.formatByType(type, input.value, input);

        if (input.value !== formatted) {
            input.value = formatted;
        }

        const shouldSync = options.sync !== false;
        const allowEmptySync = options.allowEmptySync === true;

        if (shouldSync && (formatted !== '' || allowEmptySync)) {
            this.syncLivewire(input, formatted, options.live === true);
        }
    },

    bindInput(input) {
        if (input.dataset.mask === 'date-br') {
            return;
        }

        if (input.dataset.erpMaskBound === '1') {
            return;
        }

        input.dataset.erpMaskBound = '1';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');

        input.addEventListener('input', () => {
            if (input.dataset.mask === 'cpf-cnpj') {
                this.setDocumentoValidationState(input, null);
            }

            if (input.dataset.mask === 'mobile-phone') {
                this.setMobileValidationState(input, null);
            }

            this.apply(input);
        });
        input.addEventListener(
            'blur',
            () => {
                if (input.dataset.mask === 'quantity3' && input.value.endsWith(',')) {
                    input.value = input.value.slice(0, -1);
                }

                if (this.isBrDecimalMask(input.dataset.mask) || input.dataset.mask === 'integer') {
                    input.value = this.finalizeMaskValue(input);
                }

                this.apply(input, { allowEmptySync: true, live: true });

                if (input.dataset.mask === 'cpf-cnpj') {
                    this.validateDocumentoInput(input);
                }

                if (input.dataset.mask === 'mobile-phone') {
                    this.validateMobileInput(input);
                }
            },
            true,
        );
        input.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = (event.clipboardData || window.clipboardData).getData('text');
            input.value = pasted;
            this.apply(input, { allowEmptySync: true });
        });

        if (this.isBrDecimalMask(input.dataset.mask) || input.dataset.mask === 'integer') {
            input.value = this.finalizeMaskValue(input);
            this.apply(input);
        } else if (input.value) {
            this.apply(input);
        }

        if (input.dataset.mask === 'mobile-phone') {
            input.setAttribute('maxlength', '16');
        }
    },

    bindPessoaTipoSelect(root) {
        const pairs = [
            ['#pcad-pessoa', '#pcad-cpf'],
            ['#emp-pessoa', '#emp-cnpj'],
        ];

        pairs.forEach(([selectSelector, cpfInputSelector]) => {
            const select = root.querySelector(selectSelector);
            const cpfInput = root.querySelector(cpfInputSelector);

            if (! select || ! cpfInput) {
                return;
            }

            if (select.dataset.erpPessoaBound === '1') {
                return;
            }

            select.dataset.erpPessoaBound = '1';

            const reformatDocument = () => {
                this.apply(cpfInput);
                this.setDocumentoValidationState(cpfInput, null);
            };

            select.addEventListener('change', reformatDocument);
            select.addEventListener('input', reformatDocument);
        });
    },

    init(root = document) {
        this.refresh(root);
        this.bindPessoaTipoSelect(root);
    },

    refresh(root = document) {
        root.querySelectorAll('[data-mask]').forEach((input) => {
            if (input.dataset.mask === 'date-br') {
                return;
            }

            const field = this.getWireField(input);
            const component = field ? this.getLivewireComponent(input) : null;
            const wireValue = component ? this.readWireValue(component, field) : '';

            if (wireValue !== '' && wireValue !== input.value) {
                if (document.activeElement === input) {
                    return;
                }

                input.value = wireValue;
                delete input.dataset.erpMaskSynced;
            }

            if (input.dataset.erpMaskBound !== '1') {
                this.bindInput(input);
            } else if (input.value || this.isBrDecimalMask(input.dataset.mask) || input.dataset.mask === 'integer') {
                if (this.isBrDecimalMask(input.dataset.mask) || input.dataset.mask === 'integer') {
                    input.value = this.finalizeMaskValue(input);
                }

                this.apply(input, { sync: false });
            }
        });

        if (window.ErpDatepicker) {
            window.ErpDatepicker.refresh(root);
        }
    },
};

function initErpMasks(root) {
    window.ErpMasks.init(root);
}
