@php
    $columns = [
        ['key' => 'selecao', 'label' => '', 'sortable' => false, 'align' => 'center'],
        ['key' => 'numero', 'label' => 'Número', 'sortable' => true, 'align' => 'center'],
        ['key' => 'npedido', 'label' => 'OS/DAV', 'sortable' => false, 'align' => 'start'],
        ['key' => 'data_emissao', 'label' => 'Dt.Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'data_saida', 'label' => 'Dt.Saída', 'sortable' => false, 'align' => 'center'],
        ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => false, 'align' => 'start'],
        ['key' => 'chave', 'label' => 'Chave', 'sortable' => false, 'align' => 'start'],
        ['key' => 'protocolo', 'label' => 'Protocolo', 'sortable' => false, 'align' => 'center'],
        ['key' => 'finalidade', 'label' => 'Finalidade', 'sortable' => false, 'align' => 'center'],
        ['key' => 'status', 'label' => 'Situação', 'sortable' => false, 'align' => 'center'],
        ['key' => 'total', 'label' => 'Total', 'sortable' => false, 'align' => 'end'],
        ['key' => 'historico', 'label' => '', 'sortable' => false, 'align' => 'center'],
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
                    $recordKey = (string) $record->getKey();
                    $selected = (int) $highlightedRecordId === (int) $record->getKey();
                    $rowClass = 'fi-ta-row'
                        .($index % 2 === 1 ? ' fi-striped' : '')
                        .($selected ? ' erp-row-selected' : '');
                    $cells = $formatter->format($record);
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $recordKey }}" wire:key="nfe-row-{{ $recordKey }}">
                    <td class="fi-ta-cell text-center">
                        <input
                            type="checkbox"
                            class="erp-nfe__select-check"
                            value="{{ $recordKey }}"
                            @checked($selected)
                            wire:click.prevent.stop="toggleSelecionado({{ (int) $recordKey }})"
                            wire:key="nfe-sel-{{ $recordKey }}-{{ $selected ? '1' : '0' }}"
                            title="{{ $selected ? 'Desmarcar NF-e' : 'Marcar NF-e' }}"
                            @click.stop
                        >
                    </td>
                    @foreach ($columns as $column)
                        @continue(in_array($column['key'], ['selecao', 'historico'], true))
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
                            <div class="fi-size-sm fi-font-semibold fi-ta-text-item {{ ($column['align'] ?? '') === 'center' ? 'fi-align-center' : '' }} {{ ($column['align'] ?? '') === 'end' ? 'fi-align-end' : '' }}">
                                {!! $cells[$column['key']] ?? '—' !!}
                            </div>
                        </td>
                    @endforeach
                    <td class="fi-ta-cell text-center">
                        <span
                            role="button"
                            tabindex="0"
                            wire:click.stop="openHistorico({{ (int) $recordKey }})"
                            wire:keydown.enter.stop="openHistorico({{ (int) $recordKey }})"
                            class="erp-nfe__eye-btn"
                            title="Histórico da NF-e"
                            aria-label="Histórico da NF-e"
                        >
                            <svg
                                class="erp-nfe__eye-icon"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.75"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                />
                            </svg>
                        </span>
                    </td>
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma NF-e encontrada
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
