@php
    $columns = [
        ['key' => 'baixa', 'label' => '', 'sortable' => false, 'align' => 'center', 'html' => true],
        ['key' => 'documento', 'label' => 'Pedido', 'sortable' => false, 'align' => 'center'],
        ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => false, 'align' => 'start'],
        ['key' => 'emissao', 'label' => 'Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'historico', 'label' => 'Histórico', 'sortable' => false, 'align' => 'start'],
        ['key' => 'cartao_maquininha', 'label' => 'Maquininha', 'sortable' => false, 'align' => 'center'],
        ['key' => 'cartao_bandeira', 'label' => 'Bandeira', 'sortable' => false, 'align' => 'center'],
        ['key' => 'vencimento', 'label' => 'Vencimento', 'sortable' => true, 'align' => 'center'],
        ['key' => 'valor', 'label' => 'Valor', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'numero_cheque', 'label' => 'Nº Cheque', 'sortable' => false, 'align' => 'center'],
        ['key' => 'desconto', 'label' => 'Desconto', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'juros', 'label' => 'Juros', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'multa', 'label' => 'Multa', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'valor_recebido', 'label' => 'V.Recebido', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'recebido_em', 'label' => 'Recebido Em', 'sortable' => false, 'align' => 'center'],
        ['key' => 'saldo', 'label' => 'Saldo', 'sortable' => false, 'align' => 'start', 'html' => true],
        ['key' => 'visualizar', 'label' => '', 'sortable' => false, 'align' => 'center', 'html' => true],
    ];
@endphp

<div class="fi-ta-ctn">
    <table class="fi-ta-table">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    @php
                        $isSorted = $sortColumn === $column['key'];
                        $ariaSort = $isSorted ? ($sortDirection === 'desc' ? 'descending' : 'ascending') : 'none';
                    @endphp
                    <th scope="col" class="fi-ta-header-cell" aria-sort="{{ $ariaSort }}">
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
                    $cells = $formatter->format($record, $clienteFilter, $selecionadosParaBaixa);
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
                        Nenhuma conta a receber encontrada
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
                    <button type="button" class="erp-receber__btn erp-receber__btn--secondary" wire:click="previousPage">Anterior</button>
                @endif

                <span>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>

                @if ($records->hasMorePages())
                    <button type="button" class="erp-receber__btn erp-receber__btn--secondary" wire:click="nextPage">Próxima</button>
                @else
                    <span class="opacity-50">Próxima</span>
                @endif
            </div>
        </nav>
    @endif
</div>
