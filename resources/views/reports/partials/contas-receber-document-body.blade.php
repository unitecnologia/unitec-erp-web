@php
    use App\Support\Erp\Reports\ContaReceberRelatorio;
@endphp
@include('reports.partials.contas-receber-cartoes-document-styles')
<style>
    @page { size: A4 landscape; margin: 5mm; }

    .cr-receber-doc {
        font-size: 8.5px;
        line-height: 1.15;
    }

    .cr-receber-doc .cr-cartoes-doc__frame {
        padding: 2mm 2.5mm;
    }

    .cr-receber-doc .cr-cartoes-doc__rule {
        margin: 1mm 0;
    }

    .cr-receber-doc .cr-cartoes-doc__logo-cell {
        width: 10mm;
        padding-right: 2mm;
    }

    .cr-receber-doc .cr-cartoes-doc__logo {
        width: 9mm;
        height: 9mm;
    }

    .cr-receber-doc .cr-cartoes-doc__logo img {
        max-width: 8mm;
        max-height: 8mm;
    }

    .cr-receber-doc .cr-cartoes-doc__logo-fallback {
        margin-top: 1.2mm;
        font-size: 11px;
    }

    .cr-receber-doc .cr-cartoes-doc__company-name {
        font-size: 11px;
        margin-bottom: 0;
        line-height: 1.15;
    }

    .cr-receber-doc .cr-cartoes-doc__company-cell {
        font-size: 8px;
        line-height: 1.2;
    }

    .cr-receber-doc .cr-cartoes-doc__title {
        font-size: 11px;
        letter-spacing: 0.03em;
        margin: 0.8mm 0;
    }

    .cr-receber-doc .cr-cartoes-doc__filters {
        gap: 0.6mm 2.5mm;
        margin-bottom: 1.2mm;
        font-size: 8px;
    }

    .cr-receber-doc .cr-cartoes-doc__table th,
    .cr-receber-doc .cr-cartoes-doc__table td {
        padding: 0.45mm 0.7mm;
        font-size: 8px;
        line-height: 1.15;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cr-receber-doc .cr-cartoes-doc__table th {
        font-size: 7.5px;
        font-weight: 700;
    }

    .cr-receber-doc .cr-cartoes-doc__resumo {
        width: 100%;
        margin-top: 1.2mm;
        border-collapse: collapse;
    }

    .cr-receber-doc .cr-cartoes-doc__resumo td {
        border: 1px solid #333;
        padding: 0.7mm 1.4mm;
        font-size: 8.5px;
        font-weight: 700;
        background: #f3f6fb;
        white-space: nowrap;
    }

    .cr-receber-doc .cr-cartoes-doc__footer {
        margin-top: 1.2mm;
        font-size: 8px;
    }

    @media print {
        .cr-receber-doc .cr-cartoes-doc__frame {
            border: 0;
            padding: 0;
        }

        .cr-receber-doc .cr-cartoes-doc__table thead {
            display: table-header-group;
        }

        .cr-receber-doc .cr-cartoes-doc__table tr,
        .cr-receber-doc .cr-cartoes-doc__resumo tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
<div class="cr-cartoes-doc cr-receber-doc">
    <div class="cr-cartoes-doc__frame">
        <div class="cr-cartoes-doc__header">
            <div class="cr-cartoes-doc__logo-cell">
                <div class="cr-cartoes-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="cr-cartoes-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="cr-cartoes-doc__company-cell">
                <span class="cr-cartoes-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                <span>
                    @if (filled($empresa?->responsavel))
                        {{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}
                        &nbsp;&nbsp;
                    @endif
                    @if (filled($empresaEndereco))
                        {{ $empresaEndereco }}
                        &nbsp;&nbsp;
                    @endif
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="cr-cartoes-doc__rule">

        <div class="cr-cartoes-doc__title">{{ $reportTitle }}</div>

        <div class="cr-cartoes-doc__filters">
            <span>| FORMA: {{ mb_strtoupper($formaLabel, 'UTF-8') }}</span>
            <span>| SITUAÇÃO: {{ mb_strtoupper($situacaoLabel, 'UTF-8') }}</span>
            <span>| PERÍODO (VENC.): {{ mb_strtoupper($periodoLabel, 'UTF-8') }}</span>
            @if ($searchLabel)
                <span>| PESQUISA: {{ $searchLabel }}</span>
            @endif
            <span>| QTD: {{ $resumo['qtd'] }}</span>
        </div>

        <table class="cr-cartoes-doc__table">
            <colgroup>
                @foreach ($columns as $column)
                    <col style="width: {{ ContaReceberRelatorio::columnWidthPercent($column, $columns) }}">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ ContaReceberRelatorio::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($contas as $conta)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ ContaReceberRelatorio::isNumericColumn($column) ? 'num' : '' }}">
                                {{ ContaReceberRelatorio::cellValue($conta, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(count($columns), 1) }}" class="cr-cartoes-doc__empty">
                            Nenhum título encontrado com os filtros atuais.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="cr-cartoes-doc__resumo">
            <tr>
                <td>{{ $resumo['qtd'] }} títulos</td>
                <td>Total dos títulos {{ ContaReceberRelatorio::formatMoney((float) $resumo['valor']) }}</td>
                <td>Total recebido {{ ContaReceberRelatorio::formatMoney((float) $resumo['recebido']) }}</td>
                <td>Saldo a receber {{ ContaReceberRelatorio::formatMoney((float) $resumo['saldo']) }}</td>
            </tr>
        </table>

        <div class="cr-cartoes-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span>Pág. 1</span>
        </div>
    </div>
</div>
