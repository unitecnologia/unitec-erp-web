<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Recibo nº {{ $recibo->codigo }}</title>
    <style>
        @page {
            margin: 10mm;
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

        .viewer {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        .viewer__copies {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-right: 0.25rem;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0f2847;
        }

        .viewer__copies input {
            width: 3.2rem;
            min-height: 1.7rem;
            padding: 0.15rem 0.35rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: #fff;
            color: #0f172a;
            font: inherit;
            font-weight: 700;
            text-align: center;
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
        }

        /* Vias extras só na impressão — na tela mostra 1 */
        .recibo-copia-extra {
            display: none;
        }

        @media print {
            body {
                background: #fff;
            }

            .viewer__toolbar {
                display: none !important;
            }

            .viewer__canvas {
                padding: 0;
                overflow: visible;
            }

            .viewer__paper {
                width: 100%;
            }

            .recibo-doc__frame {
                border: none;
                padding: 0;
            }

            .recibo-copia-extra {
                display: block;
            }

            /* Empilha vias na mesma folha (sem forçar página nova) */
            .recibo-doc--via {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .recibo-doc--via + .recibo-doc--via {
                margin-top: 4mm;
                padding-top: 4mm;
                border-top: 1px dashed #64748b;
                page-break-before: auto;
                break-before: auto;
            }

            /* Com 2+ vias, compacta um pouco para caber na mesma A4 */
            .viewer__paper--multi .recibo-doc {
                font-size: 9.5pt;
            }

            .viewer__paper--multi .recibo-doc__frame {
                padding: 3mm 4mm;
            }

            .viewer__paper--multi .recibo-doc__logo {
                max-width: 10mm;
                max-height: 10mm;
            }

            .viewer__paper--multi .recibo-doc__empresa {
                font-size: 10pt;
            }

            .viewer__paper--multi .recibo-doc__title {
                font-size: 13pt;
            }

            .viewer__paper--multi .recibo-doc__valor {
                font-size: 12pt;
            }

            .viewer__paper--multi .recibo-doc__texto {
                font-size: 9.5pt;
                margin-bottom: 2mm;
            }

            .viewer__paper--multi .recibo-doc__data {
                margin-bottom: 3mm;
            }

            .viewer__paper--multi .recibo-doc__assinatura {
                margin-top: 4mm;
            }

            .viewer__paper--multi .recibo-doc__rule {
                margin: 2mm 0;
            }
        }
    </style>
    @include('reports.partials.recibo-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar — Recibo</span>
            <label class="viewer__copies" for="recibo-copias">
                Cópias
                <input
                    id="recibo-copias"
                    type="number"
                    min="1"
                    max="4"
                    value="1"
                    inputmode="numeric"
                    title="Vias na mesma folha (máx. 4)"
                >
            </label>
            <button type="button" class="viewer__btn" onclick="printReport()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="saveReport()">Salvar</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__canvas">
            <div class="viewer__paper" id="recibo-paper">
                <div class="recibo-doc--via" id="recibo-master">
                    @include('reports.partials.recibo-document-body')
                </div>
            </div>
        </div>
    </div>

    <script>
        const pdfDownloadUrl = @json(route('erp.reports.recibo', ['recibo' => $recibo->id, 'pdf' => 1]));

        function copiesCount() {
            const el = document.getElementById('recibo-copias');
            const n = parseInt(el && el.value ? el.value : '1', 10);
            if (! Number.isFinite(n) || n < 1) {
                return 1;
            }
            return Math.min(4, n);
        }

        function clearExtraCopies() {
            const paper = document.getElementById('recibo-paper');
            if (! paper) {
                return;
            }
            paper.querySelectorAll('.recibo-copia-extra').forEach((node) => node.remove());
            paper.classList.remove('viewer__paper--multi');
        }

        function prepareCopiesForPrint() {
            const paper = document.getElementById('recibo-paper');
            const master = document.getElementById('recibo-master');
            const n = copiesCount();

            clearExtraCopies();

            if (! paper || ! master || n <= 1) {
                return;
            }

            paper.classList.add('viewer__paper--multi');

            for (let i = 2; i <= n; i++) {
                const clone = master.cloneNode(true);
                clone.removeAttribute('id');
                clone.classList.add('recibo-copia-extra');
                paper.appendChild(clone);
            }
        }

        function printReport() {
            prepareCopiesForPrint();
            const cleanup = function () {
                clearExtraCopies();
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
            window.print();
            // Fallback se afterprint não disparar
            window.setTimeout(cleanup, 1500);
        }

        function saveReport() {
            window.open(pdfDownloadUrl, '_blank');
        }

        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-recibo-preview-close' }, '*');
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.close();
        }

        @if (! empty($autoPrint))
            window.addEventListener('load', () => {
                window.setTimeout(() => printReport(), 300);
            });
        @endif
    </script>
</body>
</html>
