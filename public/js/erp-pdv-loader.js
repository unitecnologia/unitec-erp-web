/**
 * Carrega erp-pdv.js sob demanda (SPA Filament/Livewire).
 * Mantém atalhos/init ao entrar no PDV e libera timers ao sair.
 */
(function () {
    'use strict';

    if (window.__erpPdvLoaderBound) {
        return;
    }

    window.__erpPdvLoaderBound = true;

    var loadPromise = null;

    function isPdvContext() {
        var path = String(window.location.pathname || '');

        if (path === '/admin/pdv' || path.indexOf('/admin/pdv/') === 0) {
            return true;
        }

        return Boolean(document.querySelector('.erp-pdv-page, .erp-pdv'));
    }

    function scriptUrl() {
        return window.__erpPdvScriptUrl || '';
    }

    function loadPdvScript() {
        if (window.__erpPdvScriptLoaded && typeof window.initErpPdv === 'function') {
            return Promise.resolve();
        }

        if (loadPromise) {
            return loadPromise;
        }

        var url = scriptUrl();

        if (! url) {
            return Promise.reject(new Error('erp-pdv: URL do script ausente'));
        }

        loadPromise = new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-erp-pdv-main="1"]');

            if (existing) {
                if (window.__erpPdvScriptLoaded) {
                    resolve();
                    return;
                }

                existing.addEventListener('load', function () {
                    window.__erpPdvScriptLoaded = true;
                    resolve();
                }, { once: true });
                existing.addEventListener('error', function () {
                    loadPromise = null;
                    reject(new Error('erp-pdv: falha ao carregar'));
                }, { once: true });
                return;
            }

            var script = document.createElement('script');
            script.src = url;
            script.defer = true;
            script.dataset.erpPdvMain = '1';
            script.addEventListener('load', function () {
                window.__erpPdvScriptLoaded = true;
                resolve();
            }, { once: true });
            script.addEventListener('error', function () {
                loadPromise = null;
                reject(new Error('erp-pdv: falha ao carregar'));
            }, { once: true });
            document.head.appendChild(script);
        });

        return loadPromise;
    }

    function ensurePdv() {
        return loadPdvScript().then(function () {
            if (typeof window.bindErpPdvLivewireEvents === 'function') {
                window.bindErpPdvLivewireEvents();
            }

            if (typeof window.initErpPdv === 'function') {
                window.initErpPdv();
            }
        }).catch(function (err) {
            console.warn('[erp-pdv-loader]', err);
        });
    }

    function onNavigated() {
        if (isPdvContext()) {
            ensurePdv();
            return;
        }

        if (typeof window.teardownErpPdvRuntime === 'function') {
            window.teardownErpPdvRuntime();
        }
    }

    function prefetchOnIntent(event) {
        var link = event.target && event.target.closest
            ? event.target.closest('a[href*="/pdv"]')
            : null;

        if (! link) {
            return;
        }

        loadPdvScript().catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onNavigated);
    } else {
        onNavigated();
    }

    document.addEventListener('livewire:navigated', onNavigated);
    document.addEventListener('pointerdown', prefetchOnIntent, true);
    document.addEventListener('mouseover', prefetchOnIntent, true);
})();
