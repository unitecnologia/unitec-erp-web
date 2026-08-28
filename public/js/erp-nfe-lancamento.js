document.addEventListener('DOMContentLoaded', initErpNfeLancamento);
document.addEventListener('livewire:navigated', initErpNfeLancamento);

function bindErpNfeLancamentoLivewireEvents() {
    if (window.__erpNfeLancamentoLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpNfeLancamentoLivewireBound = true;

    window.Livewire.on('erp-nfe-focus-item-codigo', () => {
        focusNfeLancamentoInput('nfe-inclusao-produto');
    });

    window.Livewire.on('erp-nfe-focus-item-produto', () => {
        focusNfeLancamentoInput('nfe-inclusao-produto');
        requestAnimationFrame(positionNfeProdutoLookup);
    });

    window.Livewire.on('erp-nfe-focus-item-cfop', () => {
        focusNfeLancamentoInput('nfe-item-cfop');
    });

    window.Livewire.on('erp-nfe-focus-item-cst', () => {
        focusNfeLancamentoInput('nfe-item-cst');
    });

    window.Livewire.on('erp-nfe-focus-item-preco', () => {
        window.setTimeout(() => focusNfeLancamentoInput('nfe-inclusao-preco'), 40);
    });

    window.Livewire.on('erp-nfe-focus-item-quantidade', () => {
        window.setTimeout(() => focusNfeLancamentoInput('nfe-inclusao-qtd'), 40);
    });

    window.Livewire.on('erp-nfe-focus-item-unidade', () => {
        focusNfeLancamentoInput('nfe-item-unidade');
    });

    window.Livewire.on('erp-nfe-scroll-produto-selection', () => {
        scrollNfeProdutoSelectionIntoView();
        positionNfeProdutoLookup();
    });

    window.Livewire.on('erp-nfe-scroll-item-selection', () => {
        scrollNfeItemSelectionIntoView();
        focusNfeItemGridWrap();
    });

    window.Livewire.hook('morph.updated', () => {
        requestAnimationFrame(positionNfeProdutoLookup);
        refocusNfeTotaisAfterMorph();
    });

    window.Livewire.on('erp-nfe-focus-fiscal-overlay', () => {
        // Conteúdo vem do $this->js(...payload); aqui só garante foco/progresso se o js atrasar.
        hideNfeFiscalTransmitProgress();
        window.requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-overlay-entendido')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-fiscal-sucesso', () => {
        hideNfeFiscalTransmitProgress();
        window.requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-sucesso-imprimir')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-hide-fiscal-progress', () => {
        hideNfeFiscalTransmitProgress();
        syncNfeTransmitButtonState();
    });

    window.Livewire.on('erp-nfe-sync-transmit-btn', () => {
        window.requestAnimationFrame(() => syncNfeTransmitButtonState(true));
    });

    // Sempre esconder progresso ao terminar o request (sucesso, erro ou falha de rede).
    window.Livewire.hook('commit', ({ succeed, fail }) => {
        succeed(() => {
            hideNfeFiscalTransmitProgress();
            syncNfeTransmitButtonState();
        });
        fail(() => {
            hideNfeFiscalTransmitProgress();
            syncNfeTransmitButtonState();
        });
    });

    window.Livewire.hook('request', ({ respond }) => {
        respond(() => {
            hideNfeFiscalTransmitProgress();
            syncNfeTransmitButtonState();
        });
    });

    window.Livewire.on('erp-nfe-open-danfe', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.ErpNfePrint.openDanfe(url);
        }
    });

    window.Livewire.on('erp-nfe-open-whatsapp', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });

    window.Livewire.on('erp-nfe-focus-fiscal-info', () => {
        hideNfeFiscalTransmitProgress();
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-info-ok')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cancel-aberta', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cancel-aberta-sim')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-item-delete-opened', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-item-delete-sim')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-whatsapp-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-whatsapp-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-danfe-email-modal', () => {
        requestAnimationFrame(() => {
            const modal = document.querySelector('.erp-nfe-danfe-email-modal');

            if (modal && window.ErpMasks) {
                window.ErpMasks.init(modal);
            }

            const whatsapp = document.getElementById('erp-nfe-danfe-whatsapp-to');

            if (whatsapp && window.ErpMasks) {
                window.ErpMasks.apply(whatsapp, { allowEmptySync: true, live: true });
            }

            document.getElementById('erp-nfe-danfe-email-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cancel-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cancel-justificativa')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-inutilizar-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-inutilizar-justificativa')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-inutilizar-sucesso', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-inutilizar-sucesso-ok')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-correcao')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-sucesso', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-cce-sucesso-imprimir')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-whatsapp-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-whatsapp-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-email-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-email-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-import-menu', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-import-menu-numero')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-import-list', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-import-numero')?.focus();
        });
    });
}

document.addEventListener('livewire:init', bindErpNfeLancamentoLivewireEvents);
document.addEventListener('DOMContentLoaded', bindErpNfeLancamentoLivewireEvents);

if (window.Livewire) {
    bindErpNfeLancamentoLivewireEvents();
}

function initErpNfeLancamento() {
    bindErpNfeLancamentoKeys();
    bindErpNfeTotaisEnter();
    bindNfeProdutoLookupFloating();
    bindNfeFiscalTransmitTriggers();
    requestAnimationFrame(positionNfeProdutoLookup);
}

const ERP_NFE_TOTAIS_ORDER = ['frete', 'seguro', 'outras', 'desconto'];

function findNfeTotaisInput(modal, key) {
    return modal.querySelector('input[data-erp-nfe-totais="' + key + '"]');
}

function finalizeNfeTotaisMask(el) {
    if (! (window.ErpMasks && el.dataset && el.dataset.mask)) {
        return;
    }

    try {
        el.value = window.ErpMasks.finalizeMaskValue(el);
        window.ErpMasks.apply(el, {
            sync: false,
            allowEmptySync: true,
            thousands: window.ErpMasks.isBrDecimalMask(el.dataset.mask),
        });
    } catch (error) {
        // ignore
    }
}

function focarNfeTotais(modal, key) {
    const input = findNfeTotaisInput(modal, key);

    if (! input || input.disabled) {
        return false;
    }

    input.removeAttribute('readonly');

    try {
        input.focus({ preventScroll: true });
        input.select();
    } catch (error) {
        try {
            input.focus();
            input.select();
        } catch (ignored) {
            return false;
        }
    }

    return true;
}

function scheduleNfeTotaisFocus(modal, key) {
    if (! key) {
        return;
    }

    window.__erpNfeTotaisFocusKey = key;
    window.__erpNfeFocusUntil = Date.now() + 2000;

    focarNfeTotais(modal, key);
    window.setTimeout(() => focarNfeTotais(modal, key), 0);
    window.setTimeout(() => focarNfeTotais(modal, key), 60);
    window.setTimeout(() => focarNfeTotais(modal, key), 160);
    window.setTimeout(() => focarNfeTotais(modal, key), 320);
}

function refocusNfeTotaisAfterMorph() {
    if (Date.now() >= (window.__erpNfeFocusUntil || 0)) {
        return;
    }

    const key = window.__erpNfeTotaisFocusKey;

    if (! key) {
        return;
    }

    const modal = document.querySelector('.erp-nfe-lancamento-modal');

    if (! modal) {
        return;
    }

    focarNfeTotais(modal, key);
}

function bindErpNfeTotaisEnter() {
    if (window.__erpNfeTotaisEnterBound) {
        return;
    }

    window.__erpNfeTotaisEnterBound = true;
    window.__erpNfeFocusUntil = 0;
    window.__erpNfeTotaisFocusKey = null;

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
            return;
        }

        const el = event.target;

        if (! (el instanceof HTMLInputElement)) {
            return;
        }

        if (! el.hasAttribute('data-erp-nfe-totais')) {
            return;
        }

        const modal = el.closest('.erp-nfe-lancamento-modal');

        if (! modal || el.disabled) {
            return;
        }

        el.removeAttribute('readonly');
        event.preventDefault();

        const key = el.getAttribute('data-erp-nfe-totais');
        const idx = ERP_NFE_TOTAIS_ORDER.indexOf(key);

        if (idx < 0) {
            return;
        }

        finalizeNfeTotaisMask(el);

        const nextKey = ERP_NFE_TOTAIS_ORDER[idx + 1];

        if (nextKey) {
            scheduleNfeTotaisFocus(modal, nextKey);
        }
    }, true);
}

let nfeFiscalProgressStepIndex = 0;
let nfeFiscalProgressActive = false;
let nfeFiscalStreamObserver = null;
let nfeFiscalProgressWatchdogTimer = null;
let nfeFiscalProgressStepTimer = null;
const NFE_FISCAL_PROGRESS_WATCHDOG_MS = 90000;
const NFE_FISCAL_PROGRESS_STEP_MS = 800;
const NFE_FISCAL_PROGRESS_HOLD_STEP = 3; // Enviando à SEFAZ — etapa longa real
const NFE_FISCAL_PROGRESS_STEP_LABELS = [
    'Validando dados da NF-e',
    'Montando XML do documento',
    'Assinando digitalmente',
    'Enviando à SEFAZ (aguardando resposta)',
    'Processando autorização',
];

function bindNfeFiscalTransmitTriggers() {
    if (window.__erpNfeFiscalTransmitTriggersBound) {
        return;
    }

    window.__erpNfeFiscalTransmitTriggersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[wire\\:click="transmitNfe"], [wire\\:click\\.prevent="transmitNfe"]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfeFiscalTransmitProgress, 30);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F3' || event.defaultPrevented) {
            return;
        }

        if (! document.querySelector('.erp-nfe-lancamento-modal')) {
            return;
        }

        const button = document.querySelector('.erp-nfe-lancamento-modal [wire\\:click="transmitNfe"]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfeFiscalTransmitProgress, 30);
    }, true);

    bindNfeFiscalProgressStreamObserver();
}

function getNfeFiscalTransmitProgressOverlay() {
    return document.querySelector('[data-erp-nfe-fiscal-progress]');
}

function resetNfeFiscalTransmitProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    nfeFiscalProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-nfe-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfe-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfe-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfe-fiscal-step-bar]');

    steps.forEach((step, index) => {
        step.classList.toggle('is-active', index === 0);
        step.classList.toggle('is-done', false);
    });

    if (statusEl && steps[0]) {
        statusEl.textContent = `${steps[0].textContent.trim()}…`;
    }

    if (barEl) {
        barEl.style.width = '12%';
    }
}

/**
 * Avança para a etapa informada pelo servidor (0..4). Não usa timer.
 */
function setNfeFiscalTransmitProgressStep(stepIndex, label) {
    const overlay = getNfeFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    if (! nfeFiscalProgressActive) {
        nfeFiscalProgressActive = true;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');
    }

    const panel = overlay.querySelector('[data-erp-nfe-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfe-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfe-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfe-fiscal-step-bar]');
    const target = Math.max(0, Math.min(steps.length - 1, Number(stepIndex) || 0));

    nfeFiscalProgressStepIndex = target;

    steps.forEach((step, index) => {
        step.classList.toggle('is-done', index < target);
        step.classList.toggle('is-active', index === target);
    });

    const text = (label && String(label).trim()) || (steps[target] ? steps[target].textContent.trim() : '');

    if (statusEl && text) {
        statusEl.textContent = text.endsWith('…') || text.endsWith('...') ? text : `${text}…`;
    }

    if (barEl && steps.length > 0) {
        const percent = Math.min(100, Math.round(((target + 1) / steps.length) * 100));
        barEl.style.width = `${percent}%`;
    }
}

window.__erpNfeSetFiscalStep = setNfeFiscalTransmitProgressStep;

function applyNfeFiscalProgressStreamPayload(raw) {
    const text = String(raw || '').trim();

    if (! text) {
        return;
    }

    try {
        const data = JSON.parse(text);
        setNfeFiscalTransmitProgressStep(data.step, data.label);
    } catch (e) {
        // ignore non-JSON chunks
    }
}

function bindNfeFiscalProgressStreamObserver() {
    const streamEl = document.querySelector('[data-erp-nfe-fiscal-progress-stream]');

    if (! streamEl || nfeFiscalStreamObserver) {
        return;
    }

    nfeFiscalStreamObserver = new MutationObserver(() => {
        applyNfeFiscalProgressStreamPayload(streamEl.textContent);
    });

    nfeFiscalStreamObserver.observe(streamEl, {
        childList: true,
        characterData: true,
        subtree: true,
    });
}

function clearNfeFiscalTransmitWatchdog() {
    if (nfeFiscalProgressWatchdogTimer) {
        window.clearTimeout(nfeFiscalProgressWatchdogTimer);
        nfeFiscalProgressWatchdogTimer = null;
    }
}

function clearNfeFiscalProgressStepTimer() {
    if (nfeFiscalProgressStepTimer) {
        window.clearInterval(nfeFiscalProgressStepTimer);
        nfeFiscalProgressStepTimer = null;
    }
}

/**
 * Avanço cosmético 0→1→2→3 (1 a 1). Para na etapa SEFAZ até o request terminar.
 * Sem stream Livewire — evita corromper o overlay de erro.
 */
function startNfeFiscalProgressStepTimer() {
    clearNfeFiscalProgressStepTimer();

    nfeFiscalProgressStepTimer = window.setInterval(() => {
        if (! nfeFiscalProgressActive) {
            clearNfeFiscalProgressStepTimer();

            return;
        }

        if (nfeFiscalProgressStepIndex >= NFE_FISCAL_PROGRESS_HOLD_STEP) {
            clearNfeFiscalProgressStepTimer();

            return;
        }

        const next = nfeFiscalProgressStepIndex + 1;
        const label = NFE_FISCAL_PROGRESS_STEP_LABELS[next] || '';
        setNfeFiscalTransmitProgressStep(next, label);

        if (next >= NFE_FISCAL_PROGRESS_HOLD_STEP) {
            clearNfeFiscalProgressStepTimer();
        }
    }, NFE_FISCAL_PROGRESS_STEP_MS);
}

function stopNfeFiscalTransmitProgress() {
    nfeFiscalProgressActive = false;
    clearNfeFiscalTransmitWatchdog();
    clearNfeFiscalProgressStepTimer();
}

function notifyNfeFiscalProgressStuck(message) {
    try {
        if (typeof FilamentNotification !== 'undefined') {
            new FilamentNotification()
                .title('NF-e — atenção')
                .body(message)
                .warning()
                .persistent()
                .send();

            return;
        }
    } catch (_e) {
        // fallback
    }

    window.alert(message);
}

function forceHideNfeFiscalTransmitProgressStuck(reason) {
    if (! nfeFiscalProgressActive) {
        const overlay = getNfeFiscalTransmitProgressOverlay();

        if (! overlay?.classList.contains('is-visible')) {
            return;
        }
    }

    hideNfeFiscalTransmitProgress();
    notifyNfeFiscalProgressStuck(
        reason
        || 'A transmissão da NF-e demorou demais ou a conexão caiu. Tente novamente; se o erro persistir, confira o histórico da nota.'
    );
}

function startNfeFiscalTransmitProgress() {
    const overlay = getNfeFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    clearNfeFiscalTransmitWatchdog();
    clearNfeFiscalProgressStepTimer();
    bindNfeFiscalProgressStreamObserver();
    nfeFiscalProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetNfeFiscalTransmitProgressUi(overlay);
    setNfeFiscalTransmitProgressStep(0, NFE_FISCAL_PROGRESS_STEP_LABELS[0]);
    startNfeFiscalProgressStepTimer();

    nfeFiscalProgressWatchdogTimer = window.setTimeout(() => {
        nfeFiscalProgressWatchdogTimer = null;
        forceHideNfeFiscalTransmitProgressStuck(
            'A transmissão da NF-e demorou demais ou a conexão caiu. Tente novamente; se o erro persistir, confira o histórico da nota.'
        );
    }, NFE_FISCAL_PROGRESS_WATCHDOG_MS);
}

function hideNfeFiscalTransmitProgress() {
    stopNfeFiscalTransmitProgress();

    document.querySelectorAll('.erp-nfe-fiscal-progress').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetNfeFiscalTransmitProgressUi(getNfeFiscalTransmitProgressOverlay());
}

/** Força o overlay vermelho padrão e preenche título/corpo (morph às vezes deixa vazio). */
function showNfeFiscalErroOverlayUi(payload) {
    hideNfeFiscalTransmitProgress();

    const data = payload && typeof payload === 'object' ? payload : {};
    const overlay = document.querySelector('[data-erp-nfe-fiscal-erro-overlay]')
        || document.getElementById('erp-nfe-fiscal-erro-overlay');

    if (! overlay) {
        return;
    }

    const titulo = String(data.titulo ?? '').trim();
    const mensagem = String(data.mensagem ?? '').trim();
    const codigo = String(data.codigo ?? '').trim();

    const titleEl = overlay.querySelector('#erp-nfe-fiscal-overlay-title');
    const codigoEl = overlay.querySelector('.erp-nfe-fiscal-overlay__codigo');
    const textEl = overlay.querySelector('.erp-nfe-fiscal-overlay__text');
    const origemEl = overlay.querySelector('.erp-nfe-fiscal-overlay__origem');

    if (titleEl) {
        titleEl.textContent = titulo;
    }

    if (codigoEl) {
        const strong = codigoEl.querySelector('strong');

        if (strong) {
            strong.textContent = codigo;
        }

        codigoEl.style.display = codigo !== '' ? 'block' : 'none';
    }

    if (textEl) {
        textEl.textContent = '';

        if (mensagem !== '') {
            mensagem.split(/\n/).forEach((line, index) => {
                if (index > 0) {
                    textEl.appendChild(document.createElement('br'));
                }

                textEl.appendChild(document.createTextNode(line));
            });
            textEl.style.display = 'block';
        } else {
            textEl.style.display = 'none';
        }
    }

    if (origemEl) {
        // Só marca como SEFAZ quando há cStat; validação local (NCM etc.) não é SEFAZ.
        origemEl.style.display = codigo !== '' ? 'block' : 'none';
    }

    overlay.style.display = 'grid';
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-hidden', 'false');
    overlay.removeAttribute('hidden');

    window.requestAnimationFrame(() => {
        document.getElementById('erp-nfe-fiscal-overlay-entendido')?.focus();
    });
}

function showNfeFiscalSucessoOverlayUi(payload) {
    hideNfeFiscalTransmitProgress();

    const data = payload && typeof payload === 'object' ? payload : {};
    const overlay = document.querySelector('[data-erp-nfe-fiscal-sucesso-overlay]')
        || document.getElementById('erp-nfe-fiscal-sucesso-overlay');

    if (! overlay) {
        return;
    }

    const detalhe = String(data.detalhe ?? '').trim();
    const detalheEl = overlay.querySelector('.erp-nfe-fiscal-overlay__codigo');

    if (detalheEl && detalhe !== '') {
        detalheEl.textContent = detalhe;
    }

    overlay.style.display = 'grid';
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-hidden', 'false');
    overlay.removeAttribute('hidden');

    window.requestAnimationFrame(() => {
        document.getElementById('erp-nfe-fiscal-sucesso-imprimir')?.focus();
    });
}

window.__erpNfeShowFiscalErroOverlay = showNfeFiscalErroOverlayUi;
window.__erpNfeShowFiscalSucessoOverlay = showNfeFiscalSucessoOverlayUi;

/** Remove disabled residual do F3 (ex.: loading antigo). Após F2 o evento força liberar. */
function syncNfeTransmitButtonState(forceEnable = false) {
    const btn = document.querySelector('[data-erp-nfe-transmit-btn]');

    if (! btn) {
        return;
    }

    if (forceEnable) {
        btn.removeAttribute('disabled');
    }
}

let nfeDanfePrintInFlight = false;

window.ErpNfePrint = {
    openDanfe(url) {
        openNfeDanfePrint(url);
    },
};

function openNfeDanfePrint(url) {
    if (! url || nfeDanfePrintInFlight) {
        return;
    }

    nfeDanfePrintInFlight = true;

    document.getElementById('erp-nfe-print-frame')?.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'erp-nfe-print-frame';
    iframe.src = url;
    iframe.title = 'Impressão DANFE NF-e';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = [
        'position: fixed',
        'width: 0',
        'height: 0',
        'border: 0',
        'opacity: 0',
        'pointer-events: none',
        'left: -9999px',
        'top: -9999px',
    ].join(';');

    let cleanedUp = false;

    const cleanup = () => {
        if (cleanedUp) {
            return;
        }

        cleanedUp = true;
        nfeDanfePrintInFlight = false;
        iframe.remove();
        window.removeEventListener('message', onMessage);
    };

    const onMessage = (event) => {
        if (event.source !== iframe.contentWindow) {
            return;
        }

        if (event.data?.type === 'erp-nfe-danfe-print-done') {
            cleanup();
        }
    };

    const fallbackToNewTab = () => {
        cleanup();

        const separator = url.includes('?') ? '&' : '?';
        window.open(`${url}${separator}auto=1`, '_blank', 'noopener');
    };

    const printFrame = () => {
        const frameWindow = iframe.contentWindow;

        if (! frameWindow) {
            fallbackToNewTab();

            return;
        }

        try {
            frameWindow.focus();
            frameWindow.print();
            window.setTimeout(() => {
                nfeDanfePrintInFlight = false;
            }, 2000);
        } catch (error) {
            nfeDanfePrintInFlight = false;
            fallbackToNewTab();
        }
    };

    window.addEventListener('message', onMessage);

    iframe.addEventListener('load', () => {
        window.setTimeout(printFrame, 150);
        window.setTimeout(cleanup, 120000);
    }, { once: true });

    iframe.addEventListener('error', fallbackToNewTab, { once: true });

    document.body.appendChild(iframe);
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
    const wrap = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-inclusao__barcode-wrap')
        ?? document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-field');

    if (! wrap) {
        return null;
    }

    return wrap.querySelector('.erp-nfe-produto-lookup-panel')
        ?? wrap.querySelector('.erp-nfe-inclusao__suggest')
        ?? wrap.querySelector('.erp-nfe-produto-lookup--empty');
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
    const input = document.getElementById('nfe-inclusao-produto') ?? document.getElementById('nfe-item-produto');
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

    let componentEl = modal.closest('[wire\\:id]');

    if (! componentEl) {
        componentEl = document.querySelector('.erp-nfe-page [wire\\:id]');
    }

    if (! componentEl) {
        let node = modal.parentElement;

        while (node) {
            if (node.hasAttribute?.('wire:id')) {
                componentEl = node;
                break;
            }

            node = node.parentElement;
        }
    }

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function focusNfeItemGridWrap() {
    window.requestAnimationFrame(() => {
        const impostos = document.querySelector('.erp-nfe-impostos__grid-wrap');
        const itens = document.querySelector('.erp-nfe-lancamento-modal__grid-wrap--itens');
        const target = impostos?.offsetParent !== null ? impostos : itens;

        target?.focus({ preventScroll: true });
    });
}

function shouldNfeNavigateItemRows(event) {
    if (document.querySelector('.erp-nfe-item-delete-modal')) {
        return false;
    }

    const produtoInput = document.getElementById('nfe-inclusao-produto');
    const produtoSuggest = document.querySelector('.erp-nfe-inclusao__suggest-wrap');

    if (
        produtoInput
        && (document.activeElement === produtoInput || event.target === produtoInput)
        && produtoSuggest
        && produtoSuggest.offsetParent !== null
    ) {
        return false;
    }

    const active = document.activeElement;
    const target = event.target;
    const gridSelectors = [
        '.erp-nfe-lancamento-modal__grid-wrap--itens',
        '.erp-nfe-impostos__grid-wrap',
    ];

    for (const selector of gridSelectors) {
        if (active?.closest?.(selector) || target?.closest?.(selector)) {
            return true;
        }

        if (active?.matches?.(selector)) {
            return true;
        }
    }

    return false;
}

function bindErpNfeLancamentoKeys() {
    if (window.__erpNfeLancamentoKeysBound) {
        return;
    }

    window.__erpNfeLancamentoKeysBound = true;

    // Capture: roda antes do erp-list.js e evita Delete chamar deleteRecord da listagem.
    document.addEventListener('keydown', handleErpNfeLancamentoKeydown, true);
}

function handleErpNfeLancamentoKeydown(event) {
    if (! document.querySelector('.erp-nfe-lancamento-modal')) {
        return;
    }

    const component = getErpNfeLancamentoComponent();

    if (! component) {
        return;
    }

    const produtoFocused = document.activeElement?.id === 'nfe-inclusao-produto' || document.activeElement?.id === 'nfe-item-produto';

    if (produtoFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        const lookup = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-lookup-panel');

        if (! lookup) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const delta = event.key === 'ArrowDown' ? 1 : -1;

        component.call('moveNfeProdutoSelection', delta);

        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (! shouldNfeNavigateItemRows(event)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const delta = event.key === 'ArrowDown' ? 1 : -1;
        component.call('moveNfeSelectedRow', delta);

        return;
    }

    if (document.querySelector('.erp-nfe-item-delete-modal')) {
        if (event.key === 'Enter') {
            event.preventDefault();
            event.stopImmediatePropagation();
            component.call('confirmDeleteNfeItem');
        } else if (event.key === 'Escape' || event.key === 'Delete') {
            event.stopImmediatePropagation();
        }

        return;
    }

    if (event.key !== 'Delete') {
        return;
    }

    const inItensGrid = Boolean(event.target?.closest?.('.erp-nfe-lancamento-modal__grid-wrap--itens'));
    const inImpostosGrid = Boolean(event.target?.closest?.('.erp-nfe-impostos'));

    // Na grade, Delete remove o item (com confirmação). Fora dela, só se não estiver digitando.
    if (! inItensGrid && ! inImpostosGrid && isNfeEditableTarget(event.target)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const row = event.target?.closest?.('[data-erp-nfe-item-index]');
    const indexAttr = row?.getAttribute?.('data-erp-nfe-item-index');

    if (indexAttr !== null && indexAttr !== undefined && indexAttr !== '') {
        component.call('requestDeleteNfeItem', Number.parseInt(indexAttr, 10));

        return;
    }

    component.call('requestDeleteNfeSelectedItem');
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

function scrollNfeItemSelectionIntoView() {
    window.requestAnimationFrame(() => {
        const selected = document.querySelector(
            '.erp-nfe-lancamento-modal__grid-wrap--itens tr.erp-nfe-lancamento-modal__row--selected, '
            + '.erp-nfe-impostos__grid-wrap tr.erp-lookup-modal__row--selected',
        );

        selected?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
}

bindErpNfeLancamentoLivewireEvents();
initErpNfeLancamento();

