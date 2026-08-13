<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Orçamento Bobina nº {{ $numero }}</title>
    <style>
        @page {
            margin: 2mm;
            size: 80mm auto;
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
            width: max-content;
            max-width: 100%;
            margin: 0 auto;
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

            .orc-bobina {
                padding: 0;
            }
        }
    </style>
    @include('reports.partials.orcamento-bobina-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar — Bobina (48 col.)</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="saveReport()">Salvar</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__canvas">
            <div class="viewer__paper">
                @include('reports.partials.orcamento-bobina-body')
            </div>
        </div>
    </div>

    @php
        $pdfDownloadUrl = route('erp.reports.orcamento', [
            'orcamento' => $orcamento->id,
            'pdf' => 1,
            'bobina' => 1,
        ]);
    @endphp

    <script>
        const pdfDownloadUrl = @json($pdfDownloadUrl);

        function saveReport() {
            window.open(pdfDownloadUrl, '_blank');
        }

        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-orcamento-preview-close' }, '*');
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.close();
        }

        @if ($autoPrint)
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        @endif
    </script>
</body>
</html>
