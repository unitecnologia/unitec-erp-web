/**
 * Fit de altura do iframe Cadastro de Pessoas/Produtos no overlay.
 * Chamar só na abertura (x-init) — nunca em morph/inclusão de item.
 */
(function () {
    'use strict';

    function measurePessoasOverlayHeight(doc) {
        const windowEl = doc.querySelector('.erp-pessoas-window');
        const footer = doc.querySelector('.erp-pcad-form-footer');
        const workspace = doc.querySelector('.erp-pcad__workspace');

        if (! windowEl) {
            return 0;
        }

        const prev = workspace
            ? {
                overflow: workspace.style.overflow,
                flex: workspace.style.flex,
                height: workspace.style.height,
            }
            : null;

        if (workspace) {
            workspace.style.overflow = 'visible';
            workspace.style.flex = '0 0 auto';
            workspace.style.height = 'auto';
        }

        const measured = Math.ceil(Math.max(
            doc.documentElement.scrollHeight || 0,
            doc.body?.scrollHeight || 0,
            footer ? footer.getBoundingClientRect().bottom : 0,
            windowEl.getBoundingClientRect().bottom,
        ));

        if (workspace && prev) {
            workspace.style.overflow = prev.overflow;
            workspace.style.flex = prev.flex;
            workspace.style.height = prev.height;
        }

        return measured;
    }

    function bindOverlayContentFit(iframe) {
        if (! iframe || iframe.dataset.erpOverlayFitBound === '1') {
            return;
        }

        iframe.dataset.erpOverlayFitBound = '1';

        let fitObserver = null;
        let fitTimers = [];
        let fitting = false;

        const clearFitTimers = () => {
            fitTimers.forEach((id) => window.clearTimeout(id));
            fitTimers = [];
        };

        const applyFit = () => {
            if (fitting) {
                return;
            }

            let doc;

            try {
                doc = iframe.contentDocument;
            } catch (e) {
                return;
            }

            if (! doc) {
                return;
            }

            const pessoasWindow = doc.querySelector('.erp-pessoas-window');
            const produtosWindow = doc.querySelector('.erp-produtos-window');
            const maxH = Math.max(160, Math.floor(window.innerHeight - 24));

            let measured = 0;

            if (pessoasWindow) {
                if (! doc.querySelector('.erp-pcad-form-footer, .erp-pcad__workspace, .erp-pcad__content')) {
                    return;
                }

                measured = measurePessoasOverlayHeight(doc);

                if (measured < 320) {
                    return;
                }

                doc.documentElement.classList.toggle('erp-os-overlay-embed--capped', measured > maxH);
            } else if (produtosWindow) {
                measured = Math.ceil(produtosWindow.getBoundingClientRect().height);

                if (measured < 80) {
                    return;
                }
            } else {
                return;
            }

            const next = Math.min(measured, maxH);
            const current = parseFloat(iframe.style.height) || 0;

            if (Math.abs(current - next) < 2 && iframe.style.height) {
                return;
            }

            fitting = true;
            iframe.style.height = next + 'px';
            iframe.style.maxHeight = maxH + 'px';
            iframe.style.minHeight = '0';
            iframe.style.flex = '0 0 auto';

            const panel = iframe.closest('.erp-form-overlay__panel');

            if (panel) {
                panel.style.height = next + 'px';
                panel.style.maxHeight = maxH + 'px';
                panel.style.minHeight = '0';
            }

            window.requestAnimationFrame(() => {
                fitting = false;
            });
        };

        const onLoad = () => {
            applyFit();
            clearFitTimers();
            [50, 200, 500].forEach((ms) => {
                fitTimers.push(window.setTimeout(applyFit, ms));
            });

            if (fitObserver) {
                fitObserver.disconnect();
                fitObserver = null;
            }

            if (typeof ResizeObserver === 'undefined') {
                return;
            }

            let doc;

            try {
                doc = iframe.contentDocument;
            } catch (e) {
                return;
            }

            const observeEl = doc?.querySelector('.erp-pcad__content, .erp-pcad, .erp-produtos-window, .erp-pessoas-window');

            if (! observeEl) {
                return;
            }

            fitObserver = new ResizeObserver(() => applyFit());
            fitObserver.observe(observeEl);
        };

        iframe.addEventListener('load', onLoad);
    }

    function bindOverlayFitFromHost(host) {
        if (! host) {
            return;
        }

        host.querySelectorAll(
            'iframe[data-erp-form-overlay-iframe], iframe[data-erp-pdv-overlay-iframe]',
        ).forEach(bindOverlayContentFit);
    }

    window.erpFormOverlayFit = bindOverlayFitFromHost;
    // Alias TV (compat)
    window.erpFvBindOverlayFit = bindOverlayFitFromHost;
})();
