<div class="erp-compras" wire:ignore.self>
    @php
        $pageSizeOptions = [25, 50, 100];
    @endphp

    <div class="erp-compras__filters">
        <div class="erp-compras__filters-row">
            @include('filament.components.erp.empresa-badge', [
                'nome' => $this->empresaNome,
                'prefix' => 'erp-compras',
            ])

            @include('filament.components.erp.compras.toolbar-filters')

            <div class="erp-compras__page-size-group">
                <label class="erp-compras__page-size-label">
                    POR PÁGINA
                    <select wire:model.live="tableRecordsPerPage" class="erp-compras__select erp-compras__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.compras.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')

    <script>
        (function () {
            if (window.__erpLancGridEnterV4) {
                return;
            }

            window.__erpLancGridEnterV4 = true;

            function parseBr(value) {
                const raw = String(value ?? '').trim();
                if (! raw) return 0;
                if (raw.includes(',')) {
                    return Number.parseFloat(raw.replace(/\./g, '').replace(',', '.')) || 0;
                }
                return Number.parseFloat(raw) || 0;
            }

            function formatBr(num) {
                const n = Number.isFinite(num) ? num : 0;
                const parts = n.toFixed(2).split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return parts.join(',');
            }

            function findInput(modal, col, index) {
                return modal.querySelector(
                    'input[data-erp-lanc-enter="' + col + '"][data-row-index="' + String(index) + '"]'
                );
            }

            function proximo(modal, col, rowIndex) {
                const existe = (c, i) => !! findInput(modal, c, i);
                if (col === 'qtd') return { col: 'mg_venda', index: rowIndex };
                if (col === 'mg_venda') return { col: 'venda', index: rowIndex };
                if (col === 'venda') {
                    if (existe('mg_venda', rowIndex + 1)) return { col: 'mg_venda', index: rowIndex + 1 };
                    return existe('mg_atacado', 0) ? { col: 'mg_atacado', index: 0 } : null;
                }
                if (col === 'mg_atacado') return { col: 'atacado', index: rowIndex };
                if (col === 'atacado') {
                    if (existe('mg_atacado', rowIndex + 1)) return { col: 'mg_atacado', index: rowIndex + 1 };
                    return existe('mg_especial', 0) ? { col: 'mg_especial', index: 0 } : null;
                }
                if (col === 'mg_especial') return { col: 'especial', index: rowIndex };
                if (col === 'especial') {
                    return existe('mg_especial', rowIndex + 1)
                        ? { col: 'mg_especial', index: rowIndex + 1 }
                        : null;
                }
                return null;
            }

            function finalizar(input) {
                if (! window.ErpMasks || ! input?.dataset?.mask) return;
                try {
                    input.value = window.ErpMasks.finalizeMaskValue(input);
                    window.ErpMasks.apply(input, {
                        sync: false,
                        allowEmptySync: true,
                        thousands: window.ErpMasks.isBrDecimalMask(input.dataset.mask),
                    });
                } catch (e) {}
            }

            function syncPreco(modal, col, rowIndex, pctRaw) {
                const pct = parseBr(pctRaw);
                let base = 0;
                let target = null;
                if (col === 'mg_venda') {
                    target = 'venda';
                    const tr = findInput(modal, col, rowIndex)?.closest('tr');
                    const custoEl = tr?.querySelector('[data-erp-lanc-custo]');
                    base = parseBr(custoEl?.getAttribute('data-erp-lanc-custo'));
                } else if (col === 'mg_atacado') {
                    target = 'atacado';
                    base = parseBr(findInput(modal, 'venda', rowIndex)?.value);
                } else if (col === 'mg_especial') {
                    target = 'especial';
                    base = parseBr(findInput(modal, 'venda', rowIndex)?.value);
                } else {
                    return;
                }
                const input = findInput(modal, target, rowIndex);
                if (! input) return;
                input.value = formatBr(Math.round((base * (1 + pct / 100)) * 100) / 100);
                finalizar(input);
            }

            function focar(modal, col, index) {
                const input = findInput(modal, col, index);
                if (! input || input.disabled || input.readOnly) return false;
                const tr = input.closest('tr');
                const body = tr?.closest('tbody');
                body?.querySelectorAll('.erp-compras-lancamento-modal__row--selected')
                    .forEach((r) => r.classList.remove('erp-compras-lancamento-modal__row--selected'));
                tr?.classList.add('erp-compras-lancamento-modal__row--selected');
                try {
                    input.focus({ preventScroll: true });
                    input.select();
                } catch (e) {
                    try { input.focus(); input.select(); } catch (e2) { return false; }
                }
                return true;
            }

            window.__erpLancFocusUntil = 0;
            window.__erpLancPending = null;

            function onKeydown(event) {
                if (event.key !== 'Enter' && event.key !== 'NumpadEnter') return;
                const el = event.target;
                if (! (el instanceof HTMLInputElement)) return;
                if (! el.hasAttribute('data-erp-lanc-enter')) return;
                const modal = el.closest('.erp-compras-lancamento-modal');
                if (! modal) return;
                if (el.disabled || el.readOnly) return;

                // Não usa stopImmediatePropagation — deixa o wire:keydown.enter gravar no Livewire
                event.preventDefault();

                const col = el.getAttribute('data-erp-lanc-enter');
                const rowIndex = Number(el.getAttribute('data-row-index'));
                if (! col || Number.isNaN(rowIndex)) return;

                window.__erpLancFocusUntil = Date.now() + 2000;
                finalizar(el);
                if (col.startsWith('mg_')) syncPreco(modal, col, rowIndex, el.value);

                // Qtd: commit no Alpine $wire do input — não gravar/focar aqui.
                if (col === 'qtd') {
                    return;
                }

                const next = proximo(modal, col, rowIndex);
                window.__erpLancPending = next;
                if (next) {
                    focar(modal, next.col, next.index);
                    setTimeout(() => focar(modal, next.col, next.index), 0);
                    setTimeout(() => focar(modal, next.col, next.index), 50);
                    setTimeout(() => focar(modal, next.col, next.index), 150);
                    setTimeout(() => focar(modal, next.col, next.index), 320);
                }
            }

            function onFocusEvent(payload) {
                const detail = Array.isArray(payload) ? payload[0] : payload;
                const col = detail?.col;
                const index = detail?.index;
                if (! col || index === undefined || index === null) return;
                const modal = document.querySelector('.erp-compras-lancamento-modal');
                if (! modal) return;
                window.__erpLancFocusUntil = Date.now() + 2000;
                window.__erpLancPending = { col, index: Number(index) };
                focar(modal, col, Number(index));
            }

            document.addEventListener('keydown', onKeydown, true);

            document.addEventListener('livewire:init', () => {
                if (window.Livewire?.on) {
                    window.Livewire.on('erp-lanc-focus', onFocusEvent);
                }
            });

            if (window.Livewire?.on) {
                window.Livewire.on('erp-lanc-focus', onFocusEvent);
            }

            // Expõe flag para o Alpine não chamar selectLancamentoItem durante o pulo
            window.ErpComprasLancEnter = window.ErpComprasLancEnter || {};
            window.ErpComprasLancEnter.isNavigating = function () {
                return Date.now() < (window.__erpLancFocusUntil || 0);
            };
            window.ErpComprasLancEnter.version = 'v4-inline';
        })();
    </script>

    <script>
        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-nf-forn-overlay-close') {
                return;
            }

            const root = document.querySelector('.erp-compras-page');
            const componentEl = root?.closest('[wire\\:id]');
            const component = componentEl && window.Livewire
                ? window.Livewire.find(componentEl.getAttribute('wire:id'))
                : null;

            if (! component) {
                return;
            }

            const produtoId = Number.parseInt(String(event.data.produtoId ?? ''), 10);
            const itemIndex = Number.parseInt(String(event.data.itemIndex ?? ''), 10);

            if (! Number.isNaN(produtoId) && produtoId > 0 && ! Number.isNaN(itemIndex) && itemIndex >= 0) {
                component.call(
                    'applyOverlayProdutoXmlSaved',
                    itemIndex,
                    produtoId,
                    String(event.data.produtoCodigo ?? ''),
                    String(event.data.produtoDescricao ?? ''),
                    String(event.data.produtoGrupo ?? ''),
                    String(event.data.produtoPrecoVenda ?? ''),
                );

                return;
            }

            component.call('closeProductOverlay');
        });
    </script>
</div>
