@foreach (($extraSections ?? []) as $section)
    @php
        $secColumns = $section['columns'] ?? [];
        $secLabels = $section['column_labels'] ?? [];
        $secRows = $section['rows'] ?? [];
        $secNumeric = $section['numeric_columns'] ?? ['qtd', 'total', 'valor', 'percentual'];
        $secTotals = is_array($section['totals'] ?? null) ? $section['totals'] : [];
        $hideHeader = (bool) ($section['hide_header'] ?? false);
        $secTitle = trim((string) ($section['title'] ?? ''));
    @endphp
    @if ($secColumns !== [] && $secRows !== [])
        <div class="tabular-doc__section">
            @if ($secTitle !== '')
                <div class="tabular-doc__section-title">{{ $secTitle }}</div>
            @endif
            <table class="tabular-doc__table tabular-doc__table--summary{{ $hideHeader ? ' tabular-doc__table--summary-plain' : '' }}">
                @unless ($hideHeader)
                    <thead>
                        <tr>
                            @foreach ($secColumns as $column)
                                <th class="{{ in_array($column, $secNumeric, true) ? 'num' : '' }}">
                                    {{ $secLabels[$column] ?? $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endunless
                <tbody>
                    @foreach ($secRows as $secRow)
                        <tr>
                            @foreach ($secColumns as $column)
                                <td class="{{ in_array($column, $secNumeric, true) ? 'num' : '' }}">
                                    {{ $secRow[$column] ?? '' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($secTotals !== [])
                    <tfoot>
                        <tr>
                            @foreach ($secColumns as $column)
                                <td class="{{ trim((in_array($column, $secNumeric, true) ? 'num' : '').' tabular-doc__strong') }}">
                                    {{ $secTotals[$column] ?? '' }}
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
@endforeach
