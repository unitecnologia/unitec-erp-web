/**
 * Prefetch das telas do menu após login (1x por sessão) para aquecer workers HTTP.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'erp_warm_prefetch_done';
    var CONCURRENCY = 2;

    if (typeof window === 'undefined' || typeof fetch !== 'function') {
        return;
    }

    if (!window.location.pathname.match(/^\/admin\/?$/)) {
        return;
    }

    try {
        if (sessionStorage.getItem(STORAGE_KEY) === '1') {
            return;
        }
    } catch (e) {
        return;
    }

    fetch('/admin/erp/warm-urls', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('warm-urls');
            }

            return response.json();
        })
        .then(function (payload) {
            var paths = Array.isArray(payload && payload.paths) ? payload.paths : [];
            if (!paths.length) {
                return;
            }

            try {
                sessionStorage.setItem(STORAGE_KEY, '1');
            } catch (e) {
                // ignore
            }

            var index = 0;

            function worker() {
                if (index >= paths.length) {
                    return;
                }

                var path = paths[index++];
                fetch(path, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .catch(function () {
                        // prefetch best-effort
                    })
                    .finally(worker);
            }

            var starters = Math.min(CONCURRENCY, paths.length);
            for (var i = 0; i < starters; i++) {
                worker();
            }
        })
        .catch(function () {
            // silencioso
        });
})();
