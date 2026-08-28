<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Etiqueta Volume — NF-e {{ $numeroNf }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .folha {
            width: 100%;
        }

        .grade {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4mm;
            align-content: start;
        }

        .etiqueta {
            border: 1.2px solid #111;
            border-radius: 2mm;
            padding: 3.5mm 4mm 3mm;
            min-height: 52mm;
            height: 52mm;
            display: flex;
            flex-direction: column;
            break-inside: avoid;
            page-break-inside: avoid;
            background: #fff;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3mm;
            min-height: 14mm;
            margin-bottom: 2.5mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #111;
        }

        .logo {
            flex: 0 1 auto;
            max-width: 42%;
            height: 13mm;
            display: flex;
            align-items: center;
        }

        .logo img {
            max-width: 100%;
            max-height: 13mm;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: left center;
        }

        .logo-fallback {
            font-size: 11px;
            font-weight: 800;
            line-height: 1.15;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .nf {
            flex: 1 1 auto;
            text-align: right;
            font-size: 26px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }

        .rows {
            flex: 1 1 auto;
            display: grid;
            gap: 1.4mm;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .row {
            display: grid;
            grid-template-columns: 34mm 1fr;
            column-gap: 2mm;
            align-items: baseline;
        }

        .label {
            font-weight: 800;
        }

        .value {
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .row.vol .value {
            font-size: 13px;
        }

        .rodape {
            margin-top: 2mm;
            border-top: 1.5px solid #111;
            height: 0;
        }

        @media screen {
            body {
                background: #e2e8f0;
                padding: 16px;
            }

            .folha {
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                padding: 8mm;
                background: #fff;
                box-shadow: 0 4px 24px rgb(15 23 42 / 18%);
            }
        }

        @media print {
            .folha {
                width: auto;
                min-height: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="folha">
        <div class="grade">
            @foreach ($volumes as $volumeTexto)
                <section class="etiqueta">
                    <div class="header">
                        <div class="logo">
                            @if (filled($logoDataUri ?? null))
                                <img src="{{ $logoDataUri }}" alt="Logo">
                            @else
                                <span class="logo-fallback">{{ $remetenteNome }}</span>
                            @endif
                        </div>
                        <div class="nf">N.F. {{ $numeroNf }}</div>
                    </div>

                    <div class="rows">
                        <div class="row">
                            <span class="label">CLIENTE:</span>
                            <span class="value">{{ $clienteNome }}</span>
                        </div>
                        <div class="row">
                            <span class="label">REMETENTE:</span>
                            <span class="value">{{ $remetenteNome }}</span>
                        </div>
                        <div class="row">
                            <span class="label">TRANSPORTADORA:</span>
                            <span class="value">{{ $transportadoraNome }}</span>
                        </div>
                        <div class="row">
                            <span class="label">DESTINO:</span>
                            <span class="value">{{ $destino }}</span>
                        </div>
                        <div class="row vol">
                            <span class="label">Nº DE VOLUMES:</span>
                            <span class="value">{{ $volumeTexto }}</span>
                        </div>
                    </div>
                    <div class="rodape"></div>
                </section>
            @endforeach
        </div>
    </div>

    @if (! empty($autoPrint))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
