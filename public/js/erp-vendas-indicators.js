/**
 * Atualiza contadores/totais da listagem de Vendas sem remount (abas wire:ignore).
 * Sincroniza seletor SITUAÇÃO da barra F8 com as abas superiores.
 */
(function () {
    'use strict';

    function setText(selector, text) {
        var nodes = document.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    function syncStatusUi(status) {
        var value = String(status || 'todos');

        document.querySelectorAll('[data-erp-vendas-status-filter]').forEach(function (el) {
            if (el instanceof HTMLSelectElement && el.value !== value) {
                el.value = value;
            }
        });

        try {
            window.dispatchEvent(new CustomEvent('erp-vendas-status-changed', { detail: value }));
        } catch (e) {
            // ignore
        }
    }

    function applyStatusFilter(status) {
        var value = String(status || 'todos');
        syncStatusUi(value);

        var tables = (window.Livewire && window.Livewire.getByName)
            ? (window.Livewire.getByName('erp.venda-list-table') || [])
            : [];
        var table = tables[0] || null;

        if (table && typeof table.call === 'function') {
            table.call('setStatusFilter', value);
        }
    }

    function apply(data) {
        if (!data) {
            return;
        }

        var statusCounts = data.statusCounts || {};
        Object.keys(statusCounts).forEach(function (key) {
            setText('[data-venda-status-count="' + key + '"]', statusCounts[key]);
        });

        var statusValues = data.statusValues || {};
        Object.keys(statusValues).forEach(function (key) {
            setText('[data-venda-status-value="' + key + '"]', statusValues[key]);
        });

        var tipoCounts = data.tipoCounts || {};
        Object.keys(tipoCounts).forEach(function (key) {
            setText('[data-venda-tipo-count="' + key + '"]', tipoCounts[key]);
        });

        if (data.totalCount != null) {
            setText('[data-venda-total-count]', data.totalCount);
        }
        if (data.totalValue != null) {
            setText('[data-venda-total-value]', data.totalValue);
        }

        if (data.statusFilter != null) {
            syncStatusUi(data.statusFilter);
        }
    }

    window.ErpVendasIndicators = { apply: apply };
    window.ErpVendasStatusFilter = {
        syncUI: syncStatusUi,
        set: applyStatusFilter,
    };
})();
