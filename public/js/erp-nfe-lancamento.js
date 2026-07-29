document.addEventListener('DOMContentLoaded', initErpNfeLancamento);
document.addEventListener('livewire:navigated', initErpNfeLancamento);

function bindErpNfeLancamentoLivewireEvents() {
    if (window.__erpNfeLancamentoLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpNfeLancamentoLivewireBound = true;

    window.Livewire.on('erp-nfe-focus-item-codigo', () => {
        focusNfeLancamentoInput('nfe-inclusao-produto');
    });

    window.Livewire.on('erp-nfe-focus-item-produto', () => {
        focusNfeLancamentoInput('nfe-inclusao-produto');
        requestAnimationFrame(positionNfeProdutoLookup);
    });

    window.Livewire.on('erp-nfe-focus-item-cfop', () => {
        focusNfeLancamentoInput('nfe-item-cfop');
    });

    window.Livewire.on('erp-nfe-focus-item-cst', () => {
        focusNfeLancamentoInput('nfe-item-cst');
    });

    window.Livewire.on('erp-nfe-focus-item-preco', () => {
        window.setTimeout(() => focusNfeLancamentoInput('nfe-inclusao-preco'), 40);
    });

    window.Livewire.on('erp-nfe-focus-item-quantidade', () => {
        window.setTimeout(() => focusNfeLancamentoInput('nfe-inclusao-qtd'), 40);
    });

    window.Livewire.on('erp-nfe-focus-item-unidade', () => {
        focusNfeLancamentoInput('nfe-item-unidade');
    });

    window.Livewire.on('erp-nfe-scroll-produto-selection', () => {
        scrollNfeProdutoSelectionIntoView();
        positionNfeProdutoLookup();
    });

    window.Livewire.hook('morph.updated', () => {
        requestAnimationFrame(positionNfeProdutoLookup);
    });

    window.Livewire.on('erp-nfe-focus-fiscal-overlay', () => {
        hideNfeFiscalTransmitProgress();
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-overlay-entendido')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-fiscal-sucesso', () => {
        hideNfeFiscalTransmitProgress();
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-sucesso-imprimir')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-open-danfe', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.ErpNfePrint.openDanfe(url);
        }
    });

    window.Livewire.on('erp-nfe-open-whatsapp', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });

    window.Livewire.on('erp-nfe-focus-fiscal-info', () => {
        hideNfeFiscalTransmitProgress();
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-info-ok')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-whatsapp-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-whatsapp-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-danfe-email-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-danfe-email-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cancel-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cancel-justificativa')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-inutilizar-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-inutilizar-justificativa')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-inutilizar-sucesso', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-inutilizar-sucesso-ok')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-correcao')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-sucesso', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-fiscal-cce-sucesso-imprimir')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-whatsapp-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-whatsapp-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-cce-email-modal', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-cce-email-to')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-import-menu', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-import-menu-numero')?.focus();
        });
    });

    window.Livewire.on('erp-nfe-focus-import-list', () => {
        requestAnimationFrame(() => {
            document.getElementById('erp-nfe-import-numero')?.focus();
        });
    });
}

document.addEventListener('livewire:init', bindErpNfeLancamentoLivewireEvents);
document.addEventListener('DOMContentLoaded', bindErpNfeLancamentoLivewireEvents);

if (window.Livewire) {
    bindErpNfeLancamentoLivewireEvents();
}

function initErpNfeLancamento() {
    bindErpNfeLancamentoKeys();
    bindNfeProdutoLookupFloating();
    bindNfeFiscalTransmitTriggers();
    requestAnimationFrame(positionNfeProdutoLookup);
}

let nfeFiscalProgressTimer = null;
let nfeFiscalProgressStepIndex = 0;
let nfeFiscalProgressActive = false;

function bindNfeFiscalTransmitTriggers() {
    if (window.__erpNfeFiscalTransmitTriggersBound) {
        return;
    }

    window.__erpNfeFiscalTransmitTriggersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[wire\\:click="transmitNfe"], [wire\\:click\\.prevent="transmitNfe"]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfeFiscalTransmitProgress, 30);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F3' || event.defaultPrevented) {
            return;
        }

        if (! document.querySelector('.erp-nfe-lancamento-modal')) {
            return;
        }

        const button = document.querySelector('.erp-nfe-lancamento-modal [wire\\:click="transmitNfe"]');

        if (! button || button.disabled) {
            return;
        }

        window.setTimeout(startNfeFiscalTransmitProgress, 30);
    }, true);
}

function getNfeFiscalTransmitProgressOverlay() {
    return document.querySelector('[data-erp-nfe-fiscal-progress]');
}

function resetNfeFiscalTransmitProgressUi(overlay) {
    if (! overlay) {
        return;
    }

    nfeFiscalProgressStepIndex = 0;

    const panel = overlay.querySelector('[data-erp-nfe-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfe-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfe-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfe-fiscal-step-bar]');

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

function advanceNfeFiscalTransmitProgress(overlay) {
    const panel = overlay.querySelector('[data-erp-nfe-fiscal-progress-panel]') ?? overlay;
    const steps = Array.from(panel.querySelectorAll('[data-erp-nfe-fiscal-step]'));
    const statusEl = panel.querySelector('[data-erp-nfe-fiscal-step-status]');
    const barEl = panel.querySelector('[data-erp-nfe-fiscal-step-bar]');

    if (steps.length === 0) {
        return;
    }

    if (nfeFiscalProgressStepIndex < steps.length - 1) {
        steps[nfeFiscalProgressStepIndex].classList.remove('is-active');
        steps[nfeFiscalProgressStepIndex].classList.add('is-done');
        nfeFiscalProgressStepIndex += 1;
        steps[nfeFiscalProgressStepIndex].classList.add('is-active');
    }

    if (statusEl && steps[nfeFiscalProgressStepIndex]) {
        statusEl.textContent = `${steps[nfeFiscalProgressStepIndex].textContent.trim()}…`;
    }

    if (barEl) {
        const percent = Math.min(100, Math.round(((nfeFiscalProgressStepIndex + 1) / steps.length) * 100));
        barEl.style.width = `${percent}%`;
    }
}

function stopNfeFiscalTransmitProgress() {
    nfeFiscalProgressActive = false;

    if (nfeFiscalProgressTimer) {
        window.clearInterval(nfeFiscalProgressTimer);
        nfeFiscalProgressTimer = null;
    }
}

function startNfeFiscalTransmitProgress() {
    const overlay = getNfeFiscalTransmitProgressOverlay();

    if (! overlay) {
        return;
    }

    stopNfeFiscalTransmitProgress();
    nfeFiscalProgressActive = true;
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-busy', 'true');
    resetNfeFiscalTransmitProgressUi(overlay);

    window.setTimeout(() => {
        if (! nfeFiscalProgressActive) {
            return;
        }

        advanceNfeFiscalTransmitProgress(overlay);
    }, 700);

    nfeFiscalProgressTimer = window.setInterval(() => {
        const currentOverlay = getNfeFiscalTransmitProgressOverlay();

        if (! nfeFiscalProgressActive || ! currentOverlay) {
            stopNfeFiscalTransmitProgress();

            return;
        }

        advanceNfeFiscalTransmitProgress(currentOverlay);
    }, 850);
}

function hideNfeFiscalTransmitProgress() {
    stopNfeFiscalTransmitProgress();

    document.querySelectorAll('.erp-nfe-fiscal-progress').forEach((overlay) => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-busy', 'false');
    });

    resetNfeFiscalTransmitProgressUi(getNfeFiscalTransmitProgressOverlay());
}

let nfeDanfePrintInFlight = false;

window.ErpNfePrint = {
    openDanfe(url) {
        openNfeDanfePrint(url);
    },
};

function openNfeDanfePrint(url) {
    if (! url || nfeDanfePrintInFlight) {
        return;
    }

    nfeDanfePrintInFlight = true;

    document.getElementById('erp-nfe-print-frame')?.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'erp-nfe-print-frame';
    iframe.src = url;
    iframe.title = 'Impressão DANFE NF-e';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = [
        'position: fixed',
        'width: 0',
        'height: 0',
        'border: 0',
        'opacity: 0',
        'pointer-events: none',
        'left: -9999px',
        'top: -9999px',
    ].join(';');

    let cleanedUp = false;

    const cleanup = () => {
        if (cleanedUp) {
            return;
        }

        cleanedUp = true;
        nfeDanfePrintInFlight = false;
        iframe.remove();
        window.removeEventListener('message', onMessage);
    };

    const onMessage = (event) => {
        if (event.source !== iframe.contentWindow) {
            return;
        }

        if (event.data?.type === 'erp-nfe-danfe-print-done') {
            cleanup();
        }
    };

    const fallbackToNewTab = () => {
        cleanup();

        const separator = url.includes('?') ? '&' : '?';
        window.open(`${url}${separator}auto=1`, '_blank', 'noopener');
    };

    const printFrame = () => {
        const frameWindow = iframe.contentWindow;

        if (! frameWindow) {
            fallbackToNewTab();

            return;
        }

        try {
            frameWindow.focus();
            frameWindow.print();
            window.setTimeout(() => {
                nfeDanfePrintInFlight = false;
            }, 2000);
        } catch (error) {
            nfeDanfePrintInFlight = false;
            fallbackToNewTab();
        }
    };

    window.addEventListener('message', onMessage);

    iframe.addEventListener('load', () => {
        window.setTimeout(printFrame, 150);
        window.setTimeout(cleanup, 120000);
    }, { once: true });

    iframe.addEventListener('error', fallbackToNewTab, { once: true });

    document.body.appendChild(iframe);
}

function bindNfeProdutoLookupFloating() {
    if (window.__erpNfeProdutoLookupFloatingBound) {
        return;
    }

    window.__erpNfeProdutoLookupFloatingBound = true;

    window.addEventListener('resize', positionNfeProdutoLookup);
    document.addEventListener('scroll', positionNfeProdutoLookup, true);
}

function getNfeProdutoLookupDropdown() {
    const wrap = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-inclusao__barcode-wrap')
        ?? document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-field');

    if (! wrap) {
        return null;
    }

    return wrap.querySelector('.erp-nfe-produto-lookup-panel')
        ?? wrap.querySelector('.erp-nfe-inclusao__suggest')
        ?? wrap.querySelector('.erp-nfe-produto-lookup--empty');
}

function resetNfeProdutoLookupPosition(dropdown) {
    dropdown.classList.remove('is-floating');
    dropdown.style.position = '';
    dropdown.style.top = '';
    dropdown.style.left = '';
    dropdown.style.width = '';
    dropdown.style.maxHeight = '';
    dropdown.style.zIndex = '';
}

function positionNfeProdutoLookup() {
    const modal = document.querySelector('.erp-nfe-lancamento-modal__window');
    const input = document.getElementById('nfe-inclusao-produto') ?? document.getElementById('nfe-item-produto');
    const dropdown = getNfeProdutoLookupDropdown();

    document.querySelectorAll('.erp-nfe-produto-lookup-panel.is-floating, .erp-nfe-produto-lookup--empty.is-floating')
        .forEach((node) => {
            if (node !== dropdown) {
                resetNfeProdutoLookupPosition(node);
            }
        });

    if (! modal || ! input || ! dropdown) {
        return;
    }

    const rootFont = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const inputRect = input.getBoundingClientRect();
    const modalRect = modal.getBoundingClientRect();
    const gap = 2;
    const width = Math.max(
        26 * rootFont,
        Math.min(
            52 * rootFont,
            modalRect.right - inputRect.left - 8,
            window.innerWidth - inputRect.left - 8,
        ),
    );
    const maxHeight = Math.min(
        22 * rootFont,
        window.innerHeight - inputRect.bottom - gap - 12,
    );

    dropdown.classList.add('is-floating');
    dropdown.style.position = 'fixed';
    dropdown.style.top = `${inputRect.bottom + gap}px`;
    dropdown.style.left = `${inputRect.left}px`;
    dropdown.style.width = `${width}px`;
    dropdown.style.maxHeight = `${Math.max(8 * rootFont, maxHeight)}px`;
    dropdown.style.zIndex = '400';
}

function getErpNfeLancamentoComponent() {
    const modal = document.querySelector('.erp-nfe-lancamento-modal');

    if (! modal) {
        return null;
    }

    const componentEl = modal.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function bindErpNfeLancamentoKeys() {
    if (window.__erpNfeLancamentoKeysBound) {
        return;
    }

    window.__erpNfeLancamentoKeysBound = true;

    document.addEventListener('keydown', handleErpNfeLancamentoKeydown);
}

function handleErpNfeLancamentoKeydown(event) {
    if (! document.querySelector('.erp-nfe-lancamento-modal')) {
        return;
    }

    const component = getErpNfeLancamentoComponent();

    if (! component) {
        return;
    }

    const produtoFocused = document.activeElement?.id === 'nfe-inclusao-produto' || document.activeElement?.id === 'nfe-item-produto';

    if (produtoFocused && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        const lookup = document.querySelector('.erp-nfe-lancamento-modal .erp-nfe-produto-lookup-panel');

        if (! lookup) {
            return;
        }

        event.preventDefault();

        const delta = event.key === 'ArrowDown' ? 1 : -1;

        component.call('moveNfeProdutoSelection', delta);

        return;
    }

    if (event.key !== 'Delete' || ! event.ctrlKey) {
        return;
    }

    if (isNfeEditableTarget(event.target)) {
        return;
    }

    event.preventDefault();
    component.call('deleteNfeSelectedItem');
}

function isNfeEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}

function focusNfeLancamentoInput(id, retries = 10) {
    const input = document.getElementById(id);

    if (input && ! input.disabled && ! input.readOnly) {
        input.focus();

        if (typeof input.select === 'function') {
            input.select();
        }

        return;
    }

    if (retries <= 0) {
        return;
    }

    requestAnimationFrame(() => {
        focusNfeLancamentoInput(id, retries - 1);
    });
}

function scrollNfeProdutoSelectionIntoView() {
    window.requestAnimationFrame(() => {
        document.querySelector('.erp-nfe-produto-lookup-panel__list .erp-nfe-produto-lookup__row--active')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
}

bindErpNfeLancamentoLivewireEvents();
initErpNfeLancamento();

