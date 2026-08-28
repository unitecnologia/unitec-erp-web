<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Protocolo Cancelamento NFC-e #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { margin: 6mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 10px;
            max-width: 80mm;
        }
        .cupom {
            margin: 0;
            white-space: pre;
        }
        .cupom .ln {
            display: block;
            white-space: pre;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .cupom .ln--blank {
            min-height: 1em;
        }
        .toolbar { margin-bottom: 10px; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <div class="cupom">
        @foreach ($lines as $line)
            <div class="ln{{ $line === '' ? ' ln--blank' : '' }}">{{ $line }}</div>
        @endforeach
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
