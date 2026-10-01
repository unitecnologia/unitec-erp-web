/**
 * Progresso da clonagem no Monitor. Mesmo ritmo do cancelamento:
 * a barra sobe de 1% em 1% e a etapa só avança quando a barra chega nela.
 */
(function () {
    'use strict';

    const LABELS = [
        'Validando pedido cancelado',
        'Preparando clonagem',
        'Copiando cliente e vendedor',
        'Copiando itens',
        'Copiando condições comerciais',
        'Gerando novo DAV/pedido',
        'Criando reserva de estoque',
        'Abrindo Tela de Venda',
    ];

    let running = false;
    let tick = null;
    let shown = 0;
    let target = 0;

    function root() {
        return document.querySelector('[data-erp-fv-mon-clonar-progress]');
    }

    function setBar(pct) {
        const bar = root()?.querySelector('[data-erp-fv-mon-clonar-bar]');

        if (bar) {
            bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }
    }

    function setStatus(text, isError) {
        const el = root()?.querySelector('[data-erp-fv-mon-clonar-status]');

        if (! el) {
            return;
        }

        el.textContent = text;
        el.classList.toggle('is-error', !! isError);
    }

    function setSteps(active, errorIndex) {
        root()?.querySelectorAll('[data-erp-fv-mon-clonar-step]').forEach((li) => {
            const i = Number(li.getAttribute('data-step') || 0);
            li.classList.toggle('is-error', errorIndex === i);
            li.classList.toggle('is-done', errorIndex < 0 ? i < active : i < errorIndex);
            li.classList.toggle('is-active', errorIndex < 0 && i === active);
        });
    }

    function addLog(text, ok) {
        const log = root()?.querySelector('[data-erp-fv-mon-clonar-log]');

        if (! log) {
            return;
        }

        const li = document.createElement('li');
        li.className = ok ? 'is-ok' : 'is-erro';
        li.textContent = text;
        log.appendChild(li);
        log.scrollTop = log.scrollHeight;
    }

    function clearLog() {
        const log = root()?.querySelector('[data-erp-fv-mon-clonar-log]');

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

    function capFor(step, fechar) {
        if (fechar) {
            return 100;
        }

        const within = (step + 1) / LABELS.length;

        return Math.max(1, Math.min(99, Math.round(within * 100)));
    }

    function esperarBarra() {
        const inicio = Date.now();

        return new Promise((resolve) => {
            const timer = window.setInterval(() => {
                const tempo = Date.now() - inicio;

                if ((shown >= target && tempo >= 480) || tempo > 8000) {
                    window.clearInterval(timer);

                    if (shown < target) {
                        shown = target;
                        setBar(shown);
                    }

                    resolve();
                }
            }, 40);
        });
    }

    async function percorrerEtapas(ultimo, comErro) {
        const fim = Math.max(0, Math.min(LABELS.length - 1, ultimo));

        for (let i = 0; i <= fim; i++) {
            const fecha = ! comErro && i === LABELS.length - 1;
            setSteps(i, -1);
            setStatus(LABELS[i] + '...', false);
            target = Math.max(target, capFor(i, fecha));
            ensureTick();
            await esperarBarra();
        }

        if (comErro) {
            setSteps(fim, fim);

            return;
        }

        setSteps(LABELS.length, -1);
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

    window.__erpFvMonClonarRun = async function (wire) {
        if (running || ! wire) {
            return;
        }

        running = true;
        shown = 0;
        target = 1;
        setBar(0);
        clearLog();
        setSteps(0, -1);
        setStatus('Validando pedido cancelado...', false);
        ensureTick();

        try {
            const result = await wire.processarClonagemPedido();

            if (result && result.ok && result.pedidoId) {
                await percorrerEtapas(LABELS.length - 1, false);
                setStatus('Pedido clonado com sucesso.', false);
                addLog('Pedido clonado com sucesso.', true);
                target = 100;
                ensureTick();
                await waitBar();
                await new Promise((resolve) => setTimeout(resolve, 400));
            } else {
                const idx = etapaIndex(result && result.etapa);
                const stepIdx = idx < 0 ? 0 : idx;
                await percorrerEtapas(stepIdx, true);
                const msg = (result && result.etapa ? result.etapa : 'Clonagem')
                    + (result && result.erro ? ': ' + result.erro : '');
                setStatus(msg, true);
                addLog(msg, false);
                await new Promise((resolve) => setTimeout(resolve, 700));
            }

            await wire.concluirClonagemPedido();
        } catch (e) {
            console.error(e);
            setStatus('Falha ao acompanhar a clonagem.', true);

            try {
                await wire.concluirClonagemPedido();
            } catch (closeError) {
                console.error(closeError);
            }
        } finally {
            running = false;
            stopTick();
        }
    };
})();
