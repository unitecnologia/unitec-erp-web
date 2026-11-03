/**
 * Expansão sob demanda: QTD VENDAS → detalhes paginados (Histórico por Vendedor).
 * Sem polling; atualiza só a linha de detalhe.
 */
(function () {
    'use strict';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildDetailsUrl(baseUrl, filters, expand, page) {
        var params = new URLSearchParams();

        Object.keys(filters || {}).forEach(function (key) {
            var value = filters[key];
            if (key === 'cols' && Array.isArray(value)) {
                value.forEach(function (col) {
                    params.append('cols[]', col);
                });
                return;
            }
            if (value === null || value === undefined || value === '') {
                return;
            }
            params.set(key, String(value));
        });

        params.set('vendedor_id', String(expand.vendedor_id || 0));
        params.set('empresa_id', String(expand.empresa_id || 0));
        params.set('vendedor_label', String(expand.vendedor || ''));
        params.set('page', String(page || 1));

        return baseUrl + '?' + params.toString();
    }

    function renderRowsHtml(rows) {
        return (rows || []).map(function (row) {
            var numero = escapeHtml(row.numero);
            var numeroCell = row.url
                ? '<a class="tabular-doc__detail-link" href="' + escapeHtml(row.url) + '" target="_blank" rel="noopener noreferrer">' + numero + '</a>'
                : numero;

            return (
                '<tr>' +
                '<td>' + numeroCell + '</td>' +
                '<td>' + escapeHtml(row.data) + '</td>' +
                '<td>' + escapeHtml(row.cliente) + '</td>' +
                '<td class="num">' + escapeHtml(row.total) + '</td>' +
                '<td>' + escapeHtml(row.situacao) + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function ensureDetailRow(dataRow, colCount) {
        var next = dataRow.nextElementSibling;
        if (next && next.classList.contains('tabular-doc__detail-row')) {
            return next;
        }

        var detail = document.createElement('tr');
        detail.className = 'tabular-doc__detail-row';
        detail.hidden = true;
        detail.innerHTML =
            '<td colspan="' + colCount + '">' +
            '<div class="tabular-doc__detail-wrap">' +
            '<div class="tabular-doc__detail-status">Carregando vendas…</div>' +
            '<table class="tabular-doc__detail" hidden>' +
            '<thead><tr>' +
            '<th>NÚMERO</th><th>DATA</th><th>CLIENTE</th><th class="num">TOTAL</th><th>SITUAÇÃO</th>' +
            '</tr></thead>' +
            '<tbody></tbody>' +
            '</table>' +
            '<div class="tabular-doc__detail-meta" hidden>' +
            '<span class="tabular-doc__detail-summary"></span>' +
            '<button type="button" class="tabular-doc__detail-more" hidden>Carregar mais</button>' +
            '</div>' +
            '</div>' +
            '</td>';

        dataRow.parentNode.insertBefore(detail, dataRow.nextSibling);
        return detail;
    }

    function setStatus(detailRow, text) {
        var el = qs('.tabular-doc__detail-status', detailRow);
        if (el) {
            el.textContent = text || '';
            el.hidden = !text;
        }
    }

    function expandKey(expand) {
        return [expand.vendedor_id || 0, expand.empresa_id || 0, expand.vendedor || ''].join('|');
    }

    function init(config) {
        var detailsUrl = config && config.detailsUrl;
        var filters = (config && config.filters) || {};
        if (!detailsUrl) {
            return;
        }

        var table = qs('#erp-report-table-view .tabular-doc__table');
        if (!table) {
            return;
        }

        var colCount = qsa('thead th', table).length || 1;

        table.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-expand-toggle]');
            if (!toggle || !table.contains(toggle)) {
                return;
            }

            event.preventDefault();

            var dataRow = toggle.closest('tr');
            if (!dataRow) {
                return;
            }

            var expand;
            try {
                expand = JSON.parse(toggle.getAttribute('data-expand') || '{}');
            } catch (e) {
                return;
            }

            var key = expandKey(expand);
            var detailRow = ensureDetailRow(dataRow, colCount);
            var isOpen = toggle.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                toggle.setAttribute('aria-expanded', 'false');
                detailRow.hidden = true;
                return;
            }

            qsa('[data-expand-toggle][aria-expanded="true"]', table).forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
            qsa('.tabular-doc__detail-row', table).forEach(function (row) {
                row.hidden = true;
            });

            toggle.setAttribute('aria-expanded', 'true');
            detailRow.hidden = false;

            if (detailRow.getAttribute('data-loaded') === key) {
                return;
            }

            loadPage(detailRow, toggle, expand, 1, true);
        });

        function loadPage(detailRow, toggle, expand, page, replace) {
            var moreBtn = qs('.tabular-doc__detail-more', detailRow);
            var tbody = qs('.tabular-doc__detail tbody', detailRow);
            var tableEl = qs('.tabular-doc__detail', detailRow);
            var metaEl = qs('.tabular-doc__detail-meta', detailRow);
            var summaryEl = qs('.tabular-doc__detail-summary', detailRow);

            if (replace) {
                setStatus(detailRow, 'Carregando vendas…');
                if (tbody) {
                    tbody.innerHTML = '';
                }
                if (tableEl) {
                    tableEl.hidden = true;
                }
                if (metaEl) {
                    metaEl.hidden = true;
                }
            } else if (moreBtn) {
                moreBtn.disabled = true;
                moreBtn.textContent = 'Carregando…';
            }

            fetch(buildDetailsUrl(detailsUrl, filters, expand, page), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Falha ao carregar detalhes');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (toggle.getAttribute('aria-expanded') !== 'true') {
                        return;
                    }

                    var rows = payload.rows || [];
                    var meta = payload.meta || {};

                    if (tbody) {
                        if (replace) {
                            tbody.innerHTML = renderRowsHtml(rows);
                        } else {
                            tbody.insertAdjacentHTML('beforeend', renderRowsHtml(rows));
                        }
                    }

                    if (tableEl) {
                        tableEl.hidden = false;
                    }

                    var shown = tbody ? tbody.querySelectorAll('tr').length : rows.length;
                    var total = Number(meta.total || 0);
                    if (summaryEl) {
                        summaryEl.textContent =
                            'Exibindo ' + shown + ' de ' + total +
                            ' venda(s) — total R$ ' + (meta.sum_total || '0,00');
                    }
                    if (metaEl) {
                        metaEl.hidden = false;
                    }

                    if (moreBtn) {
                        var hasMore = !!meta.has_more;
                        moreBtn.hidden = !hasMore;
                        moreBtn.disabled = false;
                        moreBtn.textContent = 'Carregar mais';
                        moreBtn.onclick = hasMore
                            ? function () {
                                loadPage(detailRow, toggle, expand, Number(meta.page || page) + 1, false);
                            }
                            : null;
                    }

                    detailRow.setAttribute('data-loaded', expandKey(expand));
                    setStatus(detailRow, rows.length === 0 && replace ? 'Nenhuma venda encontrada.' : '');
                })
                .catch(function () {
                    setStatus(detailRow, 'Não foi possível carregar as vendas.');
                    if (moreBtn) {
                        moreBtn.disabled = false;
                        moreBtn.textContent = 'Carregar mais';
                    }
                });
        }
    }

    window.ErpReportVendedorExpand = { init: init };
})();
