<div class="tabular-doc">
    <div class="tabular-doc__frame">
        <div class="tabular-doc__header">
            <div class="tabular-doc__logo-cell">
                <div class="tabular-doc__logo">
                    @if (filled($logoDataUri ?? null))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="tabular-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="tabular-doc__company-cell">
                <span class="tabular-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco ?? null))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="tabular-doc__rule">

        <div class="tabular-doc__title">{{ $reportTitle }}</div>

        @if (! empty($summary))
            <div class="tabular-doc__filters">
                @foreach ($summary as $item)
                    <span>| {{ mb_strtoupper($item, 'UTF-8') }}</span>
                @endforeach
            </div>
        @endif

        <table class="tabular-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ in_array($column, $numericColumns ?? [], true) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ in_array($column, $numericColumns ?? [], true) ? 'num' : '' }}">
                                {{ $row[$column] ?? '' }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}" class="tabular-doc__empty">
                            {{ $emptyMessage ?? 'Nenhum registro encontrado.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($rows) && ! empty($totals))
                <tfoot>
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ in_array($column, $numericColumns ?? [], true) ? 'num' : '' }} tabular-doc__strong">
                                {{ $totals[$column] ?? '' }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="tabular-doc__meta">
            Impresso em {{ ($printedAt ?? now())->format('d/m/Y H:i') }}
        </div>
    </div>
</div>
