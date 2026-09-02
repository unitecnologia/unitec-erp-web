@php
    use App\Models\Product;

    $productColumns = [
        ['key' => 'codigo', 'label' => 'Código', 'sortable' => true, 'align' => 'center'],
        ['key' => 'referencia', 'label' => 'Referência', 'sortable' => false, 'align' => 'center'],
        ['key' => 'codigo_barras', 'label' => 'Cód. Barras', 'sortable' => false, 'align' => 'start'],
        ['key' => 'descricao', 'label' => 'Descrição', 'sortable' => true, 'align' => 'start'],
        ['key' => 'grupo', 'label' => 'Grupo', 'sortable' => true, 'align' => 'center'],
        ['key' => 'preco_venda', 'label' => 'Preço', 'sortable' => true, 'align' => 'end', 'html' => true],
        ['key' => 'estoque', 'label' => 'Est. Atual', 'sortable' => true, 'align' => 'end'],
        ['key' => 'estoque_reservado', 'label' => 'Est. Reserv.', 'sortable' => false, 'align' => 'end'],
        ['key' => 'estoque_disponivel', 'label' => 'Est. Disp.', 'sortable' => false, 'align' => 'end'],
        ['key' => 'localizacao', 'label' => 'Localização', 'sortable' => false, 'align' => 'center'],
        ['key' => 'validade', 'label' => 'Validade', 'sortable' => true, 'align' => 'center', 'html' => true],
        ['key' => 'lote', 'label' => 'Lote', 'sortable' => true, 'align' => 'center'],
    ];

    $serialColumns = [
        ['key' => 'descricao', 'label' => 'Descrição', 'sortable' => true, 'align' => 'start'],
        ['key' => 'numero_serie', 'label' => 'Nº Série', 'sortable' => true, 'align' => 'center'],
        ['key' => 'situacao', 'label' => 'Situação', 'sortable' => false, 'align' => 'center'],
        ['key' => 'doc_saida', 'label' => 'Doc. Saída', 'sortable' => false, 'align' => 'center'],
        ['key' => 'data_baixa', 'label' => 'Data Baixa', 'sortable' => false, 'align' => 'center'],
    ];

    $columns = $isSeriais ? $serialColumns : $productColumns;
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
                    $recordKey = $record->getKey();
                    $rowClass = 'fi-ta-row' . ($index % 2 === 1 ? ' fi-striped' : '');
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $recordKey }}">
                    @if ($isSeriais)
                        <td class="fi-ta-cell">{{ $record->product?->descricao }}</td>
                        <td class="fi-ta-cell text-center">{{ $record->numero_serie }}</td>
                        <td class="fi-ta-cell text-center">{{ $record->situacao }}</td>
                        <td class="fi-ta-cell text-center">{{ $record->doc_saida ?: '—' }}</td>
                        <td class="fi-ta-cell text-center">{{ $record->data_baixa ? \Illuminate\Support\Carbon::parse($record->data_baixa)->format('d/m/Y') : '—' }}</td>
                    @else
                        @php
                            /** @var Product $record */
                            $cells = $formatter->format($record);
                        @endphp
                        @foreach ($productColumns as $column)
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
                    @endif
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        {{ $isSeriais ? 'Nenhum serial encontrado' : 'Nenhum produto encontrado' }}
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
                    <button type="button" class="erp-produtos__btn erp-produtos__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-produtos__btn erp-produtos__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
