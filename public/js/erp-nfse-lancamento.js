document.addEventListener('DOMContentLoaded', initErpNfseLancamento);
document.addEventListener('livewire:navigated', initErpNfseLancamento);

function initErpNfseLancamento() {
    bindNfseFiscalTransmitTriggers();
    bindErpNfseLancamentoLivewireEvents();
}

function bindErpNfseLancamentoLivewireEvents() {
    if (window.__erpNfseLancamentoLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpNfseLancamentoLivewireBound = true;

    window.Livewire.on('erp-nfse-hide-fiscal-progress', () => {
        hideNfseFiscalTransmitProgress();
        syncNfseTransmitButtonState();
    });

    window.Livewire.on('erp-nfse-sync-transmit-btn', () => {
        window.requestAnimationFrame(() => syncNfseTransmitButtonState(true));
    });

    // Sempre esconder progresso ao terminar o request (sucesso, erro ou falha de rede).
    window.Livewire.hook('commit', ({ succeed, fail }) => {
        succeed(() => {
            hideNfseFiscalTransmitProgress();
            syncNfseTransmitButtonState();
        });
        fail(() => {
            hideNfseFiscalTransmitProgress();
            syncNfseTransmitButtonState();
        });
    });

    window.Livewire.hook('request', ({ respond }) => {
        respond(() => {
            hideNfseFiscalTransmitProgress();
            syncNfseTransmitButtonState();
        });
    });
}

function syncNfseTransmitButtonState(forceEnable = false) {
    const btn = document.querySelector('[data-erp-nfse-transmit-btn]');

    if (! btn) {
        return;
    }

    if (forceEnable) {
        btn.removeAttribute('disabled');
    }
}

let nfseFiscalProgressStepIndex = 0;
let nfseFiscalProgressActive = false;
let nfseFiscalProgressWatchdogTimer = null;
let nfseFiscalProgressStepTimer = null;
const NFSE_FISCAL_PROGRESS_WATCHDOG_MS = 90000;
const NFSE_FISCAL_PROGRESS_STEP_MS = 800;
const NFSE_FISCAL_PROGRESS_HOLD_STEP = 3; // Enviando à SEFIN — etapa longa real
const NFSE_FISCAL_PROGRESS_STEP_LABELS = [
    'Validando dados da NFS-e',
    'Montando XML do documento',
    'Assinando digitalmente',
    'Enviando à SEFIN (aguardando resposta)',
    'Processando autorização',
];

function bindNfseFiscalTransmitTriggers() {
    if (window.__erpNfseFiscalTransmitTriggersBound) {
        return;
    }

    window.__erpNfseFiscalTransmitTriggersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest(
            '[wire\\:click="transmitirNfse"], [wire\\:click\\.prevent="transmitirNfse"],'
            + ' [wire\\:click="confirmarTransmissaoProducao"], [wire\\:click\\.prevent="confirmarTransmissaoProducao"]'
        );

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfseFiscalTransmitProgress, 30);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F3' || event.defaultPrevented) {
            return;
        }

        if (! document.querySelector('.erp-nfse-lancamento-modal')) {
            return;
        }

        const button = document.querySelector('.erp-nfse-lancamento-modal [data-erp-nfse-transmit-btn]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfseFiscalTransmitProgress, 30);
    }, true);
}

function getNfseFiscalTransmitProgressOverlay() {
    return document.querySelector('[data-erp-nfse-fiscal-progress]');
}

function resetNfseFiscalTransmitProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    nfseFiscalProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-nfse-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfse-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfse-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfse-fiscal-step-bar]');

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

function setNfseFiscalTransmitProgressStep(stepIndex, label) {
    const overlay = getNfseFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    if (! nfseFiscalProgressActive) {
        nfseFiscalProgressActive = true;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');
    }

    const panel = overlay.querySelector('[data-erp-nfse-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfse-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfse-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfse-fiscal-step-bar]');
    const target = Math.max(0, Math.min(steps.length - 1, Number(stepIndex) || 0));

    nfseFiscalProgressStepIndex = target;

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

window.__erpNfseSetFiscalStep = setNfseFiscalTransmitProgressStep;

function clearNfseFiscalTransmitWatchdog() {
    if (nfseFiscalProgressWatchdogTimer) {
        window.clearTimeout(nfseFiscalProgressWatchdogTimer);
        nfseFiscalProgressWatchdogTimer = null;
    }
}

function clearNfseFiscalProgressStepTimer() {
    if (nfseFiscalProgressStepTimer) {
        window.clearInterval(nfseFiscalProgressStepTimer);
        nfseFiscalProgressStepTimer = null;
    }
}

/**
 * Avanço cosmético 0→1→2→3. Para na etapa SEFIN até o request terminar.
 * Mesmo padrão da NF-e (sem stream Livewire).
 */
function startNfseFiscalProgressStepTimer() {
    clearNfseFiscalProgressStepTimer();

    nfseFiscalProgressStepTimer = window.setInterval(() => {
        if (! nfseFiscalProgressActive) {
            clearNfseFiscalProgressStepTimer();

            return;
        }

        if (nfseFiscalProgressStepIndex >= NFSE_FISCAL_PROGRESS_HOLD_STEP) {
            clearNfseFiscalProgressStepTimer();

            return;
        }

        const next = nfseFiscalProgressStepIndex + 1;
        const label = NFSE_FISCAL_PROGRESS_STEP_LABELS[next] || '';
        setNfseFiscalTransmitProgressStep(next, label);

        if (next >= NFSE_FISCAL_PROGRESS_HOLD_STEP) {
            clearNfseFiscalProgressStepTimer();
        }
    }, NFSE_FISCAL_PROGRESS_STEP_MS);
}

function stopNfseFiscalTransmitProgress() {
    nfseFiscalProgressActive = false;
    clearNfseFiscalTransmitWatchdog();
    clearNfseFiscalProgressStepTimer();
}

function notifyNfseFiscalProgressStuck(message) {
    try {
        if (typeof FilamentNotification !== 'undefined') {
            new FilamentNotification()
                .title('NFS-e — atenção')
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

function forceHideNfseFiscalTransmitProgressStuck(reason) {
    if (! nfseFiscalProgressActive) {
        const overlay = getNfseFiscalTransmitProgressOverlay();

        if (! overlay?.classList.contains('is-visible')) {
            return;
        }
    }

    hideNfseFiscalTransmitProgress();
    notifyNfseFiscalProgressStuck(
        reason
        || 'A transmissão da NFS-e demorou demais ou a conexão caiu. Tente novamente; se o erro persistir, confira a nota.'
    );
}

function startNfseFiscalTransmitProgress() {
    const overlay = getNfseFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    clearNfseFiscalTransmitWatchdog();
    clearNfseFiscalProgressStepTimer();
    nfseFiscalProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetNfseFiscalTransmitProgressUi(overlay);
    setNfseFiscalTransmitProgressStep(0, NFSE_FISCAL_PROGRESS_STEP_LABELS[0]);
    startNfseFiscalProgressStepTimer();

    nfseFiscalProgressWatchdogTimer = window.setTimeout(() => {
        nfseFiscalProgressWatchdogTimer = null;
        forceHideNfseFiscalTransmitProgressStuck(
            'A transmissão da NFS-e demorou demais ou a conexão caiu. Tente novamente; se o erro persistir, confira a nota.'
        );
    }, NFSE_FISCAL_PROGRESS_WATCHDOG_MS);
}

function hideNfseFiscalTransmitProgress() {
    stopNfseFiscalTransmitProgress();

    document.querySelectorAll('[data-erp-nfse-fiscal-progress]').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetNfseFiscalTransmitProgressUi(getNfseFiscalTransmitProgressOverlay());
}

window.__erpNfseHideFiscalProgress = hideNfseFiscalTransmitProgress;
window.__erpNfseStartFiscalProgress = startNfseFiscalTransmitProgress;
