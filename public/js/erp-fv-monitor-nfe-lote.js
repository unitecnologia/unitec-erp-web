/**
 * Runner do lote NF-e no Monitor: permanece na tela, anima etapas e chama o Livewire.
 */
(function () {
    'use strict';

    const STEP_MS = 800;
    const HOLD_STEP = 3;
    const LABELS = [
        'Validando dados da NF-e',
        'Montando XML do documento',
        'Assinando digitalmente',
        'Enviando à SEFAZ (aguardando resposta)',
        'Processando autorização',
    ];

    let stepTimer = null;
    let stepIndex = 0;
    let running = false;

    function overlay() {
        return document.querySelector('[data-erp-fv-mon-nfe-lote-progress]');
    }

    function setStepUi(atual, total, step, label) {
        const root = overlay();
        if (! root) {
            return;
        }

        const status = root.querySelector('[data-erp-fv-mon-nfe-lote-status]');
        const bar = root.querySelector('[data-erp-fv-mon-nfe-lote-bar]');
        const hint = root.querySelector('.erp-nfe-fiscal-progress__hint');
        const steps = root.querySelectorAll('[data-erp-fv-mon-nfe-lote-step]');

        if (status) {
            status.textContent = 'Nota ' + atual + ' de ' + total + ' — ' + (label || LABELS[step] || 'Aguarde') + '…';
        }

        if (hint) {
            hint.textContent = 'Nota ' + atual + ' de ' + total + ' — Aguarde, não feche esta tela.';
        }

        steps.forEach((li) => {
            const i = Number(li.getAttribute('data-step') || 0);
            li.classList.toggle('is-done', i < step);
            li.classList.toggle('is-active', i === step);
        });

        if (bar && total > 0) {
            const pct = Math.max(8, Math.min(100, Math.round((((atual - 1) + ((step + 1) / 5)) / total) * 100)));
            bar.style.width = pct + '%';
        }
    }

    function clearStepTimer() {
        if (stepTimer) {
            window.clearInterval(stepTimer);
            stepTimer = null;
        }
    }

    function startCosmetic(atual, total) {
        clearStepTimer();
        stepIndex = 0;
        setStepUi(atual, total, 0, LABELS[0]);

        stepTimer = window.setInterval(() => {
            if (stepIndex >= HOLD_STEP) {
                clearStepTimer();

                return;
            }

            stepIndex += 1;
            setStepUi(atual, total, stepIndex, LABELS[stepIndex]);

            if (stepIndex >= HOLD_STEP) {
                clearStepTimer();
            }
        }, STEP_MS);
    }

    function stopCosmetic() {
        clearStepTimer();
    }

    /**
     * @param {object} wire Livewire $wire do Monitor
     */
    window.__erpFvMonNfeLoteRun = async function (wire) {
        if (running || ! wire) {
            return;
        }

        running = true;

        try {
            while (true) {
                const prep = await wire.prepararProximoItemNfeLote();

                if (! prep || prep.done) {
                    break;
                }

                const atual = Number(prep.atual || 1);
                const total = Number(prep.total || 1);

                startCosmetic(atual, total);
                await new Promise((resolve) => setTimeout(resolve, 40));

                const result = await wire.processarProximoItemNfeLote();
                stopCosmetic();

                if (! result || result.done) {
                    break;
                }

                await new Promise((resolve) => setTimeout(resolve, 60));
            }
        } catch (e) {
            console.error(e);
            stopCosmetic();
        } finally {
            running = false;
            stopCosmetic();
        }
    };
})();
