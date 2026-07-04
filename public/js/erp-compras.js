window.ErpComprasPrint = {
    openDanfe(url) {
        if (! url) {
            return;
        }

        document.getElementById('erp-compras-print-frame')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'erp-compras-print-frame';
        iframe.src = url;
        iframe.title = 'Impressão DANFE';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = [
            'position: fixed',
            'width: 0',
            'height: 0',
            'border: 0',
            'opacity: 0',
            'pointer-events: none',
            'left: -9999px',
            'top: -9999px',
        ].join(';');

        let cleanedUp = false;

        const cleanup = () => {
            if (cleanedUp) {
                return;
            }

            cleanedUp = true;
            iframe.remove();
            window.removeEventListener('message', onMessage);
        };

        const onMessage = (event) => {
            if (event.source !== iframe.contentWindow) {
                return;
            }

            if (event.data?.type === 'erp-compras-danfe-print-done') {
                cleanup();
            }
        };

        const fallbackToNewTab = () => {
            cleanup();

            const separator = url.includes('?') ? '&' : '?';
            window.open(`${url}${separator}auto=1`, '_blank', 'noopener');
        };

        const printFrame = () => {
            const frameWindow = iframe.contentWindow;

            if (! frameWindow) {
                fallbackToNewTab();

                return;
            }

            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (error) {
                fallbackToNewTab();
            }
        };

        window.addEventListener('message', onMessage);

        iframe.addEventListener('load', () => {
            window.setTimeout(printFrame, 150);
            window.setTimeout(cleanup, 120000);
        }, { once: true });

        iframe.addEventListener('error', fallbackToNewTab, { once: true });

        document.body.appendChild(iframe);
    },
};

function bindErpComprasLivewireEvents() {
    if (window.__erpComprasLivewireBound || ! window.Livewire) {
        return;
    }

    window.__erpComprasLivewireBound = true;

    window.Livewire.on('erp-compras-open-danfe', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;

        if (url) {
            window.ErpComprasPrint.openDanfe(url);
        }
    });
}

document.addEventListener('livewire:init', bindErpComprasLivewireEvents);
document.addEventListener('DOMContentLoaded', bindErpComprasLivewireEvents);

if (window.Livewire) {
    bindErpComprasLivewireEvents();
}
