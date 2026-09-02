@php
    $columns = [
        ['key' => 'numero', 'label' => 'Número', 'sortable' => true, 'align' => 'center'],
        ['key' => 'data_emissao', 'label' => 'Dt. Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'data_entrada', 'label' => 'Dt. Entrada', 'sortable' => true, 'align' => 'center'],
        ['key' => 'numero_nota', 'label' => 'Nº da Nota', 'sortable' => false, 'align' => 'center'],
        ['key' => 'fornecedor', 'label' => 'Fornecedor', 'sortable' => false, 'align' => 'start'],
        ['key' => 'chave_nfe', 'label' => 'Chave', 'sortable' => false, 'align' => 'start'],
        ['key' => 'status', 'label' => 'Situação', 'sortable' => false, 'align' => 'center', 'html' => true],
        ['key' => 'total', 'label' => 'Total', 'sortable' => false, 'align' => 'end', 'html' => true],
        ['key' => 'ver_itens', 'label' => '', 'sortable' => false, 'align' => 'center', 'html' => true],
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
                    $cells = $formatter->format($record);
                    $rowClass = 'fi-ta-row' . ($index % 2 === 1 ? ' fi-striped' : '');
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $record->getKey() }}">
                    @foreach ($columns as $column)
                        @php
                            $value = $cells[$column['key']] ?? '';
                        @endphp
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
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
                        Nenhuma compra encontrada
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
                    <button type="button" class="erp-compras__btn erp-compras__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-compras__btn erp-compras__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
