<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carnê A4 — {{ $empresaNome }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 10px 12px;
            background: #111827;
            color: #fff;
            font-family: ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
        }
        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 800;
            cursor: pointer;
            background: #2563eb;
            color: #fff;
        }
        .toolbar .hint { opacity: .85; margin-left: 6px; }

        .sheet {
            width: 198mm;
            height: 285mm;
            margin: 0 auto 10px;
            display: flex;
            flex-direction: column;
            page-break-after: always;
            break-after: page;
        }
        .sheet:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        /* ===== Capa ===== */
        .capa {
            justify-content: flex-start;
            padding: 10mm 12mm;
            border: 1.6px solid #111;
        }

        .capa__brand {
            display: grid;
            grid-template-columns: 42mm 1fr;
            gap: 5mm;
            align-items: center;
            padding-bottom: 5mm;
            border-bottom: 2px solid #111;
        }

        .capa__logo {
            width: 42mm;
            height: 32mm;
            border: 1px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
        }

        .capa__logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .capa__logo-fallback {
            font-size: 28px;
            font-weight: 800;
            color: #64748b;
        }

        .capa__empresa-nome {
            margin: 0 0 2mm;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .capa__empresa-meta {
            margin: 0;
            font-size: 11px;
            line-height: 1.35;
            text-transform: uppercase;
        }

        .capa__title {
            margin: 8mm 0 5mm;
            text-align: center;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 2px solid #111;
            padding: 4mm 3mm;
        }

        .capa__box {
            border: 1.2px solid #111;
            padding: 3.5mm 4mm;
            margin-bottom: 4mm;
        }

        .capa__box-title {
            margin: 0 0 2.5mm;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
        }

        .capa__cliente {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.25;
            word-break: break-word;
        }

        .capa__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm 5mm;
        }

        .capa__field .lbl {
            display: block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 0.8mm;
        }

        .capa__field .val {
            font-size: 14px;
            font-weight: 800;
        }

        .capa__resumo {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
            font-size: 11px;
        }

        .capa__resumo th,
        .capa__resumo td {
            border: 1px solid #334155;
            padding: 1.6mm 2mm;
        }

        .capa__resumo th {
            background: #f1f5f9;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        .capa__resumo td.num {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .capa__resumo td.center {
            text-align: center;
            font-weight: 700;
        }

        .capa__instrucao {
            margin-top: auto;
            border: 1px dashed #64748b;
            padding: 3mm 3.5mm;
            font-size: 11px;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .capa__obs {
            margin-top: 3mm;
            font-size: 11px;
            text-transform: uppercase;
        }

        /* ===== Fichas ===== */
        .row {
            flex: 1 1 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 0;
            border-bottom: 1.5px dashed #222;
            position: relative;
        }
        .row:last-child { border-bottom: 0; }

        .row::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 0;
            border-left: 1.5px dashed #222;
            pointer-events: none;
        }

        .via {
            margin: 2.2mm 2.8mm;
            border: 1.3px solid #111;
            padding: 2.2mm 2.6mm;
            display: flex;
            flex-direction: column;
            gap: 1.4mm;
            min-width: 0;
            font-size: 10.5px;
            line-height: 1.15;
        }

        .empresa {
            text-align: center;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: .02em;
            text-transform: uppercase;
            padding-bottom: 1mm;
            border-bottom: 1px solid #111;
        }

        .cliente {
            font-size: 10.5px;
            text-transform: uppercase;
            word-break: break-word;
        }
        .cliente .lbl {
            font-weight: 700;
            margin-right: 2px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2mm 3mm;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 1.4mm 0;
        }

        .field .lbl {
            display: block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            opacity: .85;
            margin-bottom: .3mm;
        }
        .field .val {
            font-size: 12px;
            font-weight: 700;
            min-height: 14px;
        }
        .field .val.blank {
            border-bottom: 1px solid #999;
            min-height: 15px;
        }

        .foot {
            margin-top: auto;
            display: grid;
            gap: 1mm;
            padding-top: 1mm;
            border-top: 1px solid #ddd;
        }
        .obs {
            font-size: 9.5px;
            text-transform: uppercase;
            word-break: break-word;
        }

        .empty-row {
            visibility: hidden;
        }

        @media print {
            .toolbar { display: none !important; }
            .sheet { margin: 0; width: 100%; height: auto; min-height: 285mm; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    @unless ($autoPrint ?? false)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir</button>
            <span class="hint">
                Carnê A4
                @if ($comCapa ?? false) · capa + @endif
                2 vias por parcela · 3 parcelas por folha
            </span>
        </div>
    @endunless

    @if ($comCapa ?? false)
        <section class="sheet capa" aria-label="Capa do carnê">
            <div class="capa__brand">
                <div class="capa__logo">
                    @if (filled($logoDataUri ?? null))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @else
                        <span class="capa__logo-fallback">{{ mb_substr($empresaNome, 0, 1, 'UTF-8') }}</span>
                    @endif
                </div>
                <div>
                    <h1 class="capa__empresa-nome">{{ $empresaNome }}</h1>
                    @if (($empresaRazao ?? '') !== '' && ($empresaRazao ?? '') !== $empresaNome)
                        <p class="capa__empresa-meta">{{ $empresaRazao }}</p>
                    @endif
                    @foreach ($empresaEnderecoLinhas ?? [] as $linha)
                        <p class="capa__empresa-meta">{{ $linha }}</p>
                    @endforeach
                    <p class="capa__empresa-meta">
                        @if (filled($empresaCnpj ?? null)) CNPJ: {{ $empresaCnpj }} @endif
                        @if (filled($empresaIe ?? null)) &nbsp; IE: {{ $empresaIe }} @endif
                    </p>
                    <p class="capa__empresa-meta">
                        @if (filled($empresaTelefone ?? null)) TEL: {{ $empresaTelefone }} @endif
                        @if (filled($empresaEmail ?? null)) &nbsp; {{ $empresaEmail }} @endif
                    </p>
                </div>
            </div>

            <div class="capa__title">Carnê de pagamento nº {{ $numeroBase }}</div>

            <div class="capa__box">
                <p class="capa__box-title">Cliente</p>
                <p class="capa__cliente">{{ $clienteNome }}</p>
            </div>

            <div class="capa__box">
                <div class="capa__grid">
                    <div class="capa__field">
                        <span class="lbl">Emissão</span>
                        <div class="val">{{ $emissao }}</div>
                    </div>
                    <div class="capa__field">
                        <span class="lbl">Vendedor</span>
                        <div class="val">{{ $vendedorNome }}</div>
                    </div>
                    <div class="capa__field">
                        <span class="lbl">Parcelas</span>
                        <div class="val">{{ $totalParcelas }}</div>
                    </div>
                    <div class="capa__field">
                        <span class="lbl">Valor total</span>
                        <div class="val">R$ {{ $totalValor }}</div>
                    </div>
                </div>
            </div>

            <div class="capa__box">
                <p class="capa__box-title">Resumo das parcelas</p>
                <table class="capa__resumo">
                    <thead>
                        <tr>
                            <th style="width:18%">Parcela</th>
                            <th style="width:32%">Vencimento</th>
                            <th style="width:25%">Valor</th>
                            <th style="width:25%">Documento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parcelas as $parcela)
                            @continue(! empty($parcela['_empty']))
                            <tr>
                                <td class="center">{{ $parcela['parcela'] ?? '' }}/{{ $totalParcelas }}</td>
                                <td class="center">{{ $parcela['vencimento'] ?? '' }}</td>
                                <td class="num">R$ {{ $parcela['valor'] ?? '' }}</td>
                                <td class="center">{{ $parcela['documento'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (filled($observacao ?? null))
                <p class="capa__obs"><strong>Observações:</strong> {{ $observacao }}</p>
            @endif

            <div class="capa__instrucao">
                1) Imprima a capa e as fichas das parcelas ·
                2) Corte as fichas nas linhas pontilhadas ·
                3) Coloque as fichas nesta capa e grampeie ·
                4) Entregue ao cliente
            </div>
        </section>
    @endif

    @forelse ($paginas as $pagina)
        <section class="sheet">
            @foreach ($pagina as $parcela)
                @php
                    $isEmpty = (bool) ($parcela['_empty'] ?? false);
                @endphp
                <div class="row {{ $isEmpty ? 'empty-row' : '' }}" @if ($isEmpty) aria-hidden="true" @endif>
                    @for ($via = 0; $via < 2; $via++)
                        <article class="via">
                            <div class="empresa">{{ $empresaNome }}</div>

                            <div class="cliente">
                                <span class="lbl">Cliente:</span>{{ $clienteNome }}
                            </div>

                            <div class="grid-2">
                                <div class="field">
                                    <span class="lbl">Documento</span>
                                    <div class="val">{{ $parcela['documento'] ?? '' }}</div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Data Emissão</span>
                                    <div class="val">{{ $emissao }}</div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Data Vencimento</span>
                                    <div class="val">{{ $parcela['vencimento'] ?? '' }}</div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Valor Parcela</span>
                                    <div class="val">{{ $parcela['valor'] ?? '' }}</div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Juros / Desconto</span>
                                    <div class="val blank"></div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Valor Pago</span>
                                    <div class="val blank"></div>
                                </div>
                            </div>

                            <div class="foot">
                                <div class="field">
                                    <span class="lbl">Vendedor</span>
                                    <div class="val">{{ $vendedorNome }}</div>
                                </div>
                                <div class="field">
                                    <span class="lbl">Observações</span>
                                    <div class="obs">{{ $observacao }}</div>
                                </div>
                            </div>
                        </article>
                    @endfor
                </div>
            @endforeach
        </section>
    @empty
        <p style="padding:16px;font-family:sans-serif;">Nenhuma parcela para imprimir.</p>
    @endforelse

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
                setTimeout(function () {
                    try {
                        window.focus();
                        window.print();
                    } catch (_) {
                        notifyDone();
                    }
                }, 250);
            });
            @endif
        })();
    </script>
</body>
</html>
