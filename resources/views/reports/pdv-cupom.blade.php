<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DAV No. {{ $layout['davNumero'] }}</title>
    <style>
        @page { margin: 4mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 8px;
            max-width: 80mm;
            line-height: 1.3;
        }
        .cupom {
            margin: 0;
            white-space: pre;
        }
        .cupom .ln {
            display: block;
            white-space: pre;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .cupom .ln--bold {
            font-weight: 700;
        }
        .cupom .ln--center {
            text-align: center;
        }
        .cupom .ln--condensed {
            font-size: 9px;
            letter-spacing: -0.02em;
            line-height: 1.22;
            transform: scaleX(0.94);
            transform-origin: left center;
            width: 106.4%;
        }
        .cupom .ln--condensed:empty {
            min-height: 0.55em;
        }
        .toolbar { margin-bottom: 10px; }
        .via-label {
            text-align: center;
            font-size: 10px;
            margin: 10px 0 6px;
            border-top: 1px dashed #999;
            padding-top: 8px;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .page-break { page-break-before: always; }
            .cupom .ln--bold { font-weight: 700; }
        }
    </style>
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
            <div class="via-label">Via {{ $via }} de {{ $copias }}</div>
        @endif

        <div class="cupom">
            @foreach ($layout['lines'] as $row)
                @php
                    $cls = 'ln';
                    if (! empty($row['bold'])) {
                        $cls .= ' ln--bold';
                    }
                    if (! empty($row['center'])) {
                        $cls .= ' ln--center';
                    }
                    if (($row['font'] ?? 'A') === 'B') {
                        $cls .= ' ln--condensed';
                    }
                @endphp
                <div class="{{ $cls }}">{{ $row['text'] }}</div>
            @endforeach
        </div>
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
