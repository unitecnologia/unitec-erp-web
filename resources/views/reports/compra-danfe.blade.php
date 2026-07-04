<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DANFE — NF-e {{ $numeroNota }}</title>
    <style>
        @page {
            margin: 5mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #c7d5e8;
        }

        body.viewer-body--auto-print {
            background: #fff;
        }

        .viewer {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .viewer--auto-print .viewer__toolbar {
            display: none;
        }

        .viewer--auto-print .viewer__canvas {
            padding: 0;
            overflow: visible;
        }

        .viewer--auto-print .viewer__paper {
            width: 100%;
            margin: 0;
            padding: 0;
            box-shadow: none;
        }

        .viewer__toolbar {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
            padding: 0.35rem 0.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
            border-bottom: 1px solid #94a3b8;
        }

        .viewer__title {
            margin-right: auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            color: #0f2847;
        }

        .viewer__btn {
            min-width: 5.5rem;
            padding: 0.28rem 0.65rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        .viewer__btn:hover {
            border-color: #1e5a9e;
            background: #ffffff;
        }

        .viewer__btn--close {
            background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #fca5a5;
        }

        .viewer__canvas {
            flex: 1;
            overflow: auto;
            padding: 1rem;
        }

        .viewer__paper {
            width: min(210mm, 100%);
            margin: 0 auto;
            background: #fff;
            padding: 4mm;
            box-shadow: 0 8px 24px rgba(15, 40, 71, 0.18);
        }

        @media print {
            body {
                background: #fff;
            }

            .viewer__toolbar {
                display: none;
            }

            .viewer__canvas {
                padding: 0;
                overflow: visible;
            }

            .viewer__paper {
                width: 100%;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
    @include('reports.partials.compra-danfe-styles')
</head>
<body @class(['viewer-body--auto-print' => $autoPrint])>
    <div @class(['viewer', 'viewer--auto-print' => $autoPrint])>
        @unless ($autoPrint)
            <div class="viewer__toolbar">
                <span class="viewer__title">DANFE — NF-e {{ $numeroNota }}</span>
                <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
                <button type="button" class="viewer__btn" onclick="saveReport()">Salvar PDF</button>
                <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
            </div>
        @endunless

        <div class="viewer__canvas">
            <div class="viewer__paper">
                @include('reports.partials.compra-danfe-body')
            </div>
        </div>
    </div>

    <script>
        const pdfDownloadUrl = @json(route('erp.reports.compra-danfe', ['compra' => $compra->id, 'pdf' => 1]));
        const autoPrint = @json($autoPrint);

        function saveReport() {
            window.open(pdfDownloadUrl, '_blank');
        }

        function closePreview() {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.close();
        }

        function triggerPrint() {
            window.print();
        }

        if (autoPrint) {
            window.addEventListener('afterprint', () => {
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'erp-compras-danfe-print-done' }, '*');

                    return;
                }

                window.close();
            });

            if (document.readyState === 'complete') {
                triggerPrint();
            } else {
                window.addEventListener('load', triggerPrint, { once: true });
            }
        }
    </script>
</body>
</html>
