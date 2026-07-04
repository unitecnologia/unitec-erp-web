@php
    use App\Support\Erp\Reports\PersonListagemReport;

    $columnTotals = PersonListagemReport::columnTotals($people, $columns);
    $hasPeople = count($people) > 0;
@endphp
<div class="pessoa-list-doc">
    <div class="pessoa-list-doc__frame">
        <div class="pessoa-list-doc__header">
            <div class="pessoa-list-doc__logo-cell">
                <div class="pessoa-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="pessoa-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="pessoa-list-doc__company-cell">
                <span class="pessoa-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="pessoa-list-doc__rule">

        <div class="pessoa-list-doc__title">{{ $reportTitle }}</div>

        <div class="pessoa-list-doc__filters">
            <span>| TIPO: {{ mb_strtoupper($tipoLabel, 'UTF-8') }}</span>
            <span>| SITUAÇÃO: {{ mb_strtoupper($statusLabel, 'UTF-8') }}</span>
            <span>| ORDENADO: {{ mb_strtoupper($orderLabel, 'UTF-8') }}</span>
            @if ($searchLabel)
                <span>| FILTRO: {{ mb_strtoupper($searchLabel, 'UTF-8') }}</span>
            @endif
        </div>

        <table class="pessoa-list-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ PersonListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($people as $person)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ PersonListagemReport::isNumericColumn($column) ? 'num' : '' }} {{ in_array($column, ['nome_razao', 'apelido_fantasia', 'endereco'], true) ? 'nome' : '' }}">
                                {{ PersonListagemReport::cellValue($person, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="pessoa-list-doc__empty">Nenhuma pessoa encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasPeople)
                <tfoot>
                    <tr class="pessoa-list-doc__totals">
                        @foreach ($columns as $column)
                            <td class="{{ PersonListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="pessoa-list-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span class="pessoa-list-doc__footer-page">Pág. 1</span>
        </div>
    </div>
</div>
