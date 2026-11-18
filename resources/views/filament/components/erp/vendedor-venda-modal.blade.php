@if ($this->vendedorVendaModalOpen)
    <div
        id="erp-vendedor-venda-modal"
        class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-vendedor-modal"
        role="dialog"
        aria-labelledby="erp-vendedor-venda-title"
        aria-modal="true"
        x-init="$nextTick(() => document.getElementById('erp-vendedor-venda-search')?.focus())"
    >
        <div class="erp-pdv-modal__backdrop" wire:click="cancelarVendedorVenda"></div>

        <div class="erp-pdv-modal__window erp-pdv-vendedor-modal__window">
            <header class="erp-pdv-vendedor-modal__hero">
                <div class="erp-pdv-vendedor-modal__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.6"/>
                        <path d="M5 20c0-3.6 3.1-5.8 7-5.8s7 2.2 7 5.8"/>
                    </svg>
                </div>
                <div class="erp-pdv-vendedor-modal__hero-text">
                    <p class="erp-pdv-vendedor-modal__eyebrow">Fechamento</p>
                    <h2 id="erp-vendedor-venda-title">Selecione o vendedor</h2>
                    <p class="erp-pdv-vendedor-modal__subtitle">Obrigatório para este fechamento. Busque pelo nome ou código.</p>
                </div>
                <button type="button" class="erp-pdv-vendedor-modal__x" wire:click="cancelarVendedorVenda" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-vendedor-modal__body">
                <div class="erp-pdv-vendedor-modal__search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.2-3.2"/>
                    </svg>
                    <input
                        id="erp-vendedor-venda-search"
                        type="text"
                        placeholder="Nome ou código do vendedor"
                        autocomplete="off"
                        spellcheck="false"
                        data-lpignore="true"
                    >
                </div>

                <ul id="erp-vendedor-venda-list" class="erp-pdv-vendedor-modal__list" role="listbox" aria-label="Vendedores">
                    @forelse ($this->vendedorVendaOpcoes as $index => $row)
                        <li>
                            <button
                                type="button"
                                id="erp-vendedor-venda-row-{{ $index }}"
                                data-vendedor-id="{{ (int) ($row['id'] ?? 0) }}"
                                data-busca="{{ $row['busca'] ?? '' }}"
                                role="option"
                                aria-selected="false"
                                class="erp-pdv-vendedor-modal__row"
                            >
                                <span class="erp-pdv-vendedor-modal__avatar" aria-hidden="true">{{ mb_substr((string) ($row['nome'] ?? '?'), 0, 1, 'UTF-8') }}</span>
                                <span class="erp-pdv-vendedor-modal__nome">{{ $row['nome'] ?? '' }}</span>
                                <span class="erp-pdv-vendedor-modal__codigo">{{ $row['codigo'] ?? '' }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-vendedor-modal__empty">
                            Nenhum vendedor ativo nesta empresa.
                        </li>
                    @endforelse
                </ul>

                <p class="erp-pdv-vendedor-modal__hint">
                    <kbd>↑</kbd><kbd>↓</kbd> navegar · <kbd>Enter</kbd> confirmar · <kbd>Esc</kbd> cancelar
                </p>
            </div>

            <footer class="erp-pdv-vendedor-modal__footer">
                <button type="button" wire:click="cancelarVendedorVenda" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                    <kbd>Esc</kbd> Cancelar
                </button>
                <button type="button" id="erp-vendedor-venda-confirm" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary" @disabled($this->vendedorVendaOpcoes === [])>
                    <kbd>Enter</kbd> Confirmar
                </button>
            </footer>
        </div>
    </div>
    <script>
        (function () {
            const root = document.getElementById('erp-vendedor-venda-modal');
            if (!root || root.dataset.bound === '1') {
                return;
            }
            root.dataset.bound = '1';

            const rows = () => Array.from(document.querySelectorAll('#erp-vendedor-venda-list [data-vendedor-id]'))
                .filter((row) => !row.hidden && !row.closest('li')?.hidden);

            const select = (row) => {
                document.querySelectorAll('#erp-vendedor-venda-list [data-vendedor-id]').forEach((item) => {
                    const on = item === row;
                    item.classList.toggle('erp-pdv-vendedor-row--selected', on);
                    item.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                row?.scrollIntoView({ block: 'nearest' });
            };

            const filter = () => {
                const term = (document.getElementById('erp-vendedor-venda-search')?.value || '').trim().toLocaleUpperCase('pt-BR');
                let first = null;
                document.querySelectorAll('#erp-vendedor-venda-list [data-vendedor-id]').forEach((row) => {
                    const show = term === '' || String(row.dataset.busca || '').toLocaleUpperCase('pt-BR').includes(term);
                    row.hidden = !show;
                    const item = row.closest('li');
                    if (item) item.hidden = !show;
                    if (show && !first) first = row;
                });
                if (first) select(first);
            };

            window.erpVendedorVendaMover = (delta) => {
                const list = rows();
                if (!list.length) return;
                const current = list.findIndex((row) => row.classList.contains('erp-pdv-vendedor-row--selected'));
                const next = Math.max(0, Math.min(list.length - 1, (current >= 0 ? current : 0) + delta));
                select(list[next]);
            };

            window.erpVendedorVendaConfirmar = () => {
                const row = rows().find((item) => item.classList.contains('erp-pdv-vendedor-row--selected')) || rows()[0];
                const id = Number.parseInt(row?.dataset.vendedorId || '', 10);
                if (!Number.isFinite(id) || id <= 0 || !window.Livewire) return;
                const host = root.closest('[wire\\:id]');
                const wireId = host?.getAttribute('wire:id');
                if (!wireId) return;
                window.Livewire.find(wireId)?.call('confirmarVendedorVenda', id);
            };

            document.getElementById('erp-vendedor-venda-search')?.addEventListener('input', filter);
            document.getElementById('erp-vendedor-venda-confirm')?.addEventListener('click', () => window.erpVendedorVendaConfirmar?.());
            document.getElementById('erp-vendedor-venda-list')?.addEventListener('click', (event) => {
                const row = event.target.closest('[data-vendedor-id]');
                if (row) select(row);
            });
            document.getElementById('erp-vendedor-venda-list')?.addEventListener('dblclick', (event) => {
                const row = event.target.closest('[data-vendedor-id]');
                if (!row) return;
                select(row);
                window.erpVendedorVendaConfirmar?.();
            });
            filter();
        })();
    </script>
@endif
