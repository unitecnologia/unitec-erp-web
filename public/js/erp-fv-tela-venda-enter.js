/**
 * Tela de Venda — Enter/inclusão rápida (padrão OS).
 * Qtde → Vlr. unit. é local; inclusão confirma qtd+preço numa única chamada Livewire.
 */
(function () {
    'use strict';

    let barReady = false;
    let including = false;
    let pendingInclude = null;
    let editingKey = null;

    window.__erpFvTvIncluding = false;
    window.__erpFvTvEditing = false;

    function rootEl() {
        return document.querySelector('.erp-fv-tv-root');
    }

    function barInput(id) {
        return document.getElementById(id);
    }

    function livewireComponent() {
        const root = rootEl();
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

    function focusInput(id, selectAll) {
        const input = barInput(id);

        if (! input || input.disabled) {
            return false;
        }

        input.removeAttribute('readonly');

        try {
            input.focus({ preventScroll: true });
        } catch (e) {
            input.focus();
        }

        if (selectAll !== false) {
            input.select?.();
        }

        return true;
    }

    function parseBrNumber(raw) {
        const text = String(raw ?? '').trim();

        if (text === '') {
            return 0;
        }

        const normalized = text.replace(/\./g, '').replace(',', '.');
        const value = Number.parseFloat(normalized);

        return Number.isFinite(value) ? value : 0;
    }

    function formatBrMoney(value) {
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function finalizePrecoInput() {
        const preco = barInput('fv-tv-preco');

        if (! preco) {
            return '';
        }

        if (window.ErpMasks && preco.dataset.mask) {
            if (
                window.ErpMasks.isBrDecimalMask?.(preco.dataset.mask)
                || preco.dataset.mask === 'integer'
            ) {
                preco.value = window.ErpMasks.finalizeMaskValue(preco);
            }

            window.ErpMasks.apply(preco, {
                allowEmptySync: true,
                live: false,
                thousands: window.ErpMasks.isBrDecimalMask?.(preco.dataset.mask) === true,
            });
        }

        return preco.value || '';
    }

    function syncTotalItemLocal() {
        const total = barInput('fv-tv-total-item');
        const qtd = parseBrNumber(barInput('fv-tv-qtd')?.value);
        const preco = parseBrNumber(barInput('fv-tv-preco')?.value);

        if (total) {
            total.value = formatBrMoney(Math.max(0, qtd * preco));
        }
    }

    function beginItemBarLocal(options) {
        const opts = options || {};
        const qtd = barInput('fv-tv-qtd');
        const preco = barInput('fv-tv-preco');
        const barcode = barInput('fv-tv-barcode');

        // Novo produto: só libera inclusão após o staged do servidor.
        barReady = false;
        pendingInclude = null;

        if (barcode && opts.nome) {
            barcode.value = opts.nome;
            window.erpFvCloseProdutoLookup?.();
        }

        if (qtd) {
            qtd.disabled = false;
            qtd.removeAttribute('readonly');
            if (! String(qtd.value || '').trim() || parseBrNumber(qtd.value) <= 0) {
                qtd.value = '1,000';
            }
        }

        if (preco) {
            preco.disabled = false;
            preco.removeAttribute('readonly');
            if (opts.preco) {
                preco.value = opts.preco;
                delete preco.dataset.erpMaskSynced;
                if (window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask)) {
                    preco.value = window.ErpMasks.finalizeMaskValue(preco);
                }
                window.ErpMasks?.apply?.(preco, {
                    sync: false,
                    thousands: window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask) === true,
                });
            }
        }

        syncTotalItemLocal();
        focusInput('fv-tv-qtd');
    }

    function activeBarFieldId() {
        return document.activeElement?.id || '';
    }

    function operatorAdvancedPastQtd() {
        const id = activeBarFieldId();

        return id === 'fv-tv-preco' || including || !! pendingInclude;
    }

    function fillItemBarLocal(data) {
        const barcode = barInput('fv-tv-barcode');
        const qtd = barInput('fv-tv-qtd');
        const preco = barInput('fv-tv-preco');
        const total = barInput('fv-tv-total-item');
        const activeId = activeBarFieldId();
        const advanced = operatorAdvancedPastQtd();

        if (barcode && data.nome && activeId !== 'fv-tv-barcode') {
            barcode.value = data.nome;
        }

        if (qtd) {
            qtd.disabled = false;
            qtd.removeAttribute('readonly');
            // Não sobrescrever Qtde se o operador já digitou ou avançou para o Preço.
            if (! advanced && activeId !== 'fv-tv-qtd') {
                qtd.value = data.qtd || qtd.value || '1,000';
            } else if (! advanced && activeId === 'fv-tv-qtd') {
                const typed = String(qtd.value || '').trim();
                const staged = String(data.qtd || '').trim();

                if (typed === '' || parseBrNumber(typed) <= 0) {
                    qtd.value = staged || '1,000';
                }
            }
        }

        if (preco) {
            preco.disabled = false;
            preco.removeAttribute('readonly');
            // Não sobrescrever Preço se o foco já está nele (valor visto/editado pelo operador).
            if (! advanced && activeId !== 'fv-tv-preco' && data.preco) {
                preco.value = data.preco;
                delete preco.dataset.erpMaskSynced;
                if (window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask)) {
                    preco.value = window.ErpMasks.finalizeMaskValue(preco);
                }
                window.ErpMasks?.apply?.(preco, {
                    sync: false,
                    thousands: window.ErpMasks?.isBrDecimalMask?.(preco.dataset.mask) === true,
                });
            }
        }

        if (! advanced) {
            if (total && data.total) {
                total.value = data.total;
            } else {
                syncTotalItemLocal();
            }
        } else {
            syncTotalItemLocal();
        }

        window.erpFvCloseProdutoLookup?.();
        applyItemFoto(data.foto, data.nome);
    }

    function clearItemBarLocal() {
        const barcode = barInput('fv-tv-barcode');
        const qtd = barInput('fv-tv-qtd');
        const preco = barInput('fv-tv-preco');
        const total = barInput('fv-tv-total-item');

        if (barcode) {
            barcode.value = '';
        }

        if (qtd) {
            qtd.value = '1,000';
        }

        if (preco) {
            preco.value = '0,00';
        }

        if (total) {
            total.value = '0,00';
        }

        window.erpFvCloseProdutoLookup?.();
    }

    function applyItemFoto(foto, nome) {
        const box = document.querySelector('.erp-fv-tv__aside .erp-fv-tv__foto');

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
        } else if (img) {
            img.remove();
            if (empty) {
                empty.hidden = false;
            }
        }

        if (nome) {
            if (! caption) {
                caption = document.createElement('p');
                caption.className = 'erp-fv-tv__foto-caption';
                box.appendChild(caption);
            }

            caption.textContent = nome;
        } else if (caption) {
            caption.remove();
        }
    }

    function applyTotais(totais) {
        if (! totais || typeof totais !== 'object') {
            return;
        }

        const bruto = document.querySelector('[data-fv-total="bruto"]');
        const acrescimos = document.querySelector('[data-fv-total="acrescimos"]');
        const descontos = document.querySelector('[data-fv-total="descontos"]');
        const liquido = document.querySelector('[data-fv-total="liquido"]');
        const count = document.querySelector('[data-fv-itens-count]');

        if (bruto) {
            bruto.textContent = totais.bruto || '0,00';
        }
        if (acrescimos) {
            acrescimos.textContent = totais.acrescimos || '0,00';
        }
        if (descontos) {
            descontos.textContent = totais.descontos || '0,00';
        }
        if (liquido) {
            liquido.textContent = 'R$ ' + (totais.liquido || '0,00');
        }
        if (count && totais.itens_qtd_label != null) {
            count.textContent = totais.itens_qtd_label;
        }

        const dirtyRoot = rootEl();
        if (dirtyRoot && totais.has_itens != null) {
            dirtyRoot.setAttribute('data-fv-dirty', totais.has_itens ? '1' : '0');
        }
    }

    function moneyCellHtml(value) {
        return '<div class="erp-fv-tv__cell erp-fv-tv__cell--money">'
            + '<span class="erp-fv-tv__money-rs">R$</span>'
            + '<span class="erp-fv-tv__money-val">' + escapeHtml(value || '0,00') + '</span>'
            + '</div>';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renumberGrid(selectedKey) {
        const rows = document.querySelectorAll('.erp-fv-tv__grid tbody tr[data-fv-item-key]');
        const preferKey = selectedKey != null ? String(selectedKey) : '';

        rows.forEach((row, index) => {
            const idx = row.querySelector('.erp-fv-tv__col-idx .erp-fv-tv__cell');
            if (idx) {
                idx.textContent = String(index + 1);
            }
            const key = row.getAttribute('data-fv-item-key') || '';
            const selected = preferKey !== '' ? key === preferKey : index === 0;
            row.classList.toggle('is-selected', selected);
        });
    }

    function markSelectedKey(key) {
        const target = String(key || '');
        document.querySelectorAll('.erp-fv-tv__grid tbody tr[data-fv-item-key]').forEach((row) => {
            row.classList.toggle('is-selected', (row.getAttribute('data-fv-item-key') || '') === target);
        });
    }

    function closeExcluirModalDom() {
        document.querySelectorAll('.erp-fv-tv-excluir').forEach((el) => el.remove());
        document.querySelector('.erp-fv-tv')?.classList.remove('is-dimmed');
    }

    function updateGridRow(row, item) {
        const code = row.querySelector('.erp-fv-tv__col-cod .erp-fv-tv__cell');
        const desc = row.querySelector('.erp-fv-tv__cell--desc');
        const nums = row.querySelectorAll('.erp-fv-tv__col-num');

        if (code) {
            code.textContent = item.codigo || '';
        }
        if (desc) {
            desc.textContent = item.descricao || '';
            desc.setAttribute('title', 'Duplo clique: voltar para a barra de inclusão');
        }

        // Ordem: qtde, vlr unit, [custo?], bruto, acrés, desc, líq
        const hasCusto = !! row.querySelector('.erp-fv-tv__col-custo');
        let i = 0;
        const setMoney = (el, value) => {
            if (! el) {
                return;
            }
            const val = el.querySelector('.erp-fv-tv__money-val');
            if (val) {
                val.textContent = value || '0,00';
            } else if (el.classList.contains('erp-fv-tv__cell--num') || el.querySelector('.erp-fv-tv__cell--num')) {
                const num = el.querySelector('.erp-fv-tv__cell--num') || el;
                num.textContent = value || '';
            }
        };

        const qtdCell = nums[i++];
        if (qtdCell) {
            const num = qtdCell.querySelector('.erp-fv-tv__cell--num') || qtdCell;
            num.textContent = item.quantidade || '';
        }

        setMoney(nums[i++], item.preco_unitario);
        if (hasCusto) {
            setMoney(nums[i++], item.custo);
        }
        setMoney(nums[i++], item.bruto);
        setMoney(nums[i++], item.acrescimo);
        setMoney(nums[i++], item.desconto);
        setMoney(nums[i++], item.total);
    }

    function bindRowActions(row, key) {
        const component = livewireComponent();
        const safeKey = String(key || '');

        row.onclick = (event) => {
            if (event.target?.closest?.('.erp-fv-tv__del-btn, .erp-fv-tv__cell--pull')) {
                return;
            }
            // Seleção azul imediata; servidor confirma via skipRender + foto.
            markSelectedKey(safeKey);
            component?.call('selecionarItemByKey', safeKey);
        };

        const delBtn = row.querySelector('.erp-fv-tv__del-btn');
        if (delBtn) {
            delBtn.onclick = (event) => {
                event.stopPropagation();
                component?.call('pedirConfirmacaoExcluirItemPorKey', safeKey);
            };
        }

        const desc = row.querySelector('.erp-fv-tv__cell--pull, .erp-fv-tv__cell--desc');
        if (desc) {
            desc.ondblclick = (event) => {
                event.stopPropagation();
                component?.call('puxarItemParaInclusaoByKey', safeKey);
            };
        }
    }

    function findRowByKey(key) {
        const rows = document.querySelectorAll('.erp-fv-tv__grid tbody tr[data-fv-item-key]');
        const target = String(key || '');

        for (const row of rows) {
            if (row.getAttribute('data-fv-item-key') === target) {
                return row;
            }
        }

        return null;
    }

    function createRowFromTemplate(item) {
        const tpl = document.getElementById('erp-fv-tv-row-template');
        const node = tpl?.content?.querySelector('tr');

        if (! node) {
            return null;
        }

        const clone = node.cloneNode(true);
        clone.setAttribute('data-fv-item-key', item.key);
        clone.classList.add('is-selected');
        updateGridRow(clone, item);
        bindRowActions(clone, item.key);

        return clone;
    }

    function prependGridRow(item) {
        const tbody = document.querySelector('.erp-fv-tv__grid tbody');

        if (! tbody || ! item.key) {
            return false;
        }

        const sample = Array.from(tbody.querySelectorAll('tr[data-fv-item-key]')).find(Boolean);
        let clone = null;

        if (sample) {
            clone = sample.cloneNode(true);
            clone.removeAttribute('wire:key');
            clone.removeAttribute('wire:click');
            clone.setAttribute('data-fv-item-key', item.key);
            clone.classList.add('is-selected');

            const delBtn = clone.querySelector('.erp-fv-tv__del-btn');
            if (delBtn) {
                delBtn.removeAttribute('wire:click.stop');
                delBtn.removeAttribute('wire:click');
                delBtn.setAttribute('aria-label', 'Excluir item 1');
            }

            const desc = clone.querySelector('.erp-fv-tv__cell--desc');
            if (desc) {
                desc.removeAttribute('wire:dblclick.stop');
                desc.removeAttribute('wire:dblclick');
            }

            updateGridRow(clone, item);
            bindRowActions(clone, item.key);
        } else {
            clone = createRowFromTemplate(item);
        }

        if (! clone) {
            return false;
        }

        tbody.querySelectorAll('tr.erp-fv-tv__empty').forEach((row) => {
            row.remove();
        });
        tbody.insertBefore(clone, tbody.firstElementChild);
        renumberGrid(item.key);

        return true;
    }

    function applyItemIncluded(data) {
        const item = data.item || {};
        applyTotais(data.totais);
        clearEditingRowClass();
        setEditingKey(null);

        const existing = item.key ? findRowByKey(item.key) : null;

        if (existing && (data.agrupado || data.editado)) {
            const tbody = existing.parentElement;
            updateGridRow(existing, item);
            if (tbody && existing !== tbody.firstElementChild) {
                tbody.insertBefore(existing, tbody.firstElementChild);
            }
            // Agrupamento pode ter removido outra key no servidor — se editado+agrupado,
            // a key do payload é a linha sobrevivente.
            renumberGrid(item.key);
            if (! existing.hasAttribute('wire:click')) {
                bindRowActions(existing, item.key);
            }
        } else if (! prependGridRow(item)) {
            clearItemBarLocal();

            return;
        }

        // Se agrupou removendo a linha editada (key diferente), limpa órfãos não necessários:
        // servidor já consolidou; DOM pode ainda ter a key antiga se agrupado a partir de edição.
        if (data.agrupado && data.editado && data.removed_key) {
            findRowByKey(data.removed_key)?.remove();
            renumberGrid(item.key);
        }

        applyItemFoto(data.foto, data.nome);
        clearItemBarLocal();
    }

    function applyItemSelected(data) {
        const key = data?.key || '';
        if (key) {
            markSelectedKey(key);
        }
        applyItemFoto(data?.foto, data?.nome);
    }

    function applyItemDeleted(data) {
        closeExcluirModalDom();
        applyTotais(data?.totais);

        const key = data?.key || '';
        const row = key ? findRowByKey(key) : null;
        row?.remove();
        renumberGrid(data?.next_key || '');

        if (data?.next_key) {
            applyItemFoto(data?.foto, data?.nome);
        } else {
            applyItemFoto(null, '');
        }
    }

    function setIncluding(on) {
        including = !! on;
        window.__erpFvTvIncluding = including;
    }

    function confirmInclude() {
        if (including) {
            return;
        }

        const component = livewireComponent();
        const qtd = barInput('fv-tv-qtd')?.value || '1,000';
        const preco = finalizePrecoInput();

        if (! component) {
            return;
        }

        if (! barReady) {
            pendingInclude = { qtd, preco };
            setIncluding(true);

            return;
        }

        setIncluding(true);
        barReady = false;

        // Inclusão nova: limpa a barra na hora. Edição: mantém barra/key até
        // item-included (sucesso) — se validação falhar, o usuário continua editando.
        const wasEditing = !!(editingKey || window.__erpFvTvEditing);
        if (! wasEditing) {
            clearItemBarLocal();
            focusInput('fv-tv-barcode', true);
        }

        const finishIncludeCall = () => {
            setIncluding(false);
            // Sem item-included: confirmação falhou — reabre Enter na edição.
            if (editingKey || window.__erpFvTvEditing) {
                barReady = true;
            }
        };

        const call = component.call('adicionarItem', qtd, preco);
        if (call && typeof call.finally === 'function') {
            call.finally(finishIncludeCall);
        } else {
            window.setTimeout(finishIncludeCall, 8000);
        }

        // Safety valve: só libera se a Promise do Livewire não concluir (rede lenta).
        // 8s reduz risco de F3/F4 gravarem antes do item chegar ao servidor.
        window.setTimeout(() => {
            if (including) {
                finishIncludeCall();
            }
        }, 8000);
    }

    function setEditingKey(key) {
        editingKey = key ? String(key) : null;
        window.__erpFvTvEditing = !! editingKey;
    }

    function clearEditingRowClass() {
        document.querySelectorAll('.erp-fv-tv__grid tbody tr.is-editing').forEach((row) => {
            row.classList.remove('is-editing');
        });
    }

    function markEditingKey(key) {
        clearEditingRowClass();
        const target = String(key || '');
        if (! target) {
            return;
        }
        const row = findRowByKey(target);
        if (row) {
            row.classList.add('is-editing');
            row.classList.add('is-selected');
        }
        document.querySelectorAll('.erp-fv-tv__grid tbody tr[data-fv-item-key]').forEach((r) => {
            if ((r.getAttribute('data-fv-item-key') || '') !== target) {
                r.classList.remove('is-selected');
            }
        });
    }

    function applyItemEditLoaded(data) {
        const detail = data || {};
        setEditingKey(detail.key || '');
        // Libera Enter no Valor: edição não passa por item-staged.
        barReady = true;
        pendingInclude = null;
        setIncluding(false);
        fillItemBarLocal(detail);
        // Não remove a linha nem altera totais — só destaca is-editing.
        markEditingKey(detail.key || '');
        focusInput('fv-tv-qtd');
    }

    function applyItemEditCancelled(data) {
        const detail = data || {};
        const key = detail.key || editingKey || '';
        setEditingKey(null);
        barReady = false;
        pendingInclude = null;
        setIncluding(false);
        clearItemBarLocal();
        clearEditingRowClass();
        if (key) {
            markSelectedKey(key);
        }
        focusInput('fv-tv-barcode', true);
    }

    function barValues() {
        return {
            qtd: barInput('fv-tv-qtd')?.value || '1,000',
            preco: finalizePrecoInput(),
        };
    }

    function applyItemStaged(data) {
        fillItemBarLocal(data || {});
        barReady = true;
        setIncluding(false);

        if (pendingInclude) {
            const pending = pendingInclude;
            pendingInclude = null;
            setIncluding(true);
            barReady = false;
            clearItemBarLocal();
            focusInput('fv-tv-barcode', true);
            const pendingCall = livewireComponent()?.call('adicionarItem', pending.qtd, pending.preco);
            if (pendingCall && typeof pendingCall.finally === 'function') {
                pendingCall.finally(() => setIncluding(false));
            } else {
                window.setTimeout(() => setIncluding(false), 8000);
            }
            window.setTimeout(() => {
                if (including) {
                    setIncluding(false);
                }
            }, 8000);
        }
    }

    function onKeydown(event) {
        if (! rootEl()) {
            return;
        }

        if (event.key === 'Escape') {
            const id = event.target?.id;
            if (
                (id === 'fv-tv-qtd' || id === 'fv-tv-preco' || id === 'fv-tv-barcode')
                && (editingKey || window.__erpFvTvEditing)
            ) {
                event.preventDefault();
                livewireComponent()?.call('cancelarEdicaoItem');
            }

            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        // Enter segurado (keydown repeat) não deve avançar/incluir sozinho.
        if (event.repeat) {
            return;
        }

        if (document.getElementById('erp-fv-tab-preco-panel')) {
            return;
        }

        const id = event.target?.id;

        if (id !== 'fv-tv-qtd' && id !== 'fv-tv-preco') {
            return;
        }

        // Não usar stopImmediatePropagation — wire:keydown legado / máscaras precisam ver o evento.
        event.preventDefault();

        if (id === 'fv-tv-qtd') {
            const preco = barInput('fv-tv-preco');
            // Tab. Preço já confirmada: Enter na qtde inclui (como antes).
            const tabConfirmada = !! livewireComponent()?.get?.('tabPrecoItemConfirmada');

            if (tabConfirmada && preco && parseBrNumber(preco.value) > 0) {
                confirmInclude();

                return;
            }

            if (preco && ! preco.disabled) {
                preco.removeAttribute('readonly');
                focusInput('fv-tv-preco');
            }

            return;
        }

        confirmInclude();
    }

    /**
     * Guard do evento atrasado fv-tela-venda-focus-qtd (Alpine).
     * false = operador já avançou / inclusão em curso — não devolver foco à Qtde.
     */
    function shouldAcceptFocusQtd() {
        if (including || pendingInclude) {
            return false;
        }

        return activeBarFieldId() !== 'fv-tv-preco';
    }

    function onInput(event) {
        const id = event.target?.id;

        if (id === 'fv-tv-qtd' || id === 'fv-tv-preco') {
            syncTotalItemLocal();
        }
    }

    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('input', onInput, true);

    // Seleção azul otimista (linhas remorphed com wire:click e clones JS).
    document.addEventListener('click', (event) => {
        if (! rootEl()) {
            return;
        }

        const row = event.target?.closest?.('.erp-fv-tv__grid tbody tr[data-fv-item-key]');

        if (! row || event.target?.closest?.('.erp-fv-tv__del-btn')) {
            return;
        }

        const key = row.getAttribute('data-fv-item-key');

        if (key) {
            markSelectedKey(key);
        }
    }, true);

    document.addEventListener('livewire:init', () => {
        Livewire.on('fv-tela-venda-item-staged', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            applyItemStaged(detail || {});
        });

        Livewire.on('fv-tela-venda-item-included', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            if (detail && detail.item) {
                applyItemIncluded(detail);
            } else {
                clearItemBarLocal();
            }
            setIncluding(false);
            barReady = false;
            pendingInclude = null;
            setEditingKey(null);
            focusInput('fv-tv-barcode', true);
        });

        Livewire.on('fv-tela-venda-item-edit-loaded', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            applyItemEditLoaded(detail || {});
        });

        Livewire.on('fv-tela-venda-item-edit-cancelled', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            applyItemEditCancelled(detail || {});
        });

        Livewire.on('fv-tela-venda-item-selected', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            applyItemSelected(detail || {});
        });

        Livewire.on('fv-tela-venda-item-deleted', (payload) => {
            const detail = Array.isArray(payload) ? payload[0] : payload;
            applyItemDeleted(detail || {});
        });

        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                if (including) {
                    setIncluding(false);
                }
            });
        });
    });

    window.erpFvTvBeginItemBarLocal = beginItemBarLocal;
    window.erpFvTvIsIncluding = () => including;
    window.erpFvTvShouldAcceptFocusQtd = shouldAcceptFocusQtd;
    window.erpFvTvBarValues = barValues;
})();
