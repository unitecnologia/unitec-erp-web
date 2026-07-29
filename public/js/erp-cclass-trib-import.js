/**
 * Progresso de importação CClassTrib — mesmo padrão da NF-e (transmitir).
 */
let cclassTribImportProgressActive = false;
let cclassTribImportProgressTimer = null;
let cclassTribImportProgressStepIndex = 0;

document.addEventListener('DOMContentLoaded', initCclassTribImportProgress);
document.addEventListener('livewire:navigated', initCclassTribImportProgress);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            hideCclassTribImportProgress();
        });
    });
});

function initCclassTribImportProgress() {
    bindCclassTribImportFileTrigger();
}

function getCclassTribImportProgressOverlay() {
    return document.querySelector('[data-erp-cclass-trib-import-progress]');
}

function resetCclassTribImportProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    cclassTribImportProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-cclass-trib-import-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-cclass-trib-import-step]'));
    const statusEl = panel.querySelector('[data-erp-cclass-trib-import-step-status]');
    const barEl = panel.querySelector('[data-erp-cclass-trib-import-step-bar]');

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

function advanceCclassTribImportProgress(overlay) {
    const panel = overlay.querySelector('[data-erp-cclass-trib-import-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-cclass-trib-import-step]'));
    const statusEl = panel.querySelector('[data-erp-cclass-trib-import-step-status]');
    const barEl = panel.querySelector('[data-erp-cclass-trib-import-step-bar]');

    if (steps.length === 0) {
        return;
    }

    if (cclassTribImportProgressStepIndex < steps.length - 1) {
        steps[cclassTribImportProgressStepIndex].classList.remove('is-active');
        steps[cclassTribImportProgressStepIndex].classList.add('is-done');
        cclassTribImportProgressStepIndex += 1;
        steps[cclassTribImportProgressStepIndex].classList.add('is-active');
    }

    if (statusEl && steps[cclassTribImportProgressStepIndex]) {
        statusEl.textContent = `${steps[cclassTribImportProgressStepIndex].textContent.trim()}…`;
    }

    if (barEl) {
        const percent = Math.min(100, Math.round(((cclassTribImportProgressStepIndex + 1) / steps.length) * 100));
        barEl.style.width = `${percent}%`;
    }
}

function stopCclassTribImportProgress() {
    cclassTribImportProgressActive = false;

    if (cclassTribImportProgressTimer) {
        window.clearInterval(cclassTribImportProgressTimer);
        cclassTribImportProgressTimer = null;
    }
}

function startCclassTribImportProgress() {
    const overlay = getCclassTribImportProgressOverlay();

    if (! overlay) {
        return;
    }

    stopCclassTribImportProgress();
    cclassTribImportProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetCclassTribImportProgressUi(overlay);

    window.setTimeout(() => {
        if (! cclassTribImportProgressActive) {
            return;
        }

        advanceCclassTribImportProgress(overlay);
    }, 650);

    cclassTribImportProgressTimer = window.setInterval(() => {
        const currentOverlay = getCclassTribImportProgressOverlay();

        if (! cclassTribImportProgressActive || ! currentOverlay) {
            stopCclassTribImportProgress();

            return;
        }

        advanceCclassTribImportProgress(currentOverlay);
    }, 800);
}

function hideCclassTribImportProgress() {
    stopCclassTribImportProgress();

    document.querySelectorAll('[data-erp-cclass-trib-import-progress]').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetCclassTribImportProgressUi(getCclassTribImportProgressOverlay());
}

function bindCclassTribImportFileTrigger() {
    if (window.__erpCclassTribImportBound) {
        return;
    }

    window.__erpCclassTribImportBound = true;

    document.addEventListener('change', (event) => {
        const input = event.target.closest('.erp-cclass-trib-modal input[type="file"][wire\\:model="cclassTribUpload"]');

        if (! input || ! input.files || input.files.length === 0) {
            return;
        }

        window.setTimeout(startCclassTribImportProgress, 40);
    }, true);
}
