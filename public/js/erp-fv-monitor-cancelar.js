/**
 * Progresso do cancelamento no Monitor. O avanço principal segue cada pedido;
 * entre as etapas a barra sobe de 1% em 1%.
 */
(function () {
    'use strict';

    const LABELS_FATURADO = [
        'Validando pedido',
        'Verificando financeiro',
        'Verificando boleto bancário',
        'Solicitando baixa do boleto',
        'Devolvendo estoque',
        'Estornando financeiro',
        'Cancelando venda',
        'Finalizando pedido',
    ];

    const LABELS_PENDENTE = [
        'Validando pedido',
        'Finalizando pedido',
    ];

    let running = false;
    let tick = null;
    let stepTimer = null;
    let shown = 0;
    let target = 0;
    let labels = LABELS_FATURADO;

    function root() {
        return document.querySelector('[data-erp-fv-mon-cancelar-progress]');
    }

    function setBar(pct) {
        const bar = root()?.querySelector('[data-erp-fv-mon-cancelar-bar]');

        if (bar) {
            bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }
    }

    function setStatus(text, isError) {
        const el = root()?.querySelector('[data-erp-fv-mon-cancelar-status]');

        if (! el) {
            return;
        }

        el.textContent = text;
        el.classList.toggle('is-error', !! isError);
    }

    function applyLabels(next) {
        labels = next;
        root()?.querySelectorAll('[data-erp-fv-mon-cancelar-step]').forEach((li) => {
            const i = Number(li.getAttribute('data-step') || 0);
            const visivel = i < labels.length;
            li.hidden = ! visivel;

            if (visivel) {
                li.textContent = labels[i];
            }

            li.classList.remove('is-error', 'is-done', 'is-active');
        });
    }

    function setSteps(active, errorIndex) {
        root()?.querySelectorAll('[data-erp-fv-mon-cancelar-step]').forEach((li) => {
            const i = Number(li.getAttribute('data-step') || 0);

            if (i >= labels.length) {
                return;
            }

            li.classList.toggle('is-error', errorIndex === i);
            li.classList.toggle('is-done', errorIndex < 0 ? i < active : i < errorIndex);
            li.classList.toggle('is-active', errorIndex < 0 && i === active);
        });
    }

    function addLog(text, ok) {
        const log = root()?.querySelector('[data-erp-fv-mon-cancelar-log]');

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
        const log = root()?.querySelector('[data-erp-fv-mon-cancelar-log]');

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

    function capFor(atual, total, step, fecharPedido) {
        if (total <= 0) {
            return 1;
        }

        if (fecharPedido) {
            return Math.max(1, Math.min(100, Math.round((atual / total) * 100)));
        }

        const within = (step + 1) / labels.length;

        return Math.max(1, Math.min(99, Math.round(((atual - 1) + within) * (100 / total))));
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

    /**
     * Mostra uma etapa por vez e só passa para a próxima quando a barra
     * chega na porcentagem daquela etapa.
     */
    async function percorrerEtapas(atual, total, numero, ultimo, comErro, desde) {
        stopSteps();
        const fim = Math.max(0, Math.min(labels.length - 1, ultimo));
        const ini = Math.max(0, Math.min(fim, desde || 0));

        for (let i = ini; i <= fim; i++) {
            const fecha = ! comErro && i === labels.length - 1;
            setSteps(i, -1);
            setStatus(
                'Cancelando pedido ' + numero + ' (' + atual + ' de ' + total + ') — ' + labels[i] + '...',
                false,
            );
            target = Math.max(target, capFor(atual, total, i, fecha));
            ensureTick();
            await esperarBarra();
        }

        if (comErro) {
            setSteps(fim, fim);

            return;
        }

        setSteps(labels.length, -1);
    }

    function etapaIndex(etapa) {
        return labels.findIndex((label) => String(etapa || '').indexOf(label) === 0);
    }

    function esconderBoleto() {
        root()?.querySelector('[data-erp-fv-mon-cancelar-boleto]')?.classList.remove('is-visible');
    }

    function perguntarBoleto(mensagem) {
        return new Promise((resolve) => {
            const box = root()?.querySelector('[data-erp-fv-mon-cancelar-boleto]');
            const msg = root()?.querySelector('[data-erp-fv-mon-cancelar-boleto-msg]');
            const sim = root()?.querySelector('[data-erp-fv-mon-cancelar-boleto-sim]');
            const nao = root()?.querySelector('[data-erp-fv-mon-cancelar-boleto-nao]');

            if (! box || ! sim || ! nao) {
                resolve(false);

                return;
            }

            if (msg) {
                msg.textContent = mensagem;
            }

            box.classList.add('is-visible');

            const fim = (valor) => {
                box.classList.remove('is-visible');
                sim.removeEventListener('click', onSim);
                nao.removeEventListener('click', onNao);
                resolve(valor);
            };
            const onSim = () => fim(true);
            const onNao = () => fim(false);
            sim.addEventListener('click', onSim);
            nao.addEventListener('click', onNao);
        });
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

    window.__erpFvMonCancelarRun = async function (wire) {
        if (running || ! wire) {
            return;
        }

        running = true;
        shown = 0;
        target = 1;
        applyLabels(LABELS_FATURADO);
        setBar(0);
        clearLog();
        esconderBoleto();
        setSteps(0, -1);
        setStatus('Preparando cancelamento...', false);
        ensureTick();

        let teveErro = false;

        try {
            while (true) {
                const prep = await wire.prepararProximoCancelamento();

                if (! prep || prep.done) {
                    break;
                }

                const atual = Number(prep.atual || 1);
                const total = Number(prep.total || 1);
                const numero = String(prep.numero || '');
                applyLabels(prep.pendente ? LABELS_PENDENTE : LABELS_FATURADO);
                setSteps(0, -1);
                setStatus(
                    'Cancelando pedido ' + numero + ' (' + atual + ' de ' + total + ') — ' + labels[0] + '...',
                    false,
                );
                target = Math.max(target, capFor(atual, total, 0, false));
                ensureTick();

                let result = await wire.processarProximoCancelamento();
                let desde = 0;

                if (result && result.aguardandoBoleto) {
                    const idx = Math.max(0, etapaIndex('Verificando boleto bancário'));
                    await percorrerEtapas(atual, total, numero, idx, false, 0);
                    desde = idx + 1;
                    setStatus(result.mensagem || '', false);
                    const sim = await perguntarBoleto(result.mensagem || '');

                    if (! sim) {
                        result = await wire.recusarBaixaBoletoCancelamento();
                    } else {
                        await wire.autorizarBaixaBoletoCancelamento();
                        result = await wire.processarProximoCancelamento();
                    }
                }

                if (! result) {
                    break;
                }

                if (result.ok) {
                    await percorrerEtapas(atual, total, numero, labels.length - 1, false, desde);
                    setStatus('Pedido ' + result.numero + ' cancelado.', false);
                    addLog('Pedido ' + result.numero + ' cancelado.', true);
                } else if (! result.aguardandoBoleto) {
                    teveErro = true;
                    const idx = etapaIndex(result.etapa);
                    const stepIdx = idx < 0 ? 0 : idx;
                    await percorrerEtapas(atual, total, numero, stepIdx, true, desde);
                    const msg = 'Pedido ' + result.numero
                        + ' — falha em ' + (result.etapa || 'cancelamento')
                        + (result.erro ? ': ' + result.erro : '');
                    setStatus(msg, true);
                    addLog(msg, false);
                    await new Promise((resolve) => setTimeout(resolve, 700));
                }

                if (result.done) {
                    break;
                }
            }

            if (! teveErro) {
                target = 100;
                ensureTick();
                setStatus('Cancelamento concluído.', false);
                await waitBar();
                await new Promise((resolve) => setTimeout(resolve, 500));
            }

            esconderBoleto();
            await wire.concluirCancelamentoMonitor();
        } catch (e) {
            console.error(e);
            stopSteps();
            esconderBoleto();
            setStatus('Falha ao acompanhar o cancelamento.', true);

            try {
                await wire.concluirCancelamentoMonitor();
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
