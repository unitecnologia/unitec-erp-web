const ERP_OS_FORM_ACTIONS = {
    F2: 'gravarOs',
    F3: 'finalizarOs',
    F4: 'abrirImportarOrcamentoOs',
    F6: 'openPrintModal',
    F8: 'openProdutosCadastro',
    F9: 'openPessoasCadastro',
    Escape: 'handleOsFormEscape',
};

function bootErpOsFormLivewire() {
    if (window.__erpOsFormLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpOsFormLivewireBound = true;

    window.Livewire.on('erp-os-focus-cliente', () => {
        focusOsFormInput('os-cliente', { selectAll: false });
    });

    // Foco do F4: erp-os-faturamento.js (respeita focusEpoch — sem setTimeout que “puxa” a seleção).

    window.Livewire.on('erp-os-lookup-highlight', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
        highlightOsLivewireLookup(data.kind, Number(data.index ?? 0));
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
        // Sem delay artificial — mesmo caminho do 1º Enter no valor.
        queueMicrotask(() => {
            window.erpOsFocusConcluirBtn?.();
        });
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

    window.Livewire.on('erp-os-focus-tab-preco', () => {
        window.setTimeout(() => {
            const panel = document.getElementById('erp-os-tab-preco-panel');
            if (! panel) {
                return;
            }
            const index = Number.parseInt(panel.dataset.selectedIndex || '0', 10) || 0;
            window.erpOsMoveTabPrecoSelection?.(0, index);
        }, 40);
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

    window.Livewire.on('erp-os-item-staged', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
        applyOsItemStaged(data);
    });

    window.Livewire.on('erp-os-item-edit-loaded', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
        applyOsItemEditLoaded(data);
    });

    window.Livewire.on('erp-os-item-bar-cleared', () => {
        clearOsItemBarLocal();
        clearOsItensRowEditing();
    });

    window.Livewire.on('erp-os-item-included', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] ?? payload) : (payload ?? {});
        applyOsItemIncluded(data);
        osItemIncluding = false;
        focusOsFormInput('os-item-descricao', { selectAll: false });
    });

    window.Livewire.on('erp-os-focus-servico-prestado', () => {
        focusOsFormInput('os-servico-prestado-modal-text', { selectAll: false });
    });

    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-os-form-page');

        if (page && window.ErpMasks) {
            window.ErpMasks.init(page);
        }

        applyOsProdutoLookupTabGuard();
        discardStaleOsProdutoLookup();
        restoreOsItensRowSelected();
        osItemIncluding = false;
    });
}

document.addEventListener('livewire:init', bootErpOsFormLivewire);
bootErpOsFormLivewire();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initErpOsForm);
} else {
    initErpOsForm();
}

document.addEventListener('livewire:navigated', initErpOsForm);

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
    bindOsItensGridSelection();
}

function getErpOsComponent() {
    const root = document.querySelector('.erp-os-form-page');

    if (! root || ! window.Livewire?.find) {
        return null;
    }

    const componentEl = (root.hasAttribute('wire:id') ? root : null)
        || root.closest('[wire\\:id]')
        || root.querySelector('[wire\\:id]');

    return componentEl
        ? window.Livewire.find(componentEl.getAttribute('wire:id'))
        : null;
}

function osFinModalVisible() {
    const root = document.querySelector('.erp-os-fin');

    if (! root || ! root.isConnected) {
        return false;
    }

    if (root.getAttribute('data-erp-os-fin-closing') === '1') {
        return false;
    }

    if (root.getAttribute('aria-hidden') === 'true') {
        return false;
    }

    if (root.style.display === 'none') {
        return false;
    }

    return true;
}

function osDialogBlocksMainShortcuts() {
    if (osFinModalVisible() || window.__erpOsFinClosing) {
        return true;
    }

    return !! document.querySelector([
        '.erp-os-import-orc-modal',
        '.erp-orc-print-modal',
        '.erp-os-print-docs',
        '.erp-orc-item-delete-modal',
        '.erp-orc-post-save-modal',
        '#erp-os-tab-preco-panel',
        '.erp-fv-tv-desconto',
        '.erp-os-servico-prestado-modal',
        '.erp-fv-transporte-modal',
        '#erp-os-cadastro-overlays .erp-form-overlay',
        '.erp-os-preview-overlay',
        '.erp-os-confirm-modal',
        '.erp-aviso-modal',
        '.erp-os-nfce-emitir-modal',
        '.erp-os-espelho-modal',
        '#erp-os-email-modal.is-visible',
        '#erp-boleto-pos-doc-pick-title',
    ].join(','));
}

function osFooterButton(key) {
    const button = document.querySelector('.erp-os-form-page .erp-os-actions [data-erp-key="' + key + '"]');

    return button instanceof HTMLButtonElement ? button : null;
}

function osFlushActiveFieldForAction() {
    const el = document.activeElement;

    if (! (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)) {
        return;
    }

    if (! el.closest('.erp-os-form-page')) {
        return;
    }

    if (window.ErpDatepicker && el._flatpickr) {
        window.ErpDatepicker.commitValue(
            el,
            el._flatpickr,
            window.ErpDatepicker.getWireFormat(el),
            false,
        );

        return;
    }

    if (window.ErpMasks && el.dataset.mask && el.dataset.mask !== 'date-br') {
        if (window.ErpMasks.isBrDecimalMask(el.dataset.mask) || el.dataset.mask === 'integer') {
            el.value = window.ErpMasks.finalizeMaskValue(el);
        }

        window.ErpMasks.apply(el, {
            allowEmptySync: true,
            live: false,
            thousands: window.ErpMasks.isBrDecimalMask(el.dataset.mask) === true,
        });

        return;
    }

    if (el instanceof HTMLInputElement && (el.type === 'date' || el.type === 'time' || el.type === 'datetime-local')) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function osReleaseMainActionLock() {
    window.__erpOsActionLock = false;
    window.clearTimeout(window.__erpOsActionLockTimer);
}

function osArmMainActionLock() {
    window.__erpOsActionLock = true;
    window.clearTimeout(window.__erpOsActionLockTimer);
    window.__erpOsActionLockTimer = window.setTimeout(osReleaseMainActionLock, 4000);
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

function bindOsProdutoOverlayFit(iframe) {
    const applyFit = () => {
        let doc;

        try {
            doc = iframe.contentDocument;
        } catch (e) {
            return;
        }

        if (! doc) {
            return;
        }

        const windowEl = doc.querySelector('.erp-produtos-window');

        if (! windowEl) {
            return;
        }

        const measured = Math.ceil(windowEl.getBoundingClientRect().height);
        if (measured < 80) {
            return;
        }

        const maxH = Math.max(120, Math.floor(window.innerHeight - 24));
        const next = Math.min(measured, maxH);
        iframe.style.height = next + 'px';
        iframe.style.flex = '0 0 auto';

        const panel = iframe.closest('.erp-form-overlay__panel--os-produto');
        if (panel) {
            panel.style.height = next + 'px';
        }
    };

    iframe.addEventListener('load', () => {
        applyFit();
        window.setTimeout(applyFit, 50);
        window.setTimeout(applyFit, 200);

        let doc;
        try {
            doc = iframe.contentDocument;
        } catch (e) {
            return;
        }

        const windowEl = doc?.querySelector('.erp-produtos-window');
        if (! windowEl || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(() => applyFit());
        observer.observe(windowEl);
    });
}

function measureOsPessoasWindowHeight(doc) {
    const windowEl = doc.querySelector('.erp-pessoas-window');
    const footer = doc.querySelector('.erp-pcad-form-footer');
    const workspace = doc.querySelector('.erp-pcad__workspace');

    if (! windowEl) {
        return 0;
    }

    const prevWorkspace = workspace
        ? { overflow: workspace.style.overflow, flex: workspace.style.flex, height: workspace.style.height }
        : null;

    if (workspace) {
        workspace.style.overflow = 'visible';
        workspace.style.flex = '0 0 auto';
        workspace.style.height = 'auto';
    }

    const footerBottom = footer ? footer.getBoundingClientRect().bottom : 0;
    const windowBottom = windowEl.getBoundingClientRect().bottom;
    const measured = Math.ceil(Math.max(
        doc.documentElement.scrollHeight || 0,
        doc.body?.scrollHeight || 0,
        footerBottom,
        windowBottom,
    ));

    if (workspace && prevWorkspace) {
        workspace.style.overflow = prevWorkspace.overflow;
        workspace.style.flex = prevWorkspace.flex;
        workspace.style.height = prevWorkspace.height;
    }

    return measured;
}

function osPessoaOverlayAvailableHeight(iframe) {
    const overlay = iframe.closest('.erp-form-overlay');
    const viewport = Math.floor((window.visualViewport?.height || window.innerHeight));

    if (! overlay) {
        return Math.max(160, viewport - 16);
    }

    const style = window.getComputedStyle(overlay);
    const pad = (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);

    return Math.max(160, Math.floor(overlay.clientHeight - pad));
}

function bindOsPessoaOverlayFit(iframe) {
    let fitting = false;

    const applyFit = () => {
        if (fitting) {
            return;
        }

        let doc;

        try {
            doc = iframe.contentDocument;
        } catch (e) {
            return;
        }

        if (! doc) {
            return;
        }

        const windowEl = doc.querySelector('.erp-pessoas-window');

        if (! windowEl) {
            return;
        }

        const measured = measureOsPessoasWindowHeight(doc);

        if (measured < 80) {
            return;
        }

        const available = osPessoaOverlayAvailableHeight(iframe);
        const capped = measured > available;
        const next = capped ? available : measured;
        const current = parseFloat(iframe.style.height) || 0;

        doc.documentElement.classList.toggle('erp-os-overlay-embed--capped', capped);

        if (Math.abs(current - next) < 2 && iframe.style.height) {
            return;
        }

        fitting = true;
        iframe.style.height = next + 'px';
        iframe.style.maxHeight = available + 'px';
        iframe.style.minHeight = '0';
        iframe.style.flex = '0 0 auto';

        const panel = iframe.closest('.erp-form-overlay__panel--os-pessoa');
        if (panel) {
            panel.style.height = next + 'px';
            panel.style.maxHeight = available + 'px';
        }

        window.requestAnimationFrame(() => {
            fitting = false;
        });
    };

    window.addEventListener('resize', applyFit);

    iframe.addEventListener('load', () => {
        applyFit();
        window.setTimeout(applyFit, 50);
        window.setTimeout(applyFit, 200);

        let doc;
        try {
            doc = iframe.contentDocument;
        } catch (e) {
            return;
        }

        const content = doc?.querySelector('.erp-pcad__content');
        const pcad = doc?.querySelector('.erp-pcad');
        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(() => applyFit());
        if (content) {
            observer.observe(content);
        }

        if (pcad && typeof MutationObserver !== 'undefined') {
            const mutations = new MutationObserver(() => applyFit());
            mutations.observe(pcad, { childList: true, subtree: true });
        }
    });
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
    const panelClass = kind === 'product'
        ? 'erp-form-overlay__panel erp-form-overlay__panel--os-produto'
        : (kind === 'person'
            ? 'erp-form-overlay__panel erp-form-overlay__panel--os-pessoa'
            : 'erp-form-overlay__panel');
    host.innerHTML =
        '<div class="erp-form-overlay" role="dialog" aria-modal="true" aria-label="' + title + '">' +
            '<div class="erp-form-overlay__backdrop" data-erp-os-overlay-close="' + closeAction + '"></div>' +
            '<div class="' + panelClass + '">' +
                '<iframe class="erp-form-overlay__iframe" title="' + title + '" data-erp-form-overlay-iframe></iframe>' +
            '</div>' +
        '</div>';

    const iframe = host.querySelector('iframe');

    if (iframe) {
        if (kind === 'product') {
            bindOsProdutoOverlayFit(iframe);
        } else if (kind === 'person') {
            bindOsPessoaOverlayFit(iframe);
        }
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

    if (window.Livewire && ! window.__erpOsProdutoLookupMorphBound) {
        window.__erpOsProdutoLookupMorphBound = true;
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => applyOsProdutoLookupTabGuard());
        });
    }

    if (window.Livewire && ! window.__erpOsMainActionLockBound) {
        window.__erpOsMainActionLockBound = true;
        window.Livewire.hook('commit', ({ component, succeed, fail }) => {
            const release = () => {
                const page = document.querySelector('.erp-os-form-page');
                const id = page?.getAttribute('wire:id')
                    || page?.closest('[wire\\:id]')?.getAttribute('wire:id');

                if (! id || String(component?.id) !== String(id)) {
                    return;
                }

                osItemIncluding = false;
                osReleaseMainActionLock();
            };

            succeed(release);
            fail(release);
        });
    }

    document.addEventListener('click', (event) => {
        if (! osItemIncluding) {
            return;
        }

        const alvo = event.target?.closest?.('[wire\\:click], [wire\\:click\\.prevent]');
        const acao = (alvo?.getAttribute('wire:click') || alvo?.getAttribute('wire:click.prevent') || '');

        if (acao.includes('gravarOs') || acao.includes('finalizarOs')) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    document.addEventListener('input', (event) => {
        if (event.target?.id !== 'os-item-descricao') {
            return;
        }

        osSuppressProdutoLookup = false;
        scheduleOsProdutoLookup(event.target.value);
    }, true);

    document.addEventListener('focusin', (event) => {
        if (event.target?.id !== 'os-item-descricao') {
            return;
        }

        if (osSuppressProdutoLookup || osItemIncluding) {
            return;
        }

        const typed = String(event.target.value || '').trim();

        if (! typed) {
            return;
        }

        scheduleOsProdutoLookup(typed, 0);
    });

    document.addEventListener('mousedown', (event) => {
        if (event.button !== 0) {
            return;
        }

        const suggestBtn = event.target?.closest?.('.erp-os-suggest-btn');

        if (suggestBtn && document.querySelector('.erp-os-form-page')) {
            event.preventDefault();
            const id = Number.parseInt(suggestBtn.getAttribute('data-os-prod-id') || '0', 10);
            if (suggestBtn.getAttribute('data-os-usa-tab') !== '1') {
                beginOsItemBarLocal();
            }
            closeOsProdutoLookupDom();
            if (id > 0) {
                getErpOsComponent()?.call('selectProdutoLookupRow', id);
            }
        }
    }, true);

    document.addEventListener('click', (event) => {
        const itemTabBtn = event.target.closest?.('[data-erp-os-item-tab]');

        if (itemTabBtn && document.querySelector('.erp-os-form-page')) {
            const nextTab = itemTabBtn.getAttribute('data-erp-os-item-tab');
            const currentPecas = osItemTabIsPecas();
            const nextPecas = nextTab === 'pecas';

            if (nextTab && nextPecas !== currentPecas) {
                resetOsProdutoLookupForItemTabChange();
            }

            scheduleOsItemTabSearchFocus();
        }

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
            const fallbackKey = event.key;

            if (
                ERP_OS_FORM_ACTIONS[fallbackKey]
                && ! event.repeat
                && ! event.ctrlKey
                && ! event.altKey
                && ! event.metaKey
                && ! osDialogBlocksMainShortcuts()
            ) {
                event.preventDefault();
                event.stopPropagation();
                const fallbackBtn = osFooterButton(fallbackKey);

                if (fallbackBtn && ! fallbackBtn.disabled && ! window.__erpOsActionLock) {
                    if ((fallbackKey === 'F2' || fallbackKey === 'F3') && osItemIncluding) {
                        return;
                    }

                    if (fallbackKey === 'F2' || fallbackKey === 'F3' || fallbackKey === 'F6') {
                        osArmMainActionLock();
                    }

                    osFlushActiveFieldForAction();
                    fallbackBtn.click();
                }
            } else if (fallbackKey === 'F3' || fallbackKey === 'F6') {
                event.preventDefault();
            }

            return;
        }

        if (event.target?.id === 'os-item-descricao' && handleOsProdutoSuggestKey(event, component)) {
            return;
        }

        if (handleOsItemBarKey(event, component)) {
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

        // Tab. Preço do item (usa_tab_preco) — setas locais + Enter confirma.
        if (document.getElementById('erp-os-tab-preco-panel')) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                event.stopPropagation();
                window.erpOsMoveTabPrecoSelection?.(event.key === 'ArrowDown' ? 1 : -1);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                event.stopPropagation();
                window.erpOsConfirmTabPreco?.();

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                component.call('cancelTabPrecoItem');

                return;
            }

            if (event.key.startsWith('F')) {
                event.preventDefault();
            }

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

        // Esc/Voltar em andamento: não cair no Escape da action bar (sair da OS).
        if (window.__erpOsFinClosing) {
            if (event.key === 'Escape') {
                event.preventDefault();
            }

            return;
        }

        if (osFinModalVisible()) {
            const tecla = event.key || '';
            const alvo = event.target;
            const emAjuste = alvo?.closest?.('.erp-fv-fin__ajuste');
            const emCliente = alvo?.id === 'erp-os-fin-cliente';
            const emCpfNota = alvo?.id === 'erp-os-finalizar-cpf';
            const emInfos = alvo?.id === 'erp-os-finalizar-informacoes';
            const emNotaFooter = emCpfNota || emInfos;
            const emDividir = !! alvo?.closest?.('[data-erp-dividir-conta]');
            const parcelasOpen = document.querySelector('.erp-os-fin .erp-pdv-parcelas-overlay');

            // Modal Transportadora/Volumes sobre o fechamento: Esc fecha só ele (não volta da OS).
            if (document.querySelector('.erp-fv-transporte-modal')) {
                if (tecla === 'Escape') {
                    event.preventDefault();
                    component.call('fecharOsTransporteModal');
                }

                return;
            }

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
                if (document.querySelector('#erp-os-fin-cliente-sugestoes')) {
                    window.erpOsCloseFinClienteLookup?.();
                    window.erpOsFinClienteRestoreCommitted?.();

                    return;
                }
                // Fecha na hora + 1 sync (valores/CPF/infos). Não espera 2 round-trips.
                if (typeof window.erpOsFecharFaturamentoInstantaneo === 'function') {
                    window.erpOsFecharFaturamentoInstantaneo(component);
                } else {
                    // Fallback duro se o JS do F4 não carregou.
                    component.call('voltarDoFaturamentoOs');
                }

                return;
            }

            // Meios / Concluir: Alpine window.capture do modal. Setas: erp-os-faturamento.js.
            // Aqui só Esc / parcelas / transporte — document capture era tarde demais p/ o Valor.

            if (emDividir && (tecla === 'ArrowDown' || tecla === 'ArrowUp')) {
                // Setas ficam no stepper Alpine do campo (não navegam meios).
                return;
            }

            if (emCliente || emNotaFooter || emAjuste || emDividir) {
                return;
            }

            // Fx sem operação neste modal: bloquear (não recarregar / não abrir cadastro).
            // F8 fora do overlay de parcelas NÃO conclui — só tabelas/parcelas usam F8.
            // Concluir (F7/F9/F10) é tratado no Alpine window.capture do modal.
            const atalhoConcluir = (
                document.querySelector('.erp-os-fin [data-erp-atalho-concluir]')?.getAttribute('data-erp-atalho-concluir')
                || 'F10'
            ).toUpperCase();
            if (/^F([1-9]|1[0-2])$/i.test(tecla) && tecla.toUpperCase() !== atalhoConcluir) {
                event.preventDefault();

                return;
            }

            return;
        }

        if (event.key === 'Escape' && document.querySelector('#erp-os-cadastro-overlays .erp-form-overlay')) {
            event.preventDefault();
            event.stopPropagation();
            closeOsCadastroOverlayDom();
            component.call('handleOsFormEscape');

            return;
        }

        if (event.key === 'Escape') {
            const clienteLookup = document.querySelector('.erp-os-cliente-field .erp-orc-cliente-lookup');
            const equipLookup = document.querySelector('.erp-os-equipamento-lookup');

            if (clienteLookup && event.target?.id === 'os-cliente') {
                event.preventDefault();
                event.stopPropagation();
                component.call('closeClienteLookup');

                return;
            }

            if (equipLookup && event.target?.id === 'os-descricao') {
                event.preventDefault();
                event.stopPropagation();
                component.call('closeEquipamentoLookup');

                return;
            }

            if (document.querySelector('.erp-os-preview-overlay')) {
                event.preventDefault();
                event.stopPropagation();
                component.call('closePreviewOverlay');

                return;
            }
        }

        if (osDialogBlocksMainShortcuts()) {
            if (event.key === 'Escape') {
                return;
            }

            if (/^F([1-9]|1[0-2])$/.test(event.key)) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }
        }

        const method = ERP_OS_FORM_ACTIONS[event.key];

        if (method) {
            if (event.repeat || event.ctrlKey || event.altKey || event.metaKey) {
                event.preventDefault();

                return;
            }

            const actionButton = osFooterButton(event.key);

            if (! actionButton || actionButton.disabled) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }

            if ((event.key === 'F2' || event.key === 'F3') && osItemIncluding) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }

            if ((event.key === 'F2' || event.key === 'F3' || event.key === 'F6') && window.__erpOsActionLock) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.key === 'F8' || event.key === 'F9') {
                requestOsCadastroOverlay(event.key === 'F9' ? 'person' : 'product');

                return;
            }

            osFlushActiveFieldForAction();

            if (event.key === 'F2' || event.key === 'F3' || event.key === 'F6') {
                osArmMainActionLock();
            }

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
            const key = osGridSelectedKey
                || document.querySelector('.erp-os-itens__grid-fv tbody tr.is-selected')?.getAttribute('data-os-item-key');

            if (key) {
                component.call('requestDeleteItemByKey', key);
            } else {
                component.call('deleteSelectedItem');
            }

            return;
        }

        if (ctrlD) {
            event.stopPropagation();

            if (document.querySelector('.erp-fv-tv-desconto') || document.querySelector('.erp-form-overlay') || osFinModalVisible()) {
                return;
            }

            component.call('abrirModalDescontoItem');
        }
    }, true);
}

let osSuppressProdutoLookup = false;
let osProdutoLookupRows = [];
let osProdutoLookupTimer = 0;
let osProdutoLookupSeq = 0;
let osProdutoLookupAbort = null;
let osItemIncluding = false;
let osItemBarReady = false;
let osPendingInclude = null;
let osGridSelectedKey = null;

function bindOsItensGridSelection() {
    if (window.__erpOsItensGridSelectBound) {
        return;
    }

    window.__erpOsItensGridSelectBound = true;

    // pointerdown em capture: marca antes de inputs/wire:click.stop e sem Livewire.
    document.addEventListener('pointerdown', (event) => {
        if (event.button !== undefined && event.button !== 0) {
            return;
        }

        const tbody = osItensGridTbody();

        if (! tbody || ! tbody.contains(event.target)) {
            return;
        }

        if (event.target.closest('.erp-os-itens__trash-btn, .erp-os-itens__desc-edit')) {
            return;
        }

        const row = event.target.closest('tbody tr');

        if (! row || ! tbody.contains(row) || row.classList.contains('erp-fv-tv__empty')) {
            return;
        }

        markOsItensRowSelected(row);
    }, true);

    // dblclick em capture: independente de wire:dblclick / linhas clonadas no JS.
    document.addEventListener('dblclick', (event) => {
        if (! document.querySelector('.erp-os-form-page')) {
            return;
        }

        const tbody = osItensGridTbody();

        if (! tbody || ! tbody.contains(event.target)) {
            return;
        }

        if (event.target.closest('.erp-os-itens__trash-btn, .erp-os-itens__desc-edit, input, select, textarea, button')) {
            return;
        }

        const row = event.target.closest('tbody tr');

        if (! row || ! tbody.contains(row) || row.classList.contains('erp-fv-tv__empty')) {
            return;
        }

        const key = row.getAttribute('data-os-item-key') || row.getAttribute('wire:key');

        if (! key) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        markOsItensRowSelected(row);
        getErpOsComponent()?.call('startEditItemByKey', key);
    }, true);
}

function osItensGridTbody() {
    return document.querySelector('.erp-os-form-page .erp-os-itens__grid-fv tbody');
}

function markOsItensRowSelected(row) {
    const tbody = osItensGridTbody();

    if (! tbody || ! row) {
        return;
    }

    tbody.querySelectorAll('tr.is-selected').forEach((tr) => {
        if (tr !== row) {
            tr.classList.remove('is-selected');
        }
    });

    row.classList.add('is-selected');
    osGridSelectedKey = row.getAttribute('data-os-item-key') || row.getAttribute('wire:key') || null;
}

function markOsItensRowEditing(key) {
    const tbody = osItensGridTbody();

    if (! tbody) {
        return;
    }

    tbody.querySelectorAll('tr.is-editing').forEach((tr) => {
        tr.classList.remove('is-editing');
    });

    if (! key) {
        return;
    }

    const rows = tbody.querySelectorAll('tr[data-os-item-key]');

    for (const row of rows) {
        if (row.getAttribute('data-os-item-key') === key) {
            row.classList.add('is-editing');
            markOsItensRowSelected(row);

            return;
        }
    }
}

function clearOsItensRowEditing() {
    const tbody = osItensGridTbody();

    if (! tbody) {
        return;
    }

    tbody.querySelectorAll('tr.is-editing').forEach((tr) => {
        tr.classList.remove('is-editing');
    });

    document.querySelector('.erp-os-form-page .erp-os-produto-bar')
        ?.classList.remove('erp-os-produto-bar--editing');
}

function restoreOsItensRowSelected() {
    const tbody = osItensGridTbody();

    if (! tbody) {
        return;
    }

    if (osGridSelectedKey) {
        const rows = tbody.querySelectorAll('tr[data-os-item-key]');

        for (const row of rows) {
            if (row.getAttribute('data-os-item-key') === osGridSelectedKey) {
                markOsItensRowSelected(row);

                return;
            }
        }
    }

    // Após remorph Livewire (dblclick editar): reaplicar destaque da linha is-editing.
    const editing = tbody.querySelector('tr.is-editing');

    if (editing) {
        markOsItensRowSelected(editing);
    }
}

function osItemTabIsPecas() {
    const active = document.querySelector('.erp-os-item-tabs [data-erp-os-item-tab].erp-os-item-tab--active');

    if (active) {
        return active.getAttribute('data-erp-os-item-tab') === 'pecas';
    }

    return !! document.querySelector('.erp-os-itens .erp-os-itens__grid-fv--pecas');
}

function closeOsProdutoLookupDom(clearInput) {
    const root = document.getElementById('os-produto-lookup-root');

    if (root) {
        root.innerHTML = '';
    }

    document.querySelectorAll(
        '.erp-os-produto-field .erp-fv-tv__suggest--produto, .erp-os-produto-field .erp-orc-produto-lookup'
    ).forEach((el) => el.remove());

    const input = document.getElementById('os-item-descricao');

    if (input) {
        if (clearInput) {
            input.value = '';
        }

        input.setAttribute('aria-expanded', 'false');
    }
}

function resetOsProdutoLookupForItemTabChange() {
    osSuppressProdutoLookup = true;
    osProdutoLookupRows = [];
    window.clearTimeout(osProdutoLookupTimer);
    if (osProdutoLookupAbort) {
        osProdutoLookupAbort.abort();
        osProdutoLookupAbort = null;
    }
    closeOsProdutoLookupDom(true);
}

function osProdutoLookupUrl() {
    const input = document.getElementById('os-item-descricao');

    return input?.getAttribute('data-os-produto-lookup-url') || '/admin/erp/os/produtos-lookup';
}

function scheduleOsProdutoLookup(value, delayMs) {
    const term = String(value || '').trim();

    window.clearTimeout(osProdutoLookupTimer);

    if (osProdutoLookupAbort) {
        osProdutoLookupAbort.abort();
        osProdutoLookupAbort = null;
    }

    if (term === '') {
        osProdutoLookupRows = [];
        closeOsProdutoLookupDom(false);

        return;
    }

    osProdutoLookupTimer = window.setTimeout(() => {
        fetchOsProdutoLookup(term);
    }, delayMs === undefined ? 180 : delayMs);
}

function fetchOsProdutoLookup(term) {
    const seq = ++osProdutoLookupSeq;
    const tab = osItemTabIsPecas() ? 'pecas' : 'servicos';
    const url = osProdutoLookupUrl() + '?q=' + encodeURIComponent(term) + '&tab=' + encodeURIComponent(tab);

    osProdutoLookupAbort = new AbortController();

    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal: osProdutoLookupAbort.signal,
    }).then((response) => {
        if (! response.ok) {
            throw new Error('lookup ' + response.status);
        }

        return response.json();
    }).then((payload) => {
        if (seq !== osProdutoLookupSeq) {
            return;
        }

        const input = document.getElementById('os-item-descricao');
        const typed = String(input?.value || '').trim().toUpperCase();

        if (typed !== String(payload.term || '').trim()) {
            return;
        }

        const currentTab = osItemTabIsPecas() ? 'pecas' : 'servicos';

        if (String(payload.tab || tab) !== currentTab) {
            return;
        }

        osProdutoLookupRows = Array.isArray(payload.results) ? payload.results : [];
        renderOsProdutoLookup(osProdutoLookupRows, payload.term || typed, tab);
    }).catch((error) => {
        if (error?.name === 'AbortError') {
            return;
        }
    });
}

function osEscapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderOsProdutoLookup(rows, term, tab) {
    const root = document.getElementById('os-produto-lookup-root');
    const input = document.getElementById('os-item-descricao');

    if (! root) {
        return;
    }

    if (! rows.length) {
        root.innerHTML = '<div class="erp-orc-produto-lookup erp-orc-produto-lookup--empty">Nenhum produto encontrado.</div>';
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }

        return;
    }

    const pecas = tab === 'pecas';
    const items = rows.map((row, index) => {
        const selected = index === 0 ? ' is-selected' : '';
        const meta = pecas
            ? '<span class="erp-os-suggest-meta">'
                + '<span class="erp-os-suggest-meta__item">EAN ' + osEscapeHtml(row.codigo_barras || '—') + '</span>'
                + '<span class="erp-os-suggest-meta__item">IMEI ' + osEscapeHtml(row.imei_resumo || '—') + '</span>'
                + '</span>'
            : '';
        const estoques = pecas
            ? '<span class="erp-fv-tv__suggest-estoques">'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--atual">Atual ' + osEscapeHtml(row.atual || '0,000') + '</span>'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--reservado">Res ' + osEscapeHtml(row.reservado || '0,000') + '</span>'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--disponivel">Disp ' + osEscapeHtml(row.disponivel || '0,000') + '</span>'
                + '</span>'
            : '';

        return '<li role="presentation"><button type="button" role="option" class="erp-os-suggest-btn' + selected + '"'
            + ' data-os-prod-index="' + index + '"'
            + ' data-os-prod-id="' + osEscapeHtml(row.id) + '"'
            + ' data-os-usa-tab="' + (row.usa_tab_preco ? '1' : '0') + '"'
            + ' aria-selected="' + (index === 0 ? 'true' : 'false') + '">'
            + '<span class="erp-fv-tv__suggest-code">' + osEscapeHtml(row.codigo) + '</span>'
            + '<span class="erp-os-suggest-main">'
            + '<span class="erp-fv-tv__suggest-nome">' + osEscapeHtml(row.descricao) + '</span>'
            + meta
            + '</span>'
            + estoques
            + '<span class="erp-fv-tv__suggest-preco"><span>R$</span><span>' + osEscapeHtml(row.preco || '0,00')
            + (row.em_promocao
                ? '<span class="erp-promo-p" title="Promoção">P</span>'
                : '')
            + '</span></span>'
            + '</button></li>';
    }).join('');

    root.innerHTML = '<ul class="erp-fv-tv__suggest erp-fv-tv__suggest--produto' + (pecas ? ' erp-os-suggest--pecas' : '') + '"'
        + ' data-os-prod-term="' + osEscapeHtml(term) + '"'
        + ' role="listbox" aria-label="Produtos encontrados">'
        + items
        + '</ul>';

    if (input) {
        input.setAttribute('aria-expanded', 'true');
    }
}

let osItemTabFocusToken = 0;

function scheduleOsItemTabSearchFocus() {
    const token = ++osItemTabFocusToken;
    const go = () => {
        if (token !== osItemTabFocusToken) {
            return;
        }

        const input = document.getElementById('os-item-descricao');

        if (! input || input.disabled) {
            return;
        }

        const bar = input.closest('.erp-os-produto-bar, .erp-os-itens');

        if (bar && bar.offsetParent === null) {
            return;
        }

        input.removeAttribute('readonly');
        input.focus({ preventScroll: true });
    };

    requestAnimationFrame(() => {
        requestAnimationFrame(go);
    });
    window.setTimeout(go, 40);
}

function applyOsProdutoLookupTabGuard() {
    if (osSuppressProdutoLookup) {
        closeOsProdutoLookupDom();
    }
}

function osProdutoSuggestButtons() {
    return Array.from(document.querySelectorAll('.erp-os-produto-field .erp-os-suggest-btn'));
}

function osProdutoSuggestOpen() {
    return !! document.querySelector('.erp-os-produto-field .erp-fv-tv__suggest--produto, .erp-os-produto-field .erp-orc-produto-lookup');
}

function osProdutoSelectedIndex() {
    const buttons = osProdutoSuggestButtons();

    if (buttons.length === 0) {
        return null;
    }

    const current = buttons.findIndex((btn) => btn.classList.contains('is-selected'));

    return current >= 0 ? current : 0;
}

function highlightOsProdutoSuggest(index) {
    const buttons = osProdutoSuggestButtons();

    if (buttons.length === 0) {
        return;
    }

    const next = Math.max(0, Math.min(buttons.length - 1, index));

    buttons.forEach((btn, i) => {
        const on = i === next;
        btn.classList.toggle('is-selected', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    buttons[next].scrollIntoView({ block: 'nearest' });
}

function osBarInput(id) {
    return document.getElementById(id);
}

function beginOsItemBarLocal() {
    const qtd = osBarInput('os-item-qtd');
    const preco = osBarInput('os-item-preco');

    if (qtd) {
        qtd.disabled = false;
        qtd.removeAttribute('readonly');
        if (! String(qtd.value || '').trim()) {
            qtd.value = '1,000';
        }
    }

    if (preco) {
        preco.disabled = false;
        preco.removeAttribute('readonly');
    }

    focusOsFormInput('os-item-qtd');
}

function applyOsItemStaged(data) {
    fillOsItemBarLocal(data, { editing: false });
    osItemBarReady = true;
    osItemIncluding = false;

    if (osPendingInclude) {
        const pending = osPendingInclude;
        osPendingInclude = null;
        osItemIncluding = true;
        osItemBarReady = false;
        clearOsItemBarLocal();
        focusOsFormInput('os-item-descricao', { selectAll: false });
        getErpOsComponent()?.call('confirmPendingItemEntry', pending.qtd, pending.preco);
    }
}

function applyOsItemEditLoaded(data) {
    fillOsItemBarLocal(data, { editing: true });
    markOsItensRowEditing(data.key || '');
    osItemBarReady = true;
    osItemIncluding = false;
    osPendingInclude = null;
}

function fillOsItemBarLocal(data, options = {}) {
    const editing = !! options.editing;
    const search = osBarInput('os-item-descricao');
    const qtd = osBarInput('os-item-qtd');
    const preco = osBarInput('os-item-preco');
    const total = osBarInput('os-item-total');
    const descBtn = document.querySelector('.erp-os-form-page .erp-os-produto-bar .erp-fv-tv__btn-desc');
    const bar = document.querySelector('.erp-os-form-page .erp-os-produto-bar');

    if (search && data.nome) {
        search.value = data.nome;
    }

    if (qtd) {
        qtd.disabled = false;
        qtd.removeAttribute('readonly');
        qtd.value = data.qtd || qtd.value || '1,000';
    }

    if (preco) {
        preco.disabled = false;
        preco.removeAttribute('readonly');
        if (data.preco) {
            preco.value = data.preco;
            delete preco.dataset.erpMaskSynced;
            if (window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask)) {
                preco.value = window.ErpMasks.finalizeMaskValue(preco);
            }
            window.ErpMasks?.apply(preco, {
                sync: false,
                thousands: window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask) === true,
            });
        }
    }

    if (total && data.total) {
        total.value = data.total;
    }

    if (descBtn) {
        descBtn.disabled = false;
        descBtn.removeAttribute('disabled');
    }

    if (bar) {
        bar.classList.toggle('erp-os-produto-bar--editing', editing);
    }

    closeOsProdutoLookupDom();
    applyOsItemFoto(data.foto, data.nome);
}

function clearOsItemBarLocal() {
    const search = osBarInput('os-item-descricao');
    const qtd = osBarInput('os-item-qtd');
    const preco = osBarInput('os-item-preco');
    const total = osBarInput('os-item-total');
    const descBtn = document.querySelector('.erp-os-form-page .erp-os-produto-bar .erp-fv-tv__btn-desc');

    if (search) {
        search.value = '';
    }

    if (qtd) {
        qtd.value = '1,000';
        qtd.disabled = true;
    }

    if (preco) {
        preco.value = '';
        preco.disabled = true;
    }

    if (total) {
        total.value = '0,00';
    }

    if (descBtn) {
        descBtn.disabled = true;
    }

    clearOsItensRowEditing();
    closeOsProdutoLookupDom();
}

function applyOsTotais(totais) {
    if (! totais || typeof totais !== 'object') {
        return;
    }

    Object.keys(totais).forEach((key) => {
        const el = document.querySelector('.erp-os-totals [wire\\:model="' + key + '"]');

        if (el) {
            el.value = totais[key];
        }
    });
}

function applyOsItemFoto(foto, nome) {
    const box = document.querySelector('.erp-os-itens .erp-fv-tv__foto');

    if (! box) {
        return;
    }

    let img = box.querySelector('img');
    const empty = box.querySelector('.erp-fv-tv__foto-empty');
    let caption = box.querySelector('.erp-fv-tv__foto-caption');

    if (foto) {
        if (! img) {
            img = document.createElement('img');
            box.insertBefore(img, box.firstChild);
        }

        img.src = foto;
        img.alt = nome || '';
        img.style.display = '';

        if (empty) {
            empty.hidden = true;
        }
    }

    if (nome) {
        if (! caption) {
            caption = document.createElement('p');
            caption.className = 'erp-fv-tv__foto-caption';
            box.appendChild(caption);
        }

        caption.textContent = nome;
    }
}

function applyOsItemIncluded(data) {
    const item = data.item || {};
    applyOsTotais(data.totais);
    prependOsGridRow(item);
    applyOsItemFoto(data.foto, data.nome);
    clearOsItemBarLocal();
}

function prependOsGridRow(item) {
    const tbody = document.querySelector('.erp-os-itens__grid-fv tbody');

    if (! tbody || ! item.key) {
        return;
    }

    const sample = Array.from(tbody.querySelectorAll('tr')).find((row) => ! row.classList.contains('erp-fv-tv__empty'));

    if (! sample) {
        return;
    }

    const clone = sample.cloneNode(true);
    clone.classList.remove('is-editing');
    clone.setAttribute('wire:key', item.key);
    clone.setAttribute('data-os-item-key', item.key);
    clone.setAttribute('x-show', item.tipo === 'S' ? "itemTab === 'servicos'" : "itemTab === 'pecas'");

    const cells = clone.querySelectorAll('.erp-fv-tv__cell, .erp-os-itens__desc-cell');
    const desc = clone.querySelector('.erp-fv-tv__cell--desc');
    const code = clone.querySelector('.erp-fv-tv__col-cod .erp-fv-tv__cell');
    const nums = clone.querySelectorAll('.erp-fv-tv__col-num .erp-fv-tv__money-val, .erp-fv-tv__col-num .erp-fv-tv__cell--num');

    if (code) {
        code.textContent = item.codigo || '';
    }

    if (desc) {
        desc.textContent = item.descricao || '';
        desc.setAttribute('title', item.descricao || '');
    }

    const qtdCell = clone.querySelector('.erp-fv-tv__cell--num');

    if (qtdCell) {
        qtdCell.textContent = item.qtd || '';
    }

    const moneyVals = clone.querySelectorAll('.erp-fv-tv__money-val');

    if (moneyVals[0]) moneyVals[0].textContent = item.preco || '0,00';
    if (moneyVals[1]) moneyVals[1].textContent = item.bruto || '0,00';
    if (moneyVals[2]) moneyVals[2].textContent = item.acrescimo || '0,00';
    if (moneyVals[3]) moneyVals[3].textContent = item.desconto || '0,00';
    if (moneyVals[4]) moneyVals[4].textContent = item.total || '0,00';

    const tecnico = clone.querySelector('.erp-os-itens__cell-tecnico');

    if (tecnico) {
        tecnico.textContent = item.tecnico || '—';
        tecnico.setAttribute('title', item.tecnico || '');
    }

    const concl = clone.querySelector('input[type="datetime-local"]');

    if (concl) {
        concl.value = '';
        concl.setAttribute('wire:key', 'os-item-' + item.key + '-concl');
        concl.setAttribute('@change', "$wire.blurItemFieldByKey('" + item.key + "', 'concluido_em', $event.target.value)");
    }

    const trash = clone.querySelector('.erp-os-itens__trash-btn');
    const descEdit = clone.querySelector('.erp-os-itens__desc-edit');

    // Duplo clique: listener global em bindOsItensGridSelection (capture).

    if (trash) {
        trash.onclick = (event) => {
            event.stopPropagation();
            getErpOsComponent()?.call('requestDeleteItemByKey', item.key);
        };
    }

    if (descEdit) {
        descEdit.onclick = (event) => {
            event.stopPropagation();
            getErpOsComponent()?.call('abrirModalServicoPrestadoByKey', item.key);
        };
    }

    tbody.querySelectorAll('tr.erp-fv-tv__empty').forEach((row) => {
        row.style.display = 'none';
    });

    tbody.insertBefore(clone, tbody.firstElementChild);
    markOsItensRowSelected(clone);

    void cells;
    void nums;
}

function discardStaleOsProdutoLookup() {
    const input = osBarInput('os-item-descricao');
    const list = document.querySelector('.erp-fv-tv__suggest--produto');

    if (! input || ! list) {
        return;
    }

    const term = String(list.getAttribute('data-os-prod-term') || '').trim();
    const typed = String(input.value || '').trim().toUpperCase();

    if (term !== '' && typed !== '' && term !== typed) {
        list.remove();
    }
}

function finalizeOsItemPrecoInput() {
    const preco = osBarInput('os-item-preco');

    if (! preco) {
        return '';
    }

    if (window.ErpMasks && preco.dataset.mask) {
        if (
            window.ErpMasks.isBrDecimalMask(preco.dataset.mask)
            || preco.dataset.mask === 'integer'
        ) {
            preco.value = window.ErpMasks.finalizeMaskValue(preco);
        }

        window.ErpMasks.apply(preco, {
            allowEmptySync: true,
            live: false,
            thousands: window.ErpMasks.isBrDecimalMask(preco.dataset.mask),
        });
    }

    return preco.value || '';
}

function handleOsItemBarKey(event, component) {
    const id = event.target?.id;

    if (event.key !== 'Enter' || (id !== 'os-item-qtd' && id !== 'os-item-preco')) {
        return false;
    }

    event.preventDefault();
    event.stopPropagation();

    if (id === 'os-item-qtd') {
        const preco = osBarInput('os-item-preco');

        if (preco && ! preco.disabled) {
            preco.removeAttribute('readonly');
            focusOsFormInput('os-item-preco');
        }

        return true;
    }

    if (osItemIncluding) {
        return true;
    }

    const qtd = osBarInput('os-item-qtd')?.value || '1,000';
    const preco = finalizeOsItemPrecoInput();

    if (! osItemBarReady) {
        osPendingInclude = { qtd, preco };
        osItemIncluding = true;

        return true;
    }

    osItemIncluding = true;
    osItemBarReady = false;
    clearOsItemBarLocal();
    focusOsFormInput('os-item-descricao', { selectAll: false });
    component.call('confirmPendingItemEntry', qtd, preco);

    return true;
}

function handleOsProdutoSuggestKey(event, component) {
    const key = event.key;

    if (key === 'ArrowDown' || key === 'ArrowUp') {
        const buttons = osProdutoSuggestButtons();

        if (buttons.length === 0) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();
        const current = osProdutoSelectedIndex() ?? 0;
        highlightOsProdutoSuggest(current + (key === 'ArrowDown' ? 1 : -1));

        return true;
    }

    if (key === 'Enter') {
        if (osSuppressProdutoLookup) {
            event.preventDefault();
            event.stopPropagation();

            return true;
        }

        const index = osProdutoSelectedIndex();

        if (index === null) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();
        const selectedBtn = osProdutoSuggestButtons()[index];
        const id = Number.parseInt(selectedBtn?.getAttribute('data-os-prod-id') || '0', 10);
        if (selectedBtn?.getAttribute('data-os-usa-tab') !== '1') {
            beginOsItemBarLocal();
        }
        closeOsProdutoLookupDom();
        if (id > 0) {
            component.call('selectProdutoLookupRow', id);
        } else {
            component.call('selectProdutoResult', index);
        }

        return true;
    }

    if (key === 'Escape' && osProdutoSuggestOpen()) {
        event.preventDefault();
        event.stopPropagation();
        component.call('closeProdutoLookup');

        return true;
    }

    return false;
}

function isOsEditableTarget(target) {
    if (! target || ! target.tagName) {
        return false;
    }

    const tag = target.tagName.toLowerCase();

    return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
}

function highlightOsLivewireLookup(kind, index) {
    if (! Number.isFinite(index) || index < 0) {
        return;
    }

    const applyRows = (root, rowSelector, activeClass, aria) => {
        const el = typeof root === 'string' ? document.querySelector(root) : root;
        if (! el) {
            return;
        }

        const rows = Array.from(el.querySelectorAll(rowSelector));
        rows.forEach((row, i) => {
            const on = i === index;
            row.classList.toggle(activeClass, on);
            if (aria) {
                row.setAttribute('aria-selected', on ? 'true' : 'false');
            }
        });
        rows[index]?.scrollIntoView({ block: 'nearest' });
    };

    const applyByIdPrefix = (prefix, activeClass) => {
        let i = 0;
        let node = document.getElementById(prefix + i);
        while (node) {
            node.classList.toggle(activeClass, i === index);
            i += 1;
            node = document.getElementById(prefix + i);
        }
        document.getElementById(prefix + index)?.scrollIntoView({ block: 'nearest' });
    };

    switch (kind) {
        case 'cliente':
            applyRows('.erp-os-cliente-field .erp-orc-cliente-lookup tbody', 'tr', 'erp-orc-cliente-lookup__row--active', false);
            break;
        case 'produto':
            applyRows('.erp-fv-tv__suggest--produto', '.erp-os-suggest-btn', 'is-selected', true);
            break;
        case 'import':
            applyRows('.erp-os-import-orc-modal tbody', 'tr.erp-nfe-import-list__row', 'erp-nfe-import-list__row--focused', false);
            break;
        case 'pagamento':
            applyByIdPrefix('erp-os-finalizar-row-', 'erp-pdv__grid-row--selected');
            break;
        case 'parcela':
            applyByIdPrefix('erp-os-finalizar-prazo-row-', 'erp-pdv__grid-row--selected');
            break;
        case 'tabela':
            applyByIdPrefix('erp-os-tabela-predef-row-', 'erp-pdv__grid-row--selected');
            break;
        default:
            break;
    }
}

window.erpOsMoveTabPrecoSelection = function (delta, forceIndex) {
    const panel = document.getElementById('erp-os-tab-preco-panel');
    if (! panel) {
        return;
    }

    const rows = Array.from(panel.querySelectorAll('[id^="erp-os-tab-preco-row-"]'));
    if (! rows.length) {
        return;
    }

    let current = Number.parseInt(panel.dataset.selectedIndex || '-1', 10);
    if (! Number.isFinite(current) || current < 0) {
        current = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
    }
    if (current < 0) {
        current = 0;
    }

    const next = forceIndex !== undefined && forceIndex !== null
        ? Math.max(0, Math.min(rows.length - 1, Number(forceIndex)))
        : Math.max(0, Math.min(rows.length - 1, current + Number(delta || 0)));

    panel.dataset.selectedIndex = String(next);
    rows.forEach((row, index) => {
        const on = index === next;
        row.classList.toggle('erp-pdv__grid-row--selected', on);
        row.setAttribute('aria-selected', on ? 'true' : 'false');
        row.tabIndex = on ? 0 : -1;
    });
    rows[next].focus({ preventScroll: true });
    rows[next].scrollIntoView({ block: 'nearest' });
};

window.erpOsConfirmTabPreco = function () {
    const panel = document.getElementById('erp-os-tab-preco-panel');
    const index = panel
        ? Number.parseInt(panel.dataset.selectedIndex || '0', 10)
        : 0;
    const component = getErpOsComponent();

    if (! component) {
        return;
    }

    component.call('confirmTabPrecoItemAt', Number.isFinite(index) ? index : 0);
};

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
