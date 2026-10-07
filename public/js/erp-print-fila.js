/**
 * Impressão em fila de relatórios HTML do ERP (mesma técnica de ErpNfePrint.openDanfe):
 * iframe invisível + contentWindow.print(). Não abre janelas, então o bloqueador de pop-up
 * não interfere. Um documento por vez: o próximo só carrega depois que o diálogo anterior fecha.
 */
(() => {
    if (window.ErpPrintFila) {
        return;
    }

    let emAndamento = false;

    function imprimirUm(url) {
        return new Promise((resolve) => {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.title = 'Impressão';
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

            let concluido = false;
            const concluir = () => {
                if (concluido) {
                    return;
                }

                concluido = true;
                window.setTimeout(() => iframe.remove(), 1000);
                resolve();
            };

            iframe.addEventListener('load', () => {
                const frameWindow = iframe.contentWindow;

                if (! frameWindow) {
                    window.open(url, '_blank', 'noopener');
                    concluir();

                    return;
                }

                frameWindow.addEventListener('afterprint', concluir, { once: true });

                window.setTimeout(() => {
                    try {
                        frameWindow.focus();
                        // Chrome/Edge: print() só retorna quando o diálogo fecha.
                        frameWindow.print();
                        window.setTimeout(concluir, 300);
                    } catch (error) {
                        window.open(url, '_blank', 'noopener');
                        concluir();
                    }
                }, 300);

                window.setTimeout(concluir, 120000);
            }, { once: true });

            iframe.addEventListener('error', () => {
                window.open(url, '_blank', 'noopener');
                concluir();
            }, { once: true });

            document.body.appendChild(iframe);
        });
    }

    window.ErpPrintFila = {
        async imprimir(urls) {
            const lista = (Array.isArray(urls) ? urls : [urls]).filter(Boolean);

            if (emAndamento || lista.length === 0) {
                return;
            }

            emAndamento = true;

            try {
                for (const url of lista) {
                    await imprimirUm(url);
                }
            } finally {
                emAndamento = false;
            }
        },
    };
})();
