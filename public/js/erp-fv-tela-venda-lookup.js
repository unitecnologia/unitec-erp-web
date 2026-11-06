/**
 * Tela de Venda — pesquisa rápida de produtos (GET + DOM).
 * Não hidrata o Livewire a cada tecla; setas/Enter na lista são locais.
 */
(function () {
    'use strict';

    let lookupTimer = null;
    let lookupAbort = null;
    let lookupSeq = 0;
    let lookupRows = [];
    let selectedIndex = 0;

    function barcodeInput() {
        return document.getElementById('fv-tv-barcode');
    }

    function barcodeWrap() {
        return document.querySelector('.erp-fv-tv__barcode-wrap[data-fv-produto-lookup-url], .erp-fv-tv__barcode-wrap');
    }

    function lookupRoot() {
        return document.getElementById('fv-tv-produto-lookup-root');
    }

    function lookupUrl() {
        const wrap = barcodeWrap();
        const input = barcodeInput();

        return wrap?.getAttribute('data-fv-produto-lookup-url')
            || input?.getAttribute('data-fv-produto-lookup-url')
            || '/admin/erp/forca-vendas/produtos-lookup';
    }

    function lookupContext() {
        const wrap = barcodeWrap();

        return {
            vendedorId: wrap?.getAttribute('data-fv-vendedor-id') || '',
            estoqueId: wrap?.getAttribute('data-fv-estoque-id') || '',
            tabelaPrecoId: wrap?.getAttribute('data-fv-tabela-preco-id') || '',
        };
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function closeProdutoLookup() {
        const root = lookupRoot();
        const input = barcodeInput();

        if (root) {
            root.innerHTML = '';
        }

        lookupRows = [];
        selectedIndex = 0;

        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
    }

    function suggestButtons() {
        return Array.from(document.querySelectorAll('#fv-tv-produto-sugestoes [role="option"]'));
    }

    function markSelected(index) {
        const buttons = suggestButtons();

        if (! buttons.length) {
            return;
        }

        selectedIndex = Math.max(0, Math.min(buttons.length - 1, index));

        buttons.forEach((btn, i) => {
            const on = i === selectedIndex;
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        buttons[selectedIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function renderProdutoLookup(rows) {
        const root = lookupRoot();
        const input = barcodeInput();

        if (! root) {
            return;
        }

        if (! rows.length) {
            root.innerHTML = '';
            if (input) {
                input.setAttribute('aria-expanded', 'false');
            }

            return;
        }

        const items = rows.map((row, index) => {
            const selected = index === 0 ? ' is-selected' : '';
            const promo = row.em_promocao
                ? '<span class="erp-promo-p" title="Promoção">P</span>'
                : '';

            return '<li role="presentation">'
                + '<button type="button" id="fv-tv-produto-sug-' + index + '"'
                + ' role="option" data-id="' + escapeHtml(row.id) + '"'
                + ' data-fv-prod-index="' + index + '"'
                + ' data-nome="' + escapeHtml(row.nome) + '"'
                + ' data-preco="' + escapeHtml(row.preco) + '"'
                + ' data-usa-tab="' + (row.usa_tab_preco ? '1' : '0') + '"'
                + ' aria-selected="' + (index === 0 ? 'true' : 'false') + '"'
                + ' class="' + selected.trim() + '">'
                + '<span class="erp-fv-tv__suggest-code">' + escapeHtml(row.codigo) + '</span>'
                + '<span class="erp-fv-tv__suggest-nome">' + escapeHtml(row.nome) + '</span>'
                + '<span class="erp-fv-tv__suggest-estoques">'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--atual">Atual ' + escapeHtml(row.atual) + '</span>'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--reservado">Res ' + escapeHtml(row.reservado) + '</span>'
                + '<span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--disponivel">Disp ' + escapeHtml(row.disponivel) + '</span>'
                + '</span>'
                + '<span class="erp-fv-tv__suggest-preco"><span>R$</span><span>' + escapeHtml(row.preco) + '</span>' + promo + '</span>'
                + '</button></li>';
        }).join('');

        root.innerHTML = '<ul id="fv-tv-produto-sugestoes"'
            + ' class="erp-fv-tv__suggest erp-fv-tv__suggest--produto"'
            + ' role="listbox" aria-label="Produtos encontrados">'
            + items
            + '</ul>';

        selectedIndex = 0;

        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
    }

    function scheduleProdutoLookup(value, delayMs) {
        const term = String(value || '').trim();

        window.clearTimeout(lookupTimer);

        if (lookupAbort) {
            lookupAbort.abort();
            lookupAbort = null;
        }

        if (term === '') {
            lookupRows = [];
            closeProdutoLookup();

            return;
        }

        lookupTimer = window.setTimeout(() => {
            fetchProdutoLookup(term);
        }, delayMs === undefined ? 180 : delayMs);
    }

    function fetchProdutoLookup(term) {
        const seq = ++lookupSeq;
        const ctx = lookupContext();
        const params = new URLSearchParams();
        params.set('q', term);

        if (ctx.vendedorId) {
            params.set('vendedor_id', ctx.vendedorId);
        }
        if (ctx.estoqueId) {
            params.set('estoque_id', ctx.estoqueId);
        }
        if (ctx.tabelaPrecoId) {
            params.set('tabela_preco_id', ctx.tabelaPrecoId);
        }

        lookupAbort = new AbortController();

        fetch(lookupUrl() + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: lookupAbort.signal,
        }).then((response) => {
            if (! response.ok) {
                throw new Error('fv-lookup ' + response.status);
            }

            return response.json();
        }).then((payload) => {
            if (seq !== lookupSeq) {
                return;
            }

            const input = barcodeInput();
            const typed = String(input?.value || '').trim();

            if (typed !== String(payload.term || '').trim()) {
                return;
            }

            lookupRows = Array.isArray(payload.results) ? payload.results : [];
            renderProdutoLookup(lookupRows);
        }).catch((error) => {
            if (error?.name === 'AbortError') {
                return;
            }
        });
    }

    function livewireComponent() {
        const root = document.querySelector('.erp-fv-tela-venda-page, .erp-fv-tv-root');
        const el = root?.closest('[wire\\:id]') || document.querySelector('[wire\\:id].fi-page, [wire\\:id]');

        if (! el || ! window.Livewire) {
            return null;
        }

        try {
            return window.Livewire.find(el.getAttribute('wire:id'));
        } catch (e) {
            return null;
        }
    }

    function findLivewireNearBarcode() {
        const input = barcodeInput();
        const host = input?.closest('[wire\\:id]');

        if (host && window.Livewire) {
            try {
                return window.Livewire.find(host.getAttribute('wire:id'));
            } catch (e) {
                // fall through
            }
        }

        return livewireComponent();
    }

    function selecionarProdutoId(id, options) {
        const component = findLivewireNearBarcode();
        const opts = options || {};

        if (! component || ! id) {
            return;
        }

        closeProdutoLookup();

        // Foco imediato na Qtde (antes da resposta) — igual OS; Tab. Preço abre modal.
        if (opts.usaTab !== true) {
            window.erpFvTvBeginItemBarLocal?.({
                nome: opts.nome || '',
                preco: opts.preco || '',
            });
        }

        component.call('selecionarProduto', Number(id));
    }

    function confirmarCodigoProduto(typed) {
        const component = findLivewireNearBarcode();

        if (! component) {
            return;
        }

        closeProdutoLookup();
        component.call('confirmarCodigoProduto', String(typed || ''));
    }

    function confirmBarcodeEnter() {
        const input = barcodeInput();
        const typed = String(input?.value || '').trim();
        const buttons = suggestButtons();

        if (buttons.length > 0) {
            const btn = buttons[selectedIndex] || buttons[0];
            const id = Number(btn?.getAttribute('data-id') || 0);

            if (id > 0) {
                selecionarProdutoId(id, {
                    nome: btn.getAttribute('data-nome') || '',
                    preco: btn.getAttribute('data-preco') || '',
                    usaTab: btn.getAttribute('data-usa-tab') === '1',
                });

                return;
            }
        }

        // Enter com código/EAN direto (lista ainda não carregou ou match exato).
        confirmarCodigoProduto(typed);
    }

    function syncBarcodeFromWire(value) {
        const input = barcodeInput();

        if (! input) {
            return;
        }

        input.value = value == null ? '' : String(value);
        closeProdutoLookup();
    }

    function onBarcodeInput(event) {
        if (event.target?.id !== 'fv-tv-barcode') {
            return;
        }

        scheduleProdutoLookup(event.target.value);
    }

    function onBarcodeKeydown(event) {
        if (event.target?.id !== 'fv-tv-barcode') {
            return;
        }

        const key = event.key;
        const open = suggestButtons().length > 0;

        if (key === 'ArrowDown' || key === 'ArrowUp') {
            if (! open) {
                return;
            }

            event.preventDefault();
            markSelected(selectedIndex + (key === 'ArrowDown' ? 1 : -1));

            return;
        }

        if (key === 'Escape') {
            if (open) {
                event.preventDefault();
                closeProdutoLookup();
            }

            return;
        }

        if (key === 'Enter') {
            // Evita seleção + avanço na Qtde no mesmo Enter segurado (keydown repeat).
            if (event.repeat) {
                event.preventDefault();

                return;
            }

            event.preventDefault();
            confirmBarcodeEnter();
        }
    }

    function onLookupClick(event) {
        const btn = event.target?.closest?.('#fv-tv-produto-sugestoes [role="option"]');

        if (! btn) {
            return;
        }

        event.preventDefault();
        const id = Number(btn.getAttribute('data-id') || 0);

        if (id > 0) {
            selecionarProdutoId(id, {
                nome: btn.getAttribute('data-nome') || '',
                preco: btn.getAttribute('data-preco') || '',
                usaTab: btn.getAttribute('data-usa-tab') === '1',
            });
        }
    }

    function onLookupMouseEnter(event) {
        const btn = event.target?.closest?.('#fv-tv-produto-sugestoes [role="option"]');

        if (! btn) {
            return;
        }

        const index = Number(btn.getAttribute('data-fv-prod-index') || 0);
        markSelected(index);
    }

    document.addEventListener('input', onBarcodeInput, true);
    document.addEventListener('keydown', onBarcodeKeydown, true);
    document.addEventListener('click', onLookupClick, true);
    document.addEventListener('mouseover', onLookupMouseEnter, true);

    document.addEventListener('fv-tela-venda-sync-barcode', (event) => {
        syncBarcodeFromWire(event.detail?.value ?? event.detail ?? '');
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('fv-tela-venda-produto-lookup-results', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            const term = String(detail?.term || '').trim();
            const results = Array.isArray(detail?.results) ? detail.results : [];
            const input = barcodeInput();
            const typed = String(input?.value || '').trim();

            if (term !== '' && typed !== '' && typed !== term) {
                return;
            }

            lookupRows = results;
            renderProdutoLookup(lookupRows);
        });
    });

    window.erpFvCloseProdutoLookup = closeProdutoLookup;
    window.erpFvScheduleProdutoLookup = scheduleProdutoLookup;
    window.erpFvSyncBarcodeValue = syncBarcodeFromWire;

    /* ------------------------------------------------------------------ */
    /* F4 — pesquisa de cliente no fechamento (GET + DOM, sem remorph).   */
    /* ------------------------------------------------------------------ */

    let cliLookupTimer = null;
    let cliLookupAbort = null;
    let cliLookupSeq = 0;
    let cliLookupRows = [];
    let cliSelectedIndex = 0;

    function finClienteInput() {
        return document.getElementById('erp-fv-fin-cliente');
    }

    function finClienteLookupRoot() {
        return document.getElementById('erp-fv-fin-cliente-lookup-root');
    }

    function finClienteLookupUrl() {
        const input = finClienteInput();
        const wrap = input?.closest('[data-fv-cliente-lookup-url]');

        return wrap?.getAttribute('data-fv-cliente-lookup-url')
            || input?.getAttribute('data-fv-cliente-lookup-url')
            || '/admin/erp/forca-vendas/clientes-lookup';
    }

    function closeFinClienteLookup() {
        const root = finClienteLookupRoot();
        const input = finClienteInput();

        if (root) {
            root.innerHTML = '';
        }

        cliLookupRows = [];
        cliSelectedIndex = 0;

        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
    }

    function finClienteSuggestButtons() {
        return Array.from(document.querySelectorAll('#erp-fv-fin-cliente-sugestoes [role="option"]'));
    }

    function markFinClienteSelected(index) {
        const buttons = finClienteSuggestButtons();

        if (! buttons.length) {
            return;
        }

        cliSelectedIndex = Math.max(0, Math.min(buttons.length - 1, index));

        buttons.forEach((btn, i) => {
            const on = i === cliSelectedIndex;
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        buttons[cliSelectedIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function renderFinClienteLookup(rows) {
        const root = finClienteLookupRoot();
        const input = finClienteInput();

        if (! root) {
            return;
        }

        if (! rows.length) {
            root.innerHTML = '';
            if (input) {
                input.setAttribute('aria-expanded', 'false');
            }

            return;
        }

        const items = rows.map((row, index) => {
            const selected = index === 0 ? ' is-selected' : '';
            const docTipo = String(row.doc_tipo || '');
            const docClass = docTipo === 'cnpj'
                ? ' is-cnpj'
                : (docTipo === 'cpf' ? ' is-cpf' : (row.cpf_cnpj ? '' : ' is-empty'));
            const vencClass = row.tem_vencidas ? ' is-vencido' : '';

            return '<li role="presentation">'
                + '<button type="button" id="erp-fv-fin-cliente-sug-' + index + '"'
                + ' role="option" data-id="' + escapeHtml(row.id) + '"'
                + ' data-fv-cli-index="' + index + '"'
                + ' aria-selected="' + (index === 0 ? 'true' : 'false') + '"'
                + ' class="' + selected.trim() + '">'
                + '<span class="erp-fv-fin__suggest-code">' + escapeHtml(row.codigo) + '</span>'
                + '<span class="erp-fv-fin__suggest-nome">' + escapeHtml(row.nome) + '</span>'
                + '<span class="erp-fv-fin__suggest-credito">'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--lim">Lim '
                + escapeHtml(row.limite || '0,00') + '</span>'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--util">Util '
                + escapeHtml(row.utilizado || '0,00') + '</span>'
                + '<span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--venc'
                + vencClass + '">Venc ' + escapeHtml(row.vencidas || '0,00') + '</span>'
                + '</span>'
                + '<span class="erp-fv-fin__suggest-doc' + docClass + '">'
                + escapeHtml(row.cpf_cnpj || '') + '</span>'
                + '</button></li>';
        }).join('');

        root.innerHTML = '<ul id="erp-fv-fin-cliente-sugestoes"'
            + ' class="erp-fv-fin__suggest erp-fv-fin__suggest--cliente"'
            + ' role="listbox" aria-label="Clientes encontrados">'
            + items
            + '</ul>';

        cliSelectedIndex = 0;

        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
    }

    function scheduleFinClienteLookup(value, delayMs) {
        const term = String(value || '').trim();
        const input = finClienteInput();
        const committed = String(input?.getAttribute('data-fv-cliente-committed') || '').trim();

        window.clearTimeout(cliLookupTimer);

        if (cliLookupAbort) {
            cliLookupAbort.abort();
            cliLookupAbort = null;
        }

        if (term === '' || term.toUpperCase() === committed.toUpperCase()) {
            cliLookupRows = [];
            closeFinClienteLookup();

            return;
        }

        cliLookupTimer = window.setTimeout(() => {
            fetchFinClienteLookup(term);
        }, delayMs === undefined ? 180 : delayMs);
    }

    function fetchFinClienteLookup(term) {
        const seq = ++cliLookupSeq;
        const params = new URLSearchParams();
        params.set('q', term);

        cliLookupAbort = new AbortController();

        fetch(finClienteLookupUrl() + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: cliLookupAbort.signal,
        }).then((response) => {
            if (! response.ok) {
                throw new Error('fv-cli-lookup ' + response.status);
            }

            return response.json();
        }).then((payload) => {
            if (seq !== cliLookupSeq) {
                return;
            }

            const input = finClienteInput();
            const typed = String(input?.value || '').trim();

            if (typed !== String(payload.term || '').trim()) {
                return;
            }

            cliLookupRows = Array.isArray(payload.results) ? payload.results : [];
            renderFinClienteLookup(cliLookupRows);
        }).catch((error) => {
            if (error?.name === 'AbortError') {
                return;
            }
        });
    }

    function findLivewireNearFinCliente() {
        const input = finClienteInput();
        const host = input?.closest('[wire\\:id]');

        if (host && window.Livewire) {
            try {
                return window.Livewire.find(host.getAttribute('wire:id'));
            } catch (e) {
                // fall through
            }
        }

        return livewireComponent();
    }

    function selecionarFinClienteId(id) {
        const component = findLivewireNearFinCliente();

        if (! component || ! id) {
            return;
        }

        closeFinClienteLookup();
        component.call('selecionarCliente', Number(id));
    }

    function syncFinClienteFromWire(value) {
        const input = finClienteInput();
        const text = value == null ? '' : String(value);

        if (input) {
            input.value = text;
            input.setAttribute('data-fv-cliente-committed', text);
        }

        closeFinClienteLookup();
    }

    function restoreFinClienteCommitted() {
        const input = finClienteInput();

        if (! input) {
            return;
        }

        const committed = String(input.getAttribute('data-fv-cliente-committed') || '');
        const typed = String(input.value || '');

        if (typed.trim() === committed.trim()) {
            closeFinClienteLookup();

            return;
        }

        // Sem seleção válida: volta o cliente já comprometido na venda.
        input.value = committed;
        closeFinClienteLookup();
    }

    function onFinClienteInput(event) {
        if (event.target?.id !== 'erp-fv-fin-cliente') {
            return;
        }

        scheduleFinClienteLookup(event.target.value);
    }

    function onFinClienteKeydown(event) {
        if (event.target?.id !== 'erp-fv-fin-cliente') {
            return;
        }

        const key = event.key;
        const open = finClienteSuggestButtons().length > 0;

        if (key === 'ArrowDown' || key === 'ArrowUp') {
            if (! open) {
                return;
            }

            event.preventDefault();
            markFinClienteSelected(cliSelectedIndex + (key === 'ArrowDown' ? 1 : -1));

            return;
        }

        if (key === 'Escape') {
            if (open) {
                event.preventDefault();
                closeFinClienteLookup();
            }

            restoreFinClienteCommitted();

            return;
        }

        if (key === 'Enter') {
            if (event.repeat) {
                event.preventDefault();

                return;
            }

            event.preventDefault();

            if (open) {
                const btn = finClienteSuggestButtons()[cliSelectedIndex]
                    || finClienteSuggestButtons()[0];
                const id = Number(btn?.getAttribute('data-id') || 0);

                if (id > 0) {
                    selecionarFinClienteId(id);

                    return;
                }
            }

            // Enter sem seleção válida: restaura — não troca cliente por texto livre.
            restoreFinClienteCommitted();
        }
    }

    function onFinClienteLookupClick(event) {
        const btn = event.target?.closest?.('#erp-fv-fin-cliente-sugestoes [role="option"]');

        if (! btn) {
            return;
        }

        event.preventDefault();
        const id = Number(btn.getAttribute('data-id') || 0);

        if (id > 0) {
            selecionarFinClienteId(id);
        }
    }

    function onFinClienteLookupMouseEnter(event) {
        const btn = event.target?.closest?.('#erp-fv-fin-cliente-sugestoes [role="option"]');

        if (! btn) {
            return;
        }

        markFinClienteSelected(Number(btn.getAttribute('data-fv-cli-index') || 0));
    }

    document.addEventListener('input', onFinClienteInput, true);
    document.addEventListener('keydown', onFinClienteKeydown, true);
    document.addEventListener('click', onFinClienteLookupClick, true);
    document.addEventListener('mouseover', onFinClienteLookupMouseEnter, true);

    document.addEventListener('livewire:init', () => {
        Livewire.on('erp-fv-fin-cliente-synced', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            const value = detail && typeof detail === 'object' && 'value' in detail
                ? detail.value
                : detail;
            syncFinClienteFromWire(value);
        });
    });

    window.erpFvFinClienteRestoreCommitted = restoreFinClienteCommitted;
    window.erpFvCloseFinClienteLookup = closeFinClienteLookup;
})();
