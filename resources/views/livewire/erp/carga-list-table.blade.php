@php
    $columns = [
        ['key' => 'selecionar', 'label' => '', 'sortable' => false, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-selecionar'],
        ['key' => 'numero', 'label' => 'Nº', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-numero'],
        ['key' => 'data', 'label' => 'Data', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-data'],
        ['key' => 'motorista', 'label' => 'Motorista', 'sortable' => true, 'align' => 'start', 'headerClass' => 'fi-ta-header-cell-motorista'],
        ['key' => 'entregador', 'label' => 'Entregador (App)', 'sortable' => true, 'align' => 'start', 'headerClass' => 'fi-ta-header-cell-entregador'],
        ['key' => 'veiculo', 'label' => 'Veículo', 'sortable' => true, 'align' => 'start', 'headerClass' => 'fi-ta-header-cell-veiculo'],
        ['key' => 'pedidos_count', 'label' => 'Qtd. pedidos', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-pedidos_count'],
        ['key' => 'valor_total', 'label' => 'Valor total', 'sortable' => true, 'align' => 'end', 'headerClass' => 'fi-ta-header-cell-valor_total'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-status'],
    ];
@endphp

<div class="fi-ta-ctn overflow-x-auto">
    <table class="fi-ta-table">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    @php
                        $isSorted = $sortColumn === $column['key'];
                        $ariaSort = $isSorted ? ($sortDirection === 'desc' ? 'descending' : 'ascending') : 'none';
                    @endphp
                    <th
                        scope="col"
                        @class([
                            'fi-ta-header-cell',
                            $column['headerClass'] ?? null,
                            'fi-ta-header-cell-sorted' => $isSorted,
                            'text-' . $column['align'] => filled($column['align'] ?? null),
                        ])
                        aria-sort="{{ $ariaSort }}"
                    >
                        @if ($column['sortable'] ?? false)
                            <button
                                type="button"
                                class="fi-ta-header-cell-sort-btn"
                                wire:click="sortBy('{{ $column['key'] }}')"
                            >{{ $column['label'] }}</button>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $index => $record)
                @php
                    $recordKey = (string) (int) $record->getKey();
                    $checked = in_array($recordKey, $selecionados, true);
                    $selected = (int) $highlightedRecordId === (int) $record->getKey();
                    $rowClass = 'fi-ta-row'
                        .($index % 2 === 1 ? ' fi-striped' : '')
                        .($checked ? ' erp-cargas-row--checked' : '')
                        .($selected ? ' erp-row-selected' : '');
                    $cells = $formatter->format($record);
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $recordKey }}" wire:key="carga-row-{{ $recordKey }}">
                    <td class="fi-ta-cell text-center">
                        <input
                            type="checkbox"
                            class="erp-cargas__check"
                            value="{{ $recordKey }}"
                            @checked($checked)
                            wire:key="carga-sel-{{ $recordKey }}-{{ $checked ? '1' : '0' }}"
                            x-data
                            x-init="
                                const paint = () => {
                                    const row = $el.closest('.fi-ta-row');
                                    if (row) {
                                        row.classList.toggle('erp-cargas-row--checked', $el.checked);
                                    }
                                };
                                paint();
                                window.addEventListener('pageshow', () => {
                                    paint();
                                    queueMicrotask(paint);
                                    setTimeout(paint, 0);
                                    setTimeout(paint, 50);
                                });
                            "
                            @click.stop="
                                const row = $el.closest('.fi-ta-row');
                                if (row) {
                                    row.classList.toggle('erp-cargas-row--checked', $el.checked);
                                }
                                $wire.syncSelecionado({{ (int) $recordKey }}, $el.checked);
                            "
                            aria-label="Selecionar carga {{ $record->numero }}"
                        >
                    </td>
                    @foreach ($columns as $column)
                        @continue($column['key'] === 'selecionar')
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
                            <div class="fi-size-sm fi-ta-text-item {{ ($column['align'] ?? '') === 'center' ? 'fi-align-center' : '' }} {{ ($column['align'] ?? '') === 'end' ? 'fi-align-end' : '' }} {{ $column['key'] === 'numero' ? 'fi-font-semibold' : '' }}">
                                {!! $cells[$column['key']] ?? '—' !!}
                            </div>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma carga encontrada
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($records->hasPages())
        <nav class="fi-pagination mt-2 px-2" aria-label="Paginação">
            <div class="flex items-center gap-2 text-sm">
                @if ($records->onFirstPage())
                    <span class="opacity-50">Anterior</span>
                @else
                    <button type="button" class="fi-btn fi-btn-color-gray fi-size-sm" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="fi-btn fi-btn-color-gray fi-size-sm" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
