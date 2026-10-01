/**
 * Progresso da reabertura no Monitor. Mesmo ritmo do cancelamento:
 * a barra sobe de 1% em 1% e a etapa só avança quando a barra chega nela.
 */
(function () {
    'use strict';

    const LABELS_COM_BOLETO = [
        'Validando pedido',
        'Verificando documentos fiscais',
        'Verificando boleto bancário',
        'Baixando boleto no banco',
        'Estornando financeiro',
        'Estornando Livro Caixa',
        'Devolvendo estoque',
        'Recriando reserva',
        'Reabrindo pedido',
    ];

    const LABELS_SEM_BOLETO = [
        'Validando pedido',
        'Verificando documentos fiscais',
        'Verificando boleto bancário',
        'Estornando financeiro',
        'Estornando Livro Caixa',
        'Devolvendo estoque',
        'Recriando reserva',
        'Reabrindo pedido',
    ];

    const LABELS_SEM_VENDA = [
        'Validando pedido',
        'Recriando reserva',
        'Reabrindo pedido',
    ];

    let running = false;
    let tick = null;
    let shown = 0;
    let target = 0;
    let labels = LABELS_SEM_BOLETO;

    function root() {
        return document.querySelector('[data-erp-fv-mon-reabrir-progress]');
    }

    function setBar(pct) {
        const bar = root()?.querySelector('[data-erp-fv-mon-reabrir-bar]');

        if (bar) {
            bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }
    }

    function setStatus(text, isError) {
        const el = root()?.querySelector('[data-erp-fv-mon-reabrir-status]');

        if (! el) {
            return;
        }

        el.textContent = text;
        el.classList.toggle('is-error', !! isError);
    }

    function applyLabels(next) {
        labels = next;
        root()?.querySelectorAll('[data-erp-fv-mon-reabrir-step]').forEach((li) => {
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
        root()?.querySelectorAll('[data-erp-fv-mon-reabrir-step]').forEach((li) => {
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
        const log = root()?.querySelector('[data-erp-fv-mon-reabrir-log]');

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
        const log = root()?.querySelector('[data-erp-fv-mon-reabrir-log]');

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

    async function percorrerEtapas(atual, total, numero, ultimo, comErro, desde) {
        const fim = Math.max(0, Math.min(labels.length - 1, ultimo));
        const ini = Math.max(0, Math.min(fim, desde || 0));

        for (let i = ini; i <= fim; i++) {
            const fecha = ! comErro && i === labels.length - 1;
            setSteps(i, -1);
            setStatus('Reabrindo pedido ' + numero + ' — ' + labels[i] + '...', false);
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
        root()?.querySelector('[data-erp-fv-mon-reabrir-boleto]')?.classList.remove('is-visible');
    }

    function perguntarBoleto(mensagem) {
        return new Promise((resolve) => {
            const box = root()?.querySelector('[data-erp-fv-mon-reabrir-boleto]');
            const msg = root()?.querySelector('[data-erp-fv-mon-reabrir-boleto-msg]');
            const sim = root()?.querySelector('[data-erp-fv-mon-reabrir-boleto-sim]');
            const nao = root()?.querySelector('[data-erp-fv-mon-reabrir-boleto-nao]');

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

    window.__erpFvMonReabrirRun = async function (wire) {
        if (running || ! wire) {
            return;
        }

        running = true;
        shown = 0;
        target = 1;
        applyLabels(LABELS_SEM_BOLETO);
        setBar(0);
        clearLog();
        esconderBoleto();
        setSteps(0, -1);
        setStatus('Preparando reabertura...', false);
        ensureTick();

        let teveErro = false;

        try {
            const numeroPrep = '';
            setStatus('Reabrindo pedido — ' + labels[0] + '...', false);
            target = Math.max(target, capFor(1, 1, 0, false));
            ensureTick();

            let result = await wire.processarReabertura();
            let desde = 0;
            const numero = String((result && result.numero) || numeroPrep);

            if (result && result.aguardandoBoleto) {
                const idx = Math.max(0, etapaIndex('Verificando boleto bancário'));
                await percorrerEtapas(1, 1, numero, idx, false, 0);
                desde = idx + 1;
                setStatus(result.mensagem || '', false);
                const sim = await perguntarBoleto(result.mensagem || '');

                if (! sim) {
                    result = await wire.recusarBaixaBoletoReabertura();
                } else {
                    await wire.autorizarBaixaBoletoReabertura();
                    result = await wire.processarReabertura();
                }
            }

            if (result) {
                const baixaFalhou = String(result.etapa || '').indexOf('Baixando boleto') === 0;
                const continuarBoleto = !!(result.temBoleto && desde > 0 && (result.ok || baixaFalhou));

                if (result.semVenda) {
                    applyLabels(LABELS_SEM_VENDA);
                    desde = 0;
                } else if (continuarBoleto) {
                    applyLabels(LABELS_COM_BOLETO);
                    const baixa = etapaIndex('Baixando boleto no banco');

                    if (baixa >= 0) {
                        desde = baixa;
                        setSteps(desde, -1);
                    }
                }

                if (result.ok) {
                    await percorrerEtapas(1, 1, String(result.numero || numero), labels.length - 1, false, desde);
                    setStatus('Pedido reaberto com sucesso.', false);
                    addLog('Pedido ' + (result.numero || numero) + ' reaberto.', true);
                    target = 100;
                    ensureTick();
                    await waitBar();
                    await new Promise((resolve) => setTimeout(resolve, 500));
                } else if (! result.aguardandoBoleto) {
                    teveErro = true;
                    const idx = etapaIndex(result.etapa);
                    const stepIdx = idx < 0 ? 0 : idx;
                    await percorrerEtapas(1, 1, String(result.numero || numero), stepIdx, true, desde);
                    const msg = 'Pedido ' + (result.numero || numero)
                        + ' — falha em ' + (result.etapa || 'reabertura')
                        + (result.erro ? ': ' + result.erro : '');
                    setStatus(msg, true);
                    addLog(msg, false);
                    await new Promise((resolve) => setTimeout(resolve, 700));
                }
            }

            esconderBoleto();
            await wire.concluirReabertura();
        } catch (e) {
            console.error(e);
            esconderBoleto();
            setStatus('Falha ao acompanhar a reabertura.', true);

            try {
                await wire.concluirReabertura();
            } catch (closeError) {
                console.error(closeError);
            }
        } finally {
            running = false;
            stopTick();
        }
    };
})();
