/**
 * Precificação: helpers de Enter (máscara, próximo campo, foco) e blur.
 * O commit é feito pelo $wire do Alpine na modal; aqui só o suporte.
 * Ordem: % → R$ → próximo % (Custos → Frete → Seguro → …).
 */
(function () {
    const VERSION = 'v37-single-request';

    if (window.__erpPrecifEnterVersion === VERSION) {
        return;
    }

    if (typeof window.__erpPrecifEnterKeydown === 'function') {
        document.removeEventListener('keydown', window.__erpPrecifEnterKeydown, true);
        window.__erpPrecifEnterKeydown = null;
    }

    if (typeof window.__erpPrecifEnterFocusOut === 'function') {
        document.removeEventListener('focusout', window.__erpPrecifEnterFocusOut, true);
    }

    window.__erpPrecifEnterVersion = VERSION;

    const ORDER = [
        'precif-compra',
        'precif-pct-custos',
        'precif-custos-rs',
        'precif-frete',
        'precif-frete-rs',
        'precif-seguro',
        'precif-seguro-rs',
        'precif-outras-pct',
        'precif-outras',
        'precif-varejo-comissao',
        'precif-varejo-comissao-rs',
        'precif-varejo-desconto',
        'precif-varejo-desconto-rs',
        'precif-varejo-margem',
        'precif-varejo-praticado',
        'precif-atacado-comissao',
        'precif-atacado-comissao-rs',
        'precif-atacado-desconto',
        'precif-atacado-desconto-rs',
        'precif-atacado-margem',
        'precif-atacado-praticado',
        'precif-especial-comissao',
        'precif-especial-comissao-rs',
        'precif-especial-desconto',
        'precif-especial-desconto-rs',
        'precif-especial-margem',
        'precif-especial-praticado',
    ];

    function getModal() {
        return document.querySelector('.erp-prod-precificacao-modal');
    }

    /**
     * "Campo digitado e ainda não gravado" fica em memória, não no DOM:
     * o morph do Livewire remove atributos que não vêm do servidor.
     */
    function dirtyMap() {
        if (! window.__erpPrecifDirtyMap) {
            window.__erpPrecifDirtyMap = Object.create(null);
        }

        return window.__erpPrecifDirtyMap;
    }

    function setDirty(fieldId) {
        if (fieldId) {
            dirtyMap()[fieldId] = true;
        }
    }

    function clearDirty(fieldId) {
        if (fieldId) {
            delete dirtyMap()[fieldId];
        }
    }

    function isDirty(fieldId) {
        return fieldId ? dirtyMap()[fieldId] === true : false;
    }

    function getWireFromEl(el) {
        if (! window.Livewire || ! el) {
            return null;
        }

        const root = el.closest('[wire\\:id]');
        const wireId = root?.getAttribute('wire:id');

        return wireId ? window.Livewire.find(wireId) : null;
    }

    function finalizeMask(input) {
        if (! input) {
            return '';
        }

        input.removeAttribute('readonly');

        if (! window.ErpMasks || ! input.dataset?.mask) {
            return input.value ?? '';
        }

        input.value = window.ErpMasks.finalizeMaskValue(input);
        window.ErpMasks.apply(input, {
            allowEmptySync: true,
            live: true,
            thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
        });

        return input.value;
    }

    function skipBlurFor(fieldId, ms) {
        window.__erpPrecifSkipBlurFor = fieldId;
        window.__erpPrecifSkipBlurUntil = Date.now() + (ms || 1800);
    }

    function shouldSkipBlur(fieldId) {
        if (
            window.__erpPrecifSkipBlurFor === fieldId
            && Date.now() < (window.__erpPrecifSkipBlurUntil || 0)
        ) {
            return true;
        }

        // Janela curta do remount após Enter: blur aqui é fantasma (grava 0,00).
        return Date.now() < ((window.__erpPrecifEnterAt || 0) + 700);
    }

    function resetPrecifState() {
        window.__erpPrecifFocusId = null;
        window.__erpPrecifFocusUntil = 0;
        window.__erpPrecifSkipBlurFor = null;
        window.__erpPrecifSkipBlurUntil = 0;
        window.__erpPrecifEnterAt = 0;
        window.__erpPrecifFocusedAt = 0;
        window.__erpPrecifLastEpoch = 0;
        window.__erpPrecifDirtyMap = Object.create(null);
    }

    window.__erpPrecifResetState = resetPrecifState;

    /**
     * O Livewire atualiza o atributo value=, mas o navegador mantém o conteúdo
     * digitado. Sem repintar, o campo volta vazio e o Enter seguinte grava 0,00.
     */
    // Diagnóstico temporário: histórico curto do que mexeu nos campos.
    function trace(evento, dados) {
        if (! Array.isArray(window.__erpPrecifTrace)) {
            window.__erpPrecifTrace = [];
        }

        window.__erpPrecifTrace.push(Object.assign({ ev: evento, t: Date.now() }, dados));

        if (window.__erpPrecifTrace.length > 24) {
            window.__erpPrecifTrace.shift();
        }
    }

    function repaintFromServer() {
        const modal = getModal();
        const body = modal?.querySelector('[data-precif-values]');

        if (! body) {
            return;
        }

        // Resposta atrasada do Livewire (chega fora de ordem): não repintar com dado velho.
        const epoch = Number(body.dataset.precifEpoch || 0);

        if (epoch < (window.__erpPrecifLastEpoch || 0)) {
            return;
        }

        window.__erpPrecifLastEpoch = epoch;

        let valores;

        try {
            valores = JSON.parse(body.dataset.precifValues || '{}');
        } catch (error) {
            return;
        }

        Object.keys(valores).forEach((id) => {
            const input = modal.querySelector('#' + CSS.escape(id));

            if (! input) {
                return;
            }

            // Digitado e ainda não gravado: nunca sobrescrever (com ou sem foco).
            if (isDirty(id)) {
                return;
            }

            const want = String(valores[id] ?? '');

            if (input.value !== want) {
                trace('repaint', { id, de: input.value, para: want });
                input.value = want;
                delete input.dataset.erpMaskSynced;
            }
        });
    }

    function onModalInput(event) {
        const target = event.target;

        if (! (target instanceof HTMLInputElement) || ! target.dataset?.mask) {
            return;
        }

        const modal = getModal();

        if (modal && modal.contains(target)) {
            setDirty(target.id);
            trace('digitou', { id: target.id, valor: target.value });
        }
    }

    if (typeof window.__erpPrecifEnterInput === 'function') {
        document.removeEventListener('input', window.__erpPrecifEnterInput, true);
    }

    window.__erpPrecifEnterInput = onModalInput;
    document.addEventListener('input', onModalInput, true);

    function armTypingGuard(el) {
        if (! el || el.dataset.erpPrecifTypingGuard === '1') {
            return;
        }

        el.dataset.erpPrecifTypingGuard = '1';

        const stop = (event) => {
            // Enter/Tab navegam: não podem cancelar o foco recém-agendado.
            if (event?.type === 'keydown' && (event.key === 'Enter' || event.key === 'Tab')) {
                return;
            }

            window.__erpPrecifFocusId = null;
            window.__erpPrecifFocusUntil = 0;
            el.dataset.erpPrecifTypingGuard = '0';
            el.removeEventListener('keydown', stop);
            el.removeEventListener('input', stop);
            el.removeEventListener('beforeinput', stop);
        };

        el.addEventListener('keydown', stop);
        el.addEventListener('input', stop);
        el.addEventListener('beforeinput', stop);
    }

    /**
     * Foco por id com retries (após remount epoch) — mesmo espírito do tryFocus da tela XML.
     */
    function focusById(fieldId, options = {}) {
        if (! fieldId) {
            return false;
        }

        const allowSelect = options.select !== false;

        window.__erpPrecifFocusId = fieldId;
        window.__erpPrecifFocusUntil = Date.now() + 2500;

        let selectedOnce = false;

        const run = () => {
            if (window.__erpPrecifFocusId !== fieldId) {
                return false;
            }

            const root = getModal();
            const el = root ? root.querySelector('#' + CSS.escape(fieldId)) : null;

            if (! el || el.disabled || ! el.isConnected) {
                return false;
            }

            // Não roubar o foco de um campo onde o usuário está digitando.
            const focado = document.activeElement;

            if (focado && focado !== el && isDirty(focado.id)) {
                return false;
            }

            el.removeAttribute('readonly');

            try {
                el.focus({ preventScroll: true });
            } catch (error) {
                try {
                    el.focus();
                } catch (ignored) {
                    return false;
                }
            }

            if (allowSelect && ! selectedOnce && typeof el.select === 'function') {
                el.select();
                selectedOnce = true;
                window.__erpPrecifFocusedAt = Date.now();
                armTypingGuard(el);
                trace('focou', { id: fieldId, valor: el.value, ro: el.readOnly });
            }

            return document.activeElement === el && el.isConnected;
        };

        const firstOk = run();

        [0, 30, 60, 120, 200, 320, 480, 700, 1000].forEach((ms) => {
            setTimeout(() => {
                if (window.__erpPrecifFocusId !== fieldId) {
                    return;
                }

                const active = document.activeElement;

                if (active?.id === fieldId && active.isConnected) {
                    return;
                }

                run();
            }, ms);
        });

        return firstOk;
    }

    window.__erpPrecifFocusById = focusById;

    window.ErpPrecifEnter = {
        version: VERSION,
        order: ORDER,
        focusById,
        finalizeMask,
        getWireFromEl,
        resetState: resetPrecifState,
        shouldSkipBlur,
        skipBlurFor,
    };

    function onFocusOut(event) {
        const modal = getModal();

        if (! modal) {
            return;
        }

        const target = event.target;

        if (! (target instanceof HTMLInputElement) || ! modal.contains(target)) {
            return;
        }

        if (target.disabled) {
            return;
        }

        const fieldId = target.id || '';

        if (ORDER.indexOf(fieldId) < 0) {
            return;
        }

        // Só grava no blur o campo que o usuário realmente editou.
        if (! isDirty(fieldId)) {
            return;
        }

        if (shouldSkipBlur(fieldId)) {
            return;
        }

        if (! target.isConnected) {
            return;
        }

        const value = finalizeMask(target);
        const wire = getWireFromEl(target);

        clearDirty(fieldId);

        if (wire && typeof wire.call === 'function') {
            wire.call('precificacaoCommitField', fieldId, value, true);
        }
    }

    window.__erpPrecifEnterFocusOut = onFocusOut;
    document.addEventListener('focusout', onFocusOut, true);

    /**
     * Próximo campo: lista viva da modal (remontada a cada Enter),
     * com a ordem fixa como reserva.
     */
    function nextFieldId(modal, current) {
        const fields = Array.from(
            modal.querySelectorAll('input[data-erp-precif-enter]:not([disabled])')
        ).filter((field) => field.offsetParent !== null);

        const index = fields.indexOf(current);

        if (index >= 0 && fields[index + 1]) {
            return fields[index + 1].id || null;
        }

        const orderIndex = ORDER.indexOf(current && current.id ? current.id : '');

        return orderIndex >= 0 ? (ORDER[orderIndex + 1] ?? null) : null;
    }

    /**
     * Preparo do Enter: chamado pelo Alpine da modal, que faz o $wire.
     * Aqui só cuidamos de máscara, próximo campo e supressão do blur.
     */
    function prepareEnter(target) {
        const modal = getModal();

        if (! modal || ! (target instanceof HTMLInputElement) || target.disabled) {
            return null;
        }

        const fieldId = target.id || '';

        // Enter em cascata: o campo acabou de receber foco pelo Enter anterior
        // e nada foi digitado — não é um Enter do usuário.
        if (
            ! isDirty(fieldId)
            && Date.now() < ((window.__erpPrecifFocusedAt || 0) + 150)
        ) {
            return null;
        }

        const eraDirty = isDirty(fieldId);

        target.removeAttribute('readonly');

        const bruto = target.value;
        const value = finalizeMask(target);
        const nextId = nextFieldId(modal, target);

        const diag = {
            bruto,
            apos_mascara: value,
            readonly: target.readOnly,
            dirty: eraDirty ? '1' : '0',
            attr_value: target.getAttribute('value'),
            trace: (window.__erpPrecifTrace || []).slice(-12),
        };

        window.__erpPrecifTrace = [];

        window.__erpPrecifEnterAt = Date.now();
        skipBlurFor(fieldId, 1200);

        if (nextId) {
            window.__erpPrecifFocusId = nextId;
            window.__erpPrecifFocusUntil = Date.now() + 3000;
        }

        return { fieldId, value, nextId, diag };
    }

    window.ErpPrecifEnter.nextFieldId = nextFieldId;
    window.ErpPrecifEnter.prepareEnter = prepareEnter;
    window.ErpPrecifEnter.order = ORDER;
    window.ErpPrecifEnter.repaint = repaintFromServer;

    function bindLivewireFocus() {
        if (! window.Livewire || typeof window.Livewire.on !== 'function') {
            return;
        }

        if (window.__erpPrecifFocusListenerBound) {
            return;
        }

        window.__erpPrecifFocusListenerBound = true;

        window.Livewire.on('erp-precif-reset', () => resetPrecifState());

        window.Livewire.on('erp-precif-focus', (event) => {
            const id = event?.id
                ?? (Array.isArray(event) ? event[0]?.id : null)
                ?? (typeof event === 'string' ? event : null);
            const committed = event?.committed
                ?? (Array.isArray(event) ? event[0]?.committed : null);

            // Só libera o campo digitado quando o servidor confirmou o commit.
            clearDirty(committed);

            repaintFromServer();

            if (id) {
                const focus = window.__erpPrecifFocusById || focusById;
                focus(id, { select: true });
            }
        });

        window.Livewire.hook('morph.updated', () => {
            repaintFromServer();
            setTimeout(repaintFromServer, 80);

            const want = window.__erpPrecifFocusId;

            if (! want || Date.now() > (window.__erpPrecifFocusUntil || 0)) {
                return;
            }

            const active = document.activeElement;

            if (active?.id === want && active.isConnected) {
                return;
            }

            const root = getModal();
            const el = root ? root.querySelector('#' + CSS.escape(want)) : null;

            if (! el || el.disabled || ! el.isConnected) {
                return;
            }

            const focus = window.__erpPrecifFocusById || focusById;
            focus(want, { select: true });
        });
    }

    if (window.Livewire) {
        bindLivewireFocus();
    } else {
        document.addEventListener('livewire:init', bindLivewireFocus);
        document.addEventListener('livewire:initialized', bindLivewireFocus);
    }
})();
