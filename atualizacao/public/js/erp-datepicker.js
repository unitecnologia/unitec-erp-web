window.ErpDatepicker = {
    calendarIcon: "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e5a9e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E\")",

    selectors: [
        'input[data-erp-date]',
        'input[data-mask="date-br"]',
        'input[data-wire-field]',
        'input[type="date"]',
    ].join(', '),

    getWireFormat(input) {
        if (input.dataset.erpDateWire === 'br' || input.dataset.erpDateWire === 'iso') {
            return input.dataset.erpDateWire;
        }

        if (input.dataset.mask === 'date-br') {
            return 'br';
        }

        return 'iso';
    },

    digitsOnly(value) {
        return String(value ?? '').replace(/\D/g, '');
    },

    isIsoDateString(value) {
        return /^\d{4}-\d{2}-\d{2}$/.test(String(value ?? '').trim());
    },

    isBrDisplayDate(value) {
        return /^\d{2}\/\d{2}\/\d{4}$/.test(String(value ?? '').trim());
    },

    coerceInputValue(value) {
        const trimmed = String(value ?? '').trim();

        if (! trimmed) {
            return '';
        }

        if (this.isIsoDateString(trimmed)) {
            if (window.flatpickr) {
                const parsed = window.flatpickr.parseDate(trimmed, 'Y-m-d');

                if (parsed) {
                    return this.formatDisplay(parsed);
                }
            }

            const [year, month, day] = trimmed.split('-');

            return `${day}/${month}/${year}`;
        }

        return trimmed;
    },

    formatDisplayValue(value, wireFormat = 'iso') {
        const coerced = this.coerceInputValue(value);

        // Nunca aplicar máscara dd/mm em ISO cru (ex.: 2026-07-01 → 20/26/0701).
        if (this.isIsoDateString(coerced)) {
            const [year, month, day] = coerced.split('-');

            return `${day}/${month}/${year}`;
        }

        if (this.isBrDisplayDate(coerced)) {
            return coerced;
        }

        if (window.ErpMasks?.formatDateBr) {
            return window.ErpMasks.formatDateBr(coerced);
        }

        const digits = this.digitsOnly(coerced).slice(0, 8);

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

    applyInputStyles(input) {
        input.classList.add('erp-date-input');
        input.style.paddingRight = '2.25rem';
        input.style.backgroundColor = 'var(--erp-bg-panel, #ffffff)';
        input.style.backgroundImage = this.calendarIcon;
        input.style.backgroundRepeat = 'no-repeat';
        input.style.backgroundPosition = 'right 0.5rem center';
        input.style.backgroundSize = '1rem 1rem';
        input.style.cursor = 'text';
    },

    parseValue(value, wireFormat) {
        if (! value || ! window.flatpickr) {
            return null;
        }

        const trimmed = this.coerceInputValue(value);

        if (! trimmed) {
            return null;
        }

        if (wireFormat === 'iso') {
            if (trimmed.includes('/')) {
                return window.flatpickr.parseDate(trimmed, 'd/m/Y')
                    ?? window.flatpickr.parseDate(trimmed, 'Y-m-d');
            }

            return window.flatpickr.parseDate(trimmed, 'Y-m-d')
                ?? window.flatpickr.parseDate(trimmed, 'd/m/Y');
        }

        return window.flatpickr.parseDate(trimmed, 'd/m/Y')
            ?? window.flatpickr.parseDate(trimmed, 'Y-m-d');
    },

    formatForWire(date, wireFormat) {
        if (! date || ! window.flatpickr) {
            return '';
        }

        return wireFormat === 'iso'
            ? window.flatpickr.formatDate(date, 'Y-m-d')
            : window.flatpickr.formatDate(date, 'd/m/Y');
    },

    formatDisplay(date) {
        if (! date || ! window.flatpickr) {
            return '';
        }

        return window.flatpickr.formatDate(date, 'd/m/Y');
    },

    isCompleteDisplayDate(value) {
        return this.isBrDisplayDate(value);
    },

    isEmptyInput(input) {
        const value = String(input?.value ?? '').trim();

        return value === '' || this.digitsOnly(value) === '';
    },

    sameDay(left, right) {
        if (! left || ! right) {
            return false;
        }

        return left.getFullYear() === right.getFullYear()
            && left.getMonth() === right.getMonth()
            && left.getDate() === right.getDate();
    },

    readWireValue(input) {
        if (! window.ErpMasks) {
            return '';
        }

        const field = window.ErpMasks.getWireField(input);
        const component = window.ErpMasks.getLivewireComponent(input);

        if (! field || ! component) {
            return '';
        }

        const value = window.ErpMasks.readWireValue(component, field);

        return value === null || value === undefined ? '' : String(value);
    },

    readInitialAttribute(input) {
        const initial = String(input?.dataset?.erpDateInitial ?? '').trim();

        return initial === '' ? '' : initial;
    },

    resolveInitialValue(input, wireFormat) {
        const wireValue = String(this.readWireValue(input) ?? '').trim();
        const attributeValue = this.readInitialAttribute(input);
        const raw = wireValue || attributeValue || String(input?.value ?? '').trim();

        if (raw === '') {
            return {
                raw: '',
                parsed: null,
                fromWire: false,
            };
        }

        const parsed = this.parseValue(raw, wireFormat);

        return {
            raw,
            parsed,
            fromWire: wireValue !== '',
        };
    },

    ensureFieldWrap(input) {
        let field = input.closest('.erp-date-field');

        if (field) {
            let toggle = field.querySelector('.erp-date-field__toggle');

            if (! toggle) {
                toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'erp-date-field__toggle';
                toggle.setAttribute('tabindex', '-1');
                toggle.setAttribute('aria-label', 'Abrir calendário');
                field.appendChild(toggle);
            }

            return {
                field,
                toggle,
            };
        }

        field = document.createElement('div');
        field.className = 'erp-date-field';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'erp-date-field__toggle';
        toggle.setAttribute('tabindex', '-1');
        toggle.setAttribute('aria-label', 'Abrir calendário');

        input.parentNode.insertBefore(field, input);
        field.appendChild(input);
        field.appendChild(toggle);

        return { field, toggle };
    },

    scheduleOpen(picker) {
        window.setTimeout(() => {
            if (! picker.isOpen) {
                picker.open();
            }
        }, 0);
    },

    clearLocal(input, picker) {
        input.value = '';

        if (picker?.selectedDates?.length > 0) {
            picker._erpSkipClearOnChange = true;
            picker.clear(false);
            delete picker._erpSkipClearOnChange;
        }
    },

    syncEmpty(input) {
        input.value = '';
        input.dataset.erpDateSynced = '';
        this.syncLivewire(input, '', true);
    },

    clearValue(input, picker) {
        this.syncEmpty(input);
        this.clearLocal(input, picker);
    },

    applyLocalDate(input, picker, date) {
        if (! date) {
            this.clearLocal(input, picker);

            return;
        }

        picker.setDate(date, false);
        input.value = this.formatDisplay(date);
    },

    applySelectedDate(input, picker, date, wireFormat, syncWire = false) {
        if (! date) {
            if (syncWire) {
                this.clearValue(input, picker);
            } else {
                this.clearLocal(input, picker);
            }

            return;
        }

        this.applyLocalDate(input, picker, date);

        if (syncWire) {
            this.syncLivewire(input, this.formatForWire(date, wireFormat));
        }
    },

    resolveDate(input, picker, wireFormat) {
        const selected = picker?.selectedDates?.[0];

        if (selected) {
            return selected;
        }

        const synced = input.dataset.erpDateSynced;

        if (synced) {
            const parsedSynced = this.parseValue(synced, wireFormat);

            if (parsedSynced) {
                return parsedSynced;
            }
        }

        const wireValue = this.readWireValue(input);

        if (wireValue) {
            const parsedWire = this.parseValue(wireValue, wireFormat);

            if (parsedWire) {
                return parsedWire;
            }
        }

        const attributeValue = this.readInitialAttribute(input);

        if (attributeValue) {
            const parsedAttribute = this.parseValue(attributeValue, wireFormat);

            if (parsedAttribute) {
                return parsedAttribute;
            }
        }

        return this.parseValue(input.value, wireFormat);
    },

    normalizeDisplay(input, picker, wireFormat) {
        if (! input || ! picker) {
            return;
        }

        const parsed = this.resolveDate(input, picker, wireFormat);

        if (! parsed) {
            if (this.isEmptyInput(input) && picker.selectedDates?.length > 0) {
                this.clearLocal(input, picker);
            }

            return;
        }

        const display = this.formatDisplay(parsed);

        if (input.value !== display) {
            input.value = display;
        }

        if (! this.sameDay(picker.selectedDates?.[0], parsed)) {
            picker.setDate(parsed, false);
        }
    },

    syncLivewire(input, wireValue, force = false) {
        if (! window.ErpMasks) {
            return;
        }

        if (! force && input.dataset.erpDateSynced === wireValue) {
            return;
        }

        input.dataset.erpDateSynced = wireValue;
        window.ErpMasks.syncLivewire(input, wireValue, true);
    },

    readCommittedWireValue(input, picker, wireFormat) {
        const parsed = this.resolveDate(input, picker, wireFormat);

        return parsed ? this.formatForWire(parsed, wireFormat) : '';
    },

    commitPeriodGroupInput(el) {
        const format = this.getWireFormat(el);

        if (el._flatpickr) {
            this.commitValue(el, el._flatpickr, format);

            return;
        }

        const typed = String(el.value ?? '').trim();

        if (typed === '' || this.digitsOnly(typed) === '') {
            this.syncLivewire(el, '', true);

            return;
        }

        const parsed = this.parseValue(typed, format);

        if (parsed) {
            this.syncLivewire(el, this.formatForWire(parsed, format), true);

            return;
        }

        if (this.isCompleteDisplayDate(typed)) {
            this.syncLivewire(el, typed, true);
        }
    },

    commitPeriodGroup(input) {
        const group = input.closest('[data-erp-date-group]');

        if (! group) {
            return false;
        }

        group.querySelectorAll('input[data-wire-field]').forEach((el) => {
            this.commitPeriodGroupInput(el);
        });

        const applyMethod = group.dataset.erpDateApplyMethod;

        if (applyMethod && window.ErpMasks) {
            const component = window.ErpMasks.getLivewireComponent(input);

            if (component && typeof component.call === 'function') {
                // Sincroniza periodoDe/periodoAte via set() e aplica no mesmo request.
                component.call(applyMethod);
            }
        }

        return true;
    },

    shouldAutoApplyPeriodGroup(input) {
        const group = input?.closest?.('[data-erp-date-group]');

        return group?.dataset?.erpDateAutoApply === '1';
    },

    maybeAutoApplyPeriodGroup(input) {
        if (! this.shouldAutoApplyPeriodGroup(input)) {
            return false;
        }

        const typed = String(input?.value ?? '').trim();

        if (
            typed !== ''
            && ! this.isCompleteDisplayDate(typed)
            && ! (input._flatpickr?.selectedDates?.length > 0)
        ) {
            return false;
        }

        return this.commitPeriodGroup(input);
    },

    applyPeriodGroupFromButton(button) {
        const group = button?.closest?.('[data-erp-date-group]');

        if (! group) {
            return false;
        }

        const input = group.querySelector('input[data-wire-field]');

        if (! input) {
            return false;
        }

        return this.commitPeriodGroup(input);
    },

    commitValue(input, picker, wireFormat) {
        const typed = input.value.trim();
        const digits = this.digitsOnly(typed);

        if (typed === '' || digits === '') {
            if (picker.selectedDates?.length > 0 || input.dataset.erpDateSynced) {
                this.clearValue(input, picker);
            } else {
                this.syncEmpty(input);
            }

            return;
        }

        const parsed = this.parseValue(typed, wireFormat);

        if (! parsed) {
            if (this.isCompleteDisplayDate(typed)) {
                this.syncLivewire(input, typed, true);
            }

            return;
        }

        this.applySelectedDate(input, picker, parsed, wireFormat, true);
    },

    bindTypingMask(input, picker, wireFormat) {
        if (input._erpDateHandlers) {
            input.removeEventListener('input', input._erpDateHandlers.input);
            input.removeEventListener('blur', input._erpDateHandlers.blur);
            input.removeEventListener('paste', input._erpDateHandlers.paste);
            input.removeEventListener('keydown', input._erpDateHandlers.keydown);
        }

        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('maxlength', '10');

        const self = this;

        const onInput = () => {
            const formatted = self.formatDisplayValue(input.value, wireFormat);

            if (input.value !== formatted) {
                input.value = formatted;
            }

            if (formatted === '' || self.digitsOnly(formatted) === '') {
                self.clearLocal(input, picker);

                return;
            }

            if (self.isCompleteDisplayDate(formatted)) {
                const parsed = self.parseValue(formatted, wireFormat);

                if (parsed) {
                    picker.setDate(parsed, false);
                    self.maybeAutoApplyPeriodGroup(input);
                }
            }
        };

        const onBlur = () => {
            window.setTimeout(() => {
                if (picker.isOpen || document.activeElement === input) {
                    return;
                }

                self.commitValue(input, picker, wireFormat);
                self.maybeAutoApplyPeriodGroup(input);
            }, 0);
        };

        const onPaste = (event) => {
            event.preventDefault();
            const pasted = (event.clipboardData || window.clipboardData).getData('text');
            input.value = self.formatDisplayValue(pasted, wireFormat);
            self.commitValue(input, picker, wireFormat);
        };

        const onKeydown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();

                if (self.commitPeriodGroup(input)) {
                    return;
                }

                self.commitValue(input, picker, wireFormat);

                return;
            }

            if (event.key !== 'Backspace' && event.key !== 'Delete') {
                return;
            }

            window.setTimeout(() => {
                if (self.isEmptyInput(input)) {
                    self.clearLocal(input, picker);
                }
            }, 0);
        };

        input._erpDateHandlers = { input: onInput, blur: onBlur, paste: onPaste, keydown: onKeydown };
        input.addEventListener('input', onInput);
        input.addEventListener('blur', onBlur);
        input.addEventListener('paste', onPaste);
        input.addEventListener('keydown', onKeydown);
        input.dataset.erpDateMaskBound = '1';
    },

    isIconZoneClick(input, event) {
        if (! input || ! event) {
            return false;
        }

        const rect = input.getBoundingClientRect();
        const zone = Math.max(28, Math.min(44, rect.width * 0.35));

        return event.clientX >= (rect.right - zone);
    },

    bindOpenHandlers(input, picker, field, toggle) {
        if (input._erpDateOpenHandlers) {
            input.removeEventListener('pointerdown', input._erpDateOpenHandlers.inputPointerDown);
            input.removeEventListener('mousedown', input._erpDateOpenHandlers.inputMousedown);
            input.removeEventListener('click', input._erpDateOpenHandlers.inputClick);

            if (input._erpDateToggle) {
                input._erpDateToggle.removeEventListener('pointerdown', input._erpDateOpenHandlers.togglePointerDown);
                input._erpDateToggle.removeEventListener('mousedown', input._erpDateOpenHandlers.mousedown);
                input._erpDateToggle.removeEventListener('click', input._erpDateOpenHandlers.click);
            }
        }

        const self = this;
        const openOnlyViaIcon = input.dataset.erpDateOpen === 'icon';

        const openCalendar = (event) => {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            self.openPicker(input);
        };

        const onInputClick = (event) => {
            if (event.button !== 0) {
                return;
            }

            if (openOnlyViaIcon) {
                if (self.isIconZoneClick(input, event)) {
                    openCalendar(event);
                }

                return;
            }

            openCalendar(event);
        };

        const onToggleMouseDown = (event) => {
            // Evita blur do input; não abre aqui (senão o Flatpickr fecha no mesmo gesto).
            if (event.button === 0) {
                event.preventDefault();
            }
        };

        const onToggleClick = (event) => {
            openCalendar(event);
        };

        input._erpDateOpenHandlers = {
            inputPointerDown: onInputClick,
            inputMousedown: onInputClick,
            inputClick: onInputClick,
            togglePointerDown: onToggleClick,
            mousedown: onToggleMouseDown,
            click: onToggleClick,
        };

        if (! openOnlyViaIcon) {
            input.addEventListener('click', onInputClick);
        } else {
            input.addEventListener('click', onInputClick);
        }

        if (toggle) {
            toggle.addEventListener('mousedown', onToggleMouseDown);
            toggle.addEventListener('click', onToggleClick);
        }

        input._erpDateField = field;
        input._erpDateToggle = toggle;
    },

    destroy(input) {
        if (input._erpDateOpenHandlers) {
            input.removeEventListener('pointerdown', input._erpDateOpenHandlers.inputPointerDown);
            input.removeEventListener('mousedown', input._erpDateOpenHandlers.inputMousedown);
            input.removeEventListener('click', input._erpDateOpenHandlers.inputClick);

            if (input._erpDateToggle) {
                input._erpDateToggle.removeEventListener('pointerdown', input._erpDateOpenHandlers.togglePointerDown);
                input._erpDateToggle.removeEventListener('mousedown', input._erpDateOpenHandlers.mousedown);
                input._erpDateToggle.removeEventListener('click', input._erpDateOpenHandlers.click);
            }

            delete input._erpDateOpenHandlers;
        }

        delete input._erpDateField;
        delete input._erpDateToggle;

        if (input._erpDateHandlers) {
            input.removeEventListener('input', input._erpDateHandlers.input);
            input.removeEventListener('blur', input._erpDateHandlers.blur);
            input.removeEventListener('paste', input._erpDateHandlers.paste);
            input.removeEventListener('keydown', input._erpDateHandlers.keydown);
            delete input._erpDateHandlers;
        }

        if (input._flatpickr) {
            input._flatpickr.destroy();
            input._flatpickr = null;
        }

        delete input.dataset.erpDateBound;
        delete input.dataset.erpDateMaskBound;
        delete input.dataset.erpDateSynced;
    },

    prepInput(input) {
        if (! input || input.disabled || input.readOnly) {
            return;
        }

        if (input.hasAttribute('data-erp-native-date') || input.dataset.erpDateSkip === '1') {
            return;
        }

        if (input.type === 'date') {
            input.type = 'text';
        }

        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('placeholder', 'dd/mm/aaaa');
        input.classList.add('erp-date-input');
        input.dataset.erpDatePrepped = '1';
        this.applyInputStyles(input);
    },

    prepAll(root = document) {
        if (typeof window.__erpPrepDateInputs === 'function') {
            window.__erpPrepDateInputs(root);
        }

        if (! root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll(this.selectors).forEach((input) => {
            this.prepInput(input);
        });
    },

    bindInput(input) {
        if (input.hasAttribute('data-erp-native-date') || input.dataset.erpDateSkip === '1') {
            return;
        }

        this.prepInput(input);

        if (! window.flatpickr || input.disabled || input.readOnly) {
            return;
        }

        const wireFormat = this.getWireFormat(input);

        if (input.dataset.erpDateBound === '1' && input._flatpickr) {
            const pickerAlive = Boolean(
                input._flatpickr.calendarContainer
                && input._flatpickr.calendarContainer.isConnected
            );
            const shouldRebind = ! pickerAlive
                || (this.isEmptyInput(input) && this.readInitialAttribute(input) !== '');

            if (! shouldRebind) {
                this.normalizeDisplay(input, input._flatpickr, wireFormat);
                const { field, toggle } = this.ensureFieldWrap(input);
                this.bindOpenHandlers(input, input._flatpickr, field, toggle);

                return;
            }
        }

        this.destroy(input);

        const initial = this.resolveInitialValue(input, wireFormat);
        const parsedInitial = initial.parsed;

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('placeholder', 'dd/mm/aaaa');
        this.applyInputStyles(input);

        const { field, toggle } = this.ensureFieldWrap(input);

        if (! input.dataset.erpDateWire) {
            input.dataset.erpDateWire = wireFormat;
        }

        const self = this;

        let picker;

        const openOnlyViaIcon = input.dataset.erpDateOpen === 'icon';

        try {
            picker = window.flatpickr(input, {
                locale: window.flatpickr.l10ns.pt ?? 'pt',
                dateFormat: 'd/m/Y',
                allowInput: true,
                disableMobile: true,
                clickOpens: ! openOnlyViaIcon,
                appendTo: document.body,
                ignoredFocusElements: toggle ? [toggle] : [],
                defaultDate: parsedInitial ?? undefined,
                onChange(selectedDates) {
                    if (picker._erpSkipClearOnChange) {
                        return;
                    }

                    self.applySelectedDate(
                        input,
                        picker,
                        selectedDates[0] ?? null,
                        wireFormat,
                        true,
                    );
                    self.maybeAutoApplyPeriodGroup(input);
                },
                onClose(_selectedDates, _dateStr, instance) {
                    if (self.isEmptyInput(instance.input)) {
                        return;
                    }

                    self.commitValue(instance.input, instance, wireFormat);
                    self.maybeAutoApplyPeriodGroup(instance.input);
                },
            });
        } catch (error) {
            console.error('[ErpDatepicker] Falha ao inicializar Flatpickr.', error);

            return;
        }

        input._flatpickr = picker;

        if (parsedInitial) {
            this.applyLocalDate(input, picker, parsedInitial);
            input.dataset.erpDateSynced = this.formatForWire(parsedInitial, wireFormat);

            if (! initial.fromWire) {
                this.syncLivewire(input, input.dataset.erpDateSynced, false);
            }
        } else if (document.activeElement !== input) {
            // Evita apagar digitação em andamento quando o Livewire remonta o campo.
            input.value = '';
        }

        this.bindTypingMask(input, picker, wireFormat);
        this.bindOpenHandlers(input, picker, field, toggle);
        input.dataset.erpDateBound = '1';
    },

    openPicker(input) {
        if (! input) {
            return;
        }

        // Flatpickr fecha no mousedown "fora". Se abrirmos no mesmo gesto, abre e fecha
        // na hora — por isso o 1º clique no ícone parecia não funcionar.
        const run = (attempt = 0) => {
            if (! window.flatpickr) {
                if (attempt < 25) {
                    window.setTimeout(() => run(attempt + 1), 40);
                }

                return;
            }

            this.bindInput(input);
            const picker = input._flatpickr;

            if (! picker || typeof picker.open !== 'function') {
                if (attempt < 15) {
                    window.setTimeout(() => run(attempt + 1), 40);
                }

                return;
            }

            if (! picker.isOpen) {
                picker.open();
            }
        };

        window.setTimeout(() => run(0), 0);
        window.setTimeout(() => run(0), 40);
    },

    resolveDateInputFromEvent(event) {
        const target = event?.target;

        if (! (target instanceof Element)) {
            return null;
        }

        const toggle = target.closest('.erp-date-field__toggle');

        if (toggle) {
            const field = toggle.closest('.erp-date-field');
            const input = field?.querySelector(
                'input[data-erp-date], input[data-mask="date-br"], input.erp-date-input'
            );

            return input instanceof HTMLInputElement ? input : null;
        }

        if (target instanceof HTMLInputElement) {
            const isDateInput = target.matches(
                'input[data-erp-date], input[data-mask="date-br"], input.erp-date-input'
            );

            if (! isDateInput) {
                return null;
            }

            if (target.dataset.erpDateOpen === 'icon') {
                return this.isIconZoneClick(target, event) ? target : null;
            }

            return target;
        }

        return null;
    },

    ensureGlobalOpenDelegation() {
        if (window.__erpDatepickerOpenDelegated) {
            return;
        }

        window.__erpDatepickerOpenDelegated = true;

        const self = this;

        const warmInput = (input) => {
            if (input && ! input.disabled && ! input.readOnly) {
                self.bindInput(input);
            }
        };

        // Pré-liga ao passar o mouse / focar.
        document.addEventListener(
            'pointerover',
            (event) => {
                const target = event.target;

                if (! (target instanceof Element)) {
                    return;
                }

                const field = target.closest('.erp-date-field');
                const input = field?.querySelector(
                    'input[data-erp-date], input[data-mask="date-br"], input.erp-date-input'
                ) || (
                    target instanceof HTMLInputElement
                    && target.matches('input[data-erp-date], input[data-mask="date-br"], input.erp-date-input')
                        ? target
                        : null
                );

                warmInput(input);
            },
            true
        );

        document.addEventListener(
            'focusin',
            (event) => {
                const target = event.target;

                if (
                    target instanceof HTMLInputElement
                    && target.matches('input[data-erp-date], input[data-mask="date-br"], input.erp-date-input')
                ) {
                    warmInput(target);
                }
            },
            true
        );

        // Só no click (depois do mousedown). Abrir no pointerdown faz o Flatpickr fechar na hora.
        document.addEventListener(
            'click',
            (event) => {
                if (event.button !== 0) {
                    return;
                }

                const input = self.resolveDateInputFromEvent(event);

                if (! input || input.disabled || input.readOnly) {
                    return;
                }

                const fromToggle = Boolean(event.target.closest?.('.erp-date-field__toggle'));
                const iconMode = input.dataset.erpDateOpen === 'icon';

                if (! fromToggle && ! (iconMode && self.isIconZoneClick(input, event))) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                self.openPicker(input);
            },
            true
        );
    },
    commitAllIn(root = document) {
        root.querySelectorAll(this.selectors).forEach((input) => {
            if (input._flatpickr) {
                this.commitValue(input, input._flatpickr, this.getWireFormat(input));
            }
        });
    },

    refresh(root = document) {
        if (! window.flatpickr) {
            return;
        }

        root.querySelectorAll(this.selectors).forEach((input) => {
            this.bindInput(input);
        });
    },

    init(root = document) {
        this.ensureGlobalOpenDelegation();
        this.prepAll(root);
        this.refresh(root);
    },
};

function initErpDatepickers(root = document) {
    window.ErpDatepicker.ensureGlobalOpenDelegation();

    if (typeof window.__erpPrepDateInputs === 'function') {
        window.__erpPrepDateInputs(root);
    }

    window.ErpDatepicker.prepAll(root);

    if (! window.flatpickr) {
        window.setTimeout(() => initErpDatepickers(root), 30);

        return;
    }

    window.ErpDatepicker.init(root);

    const scope = root?.querySelectorAll ? root : document;
    const pending = scope.querySelectorAll('input[data-erp-date-initial][data-wire-field]');
    const needsRetry = Array.from(pending).some((input) => {
        return String(input.dataset.erpDateInitial ?? '').trim() !== '' && window.ErpDatepicker.isEmptyInput(input);
    });

    if (needsRetry) {
        const key = root === document ? 'document' : root;

        if (! window.__erpDatepickerRetryCounts) {
            window.__erpDatepickerRetryCounts = {};
        }

        const attempts = window.__erpDatepickerRetryCounts[key] ?? 0;

        if (attempts < 12) {
            window.__erpDatepickerRetryCounts[key] = attempts + 1;
            window.setTimeout(() => initErpDatepickers(root), 80);

            return;
        }

        delete window.__erpDatepickerRetryCounts[key];
    }
}

function bootErpDatepickers(root = document) {
    window.ErpDatepicker.ensureGlobalOpenDelegation();
    initErpDatepickers(root);
}

document.addEventListener('DOMContentLoaded', () => bootErpDatepickers(document));
document.addEventListener('livewire:navigated', () => bootErpDatepickers(document));

document.addEventListener('livewire:init', () => {
    bootErpDatepickers(document);

    window.Livewire.on('erp-masks-refresh', () => bootErpDatepickers(document));

    window.Livewire.hook('morph.updated', ({ el }) => {
        if (! (el instanceof HTMLElement)) {
            bootErpDatepickers(document);

            return;
        }

        if (typeof el.matches === 'function' && el.matches(window.ErpDatepicker.selectors)) {
            window.ErpDatepicker.bindInput(el);
        }

        bootErpDatepickers(el);
    });
});

if (document.readyState !== 'loading') {
    bootErpDatepickers(document);
}
