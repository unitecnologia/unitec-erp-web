@php
    $columns = [
        ['key' => 'numero', 'label' => 'Numero', 'sortable' => true, 'align' => 'center'],
        ['key' => 'data', 'label' => 'Data', 'sortable' => true, 'align' => 'center'],
        ['key' => 'hora_abertura', 'label' => 'Hora ab.', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-hora_abertura', 'cellClass' => 'fi-ta-cell-hora_abertura'],
        ['key' => 'hora', 'label' => 'Hora fe.', 'sortable' => true, 'align' => 'center', 'headerClass' => 'fi-ta-header-cell-hora', 'cellClass' => 'fi-ta-cell-hora'],
        ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => false, 'align' => 'start'],
        ['key' => 'vendedor', 'label' => 'Vendedor', 'sortable' => false, 'align' => 'start'],
        ['key' => 'plataforma', 'label' => 'Plataforma', 'sortable' => false, 'align' => 'center', 'html' => true],
        ['key' => 'forma_pagamento', 'label' => 'Meio de Pagamento', 'sortable' => false, 'align' => 'start'],
        ['key' => 'total', 'label' => 'Total', 'sortable' => true, 'align' => 'end', 'html' => true],
        ['key' => 'status', 'label' => 'Situação', 'sortable' => false, 'align' => 'center', 'html' => true],
        ['key' => 'entrega', 'label' => 'Entrega', 'sortable' => false, 'align' => 'center'],
        ['key' => 'tipo', 'label' => 'Tipo', 'sortable' => false, 'align' => 'center'],
        ['key' => 'pdv_numero', 'label' => 'Nº Dav', 'sortable' => false, 'align' => 'center'],
        ['key' => 'nfce', 'label' => 'NFC-e', 'sortable' => false, 'align' => 'center'],
        ['key' => 'ver_itens', 'label' => '', 'sortable' => false, 'align' => 'center', 'html' => true, 'headerClass' => 'fi-ta-header-cell-ver-itens', 'cellClass' => 'fi-ta-cell-ver-itens'],
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
                        @class(array_filter([
                            'fi-ta-header-cell',
                            'text-' . $column['align'] => filled($column['align'] ?? null),
                            $column['headerClass'] ?? null,
                        ]))
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
                    $cells = $formatter->format($record);
                    $rowClass = 'fi-ta-row' . ($index % 2 === 1 ? ' fi-striped' : '');
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $record->getKey() }}">
                    @foreach ($columns as $column)
                        @php
                            $value = $cells[$column['key']] ?? '';
                        @endphp
                        <td @class(array_filter([
                            'fi-ta-cell',
                            'text-' . $column['align'] => filled($column['align'] ?? null),
                            $column['cellClass'] ?? null,
                        ]))>
                            @if ($column['html'] ?? false)
                                {!! $value !!}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma venda encontrada
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
                    <button type="button" class="erp-vendas__btn erp-vendas__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-vendas__btn erp-vendas__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
