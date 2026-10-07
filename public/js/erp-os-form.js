document.addEventListener('DOMContentLoaded', initErpOsForm);
document.addEventListener('livewire:navigated', initErpOsForm);

const ERP_OS_FORM_ACTIONS = {
    F2: 'gravarOs',
    F3: 'finalizarOs',
    F4: 'abrirImportarOrcamentoOs',
    F6: 'openPrintModal',
    F8: 'openProdutosCadastro',
    F9: 'openPessoasCadastro',
    Escape: 'handleOsFormEscape',
};

document.addEventListener('livewire:init', () => {
    window.Livewire.on('erp-os-focus-cliente', () => {
        focusOsFormInput('os-cliente', { selectAll: false });
    });

    window.Livewire.on('erp-os-focus-finalizar-pagamento', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
        const index = data.index ?? 0;
        const valor = data.valor ?? null;

        const apply = () => {
            const input = document.getElementById(`erp-os-finalizar-valor-${index}`);

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

        requestAnimationFrame(() => requestAnimationFrame(apply));
        window.setTimeout(apply, 50);
        window.setTimeout(apply, 150);
    });

    window.Livewire.on('erp-os-focus-finalizar-parcelas', () => {
        window.setTimeout(() => {
            const qtd = document.getElementById('erp-os-parcelas-qtd');
            qtd?.focus();
            qtd?.select?.();
        }, 50);
    });

    window.Livewire.on('erp-os-focus-finalizar-tabelas-predefinidas', () => {
        window.setTimeout(() => {
            document.querySelector('#erp-os-parcelas-tabelas .erp-pdv__grid-row--selected')
                ?.scrollIntoView({ block: 'nearest' });
        }, 50);
    });

    window.Livewire.on('erp-os-focus-finalizar-faturar', () => {
        window.setTimeout(() => {
            document.getElementById('erp-os-finalizar-op-faturar')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-os-focus-item-descricao', () => {
        focusOsFormInput('os-item-descricao', { selectAll: false });
    });

    window.Livewire.on('erp-os-focus-item-qtd', () => {
        focusOsFormInput('os-item-qtd');
    });

    window.Livewire.on('erp-os-focus-item-preco', () => {
        focusOsFormInput('os-item-preco');
    });

    window.Livewire.on('erp-os-post-save-prompt-opened', () => {
        window.setTimeout(() => {
            document.getElementById('erp-os-post-save-sair')?.focus();
        }, 50);
    });

    window.Livewire.on('erp-os-sync-bar-total', (payload) => {
        const total = payload?.total ?? payload?.[0]?.total ?? payload?.[0];

        if (typeof total !== 'string') {
            return;
        }

        const input = document.getElementById('os-item-total');

        if (input) {
            input.value = total;
        }
    });

    window.Livewire.on('erp-os-focus-servico-prestado', () => {
        focusOsFormInput('os-servico-prestado-modal-text', { selectAll: false });
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-os-form-page');

        if (page && window.ErpMasks) {
            window.ErpMasks.init(page);
        }
    });
});

function initErpOsForm() {
    const page = document.querySelector('.erp-os-form-page');

    if (! page) {
        return;
    }

    if (window.ErpMasks) {
        window.ErpMasks.init(page);
    }

    bindErpOsFormKeys();
    bindOsBarFieldUnlock();
}

function getErpOsComponent() {
    const root = document.querySelector('.erp-os-form-page');

    if (! root) {
        return null;
    }

    const componentEl = root.closest('[wire\\:id]');

    return componentEl
        ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
        : null;
}

function osCadastroOverlayHost() {
    return document.getElementById('erp-os-cadastro-overlays');
}

function closeOsCadastroOverlayDom() {
    const host = osCadastroOverlayHost();

    if (! host) {
        return false;
    }

    const overlay = host.querySelector('.erp-form-overlay');

    if (! overlay) {
        host.dataset.erpOsOverlayBusy = '';

        return false;
    }

    const iframe = overlay.querySelector('iframe');

    if (iframe) {
        iframe.src = 'about:blank';
    }

    host.innerHTML = '';
    host.dataset.erpOsOverlayBusy = '';

    return true;
}

function openOsCadastroOverlay(kind) {
    if (document.querySelector('.erp-os-form-page .erp-form-overlay')) {
        return false;
    }

    const host = osCadastroOverlayHost();

    if (! host || host.dataset.erpOsOverlayBusy === '1') {
        return false;
    }

    const url = kind === 'person' ? host.dataset.personUrl : host.dataset.productUrl;
    const title = kind === 'person' ? 'Cadastro de Clientes' : 'Cadastro de Produtos';
    const closeAction = kind === 'person' ? 'closePersonOverlay' : 'closeProductOverlay';

    if (! url) {
        return false;
    }

    host.dataset.erpOsOverlayBusy = '1';
    host.innerHTML =
        '<div class="erp-form-overlay" role="dialog" aria-modal="true" aria-label="' + title + '">' +
            '<div class="erp-form-overlay__backdrop" data-erp-os-overlay-close="' + closeAction + '"></div>' +
            '<div class="erp-form-overlay__panel">' +
                '<iframe class="erp-form-overlay__iframe" title="' + title + '" data-erp-form-overlay-iframe></iframe>' +
            '</div>' +
        '</div>';

    const iframe = host.querySelector('iframe');

    if (iframe) {
        iframe.src = url;
    }

    return true;
}

function requestOsCadastroOverlay(kind) {
    if (! openOsCadastroOverlay(kind)) {
        return;
    }

    const component = getErpOsComponent();

    if (! component) {
        closeOsCadastroOverlayDom();

        return;
    }

    component.call(kind === 'person' ? 'openPessoasCadastro' : 'openProdutosCadastro');
}

function bindOsBarFieldUnlock() {
    if (window.__erpOsBarFieldUnlockBound) {
        return;
    }

    window.__erpOsBarFieldUnlockBound = true;

    const unlock = (event) => {
        const el = event.target;

        if (! el || ! el.closest?.('.erp-os-produto-bar')) {
            return;
        }

        if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
            el.removeAttribute('readonly');
        }
    };

    document.addEventListener('focusin', unlock, true);
    document.addEventListener('keydown', unlock, true);
    document.addEventListener('mousedown', unlock, true);
}

function bindErpOsFormKeys() {
    if (window.__erpOsFormKeysBound) {
        return;
    }

    window.__erpOsFormKeysBound = true;

    document.addEventListener('click', (event) => {
        const closeTrigger = event.target.closest?.('[data-erp-os-overlay-close]');

        if (closeTrigger && document.querySelector('.erp-os-form-page')) {
            event.preventDefault();
            const closeAction = closeTrigger.getAttribute('data-erp-os-overlay-close');
            closeOsCadastroOverlayDom();
            const component = getErpOsComponent();
            if (component && (closeAction === 'closePersonOverlay' || closeAction === 'closeProductOverlay')) {
                component.call(closeAction);
            }

            return;
        }

        const escapeButton = event.target.closest?.('[wire\\:click="handleOsFormEscape"], .erp-os-window__close');

        if (escapeButton && document.querySelector('#erp-os-cadastro-overlays .erp-form-overlay')) {
            closeOsCadastroOverlayDom();
        }

        const button = event.target.closest?.('[data-erp-os-cadastro]');

        if (! button || ! document.querySelector('.erp-os-form-page')) {
            return;
        }

        event.preventDefault();
        requestOsCadastroOverlay(button.getAttribute('data-erp-os-cadastro') === 'person' ? 'person' : 'product');
    }, true);

    // capture:true — atalho A/B/C chega antes do input Valor (igual erp-pdv.js)
    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-os-form-page')) {
            return;
        }

        const ctrlD = event.ctrlKey && ! event.altKey && ! event.metaKey
            && (event.key === 'd' || event.key === 'D' || event.code === 'KeyD');

        // Chrome abre "Adicionar favorito" no Ctrl+D mesmo com foco em input ou modal aberto.
        if (ctrlD) {
            event.preventDefault();
        }

        const component = getErpOsComponent();

        if (! component) {
            return;
        }

        if (document.querySelector('.erp-os-form-page .erp-os-import-orc-modal')) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                component.call('fecharImportarOrcamentoOs');

                return;
            }

            if (event.key === 'Enter' || event.key === 'F5') {
                const alvo = event.target;
                const digitandoFiltro = alvo instanceof HTMLInputElement
                    && (alvo.id === 'erp-os-import-orc-numero' || alvo.id === 'erp-os-import-orc-cliente');

                if (event.key === 'Enter' && digitandoFiltro) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                component.call('confirmarImportarOrcamentoOs');

                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                component.call('moveOsImportOrcamentoSelection', event.key === 'ArrowDown' ? 1 : -1);

                return;
            }

            if (event.key.startsWith('F')) {
                event.preventDefault();

                return;
            }
        }

        if (event.key === 'Escape' && document.querySelector('.erp-os-form-page .erp-orc-print-modal')) {
            event.preventDefault();
            event.stopPropagation();
            component.call('closePrintModal');

            return;
        }

        if (document.querySelector('.erp-orc-item-delete-modal')) {
            if (event.key === 'Enter') {
                event.preventDefault();
                component.call('confirmDeleteItem');
            }

            return;
        }

        if (document.querySelector('.erp-orc-post-save-modal')) {
            return;
        }

        if (document.querySelector('.erp-fv-tv-desconto')) {
            if (event.key === 'Escape') {
                event.preventDefault();
                component.call('fecharModalDescontoItem');
            }

            return;
        }

        if (document.querySelector('.erp-os-servico-prestado-modal')) {
            if (event.key === 'Escape') {
                event.preventDefault();
                component.call('cancelarModalServicoPrestado');
            }

            return;
        }

        if (document.querySelector('.erp-os-fin')) {
            const tecla = event.key || '';
            const alvo = event.target;
            const emAjuste = alvo?.closest?.('.erp-fv-fin__ajuste');
            const emCliente = alvo?.id === 'erp-os-fin-cliente';
            const parcelasOpen = document.querySelector('.erp-os-fin .erp-pdv-parcelas-overlay');

            if (parcelasOpen) {
                const tabelasOpen = document.querySelector('#erp-os-parcelas-tabelas');

                if (tecla === 'Escape') {
                    event.preventDefault();
                    if (tabelasOpen) {
                        component.call('fecharOsTabelasPrazoPredefinidas');
                    } else {
                        component.call('cancelarOsTabelaPrazoConsulta');
                    }

                    return;
                }

                if (tabelasOpen) {
                    if (tecla === 'ArrowDown' || tecla === 'ArrowUp') {
                        event.preventDefault();
                        component.call('moveOsTabelaPredefinidaSelection', tecla === 'ArrowDown' ? 1 : -1);

                        return;
                    }

                    if (tecla === 'Enter') {
                        event.preventDefault();
                        component.call('aplicarOsTabelaPrazoPredefinida');

                        return;
                    }

                    return;
                }

                if (tecla === 'F2') {
                    event.preventDefault();
                    component.call('gerarOsParcelasCrediario');

                    return;
                }

                if (tecla === 'F3') {
                    event.preventDefault();
                    component.call('excluirOsParcelaCrediario');

                    return;
                }

                if (tecla === 'F4') {
                    event.preventDefault();
                    component.call('cancelarOsTabelaPrazoConsulta');

                    return;
                }

                if (tecla === 'F7' || tecla === 'Enter') {
                    const typing = alvo?.id === 'erp-os-parcelas-qtd' || alvo?.id === 'erp-os-parcelas-intervalo';

                    if (tecla === 'Enter' && typing) {
                        event.preventDefault();
                        component.call('gerarOsParcelasCrediario');

                        return;
                    }

                    event.preventDefault();
                    component.call('concluirOsParcelasCrediario');

                    return;
                }

                if (tecla === 'F8') {
                    event.preventDefault();
                    component.call('abrirOsTabelasPrazoPredefinidas');

                    return;
                }

                if (tecla === 'ArrowDown' || tecla === 'ArrowUp') {
                    event.preventDefault();
                    component.call('moveOsParcelaSelection', tecla === 'ArrowDown' ? 1 : -1);

                    return;
                }

                return;
            }

            if (tecla === 'Escape') {
                event.preventDefault();
                component.call('cancelarFaturamentoOs');

                return;
            }

            if (tecla === 'F2' || tecla === 'F3' || tecla === 'F4' || tecla === 'F5' || tecla === 'F6' || tecla === 'F9') {
                event.preventDefault();

                return;
            }

            if (tecla === 'F8') {
                event.preventDefault();
                component.call('faturarOs');

                return;
            }

            // Atalhos de forma (A/B/C…) — igual PDV: captura mesmo com foco no Valor.
            if (! event.ctrlKey && ! event.altKey && ! event.metaKey && ! emAjuste && ! emCliente) {
                const letra = tecla.length === 1 ? tecla.toUpperCase() : '';

                if (letra.length === 1 && /[A-Z]/.test(letra)) {
                    const atalhos = Array.from(document.querySelectorAll('.erp-os-fin .erp-pdv-finalizar__kbd'))
                        .map((el) => (el.textContent || '').trim().toUpperCase())
                        .filter((v) => v.length === 1);

                    if (atalhos.includes(letra)) {
                        event.preventDefault();
                        event.stopPropagation();
                        component.call('selectOsPagamentoByAtalho', letra);

                        return;
                    }
                }
            }

            return;
        }

        const method = ERP_OS_FORM_ACTIONS[event.key];

        if (method) {
            if (event.key === 'F6') {
                if (document.querySelector('.erp-os-preview-overlay, .erp-orc-print-modal')) {
                    event.preventDefault();

                    return;
                }

                event.preventDefault();
                component.call('openPrintModal');

                return;
            }

            if (event.key === 'F8' || event.key === 'F9') {
                if (document.querySelector('.erp-form-overlay')) {
                    return;
                }

                event.preventDefault();
                requestOsCadastroOverlay(event.key === 'F9' ? 'person' : 'product');

                return;
            }

            if (event.key === 'Escape') {
                closeOsCadastroOverlayDom();
            }

            event.preventDefault();
            component.call(method);

            return;
        }

        if (event.key === 'F11') {
            event.preventDefault();
            const alvo = document.getElementById('os-item-descricao');
            alvo?.removeAttribute('readonly');
            alvo?.removeAttribute('disabled');
            alvo?.focus();
            alvo?.select?.();

            return;
        }

        if (event.ctrlKey && event.key === 'Delete' && ! isOsEditableTarget(event.target)) {
            event.preventDefault();
            component.call('deleteSelectedItem');

            return;
        }

        if (ctrlD) {
            event.stopPropagation();

            if (document.querySelector('.erp-fv-tv-desconto') || document.querySelector('.erp-form-overlay') || document.querySelector('.erp-os-fin')) {
                return;
            }

            component.call('abrirModalDescontoItem');
        }
    }, true);
}

function isOsEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}

function focusOsFormInput(id, options = {}) {
    const run = () => {
        const input = document.getElementById(id);

        if (! input || input.disabled) {
            return false;
        }

        input.removeAttribute('readonly');

        const selectAll = options.selectAll !== false;

        if (document.activeElement !== input) {
            input.focus();
        }

        if (selectAll && typeof input.select === 'function') {
            input.select();
        }

        return true;
    };

    run();
    requestAnimationFrame(run);
    window.setTimeout(run, 50);
    window.setTimeout(run, 150);

    return true;
}

window.addEventListener('message', (event) => {
    if (event.data?.type !== 'erp-orcamento-overlay-close') {
        return;
    }

    if (! document.querySelector('.erp-os-form-page')) {
        return;
    }

    const component = getErpOsComponent();

    if (! component) {
        return;
    }

    const produtoCodigo = event.data.produtoCodigo;

    if (typeof produtoCodigo === 'string' && produtoCodigo.trim() !== '') {
        closeOsCadastroOverlayDom();
        component.call('applyOverlayProdutoSaved', produtoCodigo);

        return;
    }

    const clienteId = Number.parseInt(String(event.data.clienteId ?? ''), 10);

    if (! Number.isNaN(clienteId) && clienteId > 0) {
        closeOsCadastroOverlayDom();
        component.call('applyOverlayPersonSaved', clienteId);

        return;
    }

    closeOsCadastroOverlayDom();
    component.call('closeProductOverlay');
    component.call('closePersonOverlay');
});
