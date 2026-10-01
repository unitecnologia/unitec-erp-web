<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleto {{ $boleto->nosso_numero ?: $boleto->id }}</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #525659;
        }
        .boleto-print-frame {
            display: block;
            width: 100%;
            height: 100vh;
            border: 0;
            background: #fff;
        }
        @media print {
            html, body {
                background: #fff;
                height: auto;
            }
            .boleto-print-frame {
                position: fixed;
                inset: 0;
                width: 100%;
                height: 100%;
            }
        }
    </style>
</head>
<body>
    <iframe
        class="boleto-print-frame"
        src="{{ $pdfUrl }}"
        title="Boleto"
    ></iframe>

    <script>
        const autoPrint = @json($autoPrint);
        let printed = false;

        function triggerPrint() {
            if (printed) {
                return;
            }
            printed = true;
            window.print();
        }

        if (autoPrint) {
            window.addEventListener('afterprint', () => {
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'erp-boleto-print-done' }, '*');
                }
            });

            // Aguarda o PDF carregar no iframe interno antes do diálogo.
            const frame = document.querySelector('.boleto-print-frame');
            const schedule = () => {
                window.setTimeout(triggerPrint, 600);
                window.setTimeout(triggerPrint, 1600);
            };

            if (frame) {
                frame.addEventListener('load', schedule, { once: true });
            }

            if (document.readyState === 'complete') {
                schedule();
            } else {
                window.addEventListener('load', schedule, { once: true });
            }
        }
    </script>
</body>
</html>
