let erpPdvKeysBound = false;
let erpPdvLivewireBound = false;
let erpPdvIdleTimer = null;
let erpPdvIdleBound = false;
let erpPdvClockTimer = null;
let erpPdvStatusBarSync = null;

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
            } else if (payload?.modal === 'finalizar') {
                focusPdvFinalizarPagamento(0);
            } else {
                focusPdvModalField();
            }
        }, 50);
    });

    window.Livewire.on('erp-pdv-caixa-opened', () => {
        focusPdvSearchField();
    });

    window.Livewire.on('erp-pdv-item-added', () => {
        focusPdvSearchField();
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
        focusPdvSearchField();
    });

    window.Livewire.on('erp-pdv-focus-finalizar', () => {
        focusPdvFinalizarPagamento(0);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-pagamento', (payload) => {
        focusPdvFinalizarPagamento(payload?.index ?? 0, payload?.valor ?? null);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-cliente', () => {
        focusPdvFinalizarCliente();
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

    window.Livewire.on('erp-pdv-finalizar-imprimir-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-pdv-finalizar-imprimir-nao')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-pdv-focus-finalizar-operacao', () => {
        window.setTimeout(() => {
            document.querySelector('.erp-pdv-finalizar__operacao-btn')?.focus();
        }, 50);
    });

    window.Livewire.hook('morph.updated', ({ el }) => {
        if (el?.querySelector?.('.erp-pdv__grid--consulta')) {
            scrollPdvSearchSelectionIntoView();
        } else if (el?.querySelector?.('.erp-pdv__grid--cupom') || el?.classList?.contains('erp-pdv__grid-row--selected')) {
            scrollPdvCupomSelectionIntoView();
        }

        if (el?.querySelector?.('.erp-pdv-finalizar__cliente-list')) {
            scrollPdvFinalizarClienteIntoView();
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
            document.getElementById('erp-pdv-vendedor-search')?.focus();
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
            const input = document.getElementById('erp-pdv-remover-itens-qtd');

            if (input) {
                window.ErpMasks?.refresh(input.closest('.erp-pdv-modal') ?? document);
                input.focus();
                input.select();
            }
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

    const page = document.querySelector('.erp-pdv-page');

    if (! page) {
        return;
    }

    if (page.querySelector('.erp-pdv')?.dataset.caixaAberto === '1') {
        focusPdvSearchField();
    }

    resetPdvIdleTimer();
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
}

function dispatchPdvShortcut(event, component) {
    const pdvRoot = document.querySelector('.erp-pdv');

    if (event.key === 'Escape') {
        event.preventDefault();
        component.call('handlePdvEscape');

        return true;
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
    component.call('confirmFinalizarComOperacao', btn.dataset.operacao);

    return true;
}

function handlePdvFinalizarModalKeydown(event, component) {
    const valorFocused = document.activeElement?.id?.startsWith('erp-pdv-finalizar-valor-');
    const cpfFocused = document.activeElement?.id === 'erp-pdv-finalizar-cpf';
    const informacoesFocused = document.activeElement?.id === 'erp-pdv-finalizar-informacoes';
    const clienteFocused = document.activeElement?.id === 'erp-pdv-finalizar-cliente';
    const operacaoFocused = document.activeElement?.classList?.contains('erp-pdv-finalizar__operacao-btn');
    const clienteConsulta = document.querySelector('.erp-pdv-finalizar__cliente-list') !== null;

    if (event.key === 'Enter' && (cpfFocused || informacoesFocused || operacaoFocused)) {
        event.preventDefault();

        return;
    }

    if (clienteConsulta || clienteFocused) {
        if (event.key === 'F2') {
            event.preventDefault();
            component.call('openFinalizarClienteConsulta');

            return;
        }

        if (clienteFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveFinalizarClienteSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvFinalizarClienteIntoView();

            return;
        }

        if (clienteConsulta) {
            return;
        }
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

    const sairModal = document.getElementById('erp-pdv-sair-title');

    if (sairModal) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmSairPdv');
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
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmFinalizarImprimir', false);

            return;
        }

        if (event.key.toLowerCase() === 's' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('confirmFinalizarImprimir', true);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('cancelFinalizarImprimir');

            return;
        }

        return;
    }

    const finalizarSairConfirm = document.getElementById('erp-pdv-finalizar-sair-title');

    if (finalizarSairConfirm) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmCloseFinalizar');

            return;
        }

        if (event.key.toLowerCase() === 'n' && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            event.preventDefault();
            component.call('cancelCloseFinalizar');

            return;
        }

        return;
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

    const removerQtd = document.getElementById('erp-pdv-remover-itens-qtd');

    if (removerQtd) {
        if (event.key === 'Enter') {
            event.preventDefault();
            component.call('confirmRemoverItens');

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
        const consultaFocused = document.activeElement?.id === 'erp-pdv-consulta-venda-search';

        if (consultaFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            component.call('moveConsultaVendaSelection', event.key === 'ArrowDown' ? 1 : -1);
            scrollPdvModalRowIntoView('erp-pdv-consulta-venda-row-');

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
        document.querySelector(`[id^="${prefix}"]`)?.closest('.erp-pdv-vendedor-row--selected, .erp-pdv__grid-row--selected')
            ?.scrollIntoView({ block: 'nearest' });

        document.querySelector('.erp-pdv-modal .erp-pdv__grid-row--selected')?.scrollIntoView({
            block: 'nearest',
        });
    });
}

function focusPdvSearchField() {
    window.setTimeout(() => {
        document.getElementById('erp-pdv-search')?.focus();
    }, 50);
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
    const tryFocus = (attempt = 0) => {
        const id = field === 'preco' ? 'erp-pdv-launch-preco' : 'erp-pdv-launch-qtd';
        const input = document.getElementById(id);

        if (input) {
            input.focus();
            input.select?.();

            if (window.ErpMasks?.refresh) {
                window.ErpMasks.refresh(input.closest('.erp-pdv') ?? document);
            }

            return;
        }

        if (attempt < 6) {
            window.setTimeout(() => tryFocus(attempt + 1), 80);
        }
    };

    window.setTimeout(() => tryFocus(), 50);
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
        abertura.focus();
        abertura.select?.();

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
    openCupom({ url, copias = 1 }) {
        if (! url) {
            return;
        }

        const popup = window.open(url, '_blank');

        if (! popup) {
            window.open(url, '_blank');
        }

        if (copias > 1) {
            window.setTimeout(() => {
                window.open(url.replace('auto=1', 'auto=0'), '_blank');
            }, 800);
        }
    },
};

initErpPdv();
