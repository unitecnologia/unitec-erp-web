{{-- Rodapé padrão dos viewers de relatório (tela). Oculto na impressão/PDF. --}}
<style id="erp-report-end-actions-style">
    .erp-report-end-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.45rem;
        width: min(297mm, 100%);
        margin: 0.85rem auto 0.35rem;
        padding: 0.55rem 0.65rem;
        border: 1px solid #94a3b8;
        border-radius: 6px;
        background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
        box-sizing: border-box;
    }
    .erp-report-end-actions .viewer__btn {
        min-width: 7.5rem;
        padding: 0.32rem 0.75rem;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
        color: #0f172a;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
    }
    .erp-report-end-actions .viewer__btn:hover {
        border-color: #1e5a9e;
        background: #ffffff;
    }
    .erp-report-end-actions .viewer__btn--close {
        background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
        border-color: #fca5a5;
    }
    @media print {
        .erp-report-end-actions,
        #erp-report-end-actions-style,
        #erp-report-end-actions-script {
            display: none !important;
        }
    }
</style>
<div id="erp-report-end-actions" class="erp-report-end-actions" data-erp-no-print="1" hidden>
    <button type="button" class="viewer__btn" data-erp-report-top>Voltar ao Topo</button>
    <button type="button" class="viewer__btn viewer__btn--close" data-erp-report-close>Fechar</button>
</div>
<script id="erp-report-end-actions-script">
(function () {
    if (window.__erpReportEndActionsReady) {
        return;
    }
    window.__erpReportEndActionsReady = true;

    function placeFooter(footer) {
        if (!footer || footer.dataset.erpPlaced === '1') {
            return;
        }

        var canvas = document.querySelector('.viewer__canvas');
        if (canvas) {
            canvas.appendChild(footer);
            footer.dataset.erpPlaced = '1';
            footer.hidden = false;
            return;
        }

        var viewer = document.querySelector('.viewer');
        if (viewer) {
            viewer.appendChild(footer);
            footer.dataset.erpPlaced = '1';
            footer.hidden = false;
            return;
        }

        var sheet = document.querySelector('.sheet, .folha, .cupom, .print-doc');
        if (sheet && sheet.parentNode) {
            if (sheet.nextSibling) {
                sheet.parentNode.insertBefore(footer, sheet.nextSibling);
            } else {
                sheet.parentNode.appendChild(footer);
            }
            footer.dataset.erpPlaced = '1';
            footer.hidden = false;
            return;
        }

        document.body.appendChild(footer);
        footer.dataset.erpPlaced = '1';
        footer.hidden = false;
    }

    function scrollReportToTop() {
        var canvas = document.querySelector('.viewer__canvas');
        if (canvas) {
            canvas.scrollTop = 0;
        }

        if (document.scrollingElement) {
            document.scrollingElement.scrollTop = 0;
        }

        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        window.scrollTo(0, 0);
    }

    function findExistingCloseControl() {
        var selectors = [
            '.viewer__toolbar .viewer__btn--close',
            '.toolbar .toolbar__fechar',
            '.toolbar button.toolbar__fechar'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                return el;
            }
        }

        var buttons = document.querySelectorAll('.viewer__toolbar button, .toolbar button');
        for (var j = 0; j < buttons.length; j++) {
            if (String(buttons[j].textContent || '').trim() === 'Fechar') {
                return buttons[j];
            }
        }

        return null;
    }

    function closeReport() {
        if (typeof window.closePreview === 'function') {
            window.closePreview();
            return;
        }

        var existing = findExistingCloseControl();
        if (existing) {
            existing.click();
            return;
        }

        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'erp-report-close' }, '*');
            return;
        }

        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        window.close();
    }

    function bind(footer) {
        var topBtn = footer.querySelector('[data-erp-report-top]');
        var closeBtn = footer.querySelector('[data-erp-report-close]');
        if (topBtn) {
            topBtn.addEventListener('click', function (event) {
                event.preventDefault();
                scrollReportToTop();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                closeReport();
            });
        }
    }

    function boot() {
        var footer = document.getElementById('erp-report-end-actions');
        if (!footer) {
            return;
        }
        placeFooter(footer);
        bind(footer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
