let erpPdvKeysBound = false;
let erpPdvLivewireBound = false;
let erpPdvIdleTimer = null;
let erpPdvIdleBound = false;
let erpPdvClockTimer = null;
let erpPdvStatusBarSync = null;
/** @type {boolean|null} */
let erpPdvCapsLockOn = null;
/** @type {boolean|null} */
let erpPdvNumLockOn = null;
/** Evita atalho de pagamento (D/P/…) enquanto digita o cliente no finalizar. */
let erpPdvFinalizarClienteTyping = false;
/** Control físico pressionado (Chrome pode entregar KeyD com ctrlKey:false). */
let pdvCtrlLatch = false;
window.__erpPdvCtrlLatch = false;

/** @type {Record<string, [string, ...unknown[]]>} */
const ERP_PDV_FN_SHORTCUTS = {
    F1: ['openPdvModal', 'options'],
    F2: ['toggleCaixa'],
    F3: ['openPdvModal', 'acesso_rapido'],
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
    // Desconto/Acréscimo (Ctrl+D): handlePdvModifierCapture + btn.click.
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
        ensurePdvFullscreenFromUserGesture();
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

    window.Livewire.on('erp-pdv-read-scale', (payload) => {
        const settings = resolvePdvScaleSettingsFromEvent(payload);
        void window.readPdvScaleWeightAndConfirm?.(settings);
    });

    window.Livewire.on('erp-pdv-focus-finalizar', () => {
        erpPdvFinalizarClienteTyping = false;
        focusPdvFinalizarPagamento(0);
    });

    window.Livewire.on('erp-pdv-contar-moedas-opened', () => {
        window.setTimeout(() => {
            const primeiro = document.getElementById('erp-pdv-moedas-primeiro');

            if (! primeiro) {
                return;
            }

            primeiro.focus();
            primeiro.select?.();
        }, 50);
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

    window.Livewire.on('erp-pdv-focus-finalizar-aviso', () => {
        // Atraso: o Enter que disparou o aviso ainda pode soltar (keyup) e
        // ativar o botão OK se ele receber foco cedo demais.
        window.setTimeout(() => {
            const ok = document.getElementById('erp-pdv-finalizar-aviso-ok');
            const aviso = document.querySelector('.erp-pdv-finalizar-aviso');

            if (! ok || ! aviso) {
                return;
            }

            const blockKeyup = (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.stopPropagation();
                }
            };

            ok.addEventListener('keyup', blockKeyup, true);
            window.setTimeout(() => ok.removeEventListener('keyup', blockKeyup, true), 500);
            ok.focus();
        }, 350);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-informacoes', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-finalizar-informacoes')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-cpf', () => {
        window.setTimeout(() => {
            const el = document.getElementById('erp-pdv-finalizar-cpf');
            if (! el) {
                return;
            }
            el.focus();
            el.select?.();
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

        restorePdvBuscaAvancadaFocus();

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

        if (
            el?.querySelector?.('#erp-pdv-status-num')
            || el?.querySelector?.('#erp-pdv-status-caps')
            || el?.querySelector?.('.erp-pdv__status')
            || el?.classList?.contains('erp-pdv__status')
        ) {
            bindPdvStatusBar();
        }
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

    window.Livewire.on('erp-pdv-focus-vendas-espera', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-vendas-espera-search')?.focus();
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

    // "Erro ao carregar a página" / timeout: Livewire morre sem hide — fecha o overlay na hora.
    window.Livewire.hook('request', ({ fail }) => {
        fail(() => {
            const overlay = getPdvFiscalTransmitProgressOverlay();

            if (! overlay?.classList.contains('is-visible') && ! pdvFiscalProgressActive) {
                return;
            }

            forceHidePdvFiscalTransmitProgressStuck(
                'A finalização falhou (comunicação ou erro no servidor). Se a venda não aparecer em Vendas, tente de novo. Se aparecer sem NFC-e, transmita ou use contingência na tela NFC-e.'
            );
        });
    });

    window.Livewire.on('erp-pdv-imprimir-pos-venda-opened', () => {
        hidePdvFiscalTransmitProgress();
        window.setTimeout(() => {
            document.getElementById('erp-pdv-imprimir-pos-venda-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-imprimir-movimento-caixa-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-imprimir-movimento-caixa-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-imprimir-resumo-caixa-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-imprimir-resumo-caixa-nao')?.focus();
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
    bindPdvLaunchStepBack();
    bindPdvSearchFocusTrap();

    const page = document.querySelector('.erp-pdv-page');

    if (! page) {
        // Saiu do PDV (navegou para outra tela): devolve a barra do Windows.
        exitPdvFullscreen();

        return;
    }

    armPdvKioskFullscreen();

    // Overlay NFC-e às vezes fica preso com is-visible (wire:ignore) e engole o PDV.
    document.querySelectorAll('[data-erp-pdv-fiscal-progress].is-visible').forEach((overlay) => {
        if (! pdvFiscalProgressActive) {
            overlay.classList.remove('is-visible');
        }
    });

    // Remove cursor customizado antigo, se ainda estiver no DOM.
    page.querySelectorAll('.erp-pdv__search-caret').forEach((el) => el.remove());

    if (page.querySelector('.erp-pdv')?.dataset.caixaAberto === '1') {
        focusPdvSearchField();
    }

    resetPdvIdleTimer();
}

/**
 * Tela cheia "quiosque" ao abrir o PDV (esconde a barra do Windows).
 *
 * O navegador só permite fullscreen com gesto do usuário. Em PWA standalone
 * o clique no atalho PDV já é o gesto — tentamos na hora e, se falhar,
 * armamos de novo no próximo clique/tecla dentro do PDV.
 */
function isErpPwaStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true;
}

function armPdvKioskFullscreen() {
    if (document.fullscreenElement) {
        lockPdvEscapeKey();

        return;
    }

    // Em PWA, tenta entrar já (pode falhar se não houver gesto nesta carga).
    if (isErpPwaStandalone()) {
        enterPdvFullscreen();
        if (document.fullscreenElement) {
            return;
        }
    }

    if (window.__erpPdvKioskArmed) {
        return;
    }

    const enterOnce = () => {
        window.__erpPdvKioskArmed = false;
        document.removeEventListener('pointerdown', enterOnce, true);
        document.removeEventListener('keydown', enterOnce, true);
        enterPdvFullscreen();
    };

    window.__erpPdvKioskArmed = true;
    window.__erpPdvKioskEnterOnce = enterOnce;
    document.addEventListener('pointerdown', enterOnce, true);
    document.addEventListener('keydown', enterOnce, true);
}

function enterPdvFullscreen() {
    if (document.fullscreenElement) {
        lockPdvEscapeKey();

        return Promise.resolve();
    }

    const target = document.documentElement;
    const request = typeof target.requestFullscreen === 'function'
        ? target.requestFullscreen({ navigationUI: 'hide' }).catch(() => {
            // Alguns Chromium/PWA rejeitam navigationUI — tenta sem opções.
            return target.requestFullscreen();
        })
        : (typeof target.webkitRequestFullscreen === 'function'
            ? Promise.resolve(target.webkitRequestFullscreen())
            : null);

    if (! request || typeof request.then !== 'function') {
        lockPdvEscapeKey();

        return Promise.resolve();
    }

    return request.then(lockPdvEscapeKey).catch(() => {
        // Bloqueado pelo navegador: mantém janela normal do PWA.
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
    window.__erpPdvKioskArmed = false;

    if (typeof window.__erpPdvKioskEnterOnce === 'function') {
        document.removeEventListener('pointerdown', window.__erpPdvKioskEnterOnce, true);
        document.removeEventListener('keydown', window.__erpPdvKioskEnterOnce, true);
        window.__erpPdvKioskEnterOnce = null;
    }

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

/** Garante fullscreen no gesto do operador (F2 / clique em Abrir caixa). */
function ensurePdvFullscreenFromUserGesture() {
    if (! document.querySelector('.erp-pdv-page')) {
        return;
    }

    void enterPdvFullscreen();
}

let pdvFiscalProgressTimer = null;
let pdvFiscalProgressWatchdogTimer = null;
let pdvFiscalProgressStepIndex = 0;
let pdvFiscalProgressActive = false;

const PDV_FISCAL_PROGRESS_WATCHDOG_MS = 90000;

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

/**
 * Fluxo normal (sem Caixa Rápido): clique volta o passo do lançamento.
 * Qtde volta do Preço; Código volta de Qtde/Preço.
 * Feito no JS porque o campo do passo inativo é readonly e wire:mousedown não
 * chega ao servidor — mesmo padrão do Enter nas grades.
 */
function bindPdvLaunchStepBack() {
    if (window.__erpPdvLaunchStepBackBound) {
        return;
    }

    window.__erpPdvLaunchStepBackBound = true;

    document.addEventListener('mousedown', (event) => {
        if (event.button !== 0) {
            return;
        }

        const target = event.target;

        if (! (target instanceof Element)) {
            return;
        }

        const qtdInput = document.getElementById('erp-pdv-launch-qtd');
        const qtdBox = qtdInput?.closest('.erp-pdv__total-box');
        const precoBox = document.getElementById('erp-pdv-launch-preco')?.closest('.erp-pdv__total-box');

        if (! qtdBox || ! precoBox) {
            return;
        }

        const passoQtd = qtdBox.classList.contains('erp-pdv__total-box--active');
        const passoPreco = precoBox.classList.contains('erp-pdv__total-box--active');

        // Sem lançamento em andamento: nada a voltar.
        if (! passoQtd && ! passoPreco) {
            return;
        }

        const component = getErpPdvComponent();

        if (! component) {
            return;
        }

        if (target.closest('#erp-pdv-search, .erp-pdv__search-field')) {
            component.call('voltarPdvLaunchPara', 'search');

            return;
        }

        if (passoPreco && qtdBox.contains(target)) {
            event.preventDefault();

            // Libera e foca na hora: o clique já deixa o caret na quantidade.
            qtdInput.removeAttribute('readonly');

            try {
                qtdInput.focus({ preventScroll: true });
            } catch (_) {
                qtdInput.focus();
            }

            qtdInput.select?.();
            component.call('voltarPdvLaunchPara', 'qtd');
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

/**
 * Etapa real reportada pelo servidor (0..4). Sem avanço por timer.
 */
function setPdvFiscalTransmitProgressStep(stepIndex, label) {
    const overlay = getPdvFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    if (! pdvFiscalProgressActive) {
        pdvFiscalProgressActive = true;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');
    }

    const panel = overlay.querySelector('[data-erp-pdv-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-pdv-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-pdv-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-pdv-fiscal-step-bar]');
    const target = Math.max(0, Math.min(steps.length - 1, Number(stepIndex) || 0));

    pdvFiscalProgressStepIndex = target;

    steps.forEach((step, index) => {
        step.classList.toggle('is-done', index < target);
        step.classList.toggle('is-active', index === target);
    });

    const text = (label && String(label).trim()) || (steps[target] ? steps[target].textContent.trim() : '');

    if (statusEl && text) {
        statusEl.textContent = text.endsWith('…') || text.endsWith('...') ? text : `${text}…`;
    }

    if (barEl && steps.length > 0) {
        barEl.style.width = `${Math.min(100, Math.round(((target + 1) / steps.length) * 100))}%`;
    }
}

window.__erpPdvSetFiscalStep = setPdvFiscalTransmitProgressStep;

function applyPdvFiscalProgressStreamPayload(raw) {
    const text = String(raw || '').trim();

    if (! text) {
        return;
    }

    try {
        const data = JSON.parse(text);
        setPdvFiscalTransmitProgressStep(data.step, data.label);
    } catch (_e) {
        // ignore
    }
}

function bindPdvFiscalProgressStreamObserver() {
    const streamEl = document.querySelector('[data-erp-pdv-fiscal-progress-stream]');

    if (! streamEl || window.__erpPdvFiscalStreamObserverBound) {
        return;
    }

    window.__erpPdvFiscalStreamObserverBound = true;

    const observer = new MutationObserver(() => {
        applyPdvFiscalProgressStreamPayload(streamEl.textContent);
    });

    observer.observe(streamEl, {
        childList: true,
        characterData: true,
        subtree: true,
    });
}

function clearPdvFiscalTransmitWatchdog() {
    if (pdvFiscalProgressWatchdogTimer) {
        window.clearTimeout(pdvFiscalProgressWatchdogTimer);
        pdvFiscalProgressWatchdogTimer = null;
    }
}

function stopPdvFiscalTransmitProgress() {
    pdvFiscalProgressActive = false;
    clearPdvFiscalTransmitWatchdog();

    if (pdvFiscalProgressTimer) {
        window.clearInterval(pdvFiscalProgressTimer);
        pdvFiscalProgressTimer = null;
    }
}

function notifyPdvFiscalProgressStuck(message, title = 'NFC-e — atenção') {
    try {
        if (typeof FilamentNotification !== 'undefined') {
            new FilamentNotification()
                .title(title)
                .body(message)
                .warning()
                .persistent()
                .send();

            return;
        }
    } catch (_e) {
        // fallback abaixo
    }

    window.alert(message);
}

function forceHidePdvFiscalTransmitProgressStuck(reason) {
    if (! pdvFiscalProgressActive) {
        const overlay = getPdvFiscalTransmitProgressOverlay();

        if (! overlay?.classList.contains('is-visible')) {
            return;
        }
    }

    hidePdvFiscalTransmitProgress();
    notifyPdvFiscalProgressStuck(
        reason
        || 'A finalização da NFC-e não respondeu a tempo. Confira em Vendas/NFC-e: se a venda não aparecer, tente de novo; se aparecer sem NFC-e, transmita ou use contingência.'
    );
}

function startPdvFiscalTransmitProgress() {
    const overlay = getPdvFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    stopPdvFiscalTransmitProgress();
    bindPdvFiscalProgressStreamObserver();
    pdvFiscalProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetPdvFiscalTransmitProgressUi(overlay);
    setPdvFiscalTransmitProgressStep(0, 'Validando dados da NFC-e');

    // Livewire/SEFAZ podem morrer sem dispatch de hide — não deixar o operador preso.
    pdvFiscalProgressWatchdogTimer = window.setTimeout(() => {
        pdvFiscalProgressWatchdogTimer = null;
        forceHidePdvFiscalTransmitProgressStuck(
            'A transmissão da NFC-e demorou demais ou a conexão caiu. Confira em Vendas/NFC-e: se a venda não aparecer, tente de novo; se aparecer sem NFC-e, transmita ou use contingência.'
        );
    }, PDV_FISCAL_PROGRESS_WATCHDOG_MS);
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

function getPdvRootEl() {
    return document.querySelector('.erp-pdv');
}

function normalizePdvBalancaSettings(raw) {
    if (! raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return null;
    }

    return {
        marca: String(raw.marca || ''),
        port: String(raw.port || ''),
        baudRate: Number(raw.baudRate) || 9600,
        dataBits: Number(raw.dataBits) || 8,
        parity: String(raw.parity || 'None'),
        stopBits: String(raw.stopBits || '1'),
        handshake: String(raw.handshake || 'None'),
    };
}

function resolvePdvScaleSettingsFromEvent(payload) {
    if (! payload) {
        return null;
    }

    if (payload.settings && typeof payload.settings === 'object') {
        return normalizePdvBalancaSettings(payload.settings);
    }

    if (Array.isArray(payload)) {
        const first = payload[0];

        if (first?.settings && typeof first.settings === 'object') {
            return normalizePdvBalancaSettings(first.settings);
        }

        if (first?.port) {
            return normalizePdvBalancaSettings(first);
        }
    }

    if (payload.port) {
        return normalizePdvBalancaSettings(payload);
    }

    return null;
}

function getPdvBalancaSettings() {
    const raw = getPdvRootEl()?.dataset?.balancaSettings;

    if (! raw) {
        return null;
    }

    try {
        return normalizePdvBalancaSettings(JSON.parse(raw));
    } catch (error) {
        return null;
    }
}

function setPdvScaleReadingStatus(message) {
    const legend = document.querySelector('.erp-pdv__search-legend');
    const root = getPdvRootEl();

    if (root) {
        root.classList.toggle('is-reading-scale', !! message);
    }

    if (! legend) {
        return;
    }

    if (message) {
        if (legend.dataset.erpScaleLegend == null) {
            legend.dataset.erpScaleLegend = legend.textContent || 'Código:';
        }

        legend.textContent = message;
        legend.classList.add('erp-pdv__search-legend--scale');

        return;
    }

    if (legend.dataset.erpScaleLegend != null) {
        legend.textContent = legend.dataset.erpScaleLegend;
        delete legend.dataset.erpScaleLegend;
    }

    legend.classList.remove('erp-pdv__search-legend--scale');
}

function waitPdvMs(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function findPdvLivewireComponentWithRetry(attempts = 6, delayMs = 50) {
    for (let i = 0; i < attempts; i += 1) {
        const component = findPdvLivewireComponent();

        if (component) {
            return component;
        }

        await waitPdvMs(delayMs);
    }

    return null;
}

function withPdvTimeout(promise, ms, message) {
    let timer = null;

    const timeout = new Promise((_, reject) => {
        timer = window.setTimeout(() => {
            reject(new Error(message || 'Tempo esgotado ao ler a balança.'));
        }, ms);
    });

    return Promise.race([promise, timeout]).finally(() => {
        if (timer != null) {
            window.clearTimeout(timer);
        }
    });
}

async function callPdvScaleFallback(component, message) {
    const text = message || 'Informe a quantidade em kg manualmente.';
    const livewire = (component && typeof component.call === 'function')
        ? component
        : getErpPdvComponent();

    if (livewire && typeof livewire.call === 'function') {
        try {
            await Promise.resolve(livewire.call('beginScaleWeightFallback', text));

            return;
        } catch (error) {
            console.warn('[PDV balança] fallback Livewire falhou:', error);
        }
    }

    window.alert('Não foi possível ler a balança.\n' + text);
}

async function readPdvScaleWeightAndConfirm(settingsFromServer) {
    if (window.__erpPdvScaleReading) {
        return;
    }

    window.__erpPdvScaleReading = true;
    setPdvScaleReadingStatus('Lendo balança…');

    let component = null;

    try {
        component = await findPdvLivewireComponentWithRetry();

        if (! component || typeof component.call !== 'function') {
            component = getErpPdvComponent();
        }

        if (! component || typeof component.call !== 'function') {
            console.warn('[PDV balança] componente Livewire não encontrado.');
            setPdvScaleReadingStatus(null);
            window.alert('Não foi possível ler a balança.\nRecarregue o PDV (Ctrl+F5) e tente de novo.');

            return;
        }

        const fromServer = normalizePdvBalancaSettings(settingsFromServer);
        const settings = (fromServer?.port ? fromServer : null) || getPdvBalancaSettings();

        if (! settings?.port) {
            await callPdvScaleFallback(
                component,
                'Configure a porta COM da balança no terminal e recarregue o PDV (Ctrl+F5).',
            );

            return;
        }

        if (! window.ErpDeviceService?.readScale) {
            await callPdvScaleFallback(component, 'Device Service não está disponível neste navegador.');

            return;
        }

        const ensured = await withPdvTimeout(
            window.ErpDeviceService.ensureLocal(),
            5000,
            'Tempo esgotado ao conectar o Device Service.',
        );

        if (! ensured?.ok) {
            throw new Error('Device Service local não está em execução.');
        }

        if (ensured.started) {
            setPdvScaleReadingStatus('Iniciando balança…');
            await waitPdvMs(1200);
        }

        setPdvScaleReadingStatus(`Lendo ${settings.port}…`);
        const result = await withPdvTimeout(
            window.ErpDeviceService.readScale(settings),
            8000,
            'Tempo esgotado ao ler a balança. Confira a COM e o simulador.',
        );
        const weight = Number(result?.weightKg);

        if (! Number.isFinite(weight) || weight <= 0) {
            throw new Error(result?.message || 'Peso inválido retornado pela balança.');
        }

        setPdvScaleReadingStatus(null);
        const livewire = (component && typeof component.call === 'function')
            ? component
            : getErpPdvComponent();

        if (! livewire || typeof livewire.call !== 'function') {
            throw new Error('Componente Livewire do PDV não disponível. Recarregue (Ctrl+F5).');
        }

        await Promise.resolve(livewire.call('applyScaleWeightAndConfirm', weight));
    } catch (error) {
        setPdvScaleReadingStatus(null);
        await callPdvScaleFallback(
            component,
            error?.message || 'Não foi possível comunicar com a balança.',
        );
    } finally {
        window.__erpPdvScaleReading = false;
        setPdvScaleReadingStatus(null);
    }
}

window.readPdvScaleWeightAndConfirm = readPdvScaleWeightAndConfirm;

function findPdvLivewireComponent() {
    // Mesma resolução do restante do PDV (Livewire.find / wire:id).
    // Livewire.all() pode devolver instâncias internas sem .call().
    return getErpPdvComponent();
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

function restorePdvBuscaAvancadaFocus() {
    const input = document.getElementById('erp-pdv-busca-avancada-search');

    if (! input) {
        return;
    }

    window.requestAnimationFrame(() => {
        const active = document.activeElement;

        if (active === input) {
            return;
        }

        if (active && (active.tagName === 'BUTTON' || active.tagName === 'SELECT' || active.tagName === 'TEXTAREA')) {
            return;
        }

        if (! active || active === document.body || active === document.documentElement || active.id === 'erp-pdv-search') {
            try {
                input.focus({ preventScroll: true });
            } catch (_) {
                input.focus();
            }
        }
    });
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

    if (! statusBar) {
        return;
    }

    if (statusBar.dataset.bound !== '1') {
        statusBar.dataset.bound = '1';

        const clockEl = document.getElementById('erp-pdv-status-clock');

        if (clockEl) {
            tickPdvStatusClock(clockEl);
        }
    }

    if (! erpPdvClockTimer) {
        erpPdvClockTimer = window.setInterval(() => {
            const liveClock = document.getElementById('erp-pdv-status-clock');

            if (liveClock) {
                tickPdvStatusClock(liveClock);
            }
        }, 1000);
    }

    if (! erpPdvStatusBarSync) {
        erpPdvStatusBarSync = (event) => {
            syncPdvLockKeyIndicators(event);
        };

        document.addEventListener('keydown', erpPdvStatusBarSync);
        document.addEventListener('keyup', erpPdvStatusBarSync);
    }

    applyPdvLockKeyIndicatorsFromMemory();
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
        syncPdvCapsLockIndicator(event, capsEl);
    }

    if (numEl) {
        syncPdvNumLockIndicator(event, numEl);
    }
}

function isPdvLockToggleKey(event, name) {
    const key = event?.key ?? '';
    const code = event?.code ?? '';

    return key === name || code === name;
}

function syncPdvCapsLockIndicator(event, capsEl) {
    const isCapsKey = isPdvLockToggleKey(event, 'CapsLock');
    const modState = readPdvModifierState(event, 'CapsLock');

    if (isCapsKey) {
        if (event.type === 'keydown') {
            const next = erpPdvCapsLockOn === null ? ! modState : ! erpPdvCapsLockOn;
            setPdvLockKeyState(capsEl, next);

            return;
        }

        if (event.type === 'keyup') {
            setPdvLockKeyState(capsEl, modState);

            return;
        }
    }

    if (modState) {
        setPdvLockKeyState(capsEl, true);

        return;
    }

    if (erpPdvCapsLockOn === null) {
        setPdvLockKeyState(capsEl, false);
    }
}

function syncPdvNumLockIndicator(event, numEl) {
    const isNumKey = isPdvLockToggleKey(event, 'NumLock');
    const modState = readPdvModifierState(event, 'NumLock');

    if (isNumKey) {
        if (event.type === 'keydown') {
            const next = erpPdvNumLockOn === null ? ! modState : ! erpPdvNumLockOn;
            setPdvLockKeyState(numEl, next);

            return;
        }

        if (event.type === 'keyup') {
            setPdvLockKeyState(numEl, modState);

            return;
        }
    }

    if (modState) {
        setPdvLockKeyState(numEl, true);

        return;
    }

    if (
        ! modState
        && event?.location === KeyboardEvent.DOM_KEY_LOCATION_NUMPAD
        && /^\d$/.test(event.key ?? '')
    ) {
        setPdvLockKeyState(numEl, true);

        return;
    }

    if (erpPdvNumLockOn === null) {
        setPdvLockKeyState(numEl, false);
    }
}

function applyPdvLockKeyIndicatorsFromMemory() {
    const capsEl = document.getElementById('erp-pdv-status-caps');
    const numEl = document.getElementById('erp-pdv-status-num');

    if (capsEl && erpPdvCapsLockOn !== null) {
        setPdvLockKeyState(capsEl, erpPdvCapsLockOn);
    }

    if (numEl && erpPdvNumLockOn !== null) {
        setPdvLockKeyState(numEl, erpPdvNumLockOn);
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

    if (el.id === 'erp-pdv-status-caps') {
        erpPdvCapsLockOn = on;
    } else if (el.id === 'erp-pdv-status-num') {
        erpPdvNumLockOn = on;
    }
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

    // Filament pode pôr wire:id no .erp-pdv-page, no ancestral ou num filho.
    const wireId = page.getAttribute('wire:id')
        ?? page.querySelector('[wire\\:id]')?.getAttribute('wire:id')
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
    // Rebind do capture: SPA Filament pode ficar com listener velho.
    window.removeEventListener('keydown', handlePdvModifierCapture, true);
    document.removeEventListener('keydown', handlePdvModifierCapture, true);
    window.removeEventListener('keyup', handlePdvCtrlLatchKeyup, true);
    document.removeEventListener('keyup', handlePdvCtrlLatchKeyup, true);
    window.removeEventListener('beforeinput', handlePdvDescontoBeforeInput, true);
    document.removeEventListener('beforeinput', handlePdvDescontoBeforeInput, true);
    window.addEventListener('keydown', handlePdvModifierCapture, true);
    document.addEventListener('keydown', handlePdvModifierCapture, true);
    window.addEventListener('keyup', handlePdvCtrlLatchKeyup, true);
    document.addEventListener('keyup', handlePdvCtrlLatchKeyup, true);
    window.addEventListener('beforeinput', handlePdvDescontoBeforeInput, true);
    document.addEventListener('beforeinput', handlePdvDescontoBeforeInput, true);

    if (erpPdvKeysBound) {
        return;
    }

    erpPdvKeysBound = true;

    window.addEventListener('blur', resetPdvCtrlLatch);

    document.addEventListener('keydown', handlePdvKeydown, true);
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

/**
 * Letra do atalho Ctrl (ex.: KeyD → "d"). Preferir code (layout/IME); fallback key.
 *
 * @param {KeyboardEvent} event
 * @returns {string}
 */
function resolvePdvCtrlLetter(event) {
    const code = String(event.code || '');

    if (/^Key[A-Z]$/.test(code)) {
        return code.slice(3).toLowerCase();
    }

    const key = String(event.key || '');

    if (key.length === 1) {
        return key.toLowerCase();
    }

    return '';
}

/**
 * Ctrl/Meta + A/C/V/X = clipboard no input (não bloquear).
 *
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
function isPdvClipboardShortcut(event) {
    if (event.altKey || (! isPdvCtrlPressed(event) && ! event.metaKey)) {
        return false;
    }

    const letter = resolvePdvCtrlLetter(event);

    return ['a', 'c', 'v', 'x'].includes(letter);
}

/**
 * Campos onde letra com modificador não pode virar digitação/pesquisa.
 *
 * @param {EventTarget|null} target
 * @returns {boolean}
 */
function isPdvSearchTypingTarget(target) {
    if (! (target instanceof Element)) {
        return false;
    }

    const id = target.id || '';

    if (id === 'erp-pdv-search'
        || id === 'erp-pdv-busca-avancada-search'
        || id === 'erp-pdv-busca-preco-search'
        || id === 'erp-pdv-remover-itens-search'
        || id === 'erp-pdv-serial-search') {
        return true;
    }

    return target.closest('.erp-pdv__search-field') !== null;
}

/**
 * Resolve o $wire do PDV (ERP ou offline).
 *
 * @param {Element|null} page
 * @returns {object|null}
 */
function resolvePdvWireComponent(page) {
    let component = getErpPdvComponent(page);

    if (! component && window.Livewire?.getByName) {
        const byName = window.Livewire.getByName('app.filament.pages.pdv-page')
            || window.Livewire.getByName('pdv');

        if (byName?.length) {
            component = byName[0];
        }
    }

    if (! component) {
        const pdvRoot = document.querySelector('.erp-pdv');

        if (pdvRoot && window.Alpine) {
            try {
                const root = window.Alpine.findClosest(pdvRoot, (node) => node.__livewire);

                if (root?.__livewire?.$wire) {
                    component = root.__livewire.$wire;
                }
            } catch {
                // ignore
            }
        }
    }

    // Filament: wire:id costuma ficar no ancestral da page class, não no .erp-pdv-page.
    if (! component && page && window.Livewire?.find) {
        const wireEl = page.closest('[wire\\:id]') || document.querySelector('.fi-page[wire\\:id], [wire\\:id].erp-pdv-page');
        const wireId = wireEl?.getAttribute('wire:id');

        if (wireId) {
            component = window.Livewire.find(wireId) || null;
        }
    }

    if (! component && window.Livewire?.all) {
        try {
            const all = window.Livewire.all();

            for (const candidate of all) {
                const name = String(candidate?.name || candidate?.__instance?.name || '');

                if (name.includes('pdv-page') || name === 'pdv') {
                    component = candidate;
                    break;
                }
            }
        } catch {
            // ignore
        }
    }

    if (! component && window.Livewire?.first) {
        try {
            component = window.Livewire.first();
        } catch {
            // ignore
        }
    }

    return component || null;
}

/**
 * Modal desconto já aberto?
 *
 * @returns {boolean}
 */
function isPdvDescontoModalOpen() {
    return !! document.getElementById('erp-pdv-desconto-title')
        || !! document.querySelector('.erp-pdv-desconto');
}

/**
 * Abre Desconto/Acréscimo — mesmo caminho do menu Opções (wire:click no botão invisível).
 */
function openPdvDescontoFromShortcut() {
    if (isPdvDescontoModalOpen()) {
        return;
    }

    const btn = document.getElementById('erp-pdv-desconto-shortcut-btn');

    if (btn) {
        btn.click();

        return;
    }

    try {
        const list = window.Livewire?.getByName?.('app.filament.pages.pdv-page') || [];

        if (list[0] && typeof list[0].call === 'function') {
            list[0].call('openDescontoItemModal');
        }
    } catch (_) {
        // ignore
    }
}

/**
 * Remove D deixado no Código por atalho Ctrl+D.
 *
 * @param {KeyboardEvent} event
 */
function stripPdvSearchDescontoLetter(event) {
    const el = document.getElementById('erp-pdv-search');

    if (! el) {
        return;
    }

    const letter = event.code === 'KeyD' ? 'D' : '';

    if (! letter) {
        return;
    }

    const v = el.value || '';
    const lower = letter.toLowerCase();

    if (v === letter || v === lower) {
        el.value = '';
    } else if (v.endsWith(letter) || v.endsWith(lower)) {
        el.value = v.slice(0, -1);
    } else {
        return;
    }

    el.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Latch do Control físico (ControlLeft/ControlRight).
 *
 * @param {KeyboardEvent} event
 */
function syncPdvCtrlLatch(event) {
    if (event.code === 'ControlLeft' || event.code === 'ControlRight') {
        pdvCtrlLatch = event.type === 'keydown';
        window.__erpPdvCtrlLatch = pdvCtrlLatch;
    }
}

function resetPdvCtrlLatch() {
    pdvCtrlLatch = false;
    window.__erpPdvCtrlLatch = false;
}

/**
 * @param {KeyboardEvent} event
 */
function handlePdvCtrlLatchKeyup(event) {
    if (! document.querySelector('.erp-pdv')) {
        return;
    }

    syncPdvCtrlLatch(event);
}

/**
 * Ctrl pressionado (inclui latch, getModifierState — CAPS/IME).
 *
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
function isPdvCtrlPressed(event) {
    return !!(event.ctrlKey
        || pdvCtrlLatch
        || (typeof event.getModifierState === 'function' && event.getModifierState('Control')));
}

/**
 * Executa atalho Ctrl+letra no capture (com foco no Código).
 *
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
function tryDispatchPdvCtrlShortcut(event) {
    const pdvRoot = document.querySelector('.erp-pdv');

    if (! pdvRoot) {
        return false;
    }

    if (! isPdvCtrlPressed(event) || event.altKey || event.metaKey) {
        return false;
    }

    if (pdvRoot.querySelector('.erp-pdv-overlay') !== null
        || pdvRoot.querySelector('.erp-pdv-modal') !== null) {
        return false;
    }

    if (pdvRoot.dataset.caixaAberto !== '1') {
        return false;
    }

    const letter = resolvePdvCtrlLetter(event);

    // Desconto: Ctrl+D — btn.click (igual Opções).
    if (event.code === 'KeyD' || letter === 'd') {
        event.preventDefault();
        event.stopImmediatePropagation();
        stripPdvSearchDescontoLetter(event);
        openPdvDescontoFromShortcut();
        window.requestAnimationFrame(() => stripPdvSearchDescontoLetter(event));
        event.__erpPdvCtrlHandled = true;

        return true;
    }

    const callArgs = ERP_PDV_CTRL_SHORTCUTS[letter];

    if (! callArgs) {
        return false;
    }

    if (letter === 't' && pdvRoot.dataset.usaTef !== '1') {
        return false;
    }

    if (['s', 'n', 'e', 'b', 'm'].includes(letter) && pdvRoot.dataset.exibeMesas !== '1') {
        return false;
    }

    const component = getErpPdvComponent();

    if (! component) {
        return false;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    component.call(...callArgs);
    event.__erpPdvCtrlHandled = true;

    return true;
}

/**
 * Capture cedo: Ctrl+letra executa ação aqui (não só preventDefault no Código).
 *
 * @param {KeyboardEvent} event
 */
function handlePdvModifierCapture(event) {
    if (! document.querySelector('.erp-pdv')) {
        return;
    }

    syncPdvCtrlLatch(event);

    if (tryDispatchPdvCtrlShortcut(event)) {
        return;
    }

    const ctrl = isPdvCtrlPressed(event);

    if (! ctrl && ! event.altKey && ! event.metaKey) {
        return;
    }

    // Ctrl+letra sem mapa ou Alt/Meta: não digitar no Código (clipboard liberado).
    if (event.key.length === 1
        && isPdvSearchTypingTarget(event.target)
        && ! isPdvClipboardShortcut(event)
        && (ctrl || event.altKey || event.metaKey)) {
        event.preventDefault();
    }
}

/**
 * Bloqueia insertText d quando Control pressionado (fallback pós-keydown).
 *
 * @param {InputEvent} event
 */
function handlePdvDescontoBeforeInput(event) {
    if (! document.querySelector('.erp-pdv')) {
        return;
    }

    if (event.inputType !== 'insertText' && event.inputType !== 'insertCompositionText') {
        return;
    }

    const data = String(event.data || '').toLowerCase();

    if (data !== 'd') {
        return;
    }

    const ctrl = isPdvCtrlPressed(event);

    if (! ctrl) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
}

function dispatchPdvShortcut(event, component) {
    const pdvRoot = document.querySelector('.erp-pdv');
    const caixaAberto = pdvRoot?.dataset.caixaAberto === '1';

    if (event.__erpPdvCtrlHandled) {
        return true;
    }

    const ctrlDown = isPdvCtrlPressed(event) && ! event.altKey && ! event.metaKey;
    const ctrlLetter = ctrlDown ? resolvePdvCtrlLetter(event) : '';

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
            || (ctrlLetter && ERP_PDV_CTRL_SHORTCUTS[ctrlLetter])) {
            event.preventDefault();
            event.stopPropagation();
        }

        return false;
    }

    if (ctrlLetter) {
        // Ctrl+letra: tratado no capture (tryDispatchPdvCtrlShortcut).
        return false;
    }

    if (event.ctrlKey || event.altKey || event.metaKey) {
        return false;
    }

    if (event.key === 'F12') {
        event.preventDefault();
        focusPdvSearchField();

        return true;
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
    const page = document.querySelector('.erp-pdv-page')
        ?? document.querySelector('.erp-pdv');

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

    // Qualquer tecla no PDV (ex.: F2 Abrir caixa) é gesto válido para esconder a taskbar.
    ensurePdvFullscreenFromUserGesture();

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
        // Esc volta o passo (preço → qtd → código); demais teclas não viram atalho.
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('handlePdvEscape');
        }

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
 * Flush do textarea "Informações Adicionais" antes de F7/F10/atalhos de operação.
 * Sem isso, wire:model deferido perde o texto se o operador finalizar com o foco no campo.
 *
 * @param {HTMLElement|null} [input]
 */
function commitPdvFinalizarInformacoes(input) {
    const el = (input && input.id === 'erp-pdv-finalizar-informacoes')
        ? input
        : document.getElementById('erp-pdv-finalizar-informacoes');

    if (! el) {
        return;
    }

    if (window.ErpMasks && typeof window.ErpMasks.syncLivewire === 'function') {
        delete el.dataset.erpMaskSynced;
        window.ErpMasks.syncLivewire(el, el.value ?? '', false);

        return;
    }

    const component = window.ErpMasks?.getLivewireComponent?.(el)
        ?? (el.closest('[wire\\:id]') && window.Livewire
            ? window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'))
            : null);

    if (component && typeof component.set === 'function') {
        component.set('finalizarForm.informacoes_adicionais', el.value ?? '', false);
    }
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
    commitPdvFinalizarInformacoes(document.activeElement);

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
    const aviso = document.querySelector('.erp-pdv-finalizar-aviso');

    if (aviso) {
        // Ignora o mesmo Enter que acabou de abrir o aviso (evita fechar na hora).
        const openedAt = Number(aviso.getAttribute('data-opened-at') || 0);

        if (! openedAt) {
            aviso.setAttribute('data-opened-at', String(Date.now()));
        }

        const ageMs = Date.now() - Number(aviso.getAttribute('data-opened-at') || Date.now());

        if (event.key === 'Enter' || event.key === 'Escape') {
            if (event.key === 'Enter' && ageMs < 400) {
                event.preventDefault();

                return;
            }

            event.preventDefault();
            component.call('fecharFinalizarAlerta');
        }

        return;
    }

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
            if (! document.getElementById('erp-pdv-carne-btn')) {
                return;
            }

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
        commitPdvFinalizarInformacoes(document.activeElement);

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

    if (document.getElementById('erp-pdv-moedas-title')) {
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('fecharContarMoedas');

            return;
        }

        if (event.key === 'Enter' || event.key === 'F2') {
            event.preventDefault();
            component.call('confirmarContarMoedas');

            return;
        }

        // Não vazar F10/outros atalhos para o PDV enquanto conta moedas.
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

    const imprimirMovimentoCaixa = document.getElementById('erp-pdv-imprimir-movimento-caixa-title');

    if (imprimirMovimentoCaixa) {
        const sim = document.getElementById('erp-pdv-imprimir-movimento-caixa-sim');
        const nao = document.getElementById('erp-pdv-imprimir-movimento-caixa-nao');

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
            component.call('confirmImprimirMovimentoCaixa', document.activeElement === sim);

            return;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirMovimentoCaixa', true);

            return;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirMovimentoCaixa', false);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('confirmImprimirMovimentoCaixa', false);

            return;
        }

        return;
    }

    const imprimirResumoCaixa = document.getElementById('erp-pdv-imprimir-resumo-caixa-title');

    if (imprimirResumoCaixa) {
        const sim = document.getElementById('erp-pdv-imprimir-resumo-caixa-sim');
        const nao = document.getElementById('erp-pdv-imprimir-resumo-caixa-nao');

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
            component.call('confirmImprimirResumoCaixa', document.activeElement === sim);

            return;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirResumoCaixa', true);

            return;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmImprimirResumoCaixa', false);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('confirmImprimirResumoCaixa', false);

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

    // Caixa NÃO usa --form/--small; tratar F2/F10 pelo título, fora de isFormModal.
    const caixaTitle = document.getElementById('erp-pdv-caixa-title');

    if (caixaTitle) {
        const isAbrir = (caixaTitle.textContent || '').includes('Abrir');

        if (event.key === 'F10' && ! isAbrir) {
            event.preventDefault();
            component.call('confirmFecharCaixa');

            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();

            if (isAbrir) {
                component.call('confirmAbrirCaixa');
            } else {
                component.call('imprimirResumoCaixaFechamento');
            }

            return;
        }
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
        const printable = event.key.length === 1 && ! event.ctrlKey && ! event.altKey && ! event.metaKey;

        if (! buscaFocused && printable) {
            event.preventDefault();
            try {
                buscaSearch.focus({ preventScroll: true });
            } catch (_) {
                buscaSearch.focus();
            }

            const start = buscaSearch.selectionStart ?? buscaSearch.value.length;
            const end = buscaSearch.selectionEnd ?? buscaSearch.value.length;
            const inserted = buscaSearch.hasAttribute('data-erp-uppercase')
                ? event.key.toUpperCase()
                : event.key;
            buscaSearch.value = buscaSearch.value.slice(0, start) + inserted + buscaSearch.value.slice(end);
            const caret = start + inserted.length;
            try {
                buscaSearch.setSelectionRange(caret, caret);
            } catch (_) {}
            buscaSearch.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        }

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

    const vendasEsperaSearch = document.getElementById('erp-pdv-vendas-espera-search');

    if (vendasEsperaSearch) {
        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelVendaEmEspera');

            return true;
        }

        if (document.activeElement?.id === 'erp-pdv-vendas-espera-search'
            && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveVendaEsperaSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-vendas-espera-row-');

            return true;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('recuperarVendaEmEspera');

            return true;
        }

        if (event.key === 'Delete') {
            event.preventDefault();
            component.call('requestExcluirVendaEmEspera');

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

        // Blade @readonly + erp-no-browser-hints colocam readonly; se o JS
        // recusar focar por readOnly, o campo fica "ativo" mas não editável.
        if (input && ! input.disabled) {
            input.removeAttribute('readonly');

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
 * Descanso de tela:
 * - Header: letreiro (marquee) se configurado
 * - Painel da foto: carrossel de promoções (7s) se houver itens mostrar_pdv
 * Regra: após 30s SEM atividade e SEM venda em andamento (cupom vazio).
 */
(function initPdvScreensaver() {
    const IDLE_MS = 30000;
    const PROMO_ROTATE_MS = 7000;
    let idleTimer = null;
    let promoTimer = null;
    let idle = false;
    let promoIndex = 0;

    const getHeader = () => document.querySelector('.erp-pdv__header');
    const getSidePanel = () => document.querySelector('.erp-pdv__side-panel');
    const getPdv = () => document.querySelector('.erp-pdv');
    const getPromoRoot = () => document.querySelector('[data-erp-pdv-promo]');

    function vendaEmAndamento() {
        const header = getHeader();
        const side = getSidePanel();

        return header?.dataset.vendaAndamento === '1' || side?.dataset.vendaAndamento === '1';
    }

    function hasMarquee(header) {
        return header?.dataset.marquee === '1' && header.querySelectorAll('.erp-pdv__marquee-text').length > 0;
    }

    function hasPromo() {
        const side = getSidePanel();
        const root = getPromoRoot();

        return side?.dataset.promo === '1' && !! root && root.querySelectorAll('.erp-pdv__promo-slide').length > 0;
    }

    function caixaAberto() {
        const pdv = getPdv();

        return ! pdv || pdv.dataset.caixaAberto === '1';
    }

    function canIdle() {
        return caixaAberto() && ! vendaEmAndamento();
    }

    function stopPromoRotate() {
        if (promoTimer) {
            window.clearInterval(promoTimer);
            promoTimer = null;
        }
    }

    function showPromoSlide(index) {
        const root = getPromoRoot();
        const slides = root?.querySelectorAll('.erp-pdv__promo-slide');

        if (! slides?.length) {
            return;
        }

        promoIndex = ((index % slides.length) + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === promoIndex);
        });
    }

    function startPromoRotate() {
        stopPromoRotate();
        const root = getPromoRoot();
        const slides = root?.querySelectorAll('.erp-pdv__promo-slide');

        if (! slides?.length) {
            return;
        }

        if (slides.length <= 1) {
            showPromoSlide(0);

            return;
        }

        showPromoSlide(promoIndex);
        promoTimer = window.setInterval(() => {
            showPromoSlide(promoIndex + 1);
        }, PROMO_ROTATE_MS);
    }

    function syncMarqueeFontSize() {
        const header = getHeader();

        if (! header || ! hasMarquee(header)) {
            return;
        }

        const title = header.querySelector('.erp-pdv__title:not(.erp-pdv__marquee-text)');
        const marquees = header.querySelectorAll('.erp-pdv__marquee-text');

        if (! title || marquees.length === 0) {
            return;
        }

        const wasHidden = getComputedStyle(title).display === 'none';
        let size = '';
        let lineHeight = '';
        let fontWeight = '';

        if (wasHidden) {
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
        const side = getSidePanel();
        const idleOk = idle && canIdle();

        if (header) {
            if (idleOk && hasMarquee(header)) {
                syncMarqueeFontSize();
                header.classList.add('is-screensaver');
            } else {
                header.classList.remove('is-screensaver', 'is-promo', 'is-marquee');
            }
        }

        if (side) {
            if (idleOk && hasPromo()) {
                side.classList.add('is-promo-idle');
                startPromoRotate();
            } else {
                side.classList.remove('is-promo-idle');
                stopPromoRotate();
            }
        } else {
            stopPromoRotate();
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
                window.Livewire.hook('morph.updated', apply);
            }
        } catch (e) {
            // Hook indisponível: o timer de inatividade ainda funciona.
        }
    });

    document.addEventListener('DOMContentLoaded', resetIdle);
    resetIdle();
})();
