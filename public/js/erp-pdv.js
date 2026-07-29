let erpPdvKeysBound = false;
let erpPdvLivewireBound = false;
let erpPdvIdleTimer = null;
let erpPdvIdleBound = false;
let erpPdvClockTimer = null;
let erpPdvStatusBarSync = null;
/** Evita atalho de pagamento (D/P/…) enquanto digita o cliente no finalizar. */
let erpPdvFinalizarClienteTyping = false;

/** @type {Record<string, [string, ...unknown[]]>} */
const ERP_PDV_FN_SHORTCUTS = {
    F1: ['openPdvModal', 'options'],
    F2: ['toggleCaixa'],
    F3: ['openVendedorModal'],
    F4: ['openBuscaAvancadaModal'],
    F5: ['openImportarModal'],
    F6: ['cancelarCupom'],
    F7: ['openFinalizarVenda'],
    F8: ['openPdvModal', 'resumo'],
    F9: ['openPdvModal', 'sangria'],
    F10: ['openPdvModal', 'suprimento'],
    F11: ['openRemoverItensModal'],
};

/** @type {Record<string, [string, ...unknown[]]>} */
const ERP_PDV_CTRL_SHORTCUTS = {
    d: ['openDescontoItemModal'],
    a: ['abrirGaveta'],
    r: ['openReceberModal'],
    l: ['openBuscaPrecoModal'],
    t: ['moduleStubTef'],
    i: ['moduleStubNfce'],
    p: ['openReimprimirModal'],
    o: ['openConsultaVendaModal'],
    s: ['moduleStubMesa', 'Imprimir Pedido'],
    n: ['moduleStubMesa', 'Abrir Mesa'],
    e: ['moduleStubMesa', 'Imprimir Item'],
    b: ['moduleStubMesa', 'Transferir Mesa'],
    m: ['moduleStubMesa', 'Atualiza Mesas'],
};

document.addEventListener('DOMContentLoaded', initErpPdv);
document.addEventListener('livewire:navigated', initErpPdv);
document.addEventListener('livewire:init', bindErpPdvLivewireEvents);
window.addEventListener('focus', () => {
    const pdv = document.querySelector('.erp-pdv');
    if (pdv?.dataset.caixaAberto === '1' && ! pdvHasBlockingUi(pdv)) {
        focusPdvSearchField();
    }
});
window.addEventListener('erp-pdv-refocus-search', () => {
    window.__erpPdvForceSearchFocusUntil = Date.now() + 4000;
    focusPdvSearchField();
});

function bindErpPdvLivewireEvents() {
    if (erpPdvLivewireBound || ! window.Livewire) {
        return;
    }

    erpPdvLivewireBound = true;

    window.Livewire.on('erp-pdv-modal-opened', (payload) => {
        window.setTimeout(() => {
            if (payload?.modal === 'sair') {
                document.getElementById('erp-pdv-sair-sim')?.focus();
            } else if (payload?.modal === 'excluir_item') {
                document.getElementById('erp-pdv-excluir-sim')?.focus();
            } else if (payload?.modal === 'remover_itens') {
                document.getElementById('erp-pdv-remover-itens-search')?.focus()
                    || document.getElementById('erp-pdv-remover-itens-sim')?.focus();
            } else if (payload?.modal === 'finalizar') {
                focusPdvFinalizarPagamento(0);
            } else if (payload?.modal === 'abrir_caixa' || payload?.modal === 'fechar_caixa') {
                focusPdvModalField();
            } else {
                focusPdvModalField();
            }
        }, 50);
    });

    window.Livewire.on('erp-pdv-caixa-opened', () => {
        focusPdvSearchField();
    });

    window.Livewire.on('erp-pdv-item-added', () => {
        window.__erpPdvForceSearchFocusUntil = Date.now() + 3000;
        focusPdvSearchField();

        window.setTimeout(() => {
            const component = findPdvLivewireComponent();

            if (component) {
                component.call('clearPdvFlashLancamento');
            }

            window.__erpPdvForceSearchFocusUntil = Date.now() + 2500;
            focusPdvSearchField();
        }, 600);
    });

    window.Livewire.on('erp-pdv-beep', () => {
        playPdvBeep();
    });

    window.Livewire.on('erp-pdv-erro-beep', () => {
        playPdvErrorBeep();
    });

    window.Livewire.on('erp-pdv-produto-confirmado', (payload) => {
        showPdvProdutoConfirmado(payload?.nome ?? '');
    });

    window.Livewire.on('erp-pdv-focus-launch', (payload) => {
        focusPdvLaunchField(payload?.field ?? 'qtd');
    });

    window.Livewire.on('erp-pdv-focus-search', () => {
        window.__erpPdvForceSearchFocusUntil = Date.now() + 2200;
        focusPdvSearchField();
    });

    window.Livewire.on('erp-pdv-focus-finalizar', () => {
        erpPdvFinalizarClienteTyping = false;
        focusPdvFinalizarPagamento(0);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-pagamento', (payload) => {
        erpPdvFinalizarClienteTyping = false;
        focusPdvFinalizarPagamento(payload?.index ?? 0, payload?.valor ?? null);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-cliente', () => {
        focusPdvFinalizarCliente();
    });

    window.Livewire.on('erp-pdv-focus-finalizar-tabela-prazo', () => {
        window.setTimeout(() => focusPdvFinalizarParcelas(), 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-parcelas', () => {
        window.setTimeout(() => focusPdvFinalizarParcelas(), 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-cartao-canhoto', () => {
        window.setTimeout(() => focusPdvFinalizarCartaoCanhoto(), 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-tabelas-predefinidas', () => {
        window.setTimeout(() => {
            document.querySelector('#erp-pdv-parcelas-tabelas .erp-pdv__grid-row--selected')
                ?.scrollIntoView({ block: 'nearest' });
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-carne-impressao', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-carne-print-a4-capa')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-ok', () => {
        window.setTimeout(() => {
            document.querySelector('.erp-pdv-finalizar__operacao-btn')?.focus()
                || document.querySelector('.erp-pdv-finalizar__footer-actions button')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-informacoes', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-finalizar-informacoes')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-finalizar-sair-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-finalizar-sair-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-cancelar-venda-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-cancelar-venda-sim')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-operacao', () => {
        window.setTimeout(() => {
            document.querySelector('.erp-pdv-finalizar__operacao-btn')?.focus();
        }, 50);
    });

    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            if (window.__erpPdvForceSearchFocusUntil && Date.now() < window.__erpPdvForceSearchFocusUntil) {
                window.setTimeout(() => tryFocusPdvSearchField(), 0);
                window.setTimeout(() => tryFocusPdvSearchField(), 30);
                window.setTimeout(() => tryFocusPdvSearchField(), 100);
            }
        });
    });

    window.Livewire.hook('morph.updated', ({ el }) => {
        if (window.__erpPdvForceSearchFocusUntil && Date.now() < window.__erpPdvForceSearchFocusUntil) {
            window.requestAnimationFrame(() => {
                tryFocusPdvSearchField();
                window.setTimeout(() => tryFocusPdvSearchField(), 0);
                window.setTimeout(() => tryFocusPdvSearchField(), 40);
            });
        }

        if (el?.querySelector?.('.erp-pdv__grid--consulta')) {
            scrollPdvSearchSelectionIntoView();
        } else if (el?.querySelector?.('.erp-pdv__grid--cupom') || el?.classList?.contains('erp-pdv__grid-row--selected')) {
            scrollPdvCupomSelectionIntoView();
        }

        if (el?.querySelector?.('.erp-pdv-finalizar__cliente-list')) {
            erpPdvFinalizarClienteTyping = true;
            scrollPdvFinalizarClienteIntoView();
            // Remontagem Livewire pode tirar o foco do input no meio da digitação.
            window.requestAnimationFrame(() => {
                const input = document.getElementById('erp-pdv-finalizar-cliente');
                const active = document.activeElement;

                if (! input || active === input) {
                    return;
                }

                if (! active || active === document.body || active.id?.startsWith('erp-pdv-finalizar-valor-')) {
                    input.focus();
                }
            });
        }

        if (el?.querySelector?.('#erp-pdv-abertura-valor') || el?.id === 'erp-pdv-abertura-valor') {
            focusPdvModalField();
        }

        if (el?.querySelector?.('.erp-pdv-finalizar__grid-input') || el?.classList?.contains('erp-pdv-finalizar__grid-input')) {
            window.ErpMasks?.refresh(el?.closest?.('.erp-pdv-finalizar') ?? document.querySelector('.erp-pdv-finalizar') ?? document);
            scrollPdvFinalizarSelectionIntoView();
        }

        if (el?.querySelector?.('.erp-pdv__total-input') || el?.classList?.contains('erp-pdv__total-input')) {
            window.ErpMasks?.refresh(el?.closest?.('.erp-pdv') ?? document.querySelector('.erp-pdv') ?? document);
        }
    });

    window.Livewire.on('erp-pdv-focus-vendedor', () => {
        window.setTimeout(() => {
            const input = document.getElementById('erp-pdv-vendedor-search');
            input?.focus();
            input?.select();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-desconto', () => {
        window.setTimeout(() => {
            const input = document.getElementById('erp-pdv-desconto-preco');

            if (input) {
                window.ErpMasks?.refresh(input.closest('.erp-pdv-modal') ?? document);
                input.focus();
                input.select();
            }
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-grade', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-grade-confirm')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-serial', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-serial-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-busca-avancada', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-busca-avancada-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-remover-itens', () => {
        window.setTimeout(() => {
            const confirmSim = document.getElementById('erp-pdv-remover-itens-sim');

            if (confirmSim) {
                confirmSim.focus();

                return;
            }

            const input = document.getElementById('erp-pdv-remover-itens-search');

            if (input) {
                input.focus();
                input.select?.();
            }
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-remover-itens-confirm', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-remover-itens-sim')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-autorizacao', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-auth-password')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-busca-preco', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-busca-preco-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-importar', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-importar-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-importar-menu', () => {
        window.setTimeout(() => {
            const selected = document.querySelector('.erp-pdv-importar-menu__btn--selected')
                ?? document.getElementById('erp-pdv-importar-menu-row-0');

            selected?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-importar-pedido', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-importar-pedido-numero')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-receber', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-receber-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-reimprimir', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-reimprimir-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-consulta-venda', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-consulta-venda-search')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-estorno-venda', () => {
        window.setTimeout(() => {
            const motivo = document.getElementById('erp-pdv-estorno-motivo');
            motivo?.focus();
            motivo?.select();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-fiscal-overlay', () => {
        hidePdvFiscalTransmitProgress();
        window.setTimeout(() => {
            (
                document.getElementById('erp-pdv-fiscal-overlay-imprimir')
                ?? document.getElementById('erp-pdv-fiscal-overlay-entendido')
                ?? document.getElementById('erp-pdv-fiscal-overlay-sair')
            )?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-hide-fiscal-progress', () => {
        hidePdvFiscalTransmitProgress();
    });

    window.Livewire.on('erp-pdv-imprimir-pos-venda-opened', () => {
        hidePdvFiscalTransmitProgress();
        window.setTimeout(() => {
            document.getElementById('erp-pdv-imprimir-pos-venda-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-finalizar-imprimir-opened', () => {
        hidePdvFiscalTransmitProgress();
        window.setTimeout(() => {
            document.getElementById('erp-pdv-finalizar-imprimir-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-bloqueio', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-unlock-password')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-tabela-preco', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-tabela-preco-confirm')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-idle-reset', () => {
        resetPdvIdleTimer();
    });

    window.Livewire.on('erp-pdv-gaveta', () => {
        window.dispatchEvent(new CustomEvent('erp-pdv-gaveta-pulse'));
    });

    window.Livewire.on('erp-pdv-overlay-closed', () => {
        focusPdvSearchField();
    });
}

window.addEventListener('message', (event) => {
    if (event.data?.type !== 'erp-pdv-overlay-close') {
        return;
    }

    const component = getErpPdvComponent();

    if (! component) {
        return;
    }

    component.call('closeProductOverlay');
    component.call('closePersonOverlay');
});

function initErpPdv() {
    bindErpPdvKeys();
    bindPdvIdleMonitor();
    bindPdvStatusBar();
    bindPdvFiscalTransmitTriggers();
    bindPdvClickGuard();
    bindPdvSearchFocusTrap();

    const page = document.querySelector('.erp-pdv-page');

    if (! page) {
        // Saiu do PDV (navegou para outra tela): devolve a barra do Windows.
        exitPdvFullscreen();

        return;
    }

    armPdvKioskFullscreen();

    // Remove cursor customizado antigo, se ainda estiver no DOM.
    page.querySelectorAll('.erp-pdv__search-caret').forEach((el) => el.remove());

    if (page.querySelector('.erp-pdv')?.dataset.caixaAberto === '1') {
        focusPdvSearchField();
    }

    resetPdvIdleTimer();
}

/**
 * Tela cheia "quiosque" ao abrir o PDV pela retaguarda (esconde a barra do Windows).
 *
 * O navegador só permite entrar em tela cheia a partir de um gesto do usuário,
 * então armamos a entrada no primeiro toque/tecla dentro do PDV. Usamos o
 * Keyboard Lock (quando disponível, Chrome/Edge) para que o Esc continue
 * funcionando no PDV — para sair da tela cheia o operador segura o Esc ou usa
 * Alt+F4.
 */
function armPdvKioskFullscreen() {
    if (window.__erpPdvKioskArmed || document.fullscreenElement) {
        return;
    }

    const enterOnce = () => {
        window.__erpPdvKioskArmed = false;
        document.removeEventListener('pointerdown', enterOnce, true);
        document.removeEventListener('keydown', enterOnce, true);
        enterPdvFullscreen();
    };

    window.__erpPdvKioskArmed = true;
    document.addEventListener('pointerdown', enterOnce, true);
    document.addEventListener('keydown', enterOnce, true);
}

function enterPdvFullscreen() {
    if (document.fullscreenElement) {
        lockPdvEscapeKey();

        return;
    }

    const target = document.documentElement;
    const request = target.requestFullscreen
        ? target.requestFullscreen({ navigationUI: 'hide' })
        : null;

    if (! request || typeof request.then !== 'function') {
        lockPdvEscapeKey();

        return;
    }

    request.then(lockPdvEscapeKey).catch(() => {
        // Bloqueado pelo navegador: mantém o comportamento normal, sem tela cheia.
    });
}

function lockPdvEscapeKey() {
    try {
        if (navigator.keyboard && typeof navigator.keyboard.lock === 'function') {
            navigator.keyboard.lock(['Escape']).catch(() => {});
        }
    } catch (error) {
        // Keyboard Lock indisponível: Esc sai da tela cheia (fallback do navegador).
    }
}

function exitPdvFullscreen() {
    try {
        if (navigator.keyboard && typeof navigator.keyboard.unlock === 'function') {
            navigator.keyboard.unlock();
        }
    } catch (error) {
        // Ignora: nada a desbloquear.
    }

    if (document.fullscreenElement && document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
    }
}

let pdvFiscalProgressTimer = null;
let pdvFiscalProgressStepIndex = 0;
let pdvFiscalProgressActive = false;

function bindPdvFiscalTransmitTriggers() {
    if (window.__erpPdvFiscalTransmitTriggersBound) {
        return;
    }

    window.__erpPdvFiscalTransmitTriggersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.erp-pdv-finalizar__operacao-btn--fiscal');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startPdvFiscalTransmitProgress, 30);
    }, true);
}

/**
 * No PDV, clique do mouse só vale em botões, grid, inputs e modais.
 * Clique em área “morta” (título, foto, totais, status…) devolve o foco ao código.
 */
function bindPdvClickGuard() {
    if (window.__erpPdvClickGuardBound) {
        return;
    }

    window.__erpPdvClickGuardBound = true;

    const allowedSelector = [
        'button',
        'a[href]',
        'input',
        'select',
        'textarea',
        'label',
        'summary',
        '[role="button"]',
        '[role="dialog"]',
        '[role="listbox"]',
        '[role="option"]',
        '[contenteditable="true"]',
        '.erp-pdv__grid-row',
        '.erp-pdv__tool-btn',
        '.erp-pdv__search-field',
        '.erp-pdv__search-input',
        '.erp-pdv__total-input',
        '.erp-pdv__total-box--active',
        '.erp-pdv-modal',
        '.erp-pdv-overlay',
        '.erp-pdv-naoencontrado',
        '.erp-pdv-fiscal-overlay',
        '[data-erp-pdv-fiscal-progress]',
        '[data-erp-pdv-clickable]',
    ].join(',');

    document.addEventListener('mousedown', (event) => {
        if (event.button !== 0) {
            return;
        }

        const target = event.target;

        if (! (target instanceof Element)) {
            return;
        }

        const pdv = target.closest('.erp-pdv--click-guard');

        if (! pdv) {
            return;
        }

        // Cadastro embutido / overlay fora do fluxo principal.
        if (document.querySelector('.erp-pdv-overlay')) {
            return;
        }

        // Qualquer clique dentro de modal/overlay fiscal é válido.
        if (target.closest('.erp-pdv-modal, .erp-pdv-overlay, .erp-pdv-naoencontrado, [data-erp-pdv-fiscal-progress]')) {
            return;
        }

        if (target.closest(allowedSelector)) {
            return;
        }

        // Permite arrastar a barra de rolagem do grid.
        if (target.closest('.erp-pdv__grid-wrap') && isPdvScrollbarClick(event, target.closest('.erp-pdv__grid-wrap'))) {
            return;
        }

        event.preventDefault();

        if (pdv.dataset.caixaAberto === '1') {
            focusPdvSearchField();
        }
    }, true);
}

function isPdvScrollbarClick(event, scrollEl) {
    if (! scrollEl) {
        return false;
    }

    const rect = scrollEl.getBoundingClientRect();
    const hasVScroll = scrollEl.scrollHeight > scrollEl.clientHeight + 1;
    const hasHScroll = scrollEl.scrollWidth > scrollEl.clientWidth + 1;
    const sb = 18;

    if (hasVScroll && event.clientX >= rect.right - sb) {
        return true;
    }

    if (hasHScroll && event.clientY >= rect.bottom - sb) {
        return true;
    }

    return false;
}

function getPdvFiscalTransmitProgressOverlay() {
    return document.querySelector('[data-erp-pdv-fiscal-progress]');
}

function resetPdvFiscalTransmitProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    pdvFiscalProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-pdv-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-pdv-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-pdv-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-pdv-fiscal-step-bar]');

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

function advancePdvFiscalTransmitProgress(overlay) {
    const panel = overlay.querySelector('[data-erp-pdv-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-pdv-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-pdv-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-pdv-fiscal-step-bar]');

    if (steps.length === 0) {
        return;
    }

    if (pdvFiscalProgressStepIndex < steps.length - 1) {
        steps[pdvFiscalProgressStepIndex].classList.remove('is-active');
        steps[pdvFiscalProgressStepIndex].classList.add('is-done');
        pdvFiscalProgressStepIndex += 1;
        steps[pdvFiscalProgressStepIndex].classList.add('is-active');
    }

    if (statusEl && steps[pdvFiscalProgressStepIndex]) {
        statusEl.textContent = `${steps[pdvFiscalProgressStepIndex].textContent.trim()}…`;
    }

    if (barEl) {
        const percent = Math.min(100, Math.round(((pdvFiscalProgressStepIndex + 1) / steps.length) * 100));
        barEl.style.width = `${percent}%`;
    }
}

function stopPdvFiscalTransmitProgress() {
    pdvFiscalProgressActive = false;

    if (pdvFiscalProgressTimer) {
        window.clearInterval(pdvFiscalProgressTimer);
        pdvFiscalProgressTimer = null;
    }
}

function startPdvFiscalTransmitProgress() {
    const overlay = getPdvFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    stopPdvFiscalTransmitProgress();
    pdvFiscalProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetPdvFiscalTransmitProgressUi(overlay);

    window.setTimeout(() => {
        if (! pdvFiscalProgressActive) {
            return;
        }

        advancePdvFiscalTransmitProgress(overlay);
    }, 700);

    pdvFiscalProgressTimer = window.setInterval(() => {
        const currentOverlay = getPdvFiscalTransmitProgressOverlay();

        if (! pdvFiscalProgressActive || ! currentOverlay) {
            stopPdvFiscalTransmitProgress();

            return;
        }

        advancePdvFiscalTransmitProgress(currentOverlay);
    }, 850);
}

function hidePdvFiscalTransmitProgress() {
    stopPdvFiscalTransmitProgress();

    document.querySelectorAll('.erp-pdv-fiscal-progress').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetPdvFiscalTransmitProgressUi(getPdvFiscalTransmitProgressOverlay());
}

function focusPdvSearchField() {
    const delays = [0, 20, 60, 120, 250, 450, 800, 1200, 1800, 2400];

    delays.forEach((delay) => {
        window.setTimeout(() => {
            tryFocusPdvSearchField();
        }, delay);
    });
}

function findPdvLivewireComponent() {
    if (! window.Livewire?.all) {
        return null;
    }

    try {
        return window.Livewire.all().find((component) => component?.el?.querySelector?.('#erp-pdv-search')) ?? null;
    } catch (_) {
        return null;
    }
}

function pdvHasBlockingUi(pdv) {
    if (! pdv) {
        return true;
    }

    // Aviso "produto não encontrado" NÃO bloqueia (permite seguir bipando).
    // Confirmações/fiscal (classes extras) bloqueiam.
    const candidates = pdv.querySelectorAll(
        '.erp-pdv-modal, .erp-pdv-overlay, .erp-pdv-confirm-overlay, .erp-pdv-fiscal-overlay, [data-erp-pdv-fiscal-progress].is-visible',
    );

    for (const el of candidates) {
        const style = window.getComputedStyle(el);

        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            continue;
        }

        return true;
    }

    return false;
}

function tryFocusPdvSearchField() {
    const pdv = document.querySelector('.erp-pdv');

    if (! pdv || pdv.dataset.caixaAberto !== '1') {
        return false;
    }

    // Não rouba foco enquanto houver modal/overlay PDV visível.
    if (pdvHasBlockingUi(pdv)) {
        return false;
    }

    // Em fluxo normal (sem Caixa Rápido): respeita o passo Qtde/Preço.
    const launchQtd = document.getElementById('erp-pdv-launch-qtd');
    const launchPreco = document.getElementById('erp-pdv-launch-preco');

    if (launchQtd && ! launchQtd.disabled && ! launchQtd.readOnly) {
        return false;
    }

    if (launchPreco && ! launchPreco.disabled && ! launchPreco.readOnly) {
        return false;
    }

    const input = document.getElementById('erp-pdv-search');

    if (! input || input.disabled) {
        return false;
    }

    try {
        input.focus({ preventScroll: true });
    } catch (_) {
        input.focus();
    }

    try {
        const len = input.value.length;
        input.setSelectionRange(len, len);
    } catch (_) {
        // ignore
    }

    // Garante caret visível em alguns browsers após morph do Livewire.
    if (document.activeElement !== input) {
        try {
            input.focus({ preventScroll: true });
        } catch (_) {
            // ignore
        }
    }

    return document.activeElement === input;
}

/**
 * Trap estilo PDV: se o foco “sumiu” (body / área morta), devolve ao Código.
 * Não interfere em inputs/botões/modais em uso.
 */
function bindPdvSearchFocusTrap() {
    if (window.__erpPdvSearchFocusTrapBound) {
        return;
    }

    window.__erpPdvSearchFocusTrapBound = true;

    window.setInterval(() => {
        const pdv = document.querySelector('.erp-pdv');

        if (! pdv || pdv.dataset.caixaAberto !== '1' || pdvHasBlockingUi(pdv)) {
            return;
        }

        const input = document.getElementById('erp-pdv-search');

        if (! input || input.disabled) {
            return;
        }

        const active = document.activeElement;

        if (active === input) {
            return;
        }

        if (active && pdv.contains(active)) {
            const tag = (active.tagName || '').toUpperCase();

            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON') {
                return;
            }

            if (active.isContentEditable) {
                return;
            }
        }

        // Força após lançamento, ou quando o foco caiu fora do PDV / no body.
        const forceUntil = window.__erpPdvForceSearchFocusUntil || 0;
        const lostFocus = ! active
            || active === document.body
            || active === document.documentElement
            || ! pdv.contains(active);

        if (lostFocus || Date.now() < forceUntil) {
            tryFocusPdvSearchField();
        }
    }, 160);
}

function bindPdvStatusBar() {
    const statusBar = document.querySelector('.erp-pdv__status');

    if (! statusBar || statusBar.dataset.bound === '1') {
        return;
    }

    statusBar.dataset.bound = '1';

    const clockEl = document.getElementById('erp-pdv-status-clock');

    if (clockEl) {
        tickPdvStatusClock(clockEl);
    }

    if (! erpPdvClockTimer) {
        erpPdvClockTimer = window.setInterval(() => {
            const liveClock = document.getElementById('erp-pdv-status-clock');

            if (liveClock) {
                tickPdvStatusClock(liveClock);
            }
        }, 1000);
    }

    if (erpPdvStatusBarSync) {
        return;
    }

    erpPdvStatusBarSync = (event) => {
        syncPdvLockKeyIndicators(event);
    };

    document.addEventListener('keydown', erpPdvStatusBarSync);
    document.addEventListener('keyup', erpPdvStatusBarSync);
}

function tickPdvStatusClock(clockEl) {
    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    clockEl.textContent = `Data/Hora: ${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
}

function syncPdvLockKeyIndicators(event) {
    const capsEl = document.getElementById('erp-pdv-status-caps');
    const numEl = document.getElementById('erp-pdv-status-num');

    if (capsEl) {
        setPdvLockKeyState(capsEl, readPdvModifierState(event, 'CapsLock'));
    }

    if (numEl) {
        let numOn = readPdvModifierState(event, 'NumLock');

        if (! numOn && event?.location === KeyboardEvent.DOM_KEY_LOCATION_NUMPAD && /^\d$/.test(event.key ?? '')) {
            numOn = true;
        }

        setPdvLockKeyState(numEl, numOn);
    }
}

function readPdvModifierState(event, key) {
    try {
        return Boolean(event?.getModifierState?.(key));
    } catch {
        return false;
    }
}

function setPdvLockKeyState(el, on) {
    el.classList.toggle('erp-pdv__status-key--on', on);
    el.classList.toggle('erp-pdv__status-key--off', ! on);
    el.setAttribute('aria-pressed', on ? 'true' : 'false');
}

function bindPdvIdleMonitor() {
    if (erpPdvIdleBound) {
        return;
    }

    erpPdvIdleBound = true;

    const events = ['keydown', 'mousedown', 'mousemove', 'touchstart', 'scroll'];

    events.forEach((eventName) => {
        document.addEventListener(eventName, resetPdvIdleTimer, { passive: true });
    });
}

function resetPdvIdleTimer() {
    const pdvRoot = document.querySelector('.erp-pdv');

    if (! pdvRoot) {
        return;
    }

    const minutes = parseInt(pdvRoot.dataset.bloqueioMin ?? '', 10);

    if (! minutes || minutes <= 0 || pdvRoot.dataset.caixaAberto !== '1') {
        return;
    }

    if (pdvRoot.querySelector('.erp-pdv-modal--bloqueio')) {
        return;
    }

    clearTimeout(erpPdvIdleTimer);

    erpPdvIdleTimer = window.setTimeout(() => {
        const component = getErpPdvComponent();

        if (component) {
            component.call('lockPdv');
        }
    }, minutes * 60 * 1000);
}

function getErpPdvComponent(page = document.querySelector('.erp-pdv-page')) {
    if (! page || ! window.Livewire) {
        return null;
    }

    const wireId = page.getAttribute('wire:id')
        ?? page.closest('[wire\\:id]')?.getAttribute('wire:id');

    if (wireId) {
        const component = window.Livewire.find(wireId);

        if (component) {
            return component;
        }
    }

    if (window.Alpine) {
        try {
            const root = window.Alpine.findClosest(page, (node) => node.__livewire);

            if (root?.__livewire?.$wire) {
                return root.__livewire.$wire;
            }
        } catch {
            // ignore — fallback exhausted
        }
    }

    const pdvRoot = page.querySelector('.erp-pdv') ?? document.querySelector('.erp-pdv');

    if (pdvRoot && window.Livewire.getByName) {
        const byName = window.Livewire.getByName('app.filament.pages.pdv-page');

        if (byName?.length) {
            return byName[0];
        }
    }

    return null;
}

function bindErpPdvKeys() {
    if (erpPdvKeysBound) {
        return;
    }

    erpPdvKeysBound = true;

    document.addEventListener('keydown', handlePdvKeydown);
    document.addEventListener('focusin', (event) => {
        if (event.target?.id === 'erp-pdv-finalizar-cliente') {
            erpPdvFinalizarClienteTyping = true;
        }
    });
    document.addEventListener('focusout', (event) => {
        if (event.target?.id !== 'erp-pdv-finalizar-cliente') {
            return;
        }

        window.setTimeout(() => {
            if (document.activeElement?.id === 'erp-pdv-finalizar-cliente') {
                return;
            }

            // Mantém bloqueio dos atalhos enquanto a lista de clientes estiver aberta.
            if (document.querySelector('.erp-pdv-finalizar__cliente-list')
                || document.querySelector('.erp-pdv-finalizar[data-cliente-consulta="1"]')) {
                return;
            }

            erpPdvFinalizarClienteTyping = false;
        }, 0);
    });
}

function dispatchPdvShortcut(event, component) {
    const pdvRoot = document.querySelector('.erp-pdv');
    const caixaAberto = pdvRoot?.dataset.caixaAberto === '1';

    if (event.key === 'Escape') {
        event.preventDefault();
        component.call('handlePdvEscape');

        return true;
    }

    // Caixa fechado: só F1 (Opções) e F2 (Abrir Caixa) ficam ativos.
    if (! caixaAberto) {
        if (event.key === 'F1' || event.key === 'F2') {
            const callArgs = ERP_PDV_FN_SHORTCUTS[event.key];

            if (callArgs) {
                event.preventDefault();
                component.call(...callArgs);

                return true;
            }
        }

        if (ERP_PDV_FN_SHORTCUTS[event.key]
            || (event.ctrlKey && ERP_PDV_CTRL_SHORTCUTS[event.key.toLowerCase()])) {
            event.preventDefault();
        }

        return false;
    }

    if (event.ctrlKey && ! event.altKey && ! event.metaKey) {
        const key = event.key.toLowerCase();

        if (key === 't' && pdvRoot?.dataset.usaTef !== '1') {
            return false;
        }

        if (key === 'd' && pdvRoot?.dataset.permiteDescontoItem !== '1') {
            return false;
        }

        if (['s', 'n', 'e', 'b', 'm'].includes(key) && pdvRoot?.dataset.exibeMesas !== '1') {
            return false;
        }

        const callArgs = ERP_PDV_CTRL_SHORTCUTS[key];

        if (callArgs) {
            event.preventDefault();
            component.call(...callArgs);

            return true;
        }
    }

    if (event.ctrlKey || event.altKey || event.metaKey) {
        return false;
    }

    if (event.key === 'F12') {
        event.preventDefault();
        focusPdvSearchField();

        return true;
    }

    if (event.key === 'F3' && pdvRoot?.dataset.exibeF3 !== '1') {
        return false;
    }

    if (event.key === 'F4' && pdvRoot?.dataset.exibeF4 !== '1') {
        return false;
    }

    const callArgs = ERP_PDV_FN_SHORTCUTS[event.key];

    if (! callArgs) {
        return false;
    }

    event.preventDefault();
    component.call(...callArgs);

    return true;
}

function handlePdvKeydown(event) {
    const page = document.querySelector('.erp-pdv-page');

    if (! page) {
        return;
    }

    const component = getErpPdvComponent(page);

    if (! component) {
        return;
    }

    const pdvRoot = page.querySelector('.erp-pdv') ?? document.querySelector('.erp-pdv');

    if (! pdvRoot) {
        return;
    }

    const overlayOpen = pdvRoot.querySelector('.erp-pdv-overlay') !== null;
    const modalOpen = pdvRoot.querySelector('.erp-pdv-modal') !== null;
    const isFormModal = pdvRoot.querySelector('.erp-pdv-modal__window--form, .erp-pdv-modal__window--small') !== null;

    if (overlayOpen) {
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('handlePdvEscape');
        }

        return;
    }

    if (modalOpen) {
        handlePdvModalKeydown(event, component, isFormModal);

        return;
    }

    if (pdvRoot.querySelector('.erp-pdv-modal--bloqueio')) {
        return;
    }

    const searchFocused = document.activeElement?.id === 'erp-pdv-search';
    const launchQtdFocused = document.activeElement?.id === 'erp-pdv-launch-qtd';
    const launchPrecoFocused = document.activeElement?.id === 'erp-pdv-launch-preco';

    if (launchQtdFocused || launchPrecoFocused) {
        return;
    }

    if (event.key === 'Delete' && ! pdvRoot.querySelector('.erp-pdv__grid--consulta')) {
        if (pdvRoot.dataset.caixaAberto !== '1') {
            event.preventDefault();

            return;
        }

        event.preventDefault();
        component.call('deletarItemCupom');

        return;
    }

    if (searchFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        const delta = event.key === 'ArrowDown' ? 1 : -1;

        if (pdvRoot.querySelector('.erp-pdv__grid--consulta')) {
            component.call('moveSearchSelection', delta);
            scrollPdvSearchSelectionIntoView();
        } else {
            component.call('moveCupomSelection', delta);
            scrollPdvCupomSelectionIntoView();
        }

        return;
    }

    dispatchPdvShortcut(event, component);
}

/**
 * Coleta os atalhos das formas de pagamento renderizadas no modal de finalizar.
 * Os meios de pagamento (e seus atalhos) seguem o cadastro do ERP, por isso a
 * lista é dinâmica e lida diretamente do DOM.
 *
 * @returns {Set<string>}
 */
function collectFinalizarAtalhos() {
    const atalhos = new Set();

    document.querySelectorAll('.erp-pdv-finalizar__kbd').forEach((el) => {
        const valor = (el.textContent || '').trim().toUpperCase();

        if (valor.length === 1) {
            atalhos.add(valor);
        }
    });

    return atalhos;
}

/**
 * Garante que o valor digitado em uma linha de pagamento esteja formatado e
 * sincronizado com o servidor ANTES de qualquer ação (Enter/F10). Evita a
 * condição de corrida entre a máscara de dinheiro e o wire:model.live, que
 * fazia o último dígito não ser computado (ex.: 5000 virar 500).
 *
 * @param {HTMLElement|null} input
 */
function commitPdvFinalizarValor(input) {
    if (! input || ! input.id || ! input.id.startsWith('erp-pdv-finalizar-valor-')) {
        return;
    }

    if (! window.ErpMasks) {
        return;
    }

    window.ErpMasks.apply(input, { sync: false });
    delete input.dataset.erpMaskSynced;
    // Sincronização diferida: entra na mesma requisição do component.call()
    // seguinte, garantindo que o servidor receba o valor final antes de calcular.
    window.ErpMasks.syncLivewire(input, input.value, false);
}

/**
 * Mesma proteção do commitPdvFinalizarValor, porém genérica para qualquer
 * input com máscara dentro de um modal (ex.: valor de desconto/acréscimo).
 * Evita que o último dígito digitado não seja computado (10,00 virar 1,00).
 *
 * @param {HTMLElement|null} input
 */
function commitPdvMaskValue(input) {
    if (! input || ! window.ErpMasks) {
        return;
    }

    window.ErpMasks.apply(input, { sync: false });
    delete input.dataset.erpMaskSynced;
    window.ErpMasks.syncLivewire(input, input.value, false);
}

function findFinalizarOperacaoButton(atalho) {
    return document.querySelector(`.erp-pdv-finalizar__operacao-btn[data-atalho="${atalho}"]`);
}

function triggerFinalizarOperacao(component, atalho) {
    const btn = findFinalizarOperacaoButton(atalho);

    if (! btn?.dataset?.operacao) {
        return false;
    }

    commitPdvFinalizarValor(document.activeElement);

    if (btn.classList.contains('erp-pdv-finalizar__operacao-btn--fiscal')) {
        window.setTimeout(startPdvFiscalTransmitProgress, 30);
    }

    component.call('confirmFinalizarComOperacao', btn.dataset.operacao);

    return true;
}

function focusPdvFinalizarParcelas() {
    const qtd = document.getElementById('erp-pdv-parcelas-qtd');

    if (qtd) {
        qtd.focus();
        qtd.select?.();

        return;
    }

    document.querySelector('#erp-pdv-finalizar-tabela-prazo .erp-pdv__grid-row--selected')
        ?.scrollIntoView({ block: 'nearest' });
}

function focusPdvFinalizarCartaoCanhoto() {
    const nsu = document.getElementById('erp-pdv-canhoto-nsu');

    if (nsu) {
        nsu.focus();
        nsu.select?.();

        return;
    }

    document.querySelector('#erp-pdv-finalizar-cartao-canhoto .erp-pdv__grid-row--selected')
        ?.scrollIntoView({ block: 'nearest' });
}

function isFinalizarClienteTyping(event) {
    if (erpPdvFinalizarClienteTyping) {
        return true;
    }

    const target = event?.target;

    if (target?.id === 'erp-pdv-finalizar-cliente') {
        return true;
    }

    if (target?.closest?.('.erp-pdv-finalizar__cliente-list')) {
        return true;
    }

    if (document.activeElement?.id === 'erp-pdv-finalizar-cliente') {
        return true;
    }

    if (document.querySelector('.erp-pdv-finalizar__cliente-list') !== null) {
        return true;
    }

    if (document.querySelector('.erp-pdv-finalizar[data-cliente-consulta="1"]') !== null) {
        return true;
    }

    return false;
}

function handlePdvFinalizarModalKeydown(event, component) {
    const valorFocused = document.activeElement?.id?.startsWith('erp-pdv-finalizar-valor-');
    const cpfFocused = document.activeElement?.id === 'erp-pdv-finalizar-cpf';
    const informacoesFocused = document.activeElement?.id === 'erp-pdv-finalizar-informacoes';
    const clienteTyping = isFinalizarClienteTyping(event);
    const operacaoFocused = document.activeElement?.classList?.contains('erp-pdv-finalizar__operacao-btn');
    const canhotoModal = document.querySelector('.erp-pdv-canhoto-overlay') !== null;
    const parcelasModal = document.querySelector('.erp-pdv-parcelas-overlay') !== null;

    if (canhotoModal) {
        if (event.key === 'F2') {
            event.preventDefault();
            component.call('gerarParcelasCartaoCanhoto');

            return;
        }

        if (event.key === 'F4' || event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelFinalizarCartaoCanhoto');

            return;
        }

        if (event.key === 'F7') {
            event.preventDefault();
            component.call('concluirCartaoCanhoto');

            return;
        }

        if (event.key === 'Enter') {
            const typing = ['erp-pdv-canhoto-nsu', 'erp-pdv-canhoto-autorizacao', 'erp-pdv-canhoto-maquininha', 'erp-pdv-canhoto-bandeira', 'erp-pdv-canhoto-qtd', 'erp-pdv-canhoto-intervalo']
                .includes(document.activeElement?.id);

            if (typing) {
                event.preventDefault();
                const order = ['erp-pdv-canhoto-nsu', 'erp-pdv-canhoto-autorizacao', 'erp-pdv-canhoto-maquininha', 'erp-pdv-canhoto-bandeira', 'erp-pdv-canhoto-qtd', 'erp-pdv-canhoto-intervalo'];
                const idx = order.indexOf(document.activeElement.id);

                if (idx >= 0 && idx < order.length - 1) {
                    document.getElementById(order[idx + 1])?.focus();

                    return;
                }

                if (document.activeElement?.id === 'erp-pdv-canhoto-intervalo' || document.activeElement?.id === 'erp-pdv-canhoto-qtd') {
                    component.call('gerarParcelasCartaoCanhoto');

                    return;
                }
            }

            event.preventDefault();
            component.call('concluirCartaoCanhoto');

            return;
        }

        return;
    }

    if (parcelasModal) {
        const carnePrint = document.querySelector('.erp-pdv-carne-print') !== null;
        const tabelasLista = document.querySelector('.erp-pdv-parcelas__tabelas') !== null;

        if (carnePrint) {
            if (event.key === 'Escape') {
                event.preventDefault();
                component.call('fecharCarneImpressao');

                return;
            }

            if (event.key === '1' || event.key.toLowerCase() === 'c') {
                event.preventDefault();
                component.call('escolherCarneImpressaoA4ComCapa');

                return;
            }

            if (event.key === '2' || event.key.toLowerCase() === 'a') {
                event.preventDefault();
                component.call('escolherCarneImpressaoA4');

                return;
            }

            if (event.key === '3' || event.key.toLowerCase() === 'b') {
                event.preventDefault();
                component.call('escolherCarneImpressaoBobina80');

                return;
            }

            return;
        }

        if (tabelasLista) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                component.call('moveFinalizarTabelaPredefinidaSelection', event.key === 'ArrowDown' ? 1 : -1);
                window.setTimeout(() => {
                    document.querySelector('#erp-pdv-parcelas-tabelas .erp-pdv__grid-row--selected')
                        ?.scrollIntoView({ block: 'nearest' });
                }, 30);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                component.call('aplicarTabelaPrazoPredefinida');

                return;
            }

            if (event.key === 'Escape' || event.key === 'F4') {
                event.preventDefault();
                component.call('fecharTabelasPrazoPredefinidas');

                return;
            }

            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            component.call('gerarParcelasCrediario');

            return;
        }

        if (event.key === 'F8') {
            event.preventDefault();
            component.call('abrirTabelasPrazoPredefinidas');

            return;
        }

        if (event.key === 'F6') {
            event.preventDefault();
            component.call('abrirCarneImpressao');

            return;
        }

        if (event.key === 'F3') {
            event.preventDefault();
            component.call('excluirParcelaCrediario');

            return;
        }

        if (event.key === 'F4' || event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelFinalizarTabelaPrazoConsulta');

            return;
        }

        if (event.key === 'F7' || event.key === 'Enter') {
            const typing = document.activeElement?.id === 'erp-pdv-parcelas-qtd'
                || document.activeElement?.id === 'erp-pdv-parcelas-intervalo';

            if (event.key === 'Enter' && typing) {
                event.preventDefault();
                component.call('gerarParcelasCrediario');

                return;
            }

            event.preventDefault();
            component.call('concluirParcelasCrediario');

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveFinalizarParcelaSelection', event.key === 'ArrowDown' ? 1 : -1);
            window.setTimeout(() => {
                document.querySelector('#erp-pdv-finalizar-tabela-prazo .erp-pdv__grid-row--selected')
                    ?.scrollIntoView({ block: 'nearest' });
            }, 30);

            return;
        }

        return;
    }

    if (event.key === 'Enter' && (cpfFocused || informacoesFocused || operacaoFocused)) {
        event.preventDefault();

        return;
    }

    // Campo / lista de cliente: digitar nome livremente (ex.: "AD").
    // Não capturar atalhos de pagamento (D=Dinheiro, P=Pix…) até confirmar com Enter.
    if (clienteTyping) {
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('handlePdvEscape');

            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            erpPdvFinalizarClienteTyping = true;
            component.call('openFinalizarClienteConsulta');

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveFinalizarClienteSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvFinalizarClienteIntoView();

            return;
        }

        // Letras/números/espaço etc. ficam no input — não viram atalho de pagamento.
        return;
    }

    if (valorFocused && event.key === 'Enter') {
        event.preventDefault();
        commitPdvFinalizarValor(document.activeElement);
        component.call('handlePdvFinalizarValorEnter');

        return;
    }

    if (['F3', 'F4', 'F5', 'F6'].includes(event.key)) {
        if (triggerFinalizarOperacao(component, event.key)) {
            event.preventDefault();

            return;
        }
    }

    if (event.key === 'F10' || event.key === 'F7') {
        event.preventDefault();
        commitPdvFinalizarValor(document.activeElement);

        const footer = document.querySelector('.erp-pdv-finalizar__footer-actions');
        const unica = footer?.dataset?.operacaoUnica;

        if (unica) {
            component.call('confirmFinalizarComOperacao', unica);
        } else {
            component.call('confirmFinalizarVenda');
        }

        return;
    }

    if (event.key === 'F8') {
        event.preventDefault();
        component.call('movePagamentoSelection', 1);

        return;
    }

    if (event.key === 'F6') {
        event.preventDefault();
        document.getElementById('erp-pdv-finalizar-cpf')?.focus();

        return;
    }

    if (event.key === 'F2') {
        event.preventDefault();
        erpPdvFinalizarClienteTyping = true;
        component.call('openFinalizarClienteConsulta');

        return;
    }

    if (! event.ctrlKey && ! event.altKey && ! event.metaKey && ! cpfFocused && ! informacoesFocused) {
        const tecla = (event.key || '').toUpperCase();

        if (tecla.length === 1 && collectFinalizarAtalhos().has(tecla)) {
            event.preventDefault();
            component.call('selectPagamentoByAtalho', tecla);

            return;
        }
    }

    if (! event.ctrlKey && ! event.altKey && ! event.metaKey && ! valorFocused && ! cpfFocused && ! informacoesFocused) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('movePagamentoSelection', event.key === 'ArrowDown' ? 1 : -1);

            return;
        }
    }

    dispatchPdvShortcut(event, component);
}

function handlePdvModalKeydown(event, component, isFormModal) {
    if (document.getElementById('erp-pdv-unlock-password')) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmUnlockPdv');
        }

        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        component.call('handlePdvEscape');

        return;
    }

    const fiscalOverlayImprimir = document.getElementById('erp-pdv-fiscal-overlay-imprimir');
    const fiscalOverlayEntendido = document.getElementById('erp-pdv-fiscal-overlay-entendido');
    const fiscalOverlaySair = document.getElementById('erp-pdv-fiscal-overlay-sair');

    if (fiscalOverlayImprimir || fiscalOverlayEntendido || fiscalOverlaySair) {
        if (event.key === 'Enter') {
            event.preventDefault();

            if (fiscalOverlayImprimir) {
                component.call('imprimirProtocoloCancelamentoNfce');
            } else {
                component.call('sairPdvFiscalOverlay');
            }
        }

        if (event.key.toLowerCase() === 's' && fiscalOverlaySair && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('sairPdvFiscalOverlay');
        }

        return;
    }

    const sairModal = document.getElementById('erp-pdv-sair-title');

    if (sairModal) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmSairPdv');
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('closePdvModal');
        }

        return;
    }

    const excluirModal = document.getElementById('erp-pdv-excluir-title');

    if (excluirModal) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmExcluirItemCupom');
        }

        return;
    }

    const finalizarImprimirConfirm = document.getElementById('erp-pdv-finalizar-imprimir-title');

    if (finalizarImprimirConfirm) {
        const sim = document.getElementById('erp-pdv-finalizar-imprimir-sim');
        const nao = document.getElementById('erp-pdv-finalizar-imprimir-nao');

        if (event.key === 'Tab' || event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const focusSim = document.activeElement !== sim;

            (focusSim ? sim : nao)?.focus();
            sim?.classList.toggle('erp-pdv-modal__btn--primary', focusSim);
            nao?.classList.toggle('erp-pdv-modal__btn--primary', ! focusSim);

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmFinalizarImprimir', document.activeElement === sim);

            return;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmFinalizarImprimir', true);

            return;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmFinalizarImprimir', false);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelFinalizarImprimir');

            return;
        }

        return;
    }

    const imprimirPosVenda = document.getElementById('erp-pdv-imprimir-pos-venda-title');

    if (imprimirPosVenda) {
        const sim = document.getElementById('erp-pdv-imprimir-pos-venda-sim');
        const nao = document.getElementById('erp-pdv-imprimir-pos-venda-nao');

        if (event.key === 'Tab' || event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const focusSim = document.activeElement !== sim;

            (focusSim ? sim : nao)?.focus();
            sim?.classList.toggle('erp-pdv-modal__btn--primary', focusSim);
            nao?.classList.toggle('erp-pdv-modal__btn--primary', ! focusSim);

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmImprimirPosVenda', document.activeElement === sim);

            return;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirPosVenda', true);

            return;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirPosVenda', false);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('confirmImprimirPosVenda', false);

            return;
        }

        return;
    }

    const cancelarVendaConfirm = document.getElementById('erp-pdv-cancelar-venda-title');

    if (cancelarVendaConfirm) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const active = document.activeElement;

            if (active?.id === 'erp-pdv-cancelar-venda-nao') {
                component.call('cancelCancelarCupom');
            } else {
                component.call('confirmCancelarCupom');
            }

            return true;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const sim = document.getElementById('erp-pdv-cancelar-venda-sim');
            const nao = document.getElementById('erp-pdv-cancelar-venda-nao');

            if (document.activeElement === nao) {
                sim?.focus();
            } else {
                nao?.focus();
            }

            return true;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmCancelarCupom');

            return true;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('cancelCancelarCupom');

            return true;
        }

        return true;
    }
    const finalizarSairConfirm = document.getElementById('erp-pdv-finalizar-sair-title');

    if (finalizarSairConfirm) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const active = document.activeElement;

            if (active?.id === 'erp-pdv-finalizar-sair-nao') {
                component.call('cancelCloseFinalizar');
            } else {
                component.call('confirmCloseFinalizar');
            }

            return true;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const sim = document.getElementById('erp-pdv-finalizar-sair-sim');
            const nao = document.getElementById('erp-pdv-finalizar-sair-nao');

            if (document.activeElement === nao) {
                sim?.focus();
            } else {
                nao?.focus();
            }

            return true;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmCloseFinalizar');

            return true;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('cancelCloseFinalizar');

            return true;
        }

        return true;
    }

    const finalizarModal = document.getElementById('erp-pdv-finalizar-title');

    if (finalizarModal) {
        handlePdvFinalizarModalKeydown(event, component);

        return;
    }

    if (handlePdvListModalKeydown(event, component)) {
        return;
    }

    if (isFormModal) {
        if (event.key === 'F10') {
            const sangriaModal = document.getElementById('erp-pdv-sangria-title');
            const suprimentoModal = document.getElementById('erp-pdv-suprimento-title');

            if (sangriaModal || suprimentoModal) {
                event.preventDefault();
                component.call(sangriaModal ? 'gravarSangria' : 'gravarSuprimento');

                return;
            }
        }

        if (event.key === 'F2') {
            const abrirTitle = document.getElementById('erp-pdv-caixa-title');

            if (abrirTitle) {
                event.preventDefault();

                if (abrirTitle.textContent?.includes('Abrir')) {
                    component.call('confirmAbrirCaixa');
                } else {
                    component.call('confirmFecharCaixa');
                }

                return;
            }
        }
    }

    dispatchPdvShortcut(event, component);
}

function handlePdvListModalKeydown(event, component) {
    const importarMenu = document.getElementById('erp-pdv-importar-menu-panel');

    if (importarMenu) {
        const fnMap = {
            F2: 'pedido',
            F3: 'orcamento',
            F4: 'ordem_servico',
            F5: 'pre_venda',
        };

        if (fnMap[event.key]) {
            event.preventDefault();
            component.call('selectImportarTipo', fnMap[event.key]);

            return true;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveImportarMenuSelection', event.key === 'ArrowDown' ? 1 : -1);
            window.setTimeout(() => {
                document.querySelector('.erp-pdv-importar-menu__btn--selected')?.focus();
            }, 60);

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmImportarMenuSelection');

            return true;
        }

        return true;
    }

    const importarPedidoPanel = document.getElementById('erp-pdv-importar-pedido-panel');

    if (importarPedidoPanel) {
        const pedidoNumeroFocused = document.activeElement?.id === 'erp-pdv-importar-pedido-numero';
        const pedidoDeFocused = document.activeElement?.id === 'erp-pdv-importar-pedido-de';
        const pedidoAteFocused = document.activeElement?.id === 'erp-pdv-importar-pedido-ate';

        if (event.key === 'F9') {
            event.preventDefault();
            component.call('refreshImportarPedidoResults');

            return true;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            component.call('confirmImportarPedido');

            return true;
        }

        if ((pedidoNumeroFocused || pedidoDeFocused || pedidoAteFocused)
            && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveImportarPedidoSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-importar-pedido-row-');

            return true;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveImportarPedidoSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-importar-pedido-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmImportarPedido');

            return true;
        }

        return true;
    }

    const gradeModal = document.getElementById('erp-pdv-grade-confirm');

    if (gradeModal) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmPdvGrade');

            return true;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('movePdvGradeSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-grade-row-');

            return true;
        }

        return false;
    }

    const vendedorSearch = document.getElementById('erp-pdv-vendedor-search');

    if (vendedorSearch) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveVendedorSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-vendedor-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmVendedor');

            return true;
        }

        return false;
    }

    const serialSearch = document.getElementById('erp-pdv-serial-search');

    if (serialSearch) {
        const serialFocused = document.activeElement?.id === 'erp-pdv-serial-search';

        if (serialFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('movePdvSerialSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-serial-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmPdvSerial');

            return true;
        }

        return false;
    }

    const buscaSearch = document.getElementById('erp-pdv-busca-avancada-search');

    if (buscaSearch) {
        const buscaFocused = document.activeElement?.id === 'erp-pdv-busca-avancada-search';

        if (buscaFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveBuscaAvancadaSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-busca-avancada-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmBuscaAvancada');

            return true;
        }

        return false;
    }

    const removerSearch = document.getElementById('erp-pdv-remover-itens-search');
    const removerSim = document.getElementById('erp-pdv-remover-itens-sim');
    const removerNao = document.getElementById('erp-pdv-remover-itens-nao');

    if (removerSim || removerNao) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const active = document.activeElement;

            if (active === removerNao) {
                component.call('cancelRemoverItensConfirm');
            } else {
                component.call('confirmRemoverItens');
            }

            return true;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();

            if (document.activeElement === removerNao) {
                removerSim?.focus();
            } else {
                removerNao?.focus();
            }

            return true;
        }

        return true;
    }

    if (removerSearch) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('handleRemoverItensSearchEnter', removerSearch.value || '');

            return true;
        }

        return false;
    }

    const descontoValor = document.getElementById('erp-pdv-desconto-preco');

    if (descontoValor) {
        if (event.key === 'Enter') {
            event.preventDefault();
            commitPdvMaskValue(descontoValor);
            component.call('confirmDescontoItem');

            return true;
        }

        return false;
    }

    const authPassword = document.getElementById('erp-pdv-auth-password');

    if (authPassword) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmPdvAutorizacao');

            return true;
        }

        return false;
    }

    const buscaPrecoSearch = document.getElementById('erp-pdv-busca-preco-search');

    if (buscaPrecoSearch) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmBuscaPreco');

            return true;
        }

        return false;
    }

    const importarSearch = document.getElementById('erp-pdv-importar-search');

    if (importarSearch) {
        const importarFocused = document.activeElement?.id === 'erp-pdv-importar-search';

        if (importarFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveImportarSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-importar-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmImportarOrcamento');

            return true;
        }

        return false;
    }

    const receberSearch = document.getElementById('erp-pdv-receber-search');
    const receberValor = document.getElementById('erp-pdv-receber-valor');

    if (receberSearch || receberValor) {
        const receberFocused = document.activeElement?.id === 'erp-pdv-receber-search';

        if (receberFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveReceberSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-receber-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmReceberConta');

            return true;
        }

        return false;
    }

    const reimprimirSearch = document.getElementById('erp-pdv-reimprimir-search');

    if (reimprimirSearch) {
        const reimprimirFocused = document.activeElement?.id === 'erp-pdv-reimprimir-search';

        if (reimprimirFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveReimprimirSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-reimprimir-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmReimprimir');

            return true;
        }

        return false;
    }

    const consultaVendaSearch = document.getElementById('erp-pdv-consulta-venda-search');

    if (consultaVendaSearch) {
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelConsultaVenda');

            return true;
        }

        const consultaFocused = document.activeElement?.id === 'erp-pdv-consulta-venda-search';

        if (consultaFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveConsultaVendaSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-consulta-venda-row-');

            return true;
        }

        if (consultaFocused && event.key === ' ') {
            event.preventDefault();
            component.call('toggleMarkCurrentConsultaVendaRow');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('imprimirConsultaVenda');

            return true;
        }

        if (event.key === 'Delete') {
            event.preventDefault();
            component.call('requestEstornarConsultaVenda');

            return true;
        }

        return false;
    }

    const tabelaPrecoConfirm = document.getElementById('erp-pdv-tabela-preco-confirm');

    if (tabelaPrecoConfirm) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmTabelaPreco');

            return true;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            component.call('moveTabelaPrecoSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-tabela-preco-row-');

            return true;
        }

        return false;
    }

    return false;
}

function scrollPdvModalRowIntoView(prefix) {
    window.requestAnimationFrame(() => {
        const rows = document.querySelectorAll(`.erp-pdv-modal [id^="${prefix}"]`);

        for (const row of rows) {
            if (row.classList.contains('erp-pdv__grid-row--marked')
                || row.classList.contains('erp-pdv__grid-row--selected')
                || row.classList.contains('erp-pdv-vendedor-row--selected')) {
                row.scrollIntoView({ block: 'nearest' });

                return;
            }
        }
    });
}

let erpPdvConfirmTimer = null;

function showPdvProdutoConfirmado(nome) {
    const el = document.getElementById('erp-pdv-product-name');

    if (! el || ! nome) {
        return;
    }

    el.textContent = nome;
    el.classList.add('erp-pdv__product-line--flash');

    if (erpPdvConfirmTimer) {
        window.clearTimeout(erpPdvConfirmTimer);
    }

    erpPdvConfirmTimer = window.setTimeout(() => {
        el.classList.remove('erp-pdv__product-line--flash');
        erpPdvConfirmTimer = null;
    }, 700);
}

let erpPdvAudioCtx = null;

function playPdvBeep() {
    const pdvRoot = document.querySelector('.erp-pdv');

    if (pdvRoot?.dataset.somAtivo !== '1') {
        return;
    }

    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;

        if (! AudioCtx) {
            return;
        }

        if (! erpPdvAudioCtx) {
            erpPdvAudioCtx = new AudioCtx();
        }

        if (erpPdvAudioCtx.state === 'suspended') {
            erpPdvAudioCtx.resume();
        }

        const ctx = erpPdvAudioCtx;
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'square';
        oscillator.frequency.setValueAtTime(880, ctx.currentTime);

        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.16);

        oscillator.connect(gain);
        gain.connect(ctx.destination);

        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + 0.18);
    } catch (error) {
        // Áudio indisponível/bloqueado pelo navegador — ignora silenciosamente.
    }
}

function playPdvErrorBeep() {
    const pdvRoot = document.querySelector('.erp-pdv');

    if (pdvRoot?.dataset.somAtivo !== '1') {
        return;
    }

    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;

        if (! AudioCtx) {
            return;
        }

        if (! erpPdvAudioCtx) {
            erpPdvAudioCtx = new AudioCtx();
        }

        if (erpPdvAudioCtx.state === 'suspended') {
            erpPdvAudioCtx.resume();
        }

        const ctx = erpPdvAudioCtx;
        const gain = ctx.createGain();
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.32, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.55);
        gain.connect(ctx.destination);

        // Tom grave/buzzer com duas descidas — distinto do bip de sucesso.
        const oscillator = ctx.createOscillator();
        oscillator.type = 'sawtooth';
        oscillator.frequency.setValueAtTime(320, ctx.currentTime);
        oscillator.frequency.setValueAtTime(220, ctx.currentTime + 0.18);
        oscillator.frequency.setValueAtTime(160, ctx.currentTime + 0.36);
        oscillator.connect(gain);

        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + 0.55);
    } catch (error) {
        // Áudio indisponível/bloqueado pelo navegador — ignora silenciosamente.
    }
}

function focusPdvLaunchField(field) {
    // Evita o trap do Código roubar o foco no passo Qtde/Preço.
    window.__erpPdvForceSearchFocusUntil = 0;

    const tryFocus = (attempt = 0) => {
        const id = field === 'preco' ? 'erp-pdv-launch-preco' : 'erp-pdv-launch-qtd';
        const input = document.getElementById(id);

        if (input && ! input.disabled && ! input.readOnly) {
            try {
                input.focus({ preventScroll: true });
            } catch (_) {
                input.focus();
            }
            input.select?.();

            if (window.ErpMasks?.refresh) {
                window.ErpMasks.refresh(input.closest('.erp-pdv') ?? document);
            }

            return;
        }

        if (attempt < 8) {
            window.setTimeout(() => tryFocus(attempt + 1), 60);
        }
    };

    window.setTimeout(() => tryFocus(), 30);
    window.setTimeout(() => tryFocus(0), 120);
    window.setTimeout(() => tryFocus(0), 280);
}

function scrollPdvSearchSelectionIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-pdv__grid--consulta .erp-pdv__grid-row--selected')?.scrollIntoView({
            block: 'nearest',
        });
    });
}

function scrollPdvCupomSelectionIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-pdv__grid--cupom .erp-pdv__grid-row--selected')?.scrollIntoView({
            block: 'nearest',
        });
    });
}

function scrollPdvFinalizarSelectionIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-pdv-finalizar__grid .erp-pdv__grid-row--selected')?.scrollIntoView({
            block: 'nearest',
        });
    });
}

function scrollPdvFinalizarClienteIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-pdv-finalizar__cliente-grid .erp-pdv__grid-row--selected')?.scrollIntoView({
            block: 'nearest',
        });
    });
}

function focusPdvFinalizarCliente() {
    window.setTimeout(() => {
        const input = document.getElementById('erp-pdv-finalizar-cliente');

        input?.focus();
        input?.select?.();
    }, 50);
}

function focusPdvFinalizarPagamento(index, valor = null) {
    const applyFocus = () => {
        const input = document.getElementById(`erp-pdv-finalizar-valor-${index}`);

        if (! input) {
            return;
        }

        if (valor !== null && valor !== undefined && valor !== '') {
            input.value = valor;
            delete input.dataset.erpMaskSynced;
            window.ErpMasks?.apply(input, { sync: false });
        }

        input.focus();
        input.select?.();
    };

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(applyFocus);
    });
}

function focusPdvModalField() {
    const abertura = document.getElementById('erp-pdv-abertura-valor');

    if (abertura) {
        const modal = abertura.closest('.erp-pdv-caixa-modal')
            ?? abertura.closest('.erp-pdv-modal')
            ?? document;

        // Garante máscara de dinheiro no modal recém-aberto (Livewire morph).
        delete abertura.dataset.erpMaskBound;
        window.ErpMasks?.refresh(modal);

        abertura.value = '0,00';
        abertura.setAttribute('autocomplete', 'off');
        abertura.setAttribute('title', '');
        abertura.removeAttribute('list');

        window.ErpMasks?.apply(abertura, { sync: false });

        const selectAll = () => {
            try {
                abertura.focus({ preventScroll: true });
            } catch (_) {
                abertura.focus();
            }
            // Seleção total (azul) para digitar por cima.
            abertura.select();
            if (typeof abertura.setSelectionRange === 'function') {
                abertura.setSelectionRange(0, abertura.value.length);
            }
        };

        if (abertura.dataset.erpAberturaSelectBound !== '1') {
            abertura.dataset.erpAberturaSelectBound = '1';
            abertura.addEventListener('focus', () => {
                window.requestAnimationFrame(selectAll);
            });
            // Evita o Chrome “desselecionar” no clique.
            abertura.addEventListener('mouseup', (event) => {
                if (abertura.selectionStart === abertura.selectionEnd) {
                    event.preventDefault();
                    selectAll();
                }
            });
        }

        window.requestAnimationFrame(() => {
            selectAll();
            window.setTimeout(selectAll, 40);
            window.setTimeout(selectAll, 120);
        });

        return;
    }

    if (document.getElementById('erp-pdv-finalizar-title')) {
        focusPdvFinalizarPagamento(0);

        return;
    }

    const sangria = document.querySelector('.erp-pdv-form__input');
    const caixaDialog = document.querySelector('.erp-pdv-caixa-dialog');

    if (sangria) {
        sangria.focus();
        sangria.select?.();

        return;
    }

    if (caixaDialog) {
        caixaDialog.querySelector('button')?.focus();
    }
}

if (window.Livewire) {
    bindErpPdvLivewireEvents();
}

window.ErpPdvPrint = {
    openCupom(payload) {
        if (window.ErpPrint?.openCupom) {
            window.ErpPrint.openCupom(payload);
            return;
        }

        const url = payload?.url;
        const copias = payload?.copias ?? 1;

        if (! url) {
            return;
        }

        this.printUrl(url);

        if (copias > 1) {
            window.setTimeout(() => {
                this.printUrl(String(url).replace(/([?&])auto=1\b/, '$1auto=0').replace(/([?&])auto=0\b/, '$1auto=1'));
            }, 800);
        }
    },

    /**
     * Imprime relatório em iframe oculto (sem abrir aba no navegador).
     * @param {string} url
     */
    printUrl(url) {
        if (! url) {
            return;
        }

        let printUrl = String(url);

        try {
            const parsed = new URL(printUrl, window.location.origin);
            parsed.searchParams.set('auto', '1');
            printUrl = parsed.toString();
        } catch (_) {
            printUrl = printUrl.includes('auto=')
                ? printUrl.replace(/([?&])auto=\d/, '$1auto=1')
                : `${printUrl}${printUrl.includes('?') ? '&' : '?'}auto=1`;
        }

        document.getElementById('erp-pdv-print-frame')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'erp-pdv-print-frame';
        iframe.src = printUrl;
        iframe.title = 'Impressão PDV';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = [
            'position:fixed',
            'width:0',
            'height:0',
            'border:0',
            'opacity:0',
            'pointer-events:none',
            'left:-9999px',
            'top:-9999px',
        ].join(';');

        let cleanedUp = false;

        const cleanup = () => {
            if (cleanedUp) {
                return;
            }

            cleanedUp = true;
            window.removeEventListener('message', onMessage);
            iframe.remove();
        };

        const onMessage = (event) => {
            if (event.source !== iframe.contentWindow) {
                return;
            }

            if (event.data?.type === 'erp-pdv-carne-print-done') {
                cleanup();
            }
        };

        window.addEventListener('message', onMessage);

        iframe.addEventListener('load', () => {
            // O relatório com ?auto=1 dispara window.print() sozinho.
            // Só limpamos o iframe após afterprint (postMessage) ou timeout.
            window.setTimeout(cleanup, 120000);
        }, { once: true });

        document.body.appendChild(iframe);
    },
};

initErpPdv();

/**
 * Descanso de tela do letreiro (marquee).
 * Regra: após 30s SEM atividade e SEM venda em andamento (cupom vazio),
 * troca o título "CAIXA ABERTO" pelas mensagens correndo. Qualquer atividade
 * (tecla/mouse) ou item no cupom volta imediatamente para "CAIXA ABERTO".
 */
(function initPdvScreensaver() {
    const IDLE_MS = 30000;
    let idleTimer = null;
    let idle = false;

    const getHeader = () => document.querySelector('.erp-pdv__header');
    const getPdv = () => document.querySelector('.erp-pdv');

    function canScreensave() {
        const header = getHeader();
        const pdv = getPdv();

        if (! header || header.dataset.marquee !== '1') {
            return false;
        }

        // Venda em andamento (cupom com itens): mantém "CAIXA ABERTO".
        if (header.dataset.vendaAndamento === '1') {
            return false;
        }

        // Só substitui quando o caixa está aberto.
        if (pdv && pdv.dataset.caixaAberto !== '1') {
            return false;
        }

        return true;
    }

    function syncMarqueeFontSize() {
        const header = getHeader();

        if (! header) {
            return;
        }

        const title = header.querySelector('.erp-pdv__title:not(.erp-pdv__marquee-text)');
        const marquees = header.querySelectorAll('.erp-pdv__marquee-text');

        if (! title || marquees.length === 0) {
            return;
        }

        // Lê o tamanho real renderizado do "CAIXA ABERTO" (inclui overrides do Filament).
        const wasHidden = getComputedStyle(title).display === 'none';
        let size = '';
        let lineHeight = '';
        let fontWeight = '';

        if (wasHidden) {
            // Mede com o título temporariamente visível fora da tela.
            const prev = {
                display: title.style.display,
                visibility: title.style.visibility,
                position: title.style.position,
                left: title.style.left,
            };
            title.style.display = 'block';
            title.style.visibility = 'hidden';
            title.style.position = 'absolute';
            title.style.left = '-9999px';
            const cs = getComputedStyle(title);
            size = cs.fontSize;
            lineHeight = cs.lineHeight;
            fontWeight = cs.fontWeight;
            title.style.display = prev.display;
            title.style.visibility = prev.visibility;
            title.style.position = prev.position;
            title.style.left = prev.left;
        } else {
            const cs = getComputedStyle(title);
            size = cs.fontSize;
            lineHeight = cs.lineHeight;
            fontWeight = cs.fontWeight;
        }

        if (! size) {
            return;
        }

        header.style.setProperty('--erp-pdv-banner-font-size', size);
        marquees.forEach((el) => {
            el.style.fontSize = size;
            el.style.lineHeight = lineHeight || '1.15';
            el.style.fontWeight = fontWeight || '800';
        });
    }

    function apply() {
        const header = getHeader();

        if (! header) {
            return;
        }

        if (idle && canScreensave()) {
            // Mede com o título ainda visível, depois troca para o letreiro.
            syncMarqueeFontSize();
            header.classList.add('is-screensaver');
        } else {
            header.classList.remove('is-screensaver');
        }
    }

    function goIdle() {
        idle = true;
        apply();
    }

    function resetIdle() {
        idle = false;
        apply();

        if (idleTimer) {
            window.clearTimeout(idleTimer);
        }

        idleTimer = window.setTimeout(goIdle, IDLE_MS);
    }

    ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart', 'click'].forEach((evt) => {
        document.addEventListener(evt, resetIdle, { passive: true });
    });

    document.addEventListener('livewire:init', () => {
        resetIdle();

        try {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                // Reaplica o estado após cada re-render (o morph reescreve o header).
                window.Livewire.hook('morph.updated', apply);
            }
        } catch (e) {
            // Hook indisponível: o timer de inatividade ainda funciona.
        }
    });

    document.addEventListener('DOMContentLoaded', resetIdle);
    resetIdle();
})();
