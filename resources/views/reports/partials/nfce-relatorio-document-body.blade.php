@php
    use App\Support\Erp\Reports\NfceRelatorioReport;

    $fantasia = mb_strtoupper((string) ($empresa?->fantasia ?: $empresa?->nome ?: ''), 'UTF-8');
    $razao = mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome ?: ''), 'UTF-8');
    $hasData = count($resumidoRows) > 0 || count($detalhadoSections) > 0 || count($tributacaoRows) > 0;
@endphp

<div class="nfce-relatorio-doc">
    @if (! $hasData)
        <div class="nfce-relatorio-doc__frame">
            @include('reports.partials.nfce-relatorio-section-header', [
                'sectionTitle' => 'RELATÓRIO DE NFC-e',
            ])
            <div class="nfce-relatorio-doc__empty">Nenhuma NFC-e encontrada para os filtros informados.</div>
            <div class="nfce-relatorio-doc__footer">
                <span>Emissão: {{ $printedAt->format('d/m/Y  H:i:s') }}</span>
                <span>Página: 1</span>
            </div>
        </div>
    @else
        <div class="nfce-relatorio-doc__frame nfce-relatorio-doc__frame--break">
            @include('reports.partials.nfce-relatorio-section-header', [
                'sectionTitle' => NfceRelatorioReport::reportTitle(NfceRelatorioReport::TIPO_RESUMIDO),
            ])

            <div class="nfce-relatorio-doc__table-wrap nfce-relatorio-doc__table-wrap--compact">
                <table class="nfce-relatorio-doc__table nfce-relatorio-doc__table--resumido">
                    <thead>
                        <tr>
                            <th>DATA DE EMISSÃO</th>
                            <th class="col-total">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumidoRows as $row)
                            <tr>
                                <td class="center">{{ $row['data'] }}</td>
                                <td class="num">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="nfce-relatorio-doc__total-bar nfce-relatorio-doc__total-bar--grand">
                    <span class="nfce-relatorio-doc__total-label">Total --></span>
                    <span class="nfce-relatorio-doc__total-value">{{ $grandTotal }}</span>
                </div>
            </div>

            <div class="nfce-relatorio-doc__footer">
                <span>Emissão: {{ $printedAt->format('d/m/Y  H:i:s') }}</span>
                <span>Página: 1</span>
            </div>
        </div>

        <div class="nfce-relatorio-doc__frame nfce-relatorio-doc__frame--break">
            @include('reports.partials.nfce-relatorio-section-header', [
                'sectionTitle' => NfceRelatorioReport::reportTitle(NfceRelatorioReport::TIPO_DETALHADO),
            ])

            @foreach ($detalhadoSections as $section)
                <div class="nfce-relatorio-doc__section-title">EMISSÃO --> {{ $section['data'] }}</div>

                <div class="nfce-relatorio-doc__table-wrap">
                    <table class="nfce-relatorio-doc__table nfce-relatorio-doc__table--detalhado">
                        <thead>
                            <tr>
                                <th class="col-numero">Nº NFCe</th>
                                <th class="col-emissao">EMISSÃO</th>
                                <th class="col-chave">CHAVE</th>
                                <th class="col-protocolo">PROTOCOLO</th>
                                <th class="col-total">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['itens'] as $item)
                                <tr>
                                    <td class="center">{{ $item['numero'] }}</td>
                                    <td class="center">{{ $item['emissao'] }}</td>
                                    <td class="chave">{{ $item['chave'] }}</td>
                                    <td class="protocolo">{{ $item['protocolo'] }}</td>
                                    <td class="num">{{ $item['total'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="nfce-relatorio-doc__subtotal">
                                <td colspan="5">
                                    <div class="nfce-relatorio-doc__total-bar nfce-relatorio-doc__total-bar--section">
                                        <span class="nfce-relatorio-doc__total-label">Total --></span>
                                        <span class="nfce-relatorio-doc__total-value">{{ $section['total'] }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach

            <div class="nfce-relatorio-doc__total-bar nfce-relatorio-doc__total-bar--grand">
                <span class="nfce-relatorio-doc__total-label">Total --></span>
                <span class="nfce-relatorio-doc__total-value">{{ $grandTotal }}</span>
            </div>

            <div class="nfce-relatorio-doc__footer">
                <span>Emissão: {{ $printedAt->format('d/m/Y  H:i:s') }}</span>
                <span>Página: 2</span>
            </div>
        </div>

        <div class="nfce-relatorio-doc__frame">
            @include('reports.partials.nfce-relatorio-section-header', [
                'sectionTitle' => NfceRelatorioReport::reportTitle(NfceRelatorioReport::TIPO_TRIBUTACAO),
            ])

            <div class="nfce-relatorio-doc__table-wrap nfce-relatorio-doc__table-wrap--compact">
                <table class="nfce-relatorio-doc__table">
                    <thead>
                        <tr>
                            <th class="col-cst">CST</th>
                            <th class="col-csosn">CSOSN</th>
                            <th class="col-total">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tributacaoRows as $row)
                            <tr>
                                <td class="center">{{ $row['cst'] }}</td>
                                <td class="center">{{ $row['csosn'] }}</td>
                                <td class="num">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="nfce-relatorio-doc__total-bar nfce-relatorio-doc__total-bar--grand">
                    <span class="nfce-relatorio-doc__total-label">Total --></span>
                    <span class="nfce-relatorio-doc__total-value">{{ $grandTotal }}</span>
                </div>
            </div>

            <div class="nfce-relatorio-doc__footer">
                <span>Emissão: {{ $printedAt->format('d/m/Y  H:i:s') }}</span>
                <span>Página: 3</span>
            </div>
        </div>
    @endif
</div>
