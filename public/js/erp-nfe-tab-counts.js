/**
 * Contadores das abas NF-e / NFC-e e totalizadores do rodapé NFC-e (wire:ignore / sync JS).
 * Fonte dos totais NFC-e: sempre o ListNfces (pai) — o filho da grade não deve sobrescrever.
 */
(function () {
    'use strict';

    function setText(selector, text) {
        var nodes = document.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    function applyCounts(attrPrefix, counts) {
        if (!counts) {
            return;
        }

        Object.keys(counts).forEach(function (key) {
            setText('[data-' + attrPrefix + '-tab-count="' + key + '"]', counts[key]);
        });
    }

    function applyNfceFooterTotals(data) {
        if (!data) {
            return;
        }

        var page = document.querySelector('.erp-nfce-page');
        if (!page) {
            return;
        }

        if (data.bruto != null) {
            var brutoEl = page.querySelector('.erp-nfe__total-value--bruto');
            if (brutoEl) {
                brutoEl.textContent = data.bruto;
            }
        }
        if (data.canceladas != null) {
            var cancelEl = page.querySelector('.erp-nfe__total-value--canceladas');
            if (cancelEl) {
                cancelEl.textContent = data.canceladas;
            }
        }
        if (data.liquido != null) {
            var liquidoEl = page.querySelector('.erp-nfe__total-value--liquido');
            if (liquidoEl) {
                liquidoEl.textContent = data.liquido;
            }
        }
    }

    window.ErpNfeTabCounts = {
        applyNfe: function (counts) {
            applyCounts('erp-nfe', counts);
        },
        applyNfce: function (counts) {
            applyCounts('erp-nfce', counts);
        },
    };

    window.ErpNfceFooterTotals = {
        apply: applyNfceFooterTotals,
    };
})();
