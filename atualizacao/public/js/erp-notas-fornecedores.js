document.addEventListener('DOMContentLoaded', initErpNotasFornecedores);
document.addEventListener('livewire:navigated', initErpNotasFornecedores);

let nfFornProgressTimer = null;
let nfFornProgressStepIndex = 0;
let nfFornProgressActive = false;

window.ErpNotasFornecedores = {
    startConsultaProgress: startNfFornConsultaLoteProgress,
    hideConsultaProgress: hideNfFornConsultaLoteProgress,
};

function initErpNotasFornecedores() {
    bindNotasFornecedoresConsultaTriggers();

    if (window.Livewire) {
        bindNotasFornecedoresLivewireEvents();
    } else {
        document.addEventListener('livewire:init', bindNotasFornecedoresLivewireEvents, { once: true });
    }
}

function bindNotasFornecedoresConsultaTriggers() {
    if (window.__erpNfFornConsultaTriggersBound) {
        return;
    }

    window.__erpNfFornConsultaTriggersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[wire\\:click="consultarLote"], [wire\\:click\\.prevent="consultarLote"]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfFornConsultaLoteProgress, 30);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F3' || event.defaultPrevented) {
            return;
        }

        if (! document.querySelector('.erp-notas-fornecedores-page')) {
            return;
        }

        if (event.target.matches('input, textarea, select, [contenteditable="true"]')) {
            return;
        }

        window.setTimeout(startNfFornConsultaLoteProgress, 30);
    }, true);
}

function bindNotasFornecedoresLivewireEvents() {
    if (window.__erpNfFornLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpNfFornLivewireBound = true;

    window.Livewire.on('erp-nf-forn-focus-fiscal-overlay', () => {
        hideNfFornConsultaLoteProgress();

        window.setTimeout(() => {
            document.getElementById('erp-nf-forn-fiscal-overlay-entendido')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-nf-forn-focus-consulta-chave', () => {
        window.setTimeout(() => {
            const input = document.querySelector('[data-erp-nf-forn-consulta-chave-input]');

            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }
        }, 50);
    });

    window.Livewire.on('erp-nf-forn-hide-consulta-progress', () => {
        hideNfFornConsultaLoteProgress();
    });

    window.Livewire.hook('message.processed', () => {
        if (nfFornProgressActive) {
            window.setTimeout(hideNfFornConsultaLoteProgress, 150);
        }
    });
}

function getNfFornConsultaLoteProgressOverlay() {
    return document.querySelector('[data-erp-nf-forn-fiscal-progress]');
}

function resetNfFornConsultaLoteProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    nfFornProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-nf-forn-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nf-forn-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nf-forn-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nf-forn-fiscal-step-bar]');

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

function advanceNfFornConsultaLoteProgress(overlay) {
    const panel = overlay.querySelector('[data-erp-nf-forn-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nf-forn-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nf-forn-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nf-forn-fiscal-step-bar]');

    if (steps.length === 0) {
        return;
    }

    if (nfFornProgressStepIndex < steps.length - 1) {
        steps[nfFornProgressStepIndex].classList.remove('is-active');
        steps[nfFornProgressStepIndex].classList.add('is-done');
        nfFornProgressStepIndex += 1;
        steps[nfFornProgressStepIndex].classList.add('is-active');
    }

    if (statusEl && steps[nfFornProgressStepIndex]) {
        statusEl.textContent = `${steps[nfFornProgressStepIndex].textContent.trim()}…`;
    }

    if (barEl) {
        const percent = Math.min(100, Math.round(((nfFornProgressStepIndex + 1) / steps.length) * 100));
        barEl.style.width = `${percent}%`;
    }
}

function stopNfFornConsultaLoteProgress() {
    nfFornProgressActive = false;

    if (nfFornProgressTimer) {
        window.clearInterval(nfFornProgressTimer);
        nfFornProgressTimer = null;
    }
}

function startNfFornConsultaLoteProgress() {
    const overlay = getNfFornConsultaLoteProgressOverlay();

    if (! overlay) {
        return;
    }

    stopNfFornConsultaLoteProgress();
    nfFornProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetNfFornConsultaLoteProgressUi(overlay);

    window.setTimeout(() => {
        if (! nfFornProgressActive) {
            return;
        }

        advanceNfFornConsultaLoteProgress(overlay);
    }, 700);

    nfFornProgressTimer = window.setInterval(() => {
        const currentOverlay = getNfFornConsultaLoteProgressOverlay();

        if (! nfFornProgressActive || ! currentOverlay) {
            stopNfFornConsultaLoteProgress();

            return;
        }

        advanceNfFornConsultaLoteProgress(currentOverlay);
    }, 850);
}

function hideNfFornConsultaLoteProgress() {
    stopNfFornConsultaLoteProgress();

    document.querySelectorAll('[data-erp-nf-forn-fiscal-progress]').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetNfFornConsultaLoteProgressUi(getNfFornConsultaLoteProgressOverlay());
}
