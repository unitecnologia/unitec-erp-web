@php
    $columns = [
        ['key' => 'codigo', 'label' => '>>Código', 'sortable' => true, 'align' => 'center'],
        ['key' => 'emissao', 'label' => 'Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'documento', 'label' => 'Documento', 'sortable' => false, 'align' => 'start'],
        ['key' => 'historico', 'label' => 'Histórico', 'sortable' => false, 'align' => 'start'],
        ['key' => 'plano_contas', 'label' => 'Plano de Contas', 'sortable' => false, 'align' => 'start'],
        ['key' => 'conta', 'label' => 'Contas', 'sortable' => false, 'align' => 'start'],
        ['key' => 'entrada', 'label' => 'Entrada', 'sortable' => true, 'align' => 'end', 'html' => true, 'cellClass' => 'erp-caixa-money-cell'],
        ['key' => 'saida', 'label' => 'Saída', 'sortable' => true, 'align' => 'end', 'html' => true, 'cellClass' => 'erp-caixa-money-cell'],
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
                    unset($cells['row_class']);
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
                        Nenhum lançamento encontrado
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
                    <button type="button" class="erp-caixa__btn erp-caixa__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-caixa__btn erp-caixa__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
