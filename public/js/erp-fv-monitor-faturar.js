/**
 * Progresso do faturamento no Monitor. O avanço principal segue cada pedido
 * processado; entre as etapas a barra sobe de 1% em 1%.
 */
(function () {
    'use strict';

    const LABELS = [
        'Validando pedido',
        'Gerando venda',
        'Movimentando estoque',
        'Gerando financeiro',
        'Finalizando pedido',
    ];

    let running = false;
    let tick = null;
    let stepTimer = null;
    let shown = 0;
    let target = 0;

    function root() {
        return document.querySelector('[data-erp-fv-mon-faturar-progress]');
    }

    function setBar(pct) {
        const bar = root()?.querySelector('[data-erp-fv-mon-faturar-bar]');

        if (bar) {
            bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }
    }

    function setStatus(text, isError) {
        const el = root()?.querySelector('[data-erp-fv-mon-faturar-status]');

        if (! el) {
            return;
        }

        el.textContent = text;
        el.classList.toggle('is-error', !! isError);
    }

    function setSteps(active, errorIndex) {
        root()?.querySelectorAll('[data-erp-fv-mon-faturar-step]').forEach((li) => {
            const i = Number(li.getAttribute('data-step') || 0);
            li.classList.toggle('is-error', errorIndex === i);
            li.classList.toggle('is-done', errorIndex < 0 ? i < active : i < errorIndex);
            li.classList.toggle('is-active', errorIndex < 0 && i === active);
        });
    }

    function addLog(text, ok) {
        const log = root()?.querySelector('[data-erp-fv-mon-faturar-log]');

        if (! log) {
            return;
        }

        const li = document.createElement('li');
        li.className = ok ? 'is-ok' : 'is-erro';
        li.textContent = text;
        log.appendChild(li);
    }

    function clearLog() {
        const log = root()?.querySelector('[data-erp-fv-mon-faturar-log]');

        if (log) {
            log.replaceChildren();
        }
    }

    function ensureTick() {
        if (tick) {
            return;
        }

        tick = window.setInterval(() => {
            if (shown < target) {
                shown += 1;
                setBar(shown);
            }
        }, 28);
    }

    function stopTick() {
        if (tick) {
            window.clearInterval(tick);
            tick = null;
        }
    }

    function stopSteps() {
        if (stepTimer) {
            window.clearInterval(stepTimer);
            stepTimer = null;
        }
    }

    function capFor(atual, total, step) {
        if (total <= 0) {
            return 1;
        }

        const slice = 100 / total;
        const within = (step + 1) / LABELS.length;

        return Math.max(1, Math.min(99, Math.round(((atual - 1) + (within * 0.9)) * slice)));
    }

    function startCosmetic(atual, total, numero) {
        stopSteps();
        let step = 0;
        setSteps(0, -1);
        setStatus(
            'Faturando pedido ' + numero + ' (' + atual + ' de ' + total + ') — ' + LABELS[0] + '...',
            false,
        );
        target = Math.max(target, capFor(atual, total, 0));
        ensureTick();

        stepTimer = window.setInterval(() => {
            if (step >= LABELS.length - 2) {
                stopSteps();

                return;
            }

            step += 1;
            setSteps(step, -1);
            setStatus(
                'Faturando pedido ' + numero + ' (' + atual + ' de ' + total + ') — ' + LABELS[step] + '...',
                false,
            );
            target = Math.max(target, capFor(atual, total, step));
        }, 420);
    }

    function etapaIndex(etapa) {
        return LABELS.findIndex((label) => String(etapa || '').indexOf(label) === 0);
    }

    function waitBar() {
        const start = Date.now();

        return new Promise((resolve) => {
            const timer = window.setInterval(() => {
                if (shown >= target || Date.now() - start > 4000) {
                    window.clearInterval(timer);
                    shown = target;
                    setBar(shown);
                    resolve();
                }
            }, 40);
        });
    }

    window.__erpFvMonFaturarRun = async function (wire) {
        if (running || ! wire) {
            return;
        }

        running = true;
        shown = 0;
        target = 1;
        setBar(0);
        clearLog();
        setSteps(0, -1);
        setStatus('Preparando faturamento...', false);
        ensureTick();

        let teveErro = false;

        try {
            while (true) {
                const prep = await wire.prepararProximoFaturamento();

                if (! prep || prep.done) {
                    break;
                }

                const atual = Number(prep.atual || 1);
                const total = Number(prep.total || 1);
                const numero = String(prep.numero || '');

                startCosmetic(atual, total, numero);

                const result = await wire.processarProximoFaturamento();
                stopSteps();

                if (! result) {
                    break;
                }

                if (result.ok) {
                    setSteps(LABELS.length, -1);
                    setStatus('Pedido ' + result.numero + ' faturado.', false);
                    addLog('Pedido ' + result.numero + ' faturado.', true);
                    target = Math.max(
                        target,
                        Math.round((Number(result.atual) / Math.max(1, Number(result.total))) * 100),
                    );
                } else {
                    teveErro = true;
                    const idx = etapaIndex(result.etapa);
                    const stepIdx = idx < 0 ? 0 : idx;
                    setSteps(stepIdx, stepIdx);
                    const msg = 'Pedido ' + result.numero
                        + ' — falha em ' + (result.etapa || 'faturamento')
                        + (result.erro ? ': ' + result.erro : '');
                    setStatus(msg, true);
                    addLog(msg, false);
                    await new Promise((resolve) => setTimeout(resolve, 1100));
                }

                if (result.done) {
                    break;
                }
            }

            if (! teveErro) {
                target = 100;
                ensureTick();
                setStatus('Faturamento concluído.', false);
                await waitBar();
                await new Promise((resolve) => setTimeout(resolve, 500));
            }

            await wire.concluirFaturamentoMonitor();
        } catch (e) {
            console.error(e);
            stopSteps();
            setStatus('Falha ao acompanhar o faturamento.', true);

            try {
                await wire.concluirFaturamentoMonitor();
            } catch (closeError) {
                console.error(closeError);
            }
        } finally {
            running = false;
            stopSteps();
            stopTick();
        }
    };
})();
