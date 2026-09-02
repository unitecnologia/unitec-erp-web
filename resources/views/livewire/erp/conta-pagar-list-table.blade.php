@php
    $columns = [
        ['key' => 'numero', 'label' => 'Número', 'sortable' => true, 'align' => 'center'],
        ['key' => 'emissao', 'label' => 'Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'documento', 'label' => 'Doc', 'sortable' => false, 'align' => 'center'],
        ['key' => 'fornecedor', 'label' => 'Fornecedor', 'sortable' => false, 'align' => 'start'],
        ['key' => 'vencimento', 'label' => 'Vencimento', 'sortable' => true, 'align' => 'center'],
        ['key' => 'valor', 'label' => 'Valor', 'sortable' => false, 'align' => 'end'],
        ['key' => 'desconto', 'label' => 'Desconto', 'sortable' => false, 'align' => 'end'],
        ['key' => 'juros', 'label' => 'Juros', 'sortable' => false, 'align' => 'end'],
        ['key' => 'valor_pago', 'label' => 'Vl. Pago', 'sortable' => false, 'align' => 'end'],
        ['key' => 'pago_em', 'label' => 'Pago Em', 'sortable' => false, 'align' => 'center'],
        ['key' => 'saldo', 'label' => 'Saldo', 'sortable' => false, 'align' => 'end'],
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
                    $extraClasses = implode(' ', $cells['row_class'] ?? []);
                    $rowClass = trim('fi-ta-row' . ($index % 2 === 1 ? ' fi-striped' : '') . ' ' . $extraClasses);
                    unset($cells['row_class']);
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $record->getKey() }}">
                    @foreach ($columns as $column)
                        @php
                            $value = $cells[$column['key']] ?? '';
                        @endphp
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
                            {{ $value }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma conta a pagar encontrada
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
                    <button type="button" class="erp-pagar__btn erp-pagar__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-pagar__btn erp-pagar__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
