@php
    use App\Support\Erp\Reports\CargaRomaneioReport;

    $exibirCliente = (bool) ($exibirCliente ?? false);
    $exibirValor = (bool) ($exibirValor ?? false);
    $colspanPedidos = 1 + ($exibirCliente ? 1 : 0) + ($exibirValor ? 1 : 0);
    $documentos = $documentos ?? [[
        'carga' => $carga,
        'pedidos' => $pedidos ?? [],
        'produtos' => $produtos ?? [],
        'qtdPedidos' => $qtdPedidos ?? 0,
        'valorTotal' => $valorTotal ?? 0,
        'pesoTotalKg' => $pesoTotalKg ?? 0,
        'motorista' => $motorista ?? '—',
        'veiculo' => $veiculo ?? '—',
    ]];
@endphp
<style>
    .carga-romaneio__info {
        margin: 0.45rem 0 0.85rem;
        font-size: 0.78rem;
        line-height: 1.45;
        word-break: break-word;
    }
    .carga-romaneio__section-title {
        margin: 1.1rem 0 0.4rem;
        font-size: 0.85rem;
        font-weight: 400;
        color: #111827;
        text-transform: uppercase;
    }
    .carga-romaneio__summary {
        margin: 0.35rem 0 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
    }
    .carga-romaneio__pedidos-linha {
        margin: 0 0 0.65rem;
        padding: 0.45rem 0.55rem;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #f8fafc;
        font-size: 0.82rem;
        font-weight: 400;
        line-height: 1.45;
        color: #0f172a;
        word-break: break-word;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .carga-romaneio__doc + .carga-romaneio__doc {
        margin-top: 10mm;
        page-break-before: always;
        break-before: page;
    }

    .carga-romaneio__footer-user {
        text-transform: uppercase;
    }

    .carga-romaneio__footer-page {
        white-space: nowrap;
        font-weight: 700;
    }

    .carga-romaneio__page-layer {
        display: none;
    }

    @media print {
        .carga-romaneio__footer-page {
            visibility: hidden;
        }

        .carga-romaneio__page-layer {
            display: block;
            position: absolute;
            right: 0;
            left: auto;
            width: auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            font-weight: 700;
            color: #111;
            z-index: 5;
        }
    }
</style>

@foreach ($documentos as $documento)
    @php
        $carga = $documento['carga'];
        $pedidos = $documento['pedidos'];
        $produtos = $documento['produtos'];
        $qtdPedidos = $documento['qtdPedidos'];
        $valorTotal = $documento['valorTotal'];
        $pesoTotalKg = (float) ($documento['pesoTotalKg'] ?? 0);
        $motorista = $documento['motorista'];
        $veiculo = $documento['veiculo'];
    @endphp

    <div class="pessoa-list-doc carga-romaneio__doc">
        <div class="pessoa-list-doc__frame">
            <div class="pessoa-list-doc__title">{{ $reportTitle }}</div>

            <div class="carga-romaneio__info">
                <strong>Nº da carga:</strong> {{ $carga->numero }}
                &nbsp;|&nbsp;
                <strong>Data:</strong> {{ optional($carga->data)->format('d/m/Y') }}
                &nbsp;|&nbsp;
                <strong>Motorista:</strong> {{ mb_strtoupper($motorista, 'UTF-8') }}
                &nbsp;|&nbsp;
                <strong>Veículo:</strong> {{ mb_strtoupper($veiculo, 'UTF-8') }}
                &nbsp;|&nbsp;
                <strong>Peso total:</strong> {{ CargaRomaneioReport::formatPesoKg($pesoTotalKg) }}
            </div>

            @if (! $exibirCliente && ! $exibirValor)
                <div class="carga-romaneio__pedidos-linha">
                    @if (count($pedidos) > 0)
                        Pedidos: {{ collect($pedidos)->pluck('pedido')->implode(' | ') }}
                    @else
                        Pedidos: nenhum pedido na carga.
                    @endif
                </div>
            @else
                <table class="pessoa-list-doc__table">
                    <thead>
                        <tr>
                            <th>PEDIDO</th>
                            @if ($exibirCliente)
                                <th>CLIENTE</th>
                            @endif
                            @if ($exibirValor)
                                <th class="num">VALOR</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pedidos as $linha)
                            <tr>
                                <td>{{ $linha['pedido'] }}</td>
                                @if ($exibirCliente)
                                    <td class="nome">{{ mb_strtoupper($linha['cliente'], 'UTF-8') }}</td>
                                @endif
                                @if ($exibirValor)
                                    <td class="num">{{ CargaRomaneioReport::formatMoney((float) $linha['valor']) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $colspanPedidos }}" class="pessoa-list-doc__empty">Nenhum pedido na carga.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

            <div class="carga-romaneio__summary">
                Quantidade de pedidos: {{ $qtdPedidos }}
                @if ($exibirValor)
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    Valor total: {{ CargaRomaneioReport::formatMoney((float) $valorTotal) }}
                @endif
            </div>

            <div class="carga-romaneio__section-title">Produtos</div>

            <table class="pessoa-list-doc__table">
                <thead>
                    <tr>
                        <th>CÓDIGO</th>
                        <th>PRODUTO</th>
                        <th class="num">QTD. TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($produtos as $linha)
                        <tr>
                            <td>{{ $linha['codigo'] }}</td>
                            <td class="nome">{{ mb_strtoupper($linha['produto'], 'UTF-8') }}</td>
                            <td class="num">{{ CargaRomaneioReport::formatQuantidade((float) $linha['quantidade']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="pessoa-list-doc__empty">Nenhum produto encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="pessoa-list-doc__footer">
                <span class="carga-romaneio__footer-user">
                    Impresso por {{ mb_strtoupper((string) ($printedBy ?? '—'), 'UTF-8') }}
                    — {{ ($printedAt ?? now())->format('d/m/Y H:i') }}
                </span>
                @unless (! empty($isPdf))
                    <span class="pessoa-list-doc__footer-page carga-romaneio__footer-page">Pag: 1 de 1</span>
                @endunless
            </div>
        </div>
    </div>
@endforeach
