/**
 * Cliente do Unitecnologia Device Service (localhost:9330).
 */
(function () {
    'use strict';

    const DEFAULT_BASE = 'http://127.0.0.1:9330';

    function baseUrl() {
        const cfg = window.ErpDeviceConfig || {};
        return String(cfg.baseUrl || DEFAULT_BASE).replace(/\/$/, '');
    }

    function apiKey() {
        const cfg = window.ErpDeviceConfig || {};
        return cfg.apiKey ? String(cfg.apiKey) : '';
    }

    function headers(extra) {
        const h = Object.assign({ Accept: 'application/json' }, extra || {});
        const key = apiKey();
        if (key) {
            h['X-Unitec-Key'] = key;
        }
        return h;
    }

    async function request(path, options, timeoutMs) {
        const url = baseUrl() + path;
        const ctrl = new AbortController();
        const ms = timeoutMs || Number((window.ErpDeviceConfig || {}).timeoutMs) || 4000;
        const timer = window.setTimeout(function () {
            ctrl.abort();
        }, ms);

        try {
            const res = await fetch(url, Object.assign({
                mode: 'cors',
                credentials: 'omit',
                signal: ctrl.signal,
            }, options || {}, {
                headers: headers(options && options.headers),
            }));
            let body = null;
            const text = await res.text();
            try {
                body = text ? JSON.parse(text) : null;
            } catch (_) {
                body = { raw: text };
            }
            return { ok: res.ok, status: res.status, body: body };
        } finally {
            window.clearTimeout(timer);
        }
    }

    async function status() {
        try {
            const r = await request('/api/status', { method: 'GET' }, 3000);
            return !!(r.ok && r.body && r.body.online !== false);
        } catch (_) {
            return false;
        }
    }

    async function printers() {
        const r = await request('/api/printers', { method: 'GET' }, 4000);
        if (!r.ok) {
            throw new Error('Falha ao listar impressoras do Device Service.');
        }
        return (r.body && r.body.printers) || [];
    }

    async function printRaw(printer, dataBase64, copies) {
        // Impressão RAW pode demorar (spooler / impressora pausada).
        const r = await request('/api/print/raw', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                printer: printer,
                data: dataBase64,
                copies: Math.max(1, Math.min(5, Number(copies) || 1)),
            }),
        }, 20000);
        if (!r.ok || !(r.body && r.body.ok)) {
            const msg = (r.body && r.body.message) || ('HTTP ' + r.status);
            throw new Error(msg);
        }
        return r.body;
    }

    async function openDrawer(printer, dataBase64) {
        const payload = { printer: printer };
        if (dataBase64) {
            payload.data = dataBase64;
        }
        const r = await request('/api/open-drawer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }, 10000);
        if (!r.ok || !(r.body && r.body.ok)) {
            const msg = (r.body && r.body.message) || ('HTTP ' + r.status);
            throw new Error(msg);
        }
        return r.body;
    }

    window.ErpDeviceService = {
        baseUrl: baseUrl,
        status: status,
        printers: printers,
        printRaw: printRaw,
        openDrawer: openDrawer,
        isAvailable: status,
    };
})();
