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

    /**
     * Aviso amarelo (Contingência / Duplicidade / Denegado) sem remount do Filament pai.
     * Evita orphanar a grade Livewire na troca rápida de abas.
     */
    function applyNfceAvisoRodape(aviso) {
        var page = document.querySelector('.erp-nfce-page');
        if (!page) {
            return;
        }

        var host = page.querySelector('.erp-nfe__total--nfce');
        if (!host) {
            return;
        }

        var existing = host.querySelector('.erp-nfce__contingencia-aviso');

        if (!aviso || !aviso.titulo) {
            if (existing) {
                existing.remove();
            }
            return;
        }

        if (!existing) {
            existing = document.createElement('div');
            existing.className = 'erp-nfce__contingencia-aviso';
            existing.setAttribute('role', 'status');
            host.appendChild(existing);
        }

        existing.textContent = '';

        var titulo = document.createElement('strong');
        titulo.className = 'erp-nfce__contingencia-aviso__titulo';
        titulo.textContent = String(aviso.titulo || '');
        existing.appendChild(titulo);

        var motivo = document.createElement('span');
        motivo.className = 'erp-nfce__contingencia-aviso__motivo';
        motivo.textContent = 'Motivo: ' + String(aviso.motivo || '');
        existing.appendChild(motivo);

        var ajuda = document.createElement('span');
        ajuda.className = 'erp-nfce__contingencia-aviso__ajuda';
        ajuda.textContent = String(aviso.ajuda || '');
        existing.appendChild(ajuda);
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

    window.ErpNfceAvisoRodape = {
        apply: applyNfceAvisoRodape,
    };
})();
