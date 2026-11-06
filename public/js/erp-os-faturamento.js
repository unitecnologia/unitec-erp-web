/**
 * OS — F4 faturamento: navegação local de meios (paridade Tela de Venda).
 * Setas sem Livewire; restante só no atalho; flush antes de Faturar.
 */
(function () {
    'use strict';

    window.__erpOsPayNav = window.__erpOsPayNav || { index: 0, focusEpoch: 0, navigating: false };
    // Garante que lock de Esc/Voltar não fique preso após hot-reload / erro anterior.
    window.__erpOsFinClosing = false;

    function finRoot() {
        return document.querySelector('.erp-os-fin');
    }

    function livewireNearFin() {
        // Modal é @teleport('body'): .erp-os-fin não tem ancestral wire:id.
        const page = document.querySelector('.erp-os-form-page');
        const host = page?.closest('[wire\\:id]')
            || page?.querySelector?.('[wire\\:id]')
            || document.querySelector('.fi-page [wire\\:id], .fi-main [wire\\:id], [wire\\:id].erp-os-form-page')
            || finRoot()?.closest('[wire\\:id]');

        if (host && window.Livewire) {
            try {
                const wireId = host.getAttribute('wire:id');
                if (wireId) {
                    return window.Livewire.find(wireId);
                }
            } catch (e) {
                // fall through
            }
        }

        // Fallback: componente que expõe o método de fechar F4.
        try {
            const all = window.Livewire?.all?.() || [];
            for (let i = 0; i < all.length; i++) {
                const c = all[i];
                if (c && typeof c.get === 'function' && typeof c.get('osFaturamentoOpen') !== 'undefined') {
                    return c;
                }
            }
        } catch (e) {
            // ignore
        }

        return null;
    }

    window.erpOsParseMoneyBr = function (raw) {
        const s = String(raw ?? '').trim();
        if (! s) return 0;
        const neg = s.startsWith('-');
        const limpo = s.replace(/[^\d,.\-]/g, '').replace('-', '');
        if (! limpo) return 0;
        let n;
        if (limpo.includes(',')) {
            n = Number.parseFloat(limpo.replace(/\./g, '').replace(',', '.'));
        } else {
            n = Number.parseFloat(limpo);
        }
        if (! Number.isFinite(n)) return 0;

        return Math.round((neg ? -n : n) * 100) / 100;
    };

    window.erpOsFormatMoneyBr = function (value) {
        const n = Math.round((Number(value) || 0) * 100) / 100;
        const neg = n < 0;
        const abs = Math.abs(n);
        const [intPart, frac] = abs.toFixed(2).split('.');
        const intFmt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return (neg ? '-' : '') + intFmt + ',' + frac;
    };

    window.erpOsPayRows = function (root) {
        const scope = root || finRoot();
        if (! scope) return [];

        return Array.from(scope.querySelectorAll('.erp-pdv-finalizar__grid tbody tr.erp-pdv-finalizar__pay-row'));
    };

    window.erpOsUpdateFinTotaisUi = function (root) {
        if (! root) return;
        const total = window.erpOsParseMoneyBr(root.querySelector('[data-erp-os-total-pagar]')?.textContent);
        let informado = 0;
        root.querySelectorAll('[id^="erp-os-finalizar-valor-"]').forEach((el) => {
            informado += window.erpOsParseMoneyBr(el.value);
        });
        informado = Math.round(informado * 100) / 100;
        const restante = Math.max(0, Math.round((total - informado) * 100) / 100);
        const troco = Math.max(0, Math.round((informado - total) * 100) / 100);
        const restEl = root.querySelector('[data-erp-os-restante]');
        const trocoEl = root.querySelector('[data-erp-os-troco]');
        if (restEl) restEl.textContent = window.erpOsFormatMoneyBr(restante);
        if (trocoEl) trocoEl.textContent = window.erpOsFormatMoneyBr(troco);
    };

    window.erpOsApplyPagamentoSelection = function (index, opts) {
        const options = opts || {};
        const focus = options.focus !== false;
        const fromServer = !!options.fromServer;
        const valor = options.valor;
        const root = finRoot();
        if (! root) return;

        const rows = window.erpOsPayRows(root);
        if (! rows.length) return;

        const next = Math.max(0, Math.min(rows.length - 1, Number(index) || 0));

        if (! fromServer) {
            window.__erpOsPayNav.focusEpoch = (window.__erpOsPayNav.focusEpoch || 0) + 1;
            window.__erpOsPayNav.navigating = true;
        }
        window.__erpOsPayNav.index = next;

        rows.forEach((row, i) => {
            row.classList.toggle('erp-pdv__grid-row--selected', i === next);
        });

        const input = document.getElementById('erp-os-finalizar-valor-' + next);
        if (valor != null && valor !== '' && input) {
            input.value = valor;
            delete input.dataset.erpMaskSynced;
            window.ErpMasks?.apply?.(input, { sync: false });
            rows[next]?.classList.toggle(
                'erp-pdv-finalizar__pay-row--filled',
                window.erpOsParseMoneyBr(input.value) > 0.009
            );
        }

        if (focus) {
            window.__erpOsPayNav.concluirFocused = false;
            input?.focus({ preventScroll: true });
            input?.select?.();
        }
        rows[next]?.scrollIntoView({ block: 'nearest' });
        window.erpOsUpdateFinTotaisUi(root);

        if (! fromServer) {
            queueMicrotask(() => {
                window.__erpOsPayNav.navigating = false;
            });
        }
    };

    window.erpOsFocusConcluirBtn = function () {
        window.__erpOsPayNav = window.__erpOsPayNav || { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };
        // Invalida focos de meio ainda pendentes (atalho/Enter atrasado).
        window.__erpOsPayNav.focusEpoch = (window.__erpOsPayNav.focusEpoch || 0) + 1;
        window.__erpOsPayNav.concluirFocused = true;
        const btn = document.getElementById('erp-os-finalizar-op-faturar')
            || document.querySelector('.erp-os-fin .erp-pdv-finalizar__operacao-btn:not([disabled])');
        btn?.focus({ preventScroll: true });
        window.erpOsFinPerfMark?.('focus-concluir');
    };

    /**
     * Feedback imediato do Concluir (antes do Livewire).
     * Sucesso real só vem do servidor (toast / redirect).
     */
    window.erpOsSetConcluirBusy = function (busy) {
        const btn = document.getElementById('erp-os-finalizar-op-faturar');
        if (! btn) return;

        const idle = btn.querySelector('[data-erp-os-concluir-idle]');
        const loading = btn.querySelector('[data-erp-os-concluir-busy]');

        if (busy) {
            btn.disabled = true;
            btn.setAttribute('disabled', 'disabled');
            btn.setAttribute('aria-busy', 'true');
            if (idle) idle.hidden = true;
            if (loading) loading.hidden = false;

            return;
        }

        // Só restaura se o modal ainda está aberto (erro / validação).
        if (! finRoot() || finRoot().getAttribute('data-erp-os-fin-closing') === '1') {
            return;
        }

        btn.disabled = false;
        btn.removeAttribute('disabled');
        btn.removeAttribute('aria-busy');
        if (idle) idle.hidden = false;
        if (loading) loading.hidden = true;
    };

    /** Medição real (opt-in): localStorage.setItem('erpOsFinPerf','1') */
    window.erpOsFinPerfMark = function (label) {
        try {
            if (localStorage.getItem('erpOsFinPerf') !== '1') return;
        } catch (e) {
            return;
        }
        const now = performance.now();
        const prev = window.__erpOsFinPerfLast || now;
        // eslint-disable-next-line no-console
        console.info('[os-fin-perf]', label, Math.round(now - prev) + 'ms desde anterior', '| t=' + Math.round(now));
        window.__erpOsFinPerfLast = now;
    };

    window.erpOsCollectValoresPayload = function () {
        const payload = {};
        document.querySelectorAll('[id^="erp-os-finalizar-valor-"]').forEach((el) => {
            const m = String(el.id).match(/^erp-os-finalizar-valor-(\d+)$/);
            if (m) payload[m[1]] = el.value;
        });

        return payload;
    };

    window.erpOsRestanteAtual = function (root) {
        const scope = root || finRoot();
        if (! scope) return 0;
        const total = window.erpOsParseMoneyBr(scope.querySelector('[data-erp-os-total-pagar]')?.textContent);
        let informado = 0;
        scope.querySelectorAll('[id^="erp-os-finalizar-valor-"]').forEach((el) => {
            informado += window.erpOsParseMoneyBr(el.value);
        });

        return Math.max(0, Math.round((total - informado) * 100) / 100);
    };

    /**
     * Atalho A/P/D… — 100% local (0 Livewire). Parcelas (boleto/crediário): sync em background.
     */
    window.erpOsSelectPagamentoByAtalhoLocal = function (atalho, wire) {
        window.erpOsFinPerfMark?.('atalho-start:' + atalho);
        const root = finRoot();
        if (! root) return false;

        const key = String(atalho || '').trim().toUpperCase();
        const rows = window.erpOsPayRows(root);
        let index = -1;
        for (let i = 0; i < rows.length; i++) {
            const kbd = (rows[i].querySelector('.erp-pdv-finalizar__kbd')?.textContent || '').trim().toUpperCase();
            if (kbd === key) {
                index = i;
                break;
            }
        }
        if (index < 0) return false;

        const input = document.getElementById('erp-os-finalizar-valor-' + index);
        const atual = window.erpOsParseMoneyBr(input?.value);
        const aPrazo = rows[index].getAttribute('data-os-a-prazo') === '1';
        const precisaParcelas = rows[index].getAttribute('data-os-precisa-parcelas') === '1';
        let valorAplicar = null;

        if (atual <= 0.009) {
            const total = window.erpOsParseMoneyBr(root.querySelector('[data-erp-os-total-pagar]')?.textContent);
            if (aPrazo) {
                rows.forEach((row, i) => {
                    if (i === index) return;
                    const other = document.getElementById('erp-os-finalizar-valor-' + i);
                    if (! other) return;
                    other.value = '0,00';
                    delete other.dataset.erpMaskSynced;
                    window.ErpMasks?.apply?.(other, { sync: false });
                    row.classList.remove('erp-pdv-finalizar__pay-row--filled');
                });
                valorAplicar = window.erpOsFormatMoneyBr(total);
            } else {
                let outros = 0;
                rows.forEach((_, i) => {
                    if (i === index) return;
                    outros += window.erpOsParseMoneyBr(
                        document.getElementById('erp-os-finalizar-valor-' + i)?.value
                    );
                });
                valorAplicar = window.erpOsFormatMoneyBr(Math.max(0, Math.round((total - outros) * 100) / 100));
            }
        }

        window.erpOsApplyPagamentoSelection(index, {
            focus: true,
            fromServer: false,
            valor: valorAplicar,
        });
        window.erpOsFinPerfMark?.('atalho-ui-pronta');

        // Carnê: UI já atualizou; abre parcelas no servidor sem bloquear o teclado.
        if (precisaParcelas && window.erpOsParseMoneyBr(input?.value) > 0.009 && wire) {
            const payload = window.erpOsCollectValoresPayload();
            try {
                const comp = window.erpOsResolveFinComponent?.(wire);
                if (comp) {
                    Promise.resolve(comp.call('aplicarOsPagamentoLocalEParcelas', payload, index)).catch(() => null);
                } else if (typeof wire.aplicarOsPagamentoLocalEParcelas === 'function') {
                    Promise.resolve(wire.aplicarOsPagamentoLocalEParcelas(payload, index)).catch(() => null);
                }
            } catch (e) {
                /* ignore */
            }
        }

        return true;
    };

    /**
     * 1º Enter no valor — local. Foco Concluir sem rede quando restante zerou.
     * Parcelas obrigatórias: 1 request. Misto incompleto: avança linha local.
     */
    window.erpOsConfirmarValorEnterLocal = function (index, wire) {
        window.erpOsFinPerfMark?.('enter1-start');
        const root = finRoot();
        if (! root) return;

        const rows = window.erpOsPayRows(root);
        const idx = Math.max(0, Math.min(rows.length - 1, Number(index) || 0));
        const row = rows[idx];
        const input = document.getElementById('erp-os-finalizar-valor-' + idx);
        if (! input) return;

        const raw = String(input.value || '');
        const valorNum = window.erpOsParseMoneyBr(raw);
        row?.classList.toggle('erp-pdv-finalizar__pay-row--filled', valorNum > 0.009);
        window.erpOsUpdateFinTotaisUi(root);
        window.__erpOsPayNav.index = idx;

        if (valorNum > 0.009 && row?.getAttribute('data-os-precisa-parcelas') === '1') {
            const payload = window.erpOsCollectValoresPayload();
            // Nunca use wire.$wire quando wire já é o Alpine $wire (vira invocador do método "$wire").
            try {
                const comp = window.erpOsResolveFinComponent?.(wire);
                if (comp) {
                    Promise.resolve(comp.call('aplicarOsPagamentoLocalEParcelas', payload, idx)).catch(() => null);
                } else if (wire && typeof wire.aplicarOsPagamentoLocalEParcelas === 'function') {
                    Promise.resolve(wire.aplicarOsPagamentoLocalEParcelas(payload, idx)).catch(() => null);
                }
            } catch (e) {
                /* ignore */
            }
            window.erpOsFinPerfMark?.('enter1-parcelas-request');

            return;
        }

        const restante = window.erpOsRestanteAtual(root);
        if (restante <= 0.009) {
            window.erpOsFocusConcluirBtn?.();
            window.erpOsFinPerfMark?.('enter1-concluir-local');

            return;
        }

        if (idx < rows.length - 1) {
            window.erpOsApplyPagamentoSelection(idx + 1, { focus: true, fromServer: false });
            window.erpOsFinPerfMark?.('enter1-proxima-linha');

            return;
        }

        window.erpOsApplyPagamentoSelection(idx, { focus: true, fromServer: false });
        window.erpOsFinPerfMark?.('enter1-mesmo-linha');
    };

    /**
     * Concluir (2º Enter / clique): 1 round-trip (flush + faturar).
     * Nunca faça `wire.$wire` quando wire já é o Alpine $wire — o proxy trata
     * "$wire" como nome de método e estoura: Public method [$wire] not found.
     */
    window.erpOsConcluirComFlush = function (wire) {
        window.erpOsFinPerfMark?.('concluir-start');
        if (! wire) return Promise.resolve();
        if (window.__erpOsFinClosing) return Promise.resolve();
        if (window.__erpOsConcluirLock) return Promise.resolve();

        const comp = window.erpOsResolveFinComponent?.(wire) || null;
        const alpineWire = window.erpOsIsLivewireComponent?.(wire) ? null : wire;

        let persistindo = false;
        try {
            if (comp && typeof comp.get === 'function') {
                persistindo = !! comp.get('osPersistindo');
            } else if (alpineWire) {
                persistindo = !! alpineWire.osPersistindo;
            }
        } catch (e) {
            persistindo = false;
        }
        if (persistindo) return Promise.resolve();

        const payload = window.erpOsCollectValoresPayload();
        const selectedIndex = Number.isInteger(window.__erpOsPayNav?.index)
            ? window.__erpOsPayNav.index
            : 0;
        const clienteEl = document.getElementById('erp-os-fin-cliente');
        const clienteBusca = clienteEl ? String(clienteEl.value || '') : '';
        const cpfEl = document.getElementById('erp-os-finalizar-cpf');
        const infosEl = document.getElementById('erp-os-finalizar-informacoes');
        const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
        const observacoes = infosEl ? String(infosEl.value || '') : '';

        window.__erpOsConcluirLock = true;
        window.erpOsFinPerfMark?.('concluir-payload-pronto');
        // Imediato no browser — não espera o 1º pacote Livewire.
        window.erpOsSetConcluirBusy(true);
        window.erpOsFinPerfMark?.('concluir-ui-busy');

        const call = (method, ...args) => {
            if (comp) {
                return comp.call(method, ...args);
            }
            if (alpineWire && typeof alpineWire[method] === 'function') {
                return alpineWire[method](...args);
            }
            throw new Error('os-fin-wire-indisponivel:' + method);
        };

        window.erpOsFinPerfMark?.('concluir-request-start');
        const t0 = performance.now();
        let requestDoneAt = null;

        try {
            if (localStorage.getItem('erpOsFinPerf') === '1') {
                sessionStorage.setItem('erpOsFinPerfConcluir', JSON.stringify({
                    t0: t0,
                    startedAt: Date.now(),
                }));
                const onPageHide = () => {
                    const navStart = performance.now();
                    try {
                        const prev = JSON.parse(sessionStorage.getItem('erpOsFinPerfConcluir') || '{}');
                        prev.pagehideAt = navStart;
                        prev.pagehideWall = Date.now();
                        prev.roundtripMs = requestDoneAt != null ? Math.round(requestDoneAt - t0) : null;
                        prev.redirectGapMs = requestDoneAt != null ? Math.round(navStart - requestDoneAt) : null;
                        prev.enterToPagehideMs = Math.round(navStart - t0);
                        sessionStorage.setItem('erpOsFinPerfConcluir', JSON.stringify(prev));
                        // eslint-disable-next-line no-console
                        console.info('[os-fin-perf] pagehide (início navegação/reload)', {
                            roundtripMs: prev.roundtripMs,
                            redirectGapMs: prev.redirectGapMs,
                            enterToPagehideMs: prev.enterToPagehideMs,
                        });
                    } catch (e) { /* ignore */ }
                };
                window.addEventListener('pagehide', onPageHide, { once: true });
            }
        } catch (e) { /* ignore */ }

        return Promise.resolve(
            call('faturarOsComFlush', payload, selectedIndex, clienteBusca, cpfNota, observacoes)
        )
            .then(() => {
                requestDoneAt = performance.now();
                window.erpOsFinPerfMark?.('concluir-request-done');
                try {
                    if (localStorage.getItem('erpOsFinPerf') === '1') {
                        const roundtrip = Math.round(requestDoneAt - t0);
                        // eslint-disable-next-line no-console
                        console.info('[os-fin-perf] livewire-roundtrip', roundtrip + 'ms');
                        const prev = JSON.parse(sessionStorage.getItem('erpOsFinPerfConcluir') || '{}');
                        prev.requestDoneAt = requestDoneAt;
                        prev.roundtripMs = roundtrip;
                        sessionStorage.setItem('erpOsFinPerfConcluir', JSON.stringify(prev));
                    }
                } catch (e) { /* ignore */ }

                // Sucesso com redirect: modal some. Erro/validação: modal permanece → restaura.
                queueMicrotask(() => {
                    if (finRoot() && finRoot().style.display !== 'none') {
                        window.erpOsSetConcluirBusy(false);
                    }
                });
            })
            .catch((err) => {
                window.erpOsSetConcluirBusy(false);
                try {
                    console.warn('[os-fin] falha ao concluir faturamento', err);
                } catch (e) { /* ignore */ }
            })
            .finally(() => {
                window.__erpOsConcluirLock = false;
            });
    };

    window.erpOsMovePagamentoSelection = function (delta) {
        const root = finRoot();
        if (! root) return;

        const rows = window.erpOsPayRows(root);
        if (! rows.length) return;

        let current = Number.isInteger(window.__erpOsPayNav?.index)
            ? window.__erpOsPayNav.index
            : rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
        if (current < 0 || current >= rows.length) {
            current = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
        }
        if (current < 0) current = 0;

        const next = Math.max(0, Math.min(rows.length - 1, current + Number(delta || 0)));
        window.erpOsApplyPagamentoSelection(next, { focus: true, fromServer: false });
    };

    window.erpOsFlushNotaFooterToWire = function (wire, validarCpf) {
        if (! wire || typeof wire.flushOsCpfNotaEObservacoes !== 'function') {
            return Promise.resolve(true);
        }

        const cpfEl = document.getElementById('erp-os-finalizar-cpf');
        const infosEl = document.getElementById('erp-os-finalizar-informacoes');
        const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
        const observacoes = infosEl ? String(infosEl.value || '') : '';

        return Promise.resolve(wire.flushOsCpfNotaEObservacoes(cpfNota, observacoes, validarCpf !== false));
    };

    /**
     * Livewire.find() Component tem .id + .get + .call.
     * Alpine $wire NÃO: o proxy devolve function para qualquer nome (inclui "call"),
     * e usar isso como Component.call() quebra Esc/Voltar.
     */
    window.erpOsIsLivewireComponent = function (obj) {
        return !!(
            obj
            && typeof obj.call === 'function'
            && typeof obj.get === 'function'
            && (typeof obj.id === 'string' || typeof obj.id === 'number')
        );
    };

    window.erpOsResolveFinComponent = function (wire) {
        if (window.erpOsIsLivewireComponent(wire)) {
            return wire;
        }
        if (wire && window.erpOsIsLivewireComponent(wire.__instance)) {
            return wire.__instance;
        }
        const near = livewireNearFin();
        if (window.erpOsIsLivewireComponent(near)) {
            return near;
        }

        return null;
    };

    window.erpOsRestoreFinModalUi = function (root) {
        if (! root || ! root.isConnected) return;
        root.style.display = '';
        root.removeAttribute('aria-hidden');
        root.removeAttribute('data-erp-os-fin-closing');
    };

    /**
     * Esc / Voltar / X / backdrop: fecha na hora; 1 request preserva valores/CPF/infos.
     * “Aberto” = DOM (.erp-os-fin). Não confiar em $wire.osFaturamentoOpen (teleport).
     */
    window.erpOsFecharFaturamentoInstantaneo = function (wire) {
        window.erpOsFinPerfMark?.('esc-start');
        if (window.__erpOsFinClosing) return Promise.resolve();

        const root = finRoot();
        if (! root || root.getAttribute('data-erp-os-fin-closing') === '1') {
            return Promise.resolve();
        }

        const comp = window.erpOsResolveFinComponent(wire);
        // Alpine $wire (props/métodos) — só se NÃO for Component.
        // Nunca acesse Component.$wire / $wire.$wire: o proxy trata "$wire" como método.
        const alpineWire = window.erpOsIsLivewireComponent(wire) ? null : wire;

        const isPersistindo = () => {
            try {
                if (comp && typeof comp.get === 'function' && comp.get('osPersistindo')) {
                    return true;
                }
            } catch (e) { /* ignore */ }
            try {
                if (alpineWire && alpineWire.osPersistindo) {
                    return true;
                }
            } catch (e) { /* ignore */ }

            return !! document.querySelector(
                '#erp-os-finalizar-op-faturar[disabled], .erp-os-fin .erp-pdv-finalizar__operacao-btn[disabled]'
            );
        };

        if (isPersistindo()) {
            return Promise.resolve();
        }

        const call = (method, ...args) => {
            if (comp) {
                return comp.call(method, ...args);
            }
            if (alpineWire && typeof alpineWire[method] === 'function') {
                return alpineWire[method](...args);
            }
            throw new Error('os-fin-wire-indisponivel:' + method);
        };

        // Modais internos: Esc fecha só o ativo (não o F4).
        if (document.querySelector('.erp-fv-transporte-modal')) {
            return Promise.resolve(call('fecharOsTransporteModal')).catch(() => null);
        }
        if (root.querySelector('.erp-pdv-parcelas-overlay')) {
            if (document.querySelector('#erp-os-parcelas-tabelas')) {
                return Promise.resolve(call('fecharOsTabelasPrazoPredefinidas')).catch(() => null);
            }

            return Promise.resolve(call('cancelarOsTabelaPrazoConsulta')).catch(() => null);
        }

        const payload = window.erpOsCollectValoresPayload?.() || {};
        const selectedIndex = Number.isInteger(window.__erpOsPayNav?.index)
            ? window.__erpOsPayNav.index
            : 0;
        const cpfEl = document.getElementById('erp-os-finalizar-cpf');
        const infosEl = document.getElementById('erp-os-finalizar-informacoes');
        const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
        const observacoes = infosEl ? String(infosEl.value || '') : '';

        window.__erpOsFinClosing = true;
        window.erpOsFinClienteRestoreCommitted?.();
        window.erpOsCloseFinClienteLookup?.();

        // Fecha visualmente já — não espera rede.
        root.setAttribute('data-erp-os-fin-closing', '1');
        root.style.display = 'none';
        root.setAttribute('aria-hidden', 'true');
        window.__erpOsPayNav = { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };
        window.erpOsFinPerfMark?.('esc-ui-fechada');

        const itemEl = document.getElementById('os-item-descricao');
        itemEl?.removeAttribute?.('readonly');
        itemEl?.focus?.({ preventScroll: true });

        return Promise.resolve(
            call('voltarDoFaturamentoOsComFlush', payload, selectedIndex, cpfNota, observacoes)
        )
            .catch(() => {
                window.erpOsFinPerfMark?.('esc-fallback');
                return Promise.resolve(call('flushOsValoresPagamento', payload, selectedIndex))
                    .catch(() => null)
                    .then(() => Promise.resolve(
                        alpineWire
                            ? window.erpOsFlushNotaFooterToWire?.(alpineWire, false)
                            : null
                    ).catch(() => null))
                    .then(() => call('voltarDoFaturamentoOs'));
            })
            .then(() => {
                window.erpOsFinPerfMark?.('esc-sync-ok');
                // Servidor não removeu o modal → reexibir (evita travar oculto).
                const leftover = document.querySelector('.erp-os-fin[data-erp-os-fin-closing="1"]');
                if (leftover) {
                    window.erpOsRestoreFinModalUi(leftover);
                    window.erpOsFinPerfMark?.('esc-servidor-manteve-aberto');
                }
            })
            .catch((err) => {
                window.erpOsRestoreFinModalUi(root);
                window.erpOsFinPerfMark?.('esc-sync-fail');
                try {
                    console.warn('[os-fin] falha ao fechar faturamento', err);
                } catch (e) { /* ignore */ }
            })
            .finally(() => {
                window.__erpOsFinClosing = false;
            });
    };

    window.erpOsFlushPagamentosToWire = function (wire) {
        if (! wire || typeof wire.flushOsValoresPagamento !== 'function') {
            return Promise.resolve();
        }

        const payload = {};
        document.querySelectorAll('[id^="erp-os-finalizar-valor-"]').forEach((el) => {
            const m = String(el.id).match(/^erp-os-finalizar-valor-(\d+)$/);
            if (m) {
                payload[m[1]] = el.value;
            }
        });
        const selectedIndex = Number.isInteger(window.__erpOsPayNav?.index)
            ? window.__erpOsPayNav.index
            : 0;

        const clienteEl = document.getElementById('erp-os-fin-cliente');
        const clienteBusca = clienteEl ? String(clienteEl.value || '') : '';

        const syncCliente = typeof wire.sincronizarClienteFaturamentoOs === 'function'
            ? Promise.resolve(wire.sincronizarClienteFaturamentoOs(clienteBusca))
            : Promise.resolve(true);

        return syncCliente.then((ok) => {
            if (ok === false) {
                return Promise.reject(new Error('os-cliente-inconsistente'));
            }

            return Promise.resolve(window.erpOsFlushNotaFooterToWire?.(wire, true)).then((notaOk) => {
                if (notaOk === false) {
                    return Promise.reject(new Error('os-cpf-nota-invalido'));
                }

                return wire.flushOsValoresPagamento(payload, selectedIndex);
            });
        });
    };

    window.erpOsSyncFinCpfNota = function (value) {
        const el = document.getElementById('erp-os-finalizar-cpf');
        if (! el) return;
        el.value = value == null ? '' : String(value);
    };

    window.erpOsSyncFinCliente = function (value) {
        const el = document.getElementById('erp-os-fin-cliente');
        if (! el) return;
        const text = value == null ? '' : String(value);
        el.value = text;
        el.setAttribute('data-os-cliente-committed', text);
    };

    window.erpOsFinClienteRestoreCommitted = function () {
        const input = document.getElementById('erp-os-fin-cliente');
        if (! input) return;
        const committed = String(input.getAttribute('data-os-cliente-committed') || '');
        input.value = committed;
        window.erpOsCloseFinClienteLookup?.();
    };

    /* ---- Cliente lookup no F4 (GET, sem remorph) ---- */

    let cliTimer = null;
    let cliAbort = null;
    let cliSeq = 0;
    let cliSelected = 0;

    function cliInput() {
        return document.getElementById('erp-os-fin-cliente');
    }

    function cliRoot() {
        return document.getElementById('erp-os-fin-cliente-lookup-root');
    }

    function cliUrl() {
        const wrap = cliInput()?.closest('[data-os-cliente-lookup-url]');

        return wrap?.getAttribute('data-os-cliente-lookup-url')
            || '/admin/erp/ordens-servico/clientes-lookup';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.erpOsCloseFinClienteLookup = function () {
        const root = cliRoot();
        if (root) root.innerHTML = '';
        cliSelected = 0;
        cliInput()?.setAttribute('aria-expanded', 'false');
    };

    function cliButtons() {
        return Array.from(document.querySelectorAll('#erp-os-fin-cliente-sugestoes [role="option"]'));
    }

    function markCli(index) {
        const buttons = cliButtons();
        if (! buttons.length) return;
        cliSelected = Math.max(0, Math.min(buttons.length - 1, index));
        buttons.forEach((btn, i) => {
            const on = i === cliSelected;
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        buttons[cliSelected]?.scrollIntoView({ block: 'nearest' });
    }

    function renderCli(rows) {
        const root = cliRoot();
        const input = cliInput();
        if (! root) return;

        if (! rows.length) {
            root.innerHTML = '';
            input?.setAttribute('aria-expanded', 'false');

            return;
        }

        const items = rows.map((row, index) => {
            const selected = index === 0 ? ' is-selected' : '';
            const docTipo = String(row.doc_tipo || '');
            const docClass = docTipo === 'cnpj' ? ' is-cnpj' : (docTipo === 'cpf' ? ' is-cpf' : (row.cpf_cnpj ? '' : ' is-empty'));
            const vencClass = row.tem_vencidas ? ' is-vencido' : '';

            return '<li role="presentation">'
                + '<button type="button" id="erp-os-fin-cliente-sug-' + index + '"'
                + ' role="option" data-id="' + escapeHtml(row.id) + '"'
                + ' data-os-cli-index="' + index + '"'
                + ' aria-selected="' + (index === 0 ? 'true' : 'false') + '"'
                + ' class="' + selected.trim() + '">'
                + '<span class="erp-fv-fin__suggest-code">' + escapeHtml(row.codigo) + '</span>'
                + '<span class="erp-fv-fin__suggest-nome">' + escapeHtml(row.nome) + '</span>'
                + '<span class="erp-fv-fin__suggest-credito">'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--lim">Lim '
                + escapeHtml(row.limite || '0,00') + '</span>'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--util">Util '
                + escapeHtml(row.utilizado || '0,00') + '</span>'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--venc'
                + vencClass + '">Venc ' + escapeHtml(row.vencidas || '0,00') + '</span>'
                + '</span>'
                + '<span class="erp-fv-fin__suggest-doc' + docClass + '">'
                + escapeHtml(row.cpf_cnpj || '') + '</span>'
                + '</button></li>';
        }).join('');

        root.innerHTML = '<ul id="erp-os-fin-cliente-sugestoes"'
            + ' class="erp-fv-fin__suggest erp-fv-fin__suggest--cliente"'
            + ' role="listbox" aria-label="Clientes encontrados">'
            + items
            + '</ul>';
        cliSelected = 0;
        input?.setAttribute('aria-expanded', 'true');
    }

    function scheduleCli(value) {
        const term = String(value || '').trim();
        const input = cliInput();
        const committed = String(input?.getAttribute('data-os-cliente-committed') || '').trim();

        window.clearTimeout(cliTimer);
        if (cliAbort) {
            cliAbort.abort();
            cliAbort = null;
        }

        if (term === '' || term.toUpperCase() === committed.toUpperCase()) {
            window.erpOsCloseFinClienteLookup?.();

            return;
        }

        cliTimer = window.setTimeout(() => fetchCli(term), 180);
    }

    function fetchCli(term) {
        const seq = ++cliSeq;
        const params = new URLSearchParams();
        params.set('q', term);
        cliAbort = new AbortController();

        fetch(cliUrl() + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: cliAbort.signal,
        }).then((r) => {
            if (! r.ok) throw new Error('os-cli ' + r.status);

            return r.json();
        }).then((payload) => {
            if (seq !== cliSeq) return;
            const typed = String(cliInput()?.value || '').trim();
            if (typed !== String(payload.term || '').trim()) return;
            renderCli(Array.isArray(payload.results) ? payload.results : []);
        }).catch((e) => {
            if (e?.name === 'AbortError') return;
        });
    }

    function selecionarCli(id) {
        const component = livewireNearFin();
        if (! component || ! id) return;
        window.erpOsCloseFinClienteLookup?.();
        component.call('selecionarClienteFaturamentoOs', Number(id));
    }

    document.addEventListener('input', (event) => {
        const el = event.target;
        if (! (el instanceof HTMLInputElement)) return;

        if (el.id && el.id.startsWith('erp-os-finalizar-valor-')) {
            const root = el.closest('.erp-os-fin');
            if (! root) return;
            el.closest('tr')?.classList.toggle(
                'erp-pdv-finalizar__pay-row--filled',
                window.erpOsParseMoneyBr(el.value) > 0.009
            );
            window.erpOsUpdateFinTotaisUi(root);

            return;
        }

        if (el.id === 'erp-os-fin-cliente') {
            scheduleCli(el.value);
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.target?.id === 'erp-os-fin-cliente') {
            const key = event.key;
            const open = cliButtons().length > 0;

            if (key === 'ArrowDown' || key === 'ArrowUp') {
                if (! open) return;
                event.preventDefault();
                markCli(cliSelected + (key === 'ArrowDown' ? 1 : -1));

                return;
            }

            if (key === 'Escape') {
                if (open) {
                    event.preventDefault();
                    window.erpOsCloseFinClienteLookup?.();
                }
                window.erpOsFinClienteRestoreCommitted?.();

                return;
            }

            if (key === 'Enter') {
                if (event.repeat) {
                    event.preventDefault();

                    return;
                }
                event.preventDefault();
                if (open) {
                    const btn = cliButtons()[cliSelected] || cliButtons()[0];
                    const id = Number(btn?.getAttribute('data-id') || 0);
                    if (id > 0) {
                        selecionarCli(id);

                        return;
                    }
                }
                window.erpOsFinClienteRestoreCommitted?.();
            }

            return;
        }
    }, true);

    document.addEventListener('click', (event) => {
        const btn = event.target?.closest?.('#erp-os-fin-cliente-sugestoes [role="option"]');
        if (! btn) return;
        event.preventDefault();
        const id = Number(btn.getAttribute('data-id') || 0);
        if (id > 0) selecionarCli(id);
    }, true);

    document.addEventListener('mouseover', (event) => {
        const btn = event.target?.closest?.('#erp-os-fin-cliente-sugestoes [role="option"]');
        if (! btn) return;
        markCli(Number(btn.getAttribute('data-os-cli-index') || 0));
    }, true);

    /**
     * Setas em window.capture (paridade TV): chega antes do input/blur e do keydown em document.
     * Letras/F + Concluir: Alpine x-on:keydown.window.capture no modal (mesmo padrão da TV).
     * Dividir / cliente / ajustes / CPF / infos: não intercepta.
     */
    window.addEventListener('keydown', (event) => {
        const root = finRoot();
        if (! root) return;
        if (
            root.getAttribute('data-erp-os-fin-closing') === '1'
            || root.getAttribute('aria-hidden') === 'true'
            || root.style.display === 'none'
        ) {
            return;
        }

        const tecla = event.key || '';
        if (tecla !== 'ArrowDown' && tecla !== 'ArrowUp') return;
        if (root.querySelector('.erp-pdv-parcelas-overlay')) return;
        if (document.querySelector('.erp-fv-transporte-modal')) return;

        const alvo = event.target;
        if (
            alvo?.id === 'erp-os-fin-cliente'
            || alvo?.id === 'erp-os-finalizar-cpf'
            || alvo?.id === 'erp-os-finalizar-informacoes'
            || alvo?.closest?.('.erp-fv-fin__ajuste')
            || alvo?.closest?.('[data-erp-dividir-conta]')
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        window.erpOsMovePagamentoSelection?.(tecla === 'ArrowDown' ? 1 : -1);
    }, true);

    document.addEventListener('livewire:init', () => {
        Livewire.on('erp-os-fin-cpf-synced', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            const value = detail && typeof detail === 'object' && 'value' in detail ? detail.value : detail;
            window.erpOsSyncFinCpfNota(value);
        });
        Livewire.on('erp-os-fin-cliente-synced', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            const value = detail && typeof detail === 'object' && 'value' in detail ? detail.value : detail;
            window.erpOsSyncFinCliente(value);
        });
        // Foco de meios: só Alpine no modal (paridade TV). Livewire.on com hasValor
        // ignorava focusEpoch e roubava o foco do Concluir após o 1º Enter.

        // Paridade TV: remorph (desconto/acréscimo live) não pode restaurar índice PHP stale.
        Livewire.hook('morph.updated', () => {
            const root = finRoot();
            if (! root) {
                window.__erpOsPayNav = { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };

                return;
            }
            // Não mexer no foco se o operador já está no Concluir (Enter → Enter).
            if (window.__erpOsPayNav?.concluirFocused) {
                return;
            }
            if ((window.__erpOsPayNav?.focusEpoch || 0) > 0) {
                window.erpOsApplyPagamentoSelection?.(window.__erpOsPayNav.index, {
                    focus: false,
                    fromServer: true,
                });

                return;
            }
            const rows = window.erpOsPayRows?.(root) || [];
            const selected = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
            if (selected >= 0) {
                window.__erpOsPayNav.index = selected;
            }
        });
    });
})();
