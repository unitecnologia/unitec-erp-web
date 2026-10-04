@php
    $columns = [
        ['key' => 'baixa', 'label' => '', 'sortable' => false, 'align' => 'center', 'check' => true],
        ['key' => 'numero', 'label' => 'ID', 'sortable' => true, 'align' => 'center'],
        ['key' => 'num_compra', 'label' => 'Num.Compra', 'sortable' => false, 'align' => 'center'],
        ['key' => 'emissao', 'label' => 'Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'documento', 'label' => 'Doc', 'sortable' => false, 'align' => 'center'],
        ['key' => 'nota_fiscal', 'label' => 'Nota fiscal', 'sortable' => false, 'align' => 'center'],
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
        <colgroup>
            @foreach ($columns as $column)
                <col class="erp-pagar-col erp-pagar-col--{{ $column['key'] }}">
            @endforeach
        </colgroup>
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
                            'erp-pagar-col--'.$column['key'],
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
                @php
                    $podeMarcar = $fornecedorFilter !== 'todos'
                        && is_numeric($fornecedorFilter)
                        && (int) $record->fornecedor_id === (int) $fornecedorFilter
                        && (float) $record->saldo > 0;
                    $marcado = in_array((int) $record->getKey(), array_map('intval', $selecionadosParaBaixa), true);
                    if ($marcado) {
                        $rowClass = trim($rowClass.' erp-row-selected');
                    }
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $record->getKey() }}">
                    @foreach ($columns as $column)
                        @php
                            $value = $cells[$column['key']] ?? '';
                        @endphp
                        @php
                            $cellTitle = match ($column['key']) {
                                'fornecedor' => $record->fornecedor?->nome_razao,
                                'nota_fiscal' => $record->compra?->numero_nota,
                                default => null,
                            };
                        @endphp
                        <td
                            @class([
                                'fi-ta-cell',
                                'erp-pagar-col--'.$column['key'],
                                'text-' . $column['align'] => filled($column['align'] ?? null),
                            ])
                            @if (filled($cellTitle)) title="{{ $cellTitle }}" @endif
                        >
                            @if ($column['check'] ?? false)
                                <input
                                    type="checkbox"
                                    class="erp-pagar__check"
                                    value="{{ $record->getKey() }}"
                                    @checked($marcado)
                                    @disabled(! $podeMarcar)
                                    title="{{ $podeMarcar ? 'Marcar para baixa' : 'Selecione o fornecedor para marcar contas' }}"
                                    onclick="const row=this.closest('tr'); if(row){ row.classList.toggle('erp-row-selected', this.checked); }"
                                    wire:click.stop="$dispatch('erp-pagar-toggle-baixa', { contaId: {{ (int) $record->getKey() }}, selected: $event.target.checked })"
                                    @click.stop
                                >
                            @else
                                {{ $value }}
                            @endif
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
