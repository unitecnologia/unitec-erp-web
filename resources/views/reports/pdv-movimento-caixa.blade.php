<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $tipo === 'sangria' ? 'SANGRIA' : 'SUPRIMENTO' }}</title>
    <style>
        @page { margin: 4mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 8px;
            max-width: 80mm;
            line-height: 1.35;
        }
        .toolbar { margin-bottom: 10px; }
        .toolbar button {
            padding: 8px 14px;
            font-size: 13px;
            cursor: pointer;
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
