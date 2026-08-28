/**
 * Impressão de cupom NFC-e/PDV.
 * 1) Unitecnologia Device Service (RAW silencioso)
 * 2) Fallback: navegador (com aviso se Device Service estava esperado)
 */
(function () {
    'use strict';

    function buildPrintUrl(url) {
        if (!url) {
            return '';
        }

        try {
            const u = new URL(url, window.location.origin);
            u.searchParams.set('auto', '1');
            u.searchParams.delete('embed');
            return u.toString();
        } catch (_) {
            return String(url).replace(/([?&])auto=0\b/, '$1auto=1');
        }
    }

    function notify(message) {
        try {
            if (window.FilamentNotification) {
                new window.FilamentNotification()
                    .title('Impressão')
                    .body(message)
                    .warning()
                    .send();
                return;
            }
        } catch (_) {}
        try {
            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                // ignore
            }
        } catch (_) {}
        console.warn('[ErpPrint]', message);
    }

    function refocusPdvSearch() {
        try {
            window.dispatchEvent(new CustomEvent('erp-pdv-refocus-search'));
        } catch (_) {}
    }

    function openBrowser(payload) {
        const url = buildPrintUrl(payload && payload.url);
        const copias = Math.max(1, Math.min(3, Number(payload && payload.copias) || 1));

        if (!url) {
            refocusPdvSearch();
            return;
        }

        window.open(url, '_blank');

        if (copias > 1) {
            window.setTimeout(function () {
                window.open(url.replace(/([?&])auto=1\b/, '$1auto=0'), '_blank');
            }, 900);
        }

        refocusPdvSearch();
    }

    async function tryDeviceRaw(payload) {
        const printer = payload && payload.printer;
        const escposUrl = payload && payload.escposUrl;
        if (!printer || !escposUrl || !window.ErpDeviceService) {
            return { ok: false, reason: 'missing' };
        }

        const online = await window.ErpDeviceService.status();
        if (!online) {
            return { ok: false, reason: 'offline' };
        }

        const res = await fetch(escposUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) {
            throw new Error('Falha ao montar ESC/POS (' + res.status + ').');
        }

        const body = await res.json();
        const data = body && (body.raw_base64 || body.data);
        const printerName = (body && body.printer) || printer;
        const copies = Math.max(1, Math.min(3, Number((body && body.copias) || payload.copias) || 1));

        if (!data) {
            throw new Error('Payload ESC/POS vazio.');
        }

        await window.ErpDeviceService.printRaw(printerName, data, copies);
        return { ok: true };
    }

    function openCupom(payload) {
        const preferDevice = payload
            && (payload.mode === 'device' || payload.mode === 'raw' || payload.escposUrl)
            && payload.printer;

        if (!preferDevice) {
            openBrowser(payload);
            return;
        }

        tryDeviceRaw(payload).then(function (result) {
            if (result && result.ok) {
                refocusPdvSearch();
                return;
            }
            if (result && result.reason === 'offline') {
                notify('Device Service (serviço local) offline. Abrindo impressão do Windows.');
            } else {
                notify('Não foi possível imprimir silencioso. Abrindo impressão do Windows.');
            }
            openBrowser(payload);
        }).catch(function (err) {
            console.warn('[ErpPrint] Device Service falhou, fallback navegador:', err);
            notify('Falha no Device Service: ' + (err && err.message ? err.message : err) + '. Abrindo Windows.');
            openBrowser(payload);
        });
    }

    window.ErpPrint = {
        openCupom: openCupom,
        openBrowser: openBrowser,
        isQzAvailable: function () {
            return false;
        },
        isDeviceAvailable: function () {
            return window.ErpDeviceService
                ? window.ErpDeviceService.status()
                : Promise.resolve(false);
        },
    };
})();
