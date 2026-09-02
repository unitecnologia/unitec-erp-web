@php
    $columns = [
        ['key' => 'codigo', 'label' => 'Código', 'sortable' => true, 'align' => 'center'],
        ['key' => 'nome_razao', 'label' => 'Nome/Razão', 'sortable' => true, 'align' => 'start'],
        ['key' => 'apelido_fantasia', 'label' => 'Apelido/Fantasia', 'sortable' => false, 'align' => 'start'],
        ['key' => 'cpf_cnpj', 'label' => 'CPF/CNPJ', 'sortable' => false, 'align' => 'start'],
        ['key' => 'rg_ie', 'label' => 'RG/IE', 'sortable' => false, 'align' => 'center'],
        ['key' => 'endereco_lista', 'label' => 'Endereço', 'sortable' => false, 'align' => 'start'],
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
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
                            {{ $cells[$column['key']] ?? '' }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma pessoa encontrada
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
                    <button type="button" class="erp-pessoas__btn erp-pessoas__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-pessoas__btn erp-pessoas__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
