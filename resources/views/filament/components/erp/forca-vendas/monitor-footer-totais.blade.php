@php
    $totais = is_array($this->totaisFiltrados ?? null) ? $this->totaisFiltrados : [];
    $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
    $desconto = $fmt((float) ($totais['desconto'] ?? 0));
    $acrescimo = $fmt((float) ($totais['acrescimo'] ?? 0));
    $bruto = $fmt((float) ($totais['bruto'] ?? 0));
    $liquido = $fmt((float) ($totais['liquido'] ?? 0));
@endphp
{{-- Dados dos totais; JS posiciona os quadradinhos na barra de paginação, sob as colunas. --}}
<div
    id="erp-fv-mon-totais-src"
    class="erp-fv-mon-totais-src"
    hidden
    data-desconto="{{ $desconto }}"
    data-acrescimo="{{ $acrescimo }}"
    data-bruto="{{ $bruto }}"
    data-liquido="{{ $liquido }}"
></div>
@once
    @script
    <script>
        (function () {
            const COLS = [
                { key: 'desconto', attr: 'data-desconto', variant: 'desc', selectors: ['.fi-ta-header-cell-desconto', 'th.fi-ta-header-cell.fi-ta-cell-desconto', '.fi-ta-table thead th:has([data-column="desconto"])'] },
                { key: 'acrescimo', attr: 'data-acrescimo', variant: 'acre', selectors: ['.fi-ta-header-cell-acrescimo', 'th.fi-ta-header-cell.fi-ta-cell-acrescimo'] },
                { key: 'tt_bruto', attr: 'data-bruto', variant: '', selectors: ['.fi-ta-header-cell-tt_bruto', '.fi-ta-header-cell-tt-bruto', 'th.fi-ta-header-cell.fi-ta-cell-tt_bruto'] },
                { key: 'total', attr: 'data-liquido', variant: 'liq', selectors: ['.fi-ta-header-cell-total', 'th.fi-ta-header-cell.fi-ta-cell-total'] },
            ];

            const findHeader = (page, col) => {
                for (const sel of col.selectors) {
                    try {
                        const el = page.querySelector(sel);
                        if (el) return el;
                    } catch (_) {}
                }

                // Fallback: rótulo do cabeçalho.
                const labels = {
                    desconto: 'Desc.',
                    acrescimo: 'Acre.',
                    tt_bruto: 'TT Bruto',
                    total: 'TT Líquido',
                };
                const want = (labels[col.key] || '').toLowerCase();
                if (! want) return null;

                const headers = page.querySelectorAll('.fi-ta-table thead th.fi-ta-header-cell, .fi-ta-table thead th');
                for (const th of headers) {
                    const text = (th.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
                    if (text === want || text.startsWith(want)) {
                        return th;
                    }
                }

                return null;
            };

            const ensureHost = (pagination) => {
                let host = pagination.querySelector('#erp-fv-mon-totais');
                if (host) return host;

                host = document.createElement('div');
                host.id = 'erp-fv-mon-totais';
                host.className = 'erp-fv-mon-totais';
                host.setAttribute('role', 'status');
                host.setAttribute('aria-label', 'Totais dos pedidos filtrados');
                pagination.appendChild(host);
                return host;
            };

            const renderBoxes = (host, src) => {
                const signature = COLS.map((c) => src.getAttribute(c.attr) || '0,00').join('|');
                if (host.dataset.signature === signature && host.childElementCount === COLS.length) {
                    return;
                }
                host.dataset.signature = signature;
                host.innerHTML = COLS.map((col) => {
                    const valor = src.getAttribute(col.attr) || '0,00';
                    const extra = col.variant ? ` erp-fv-mon-totais__box--${col.variant}` : '';
                    return (
                        `<span class="erp-fv-mon-totais__box${extra}" data-col="${col.key}" title="R$ ${valor}">` +
                        `<span class="erp-fv-mon-money">` +
                        `<span class="erp-fv-mon-money__currency">R$</span>` +
                        `<span class="erp-fv-mon-money__amount">${valor}</span>` +
                        `</span></span>`
                    );
                }).join('');
            };

            const layout = () => {
                const page = document.querySelector('.erp-fv-monitor-page');
                if (! page) return;

                const src = page.querySelector('#erp-fv-mon-totais-src');
                const pagination = page.querySelector('.fi-pagination');
                if (! src || ! pagination) return;

                const host = ensureHost(pagination);
                renderBoxes(host, src);

                const pagRect = pagination.getBoundingClientRect();
                const content = page.querySelector('.fi-ta-content-ctn');

                COLS.forEach((col) => {
                    const box = host.querySelector(`[data-col="${col.key}"]`);
                    const th = findHeader(page, col);
                    if (! box || ! th) {
                        if (box) box.style.visibility = 'hidden';
                        return;
                    }

                    const thRect = th.getBoundingClientRect();
                    // Fora da área visível horizontal → esconde.
                    if (thRect.right < pagRect.left || thRect.left > pagRect.right) {
                        box.style.visibility = 'hidden';
                        return;
                    }

                    const left = thRect.left - pagRect.left;
                    const width = Math.max(48, thRect.width - 4);
                    box.style.visibility = 'visible';
                    box.style.left = `${Math.round(left + 2)}px`;
                    box.style.width = `${Math.round(width)}px`;
                });

                if (content && ! content.dataset.erpFvTotaisScroll) {
                    content.dataset.erpFvTotaisScroll = '1';
                    content.addEventListener('scroll', () => window.requestAnimationFrame(layout), { passive: true });
                }
            };

            const mount = () => window.requestAnimationFrame(layout);

            mount();
            window.addEventListener('resize', mount);
            document.addEventListener('livewire:navigated', mount);
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.Livewire.hook('morph.updated', mount);
            }
        })();
    </script>
    @endscript
@endonce
