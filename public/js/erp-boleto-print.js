/**
 * Impressão do boleto no diálogo nativo do navegador (igual NF-e / Ctrl+P).
 * Carrega o PDF em iframe oculto e chama print() — abre o preview do Chrome.
 */
window.ErpBoletoPrint = {
    openPdf(url) {
        if (! url) {
            return;
        }

        document.getElementById('erp-boleto-print-frame')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'erp-boleto-print-frame';
        iframe.src = url;
        iframe.title = 'Impressão boleto';
        iframe.setAttribute('aria-hidden', 'true');
        // Precisa de tamanho mínimo para o viewer de PDF do Chrome montar.
        iframe.style.cssText = [
            'position: fixed',
            'right: 0',
            'bottom: 0',
            'width: 1px',
            'height: 1px',
            'border: 0',
            'opacity: 0.01',
            'pointer-events: none',
            'z-index: -1',
        ].join(';');

        let cleanedUp = false;
        let printed = false;

        const cleanup = () => {
            if (cleanedUp) {
                return;
            }
            cleanedUp = true;
            iframe.remove();
        };

        const fallbackToNewTab = () => {
            cleanup();
            window.open(url, '_blank', 'noopener');
        };

        const tryPrint = () => {
            if (printed || cleanedUp) {
                return;
            }

            const frameWindow = iframe.contentWindow;
            if (! frameWindow) {
                return;
            }

            try {
                frameWindow.focus();
                frameWindow.print();
                printed = true;
                window.setTimeout(cleanup, 120000);
            } catch (error) {
                // tenta de novo nos timeouts abaixo
            }
        };

        iframe.addEventListener('load', () => {
            // Viewer de PDF demora a inicializar.
            window.setTimeout(tryPrint, 400);
            window.setTimeout(tryPrint, 1000);
            window.setTimeout(tryPrint, 1800);
            window.setTimeout(() => {
                if (! printed) {
                    fallbackToNewTab();
                }
            }, 2800);
        }, { once: true });

        iframe.addEventListener('error', fallbackToNewTab, { once: true });
        document.body.appendChild(iframe);
    },
};
