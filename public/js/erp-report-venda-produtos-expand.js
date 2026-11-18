/**
 * Expansão sob demanda: número da venda → produtos (Relatório de Vendas Geral).
 */
(function () {
    'use strict';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
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

        params.set('venda_id', String(expand.venda_id || 0));
        params.set('page', String(page || 1));
        params.set('gerar', '1');

        return baseUrl + '?' + params.toString();
    }

    function renderRowsHtml(rows) {
        return (rows || []).map(function (row) {
            return (
                '<tr>' +
                '<td>' + escapeHtml(row.codigo) + '</td>' +
                '<td>' + escapeHtml(row.descricao) + '</td>' +
                '<td>' + escapeHtml(row.grupo) + '</td>' +
                '<td class="num">' + escapeHtml(row.qtd) + '</td>' +
                '<td class="num">' + escapeHtml(row.unitario) + '</td>' +
                '<td class="num">' + escapeHtml(row.total) + '</td>' +
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
            '<div class="tabular-doc__detail-status">Carregando produtos…</div>' +
            '<table class="tabular-doc__detail" hidden>' +
            '<thead><tr>' +
            '<th>CÓDIGO</th><th>DESCRIÇÃO</th><th>GRUPO</th>' +
            '<th class="num">QTD</th><th class="num">UNIT.</th><th class="num">TOTAL ITEM</th>' +
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

    function applyPage(detailRow, payload, append) {
        var table = qs('.tabular-doc__detail', detailRow);
        var tbody = qs('tbody', table);
        var meta = payload.meta || {};
        var rows = payload.rows || [];

        if (!append) {
            tbody.innerHTML = '';
        }
        tbody.insertAdjacentHTML('beforeend', renderRowsHtml(rows));
        table.hidden = false;

        var metaWrap = qs('.tabular-doc__detail-meta', detailRow);
        var summary = qs('.tabular-doc__detail-summary', detailRow);
        var more = qs('.tabular-doc__detail-more', detailRow);
        metaWrap.hidden = false;
        summary.textContent = 'Itens: ' + (meta.total || 0) + ' | Página ' + (meta.page || 1) + '/' + (meta.last_page || 1);
        more.hidden = !meta.has_more;
        more.dataset.nextPage = String((meta.page || 1) + 1);
        setStatus(detailRow, rows.length ? '' : 'Nenhum produto nesta venda.');
    }

    function loadDetails(cfg, dataRow, expand, page, append) {
        var colCount = dataRow.children.length;
        var detailRow = ensureDetailRow(dataRow, colCount);
        detailRow.hidden = false;
        if (!append) {
            setStatus(detailRow, 'Carregando produtos…');
        }

        fetch(buildDetailsUrl(cfg.detailsUrl, cfg.filters, expand, page), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (payload) {
                applyPage(detailRow, payload, append);
            })
            .catch(function () {
                setStatus(detailRow, 'Falha ao carregar produtos.');
            });
    }

    function init(cfg) {
        if (!cfg || !cfg.detailsUrl) {
            return;
        }

        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-expand-toggle]');
            if (toggle) {
                event.preventDefault();
                var dataRow = toggle.closest('tr[data-expand-row]');
                if (!dataRow) {
                    return;
                }
                var expand;
                try {
                    expand = JSON.parse(toggle.getAttribute('data-expand') || '{}');
                } catch (e) {
                    return;
                }
                if ((expand.mode || '') !== 'produtos') {
                    return;
                }

                var detailRow = dataRow.nextElementSibling;
                var open = toggle.getAttribute('aria-expanded') === 'true';
                if (open && detailRow && detailRow.classList.contains('tabular-doc__detail-row')) {
                    detailRow.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                    return;
                }

                toggle.setAttribute('aria-expanded', 'true');
                loadDetails(cfg, dataRow, expand, 1, false);
                return;
            }

            var more = event.target.closest('.tabular-doc__detail-more');
            if (!more) {
                return;
            }
            var detail = more.closest('.tabular-doc__detail-row');
            var dataRow = detail && detail.previousElementSibling;
            var btn = dataRow && qs('[data-expand-toggle]', dataRow);
            if (!btn) {
                return;
            }
            var expandMore;
            try {
                expandMore = JSON.parse(btn.getAttribute('data-expand') || '{}');
            } catch (e2) {
                return;
            }
            loadDetails(cfg, dataRow, expandMore, parseInt(more.dataset.nextPage || '2', 10), true);
        });
    }

    window.ErpReportVendaProdutosExpand = { init: init };
})();
