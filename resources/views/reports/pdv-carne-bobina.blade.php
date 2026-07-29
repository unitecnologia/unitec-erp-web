<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carnê Bobina 80</title>
    <style>
        @page {
            margin: 0;
            size: 80mm auto;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            width: 80mm;
            background: #fff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .viewer__toolbar {
            display: none;
        }

        .viewer__canvas {
            padding: 0;
        }

        /* Tela: faixa vertical contínua (como a bobina), nunca lado a lado */
        .viewer__paper {
            width: 80mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 0;
            background: #fff;
        }

        body.erp-carne-preview {
            background: #c7d5e8;
            width: auto;
            min-height: 100vh;
        }

        body.erp-carne-preview .viewer__toolbar {
            display: flex;
            position: sticky;
            top: 0;
            z-index: 10;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
            border-bottom: 1px solid #94a3b8;
        }

        body.erp-carne-preview .viewer__title {
            margin-right: auto;
            font-size: 0.82rem;
            font-weight: 700;
            color: #0f2847;
        }

        body.erp-carne-preview .viewer__btn {
            min-width: 5.5rem;
            padding: 0.28rem 0.65rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        body.erp-carne-preview .viewer__canvas {
            padding: 1rem;
        }

        body.erp-carne-preview .viewer__paper {
            box-shadow: 0 2px 8px rgb(15 36 72 / 12%);
        }

        /* Cada parcela = 1 papel (impressora térmica corta automático entre páginas) */
        .carne-slip {
            width: 80mm;
            background: #fff;
            border: none;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: always;
            break-after: page;
        }

        .carne-slip:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        body.erp-carne-preview .carne-slip {
            border: 1px solid #64748b;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 4px rgb(15 36 72 / 10%);
            page-break-after: auto;
            break-after: auto;
        }

        body.erp-carne-preview .carne-slip + .carne-slip::before {
            content: '✂ corte automático';
            display: block;
            text-align: center;
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.2rem 0;
            margin: -0.55rem 0 0.35rem;
            border-top: 1px dashed #94a3b8;
        }

        .carne-slip + .carne-slip {
            border-top: none;
        }

        .carne-via {
            padding: 2.2mm 2.4mm 2mm;
        }

        .carne-via + .carne-via {
            border-top: 1px dashed #64748b;
        }

        .carne-via__empresa {
            margin: 0 0 1.6mm;
            text-align: center;
            font-size: 9.5pt;
            font-weight: 800;
            letter-spacing: 0.01em;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .carne-field {
            border: 1px solid #334155;
            margin-bottom: 1.4mm;
        }

        .carne-field__label {
            display: block;
            padding: 0.5mm 1.2mm 0;
            font-size: 6.5pt;
            font-weight: 700;
            color: #334155;
            line-height: 1.1;
        }

        .carne-field__value {
            display: block;
            padding: 0.2mm 1.2mm 0.8mm;
            font-size: 8.2pt;
            font-weight: 700;
            line-height: 1.15;
            min-height: 3.2mm;
            word-break: break-word;
        }

        .carne-row {
            display: grid;
            gap: 1.2mm;
            margin-bottom: 1.4mm;
        }

        .carne-row--3 {
            grid-template-columns: 1.1fr 1fr 1.15fr;
        }

        .carne-row .carne-field {
            margin-bottom: 0;
        }

        .carne-field--obs {
            min-height: 14mm;
        }

        .carne-field--obs .carne-field__value {
            min-height: 9mm;
            font-size: 7.5pt;
            font-weight: 600;
        }

        .carne-via__copy {
            margin: 0 0 1mm;
            text-align: right;
            font-size: 6pt;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        @media print {
            html, body {
                width: 80mm;
                background: #fff !important;
                min-height: 0 !important;
                height: auto !important;
            }

            .viewer__toolbar { display: none !important; }
            .viewer__canvas { padding: 0 !important; }
            .viewer__paper {
                width: 80mm;
                margin: 0;
                box-shadow: none;
            }

            .carne-slip {
                width: 80mm;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                /* 1 parcela = 1 página → corte automático da bobina (como no Delphi) */
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .carne-slip:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            body.erp-carne-preview .carne-slip + .carne-slip::before {
                display: none !important;
            }
        }
    </style>
</head>
<body class="{{ ($autoPrint ?? false) ? '' : 'erp-carne-preview' }}">
<div class="viewer">
    @unless ($autoPrint ?? false)
        <div class="viewer__toolbar">
            <span class="viewer__title">Carnê Bobina 80</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

    <div class="viewer__canvas">
        <div class="viewer__paper">
            @foreach ($parcelas as $index => $parcela)
                @php
                    $doc = (string) ($parcela['documento'] ?? (($index + 1)));
                    $venc = (string) ($parcela['vencimento'] ?? '—');
                    $valor = (string) ($parcela['valor'] ?? '0,00');
                @endphp
                <article class="carne-slip" aria-label="Parcela {{ $doc }}">
                    @foreach (['VIA EMPRESA', 'VIA CLIENTE'] as $viaLabel)
                        <section class="carne-via">
                            <p class="carne-via__copy">{{ $viaLabel }}</p>
                            <h1 class="carne-via__empresa">{{ $empresaNome }}</h1>

                            <div class="carne-field">
                                <span class="carne-field__label">Cliente</span>
                                <span class="carne-field__value">{{ $clienteNome }}</span>
                            </div>

                            <div class="carne-row carne-row--3">
                                <div class="carne-field">
                                    <span class="carne-field__label">Documento</span>
                                    <span class="carne-field__value">{{ $doc }}</span>
                                </div>
                                <div class="carne-field">
                                    <span class="carne-field__label">Emissão</span>
                                    <span class="carne-field__value">{{ $emissao }}</span>
                                </div>
                                <div class="carne-field">
                                    <span class="carne-field__label">Vencimento</span>
                                    <span class="carne-field__value">{{ $venc }}</span>
                                </div>
                            </div>

                            <div class="carne-row carne-row--3">
                                <div class="carne-field">
                                    <span class="carne-field__label">Valor Parcela</span>
                                    <span class="carne-field__value">{{ $valor }}</span>
                                </div>
                                <div class="carne-field">
                                    <span class="carne-field__label">Juros/Desconto</span>
                                    <span class="carne-field__value"></span>
                                </div>
                                <div class="carne-field">
                                    <span class="carne-field__label">Valor Pago</span>
                                    <span class="carne-field__value"></span>
                                </div>
                            </div>

                            <div class="carne-field">
                                <span class="carne-field__label">Vendedor</span>
                                <span class="carne-field__value">{{ $vendedorNome }}</span>
                            </div>

                            <div class="carne-field carne-field--obs">
                                <span class="carne-field__label">Observações</span>
                                <span class="carne-field__value">{{ $observacao }}</span>
                            </div>
                        </section>
                    @endforeach
                </article>
            @endforeach
        </div>
    </div>
</div>

<script>
    (function () {
        function notifyDone() {
            try {
                window.parent.postMessage({ type: 'erp-pdv-carne-print-done' }, '*');
            } catch (_) {}
        }

        window.addEventListener('afterprint', notifyDone);

        @if ($autoPrint ?? false)
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                try {
                    window.focus();
                    window.print();
                } catch (_) {
                    notifyDone();
                }
            }, 200);
        });
        @endif
    })();
</script>
</body>
</html>
