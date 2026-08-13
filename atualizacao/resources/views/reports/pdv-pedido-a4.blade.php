<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pedido PDV #{{ $numero }}</title>
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
            padding: 12px;
            background: #fff;
        }

        .toolbar {
            margin-bottom: 12px;
        }

        .toolbar button {
            padding: 0.35rem 0.75rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                padding: 0;
            }

            .pdv-pedido-a4__frame {
                border: none;
                padding: 0;
            }
        }
    </style>
    @include('reports.partials.pdv-pedido-a4-styles')
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @for ($via = 1; $via <= $copias; $via++)
        @if ($via > 1)
            <div class="page-break"></div>
        @endif

        @if ($copias > 1)
            <div class="pdv-pedido-a4__via-label">Via {{ $via }} de {{ $copias }}</div>
        @endif

        @include('reports.partials.pdv-pedido-a4-body')
    @endfor

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
