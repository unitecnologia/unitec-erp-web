/**
 * Indicador de comunicação com a SEFAZ (mesmo overlay do PDV — pdvui::fiscal-progress) para
 * comandos de telas do ERP. Cada overlay tem data-erp-sefaz-progress="<métodoLivewire>".
 *
 * - abre quando o método é chamado; as etapas avançam em sequência até "aguardando a SEFAZ";
 * - resposta com o evento erp-sefaz-resultado (ok) = conclui as etapas restantes e fica verde;
 *   qualquer outra resposta (aviso, rejeição, erro de rede) = marca a etapa atual como falha;
 * - fica aberto no mínimo MIN_VISIVEL_MS e bloqueia cliques/teclas até fechar (sem duplo disparo).
 * Só a animação espera: a requisição ao servidor sai e volta no tempo real.
 */
(() => {
    if (window.__erpSefazProgressBound) {
        return;
    }

    window.__erpSefazProgressBound = true;

    const PASSO_MS = 480;
    const PASSO_FINAL_MS = 220;
    const MIN_VISIVEL_MS = 1500;
    const SUCESSO_VISIVEL_MS = 650;
    const FALHA_VISIVEL_MS = 1400;
    const FADE_MS = 200;
    const SEGURANCA_MS = 10 * 60 * 1000;

    let ativo = null;
    let inicio = 0;
    let passoTimer = null;
    let segurancaTimer = null;
    let pendentes = 0;
    let resultadoOk = false;
    let resultado = null;
    let falhaRede = false;
    let encerrando = false;

    const overlayFor = (method) => (method
        ? document.querySelector(`[data-erp-sefaz-progress="${CSS.escape(method)}"]`)
        : null);

    const esperar = (ms) => new Promise((resolve) => window.setTimeout(resolve, Math.max(0, ms)));

    function partes(overlay) {
        const panel = overlay.querySelector('[data-erp-pdv-fiscal-progress-panel]') ?? overlay;

        return {
            panel,
            steps: Array.from(panel.querySelectorAll('[data-erp-pdv-fiscal-step]')),
            statusEl: panel.querySelector('[data-erp-pdv-fiscal-step-status]'),
            barEl: panel.querySelector('[data-erp-pdv-fiscal-step-bar]'),
            titleEl: panel.querySelector('.erp-pdv-fiscal-progress__title'),
        };
    }

    function etapaAtual(overlay) {
        return Number(overlay.dataset.erpSefazStep || 0);
    }

    function setStatus(statusEl, text) {
        if (! statusEl || ! text) {
            return;
        }

        statusEl.textContent = text.endsWith('…') || text.endsWith('.') ? text : `${text}…`;
    }

    function setStep(overlay, index) {
        const { steps, statusEl, barEl } = partes(overlay);
        const target = Math.max(0, Math.min(steps.length - 1, index));

        steps.forEach((step, i) => {
            step.classList.toggle('is-done', i < target);
            step.classList.toggle('is-active', i === target);
            step.classList.remove('is-error');
        });

        setStatus(statusEl, steps[target] ? steps[target].textContent.trim() : '');

        if (barEl && steps.length > 0) {
            barEl.style.width = `${Math.round(((target + 0.5) / steps.length) * 100)}%`;
        }

        overlay.dataset.erpSefazStep = String(target);
    }

    function marcarSucesso(overlay) {
        const { steps, statusEl, barEl } = partes(overlay);

        steps.forEach((step) => {
            step.classList.remove('is-active', 'is-error');
            step.classList.add('is-done');
        });

        if (barEl) {
            barEl.style.width = '100%';
        }

        overlay.classList.add('is-sucesso');
        overlay.setAttribute('aria-busy', 'false');

        if (statusEl) {
            statusEl.textContent = 'Concluído com sucesso.';
        }
    }

    /**
     * Etapa que falhou: a informada pelo servidor (data-etapa); sem ela, a validação (nada foi
     * enviado) ou, em queda de conexão, a etapa em que a animação estava.
     */
    function indiceDaFalha(overlay) {
        const { steps } = partes(overlay);
        const etapa = resultado?.etapa;

        if (! falhaRede && etapa) {
            const exato = steps.findIndex((li) => li.dataset.etapa === etapa);

            if (exato >= 0) {
                return exato;
            }

            // Ex.: certificado na consulta (sem etapa de assinatura) = antes do envio à SEFAZ.
            if (etapa === 'assinatura') {
                const sefaz = steps.findIndex((li) => li.dataset.etapa === 'sefaz');

                return Math.max(0, sefaz - 1);
            }
        }

        if (falhaRede) {
            return etapaAtual(overlay);
        }

        return 0;
    }

    function marcarFalha(overlay) {
        const { steps, statusEl, barEl } = partes(overlay);
        const falhou = indiceDaFalha(overlay);

        steps.forEach((step, i) => {
            step.classList.remove('is-active');
            step.classList.toggle('is-done', i < falhou);
            step.classList.toggle('is-error', i === falhou);
        });

        if (barEl && steps.length > 0) {
            barEl.style.width = `${Math.round(((falhou + 0.5) / steps.length) * 100)}%`;
        }

        overlay.dataset.erpSefazStep = String(falhou);
        overlay.classList.add('is-falha');
        overlay.setAttribute('aria-busy', 'false');

        if (statusEl) {
            const mensagem = String(resultado?.mensagem || '').trim();

            statusEl.textContent = falhaRede
                ? 'Falha de comunicação com o servidor.'
                : (mensagem || 'Operação não concluída — confira a mensagem.');
        }
    }

    function pararPassos() {
        if (passoTimer) {
            window.clearInterval(passoTimer);
            passoTimer = null;
        }
    }

    function limparTimers() {
        pararPassos();

        if (segurancaTimer) {
            window.clearTimeout(segurancaTimer);
            segurancaTimer = null;
        }
    }

    function resetVisual(overlay) {
        overlay.classList.remove('is-visible', 'is-saindo', 'is-sucesso', 'is-falha');
        overlay.setAttribute('aria-busy', 'false');
        setStep(overlay, 0);
    }

    function show(overlay) {
        limparTimers();
        document.querySelectorAll('[data-erp-sefaz-progress].is-visible').forEach(resetVisual);

        ativo = overlay;
        inicio = performance.now();
        resultadoOk = false;
        resultado = null;
        falhaRede = false;
        encerrando = false;

        resetVisual(overlay);
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');

        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        const segurar = Math.max(0, partes(overlay).steps.length - 2);

        passoTimer = window.setInterval(() => {
            const atual = etapaAtual(overlay);

            if (atual >= segurar) {
                pararPassos();

                return;
            }

            setStep(overlay, atual + 1);
        }, PASSO_MS);

        segurancaTimer = window.setTimeout(() => fechar(overlay), SEGURANCA_MS);
    }

    async function concluir(overlay) {
        if (encerrando || ativo !== overlay) {
            return;
        }

        encerrando = true;
        pararPassos();

        if (resultadoOk) {
            const total = partes(overlay).steps.length;

            for (let i = etapaAtual(overlay) + 1; i < total; i++) {
                await esperar(PASSO_FINAL_MS);
                setStep(overlay, i);
            }

            await esperar(PASSO_FINAL_MS);
            await esperar(MIN_VISIVEL_MS - SUCESSO_VISIVEL_MS - (performance.now() - inicio));
            marcarSucesso(overlay);
            await esperar(SUCESSO_VISIVEL_MS);
        } else {
            await esperar(PASSO_FINAL_MS);
            marcarFalha(overlay);
            await esperar(Math.max(FALHA_VISIVEL_MS, MIN_VISIVEL_MS - (performance.now() - inicio)));
        }

        fechar(overlay);
    }

    function fechar(overlay) {
        limparTimers();
        overlay.classList.add('is-saindo');

        window.setTimeout(() => {
            resetVisual(overlay);

            if (ativo === overlay) {
                ativo = null;
                pendentes = 0;
                encerrando = false;
            }
        }, FADE_MS);
    }

    function bloquearDuranteOperacao(event) {
        if (! ativo) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    }

    ['click', 'dblclick', 'mousedown', 'keydown', 'submit'].forEach((type) => {
        window.addEventListener(type, bloquearDuranteOperacao, true);
    });

    /** Último erp-sefaz-resultado da resposta: { ok, etapa?, mensagem? } ou null. */
    const lerResultado = (effects) => {
        const eventos = (effects?.dispatches || []).filter((d) => d?.name === 'erp-sefaz-resultado');
        const ultimo = eventos[eventos.length - 1];

        if (! ultimo) {
            return null;
        }

        const params = Array.isArray(ultimo.params) ? (ultimo.params[0] || {}) : (ultimo.params || {});

        return {
            ok: params.ok === true,
            etapa: typeof params.etapa === 'string' ? params.etapa : null,
            mensagem: typeof params.mensagem === 'string' ? params.mensagem : null,
        };
    };

    function register() {
        window.Livewire.hook('commit', ({ commit, succeed, fail }) => {
            const method = (commit?.calls || [])
                .map((call) => call?.method)
                .find((name) => overlayFor(name) !== null);

            if (! method) {
                return;
            }

            const overlay = overlayFor(method);

            if (! ativo) {
                show(overlay);
            }

            const alvo = ativo;
            pendentes++;

            const finalizar = () => {
                pendentes = Math.max(0, pendentes - 1);

                if (pendentes === 0) {
                    void concluir(alvo);
                }
            };

            succeed(({ effects } = {}) => {
                const lido = lerResultado(effects);

                if (lido) {
                    resultado = lido;
                    resultadoOk = lido.ok;
                }

                finalizar();
            });

            fail(() => {
                falhaRede = true;
                resultadoOk = false;
                finalizar();
            });
        });
    }

    if (window.Livewire?.hook) {
        register();
    } else {
        document.addEventListener('livewire:init', register, { once: true });
    }

    document.addEventListener('livewire:navigating', () => {
        if (ativo) {
            limparTimers();
            resetVisual(ativo);
            ativo = null;
            pendentes = 0;
            encerrando = false;
        }
    });
})();
