@php
    /** @var \App\Support\Erp\OsListRowFormatter $formatter */
    $columns = [
        ['key' => 'numero', 'label' => 'Número', 'sortable' => true, 'col' => 'numero', 'align' => 'center'],
        ['key' => 'nfse', 'label' => 'NFS-e', 'sortable' => false, 'col' => 'nfse', 'align' => 'center'],
        ['key' => 'nfe', 'label' => 'NF-e', 'sortable' => false, 'col' => 'nfe', 'align' => 'center'],
        ['key' => 'nfce', 'label' => 'NFC-e', 'sortable' => false, 'col' => 'nfce', 'align' => 'center'],
        ['key' => 'data', 'label' => 'Data', 'sortable' => true, 'col' => 'data', 'align' => 'center'],
        ['key' => 'hora_abertura', 'label' => 'H.Aber.', 'sortable' => false, 'col' => 'hora-abertura', 'align' => 'center'],
        ['key' => 'hora_fechamento', 'label' => 'H.Fech.', 'sortable' => false, 'col' => 'hora-fechamento', 'align' => 'center'],
        ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => false, 'col' => 'cliente', 'align' => 'start'],
        ['key' => 'atendente', 'label' => 'Atendente', 'sortable' => false, 'col' => 'atendente', 'align' => 'start'],
        ['key' => 'equipamento', 'label' => 'Equipamento', 'sortable' => false, 'col' => 'equipamento', 'align' => 'start'],
        ['key' => 'placa', 'label' => 'Placa', 'sortable' => false, 'col' => 'placa', 'align' => 'center'],
        ['key' => 'meio_pagamento', 'label' => 'Meio de Pag.', 'sortable' => false, 'col' => 'meio-pagamento', 'align' => 'start', 'html' => true],
        ['key' => 'envio', 'label' => 'Env.', 'sortable' => false, 'col' => 'envio', 'align' => 'center', 'html' => true],
        ['key' => 'situacao', 'label' => 'Situação', 'sortable' => false, 'col' => 'situacao', 'align' => 'center', 'html' => true],
        ['key' => 'desconto', 'label' => 'Desconto', 'sortable' => false, 'col' => 'desconto', 'align' => 'end', 'html' => true],
        ['key' => 'total', 'label' => 'Total', 'sortable' => false, 'col' => 'total', 'align' => 'end', 'html' => true],
        ['key' => 'espelho', 'label' => '', 'sortable' => false, 'col' => 'espelho', 'align' => 'center', 'html' => true],
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
                        $colClass = 'fi-ta-header-cell erp-os-col erp-os-col-'.$column['col'];
                    @endphp
                    <th
                        scope="col"
                        @class([
                            $colClass,
                            'text-'.$column['align'] => filled($column['align'] ?? null),
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
                    $extraClasses = $formatter->rowClasses($record);
                    $meta = $formatter->selectionMeta($record);
                    $selected = $highlightedRecordId !== null && (int) $highlightedRecordId === (int) $record->getKey();
                    $rowClass = trim('fi-ta-row'
                        .($index % 2 === 1 ? ' fi-striped' : '')
                        .($selected ? ' erp-row-selected' : '')
                        .(count($extraClasses) ? ' '.implode(' ', $extraClasses) : '')
                    );
                @endphp
                <tr
                    class="{{ $rowClass }}"
                    data-record-key="{{ $record->getKey() }}"
                    data-erp-os-meta="{{ e(json_encode($meta, JSON_UNESCAPED_UNICODE)) }}"
                >
                    @foreach ($columns as $column)
                        @php
                            $value = $cells[$column['key']] ?? '';
                            $cellClass = 'fi-ta-cell erp-os-col erp-os-col-'.$column['col'];
                        @endphp
                        <td @class([
                            $cellClass,
                            'text-'.$column['align'] => filled($column['align'] ?? null),
                        ])>
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
                        Nenhuma ordem de serviço encontrada
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
                    <button type="button" class="erp-orcamentos__btn erp-orcamentos__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-orcamentos__btn erp-orcamentos__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
