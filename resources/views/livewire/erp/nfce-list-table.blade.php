@php
    $columns = [
        ['key' => 'transmitir', 'label' => '', 'sortable' => false, 'align' => 'center'],
        ['key' => 'serie', 'label' => '>>Série', 'sortable' => false, 'align' => 'center'],
        ['key' => 'numero', 'label' => 'NFC-e', 'sortable' => true, 'align' => 'center'],
        ['key' => 'fechado_em', 'label' => 'Dt.Emissão', 'sortable' => true, 'align' => 'center'],
        ['key' => 'chave', 'label' => 'Chave', 'sortable' => false, 'align' => 'start'],
        ['key' => 'protocolo', 'label' => 'Protocolo', 'sortable' => false, 'align' => 'center'],
        ['key' => 'cpf', 'label' => 'CPF', 'sortable' => false, 'align' => 'center'],
        ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => false, 'align' => 'start'],
        ['key' => 'caixa', 'label' => 'Caixa', 'sortable' => false, 'align' => 'start'],
        ['key' => 'vendedor', 'label' => 'Vendedor', 'sortable' => false, 'align' => 'start'],
        ['key' => 'desc_acres', 'label' => 'Desc/Acrés', 'sortable' => false, 'align' => 'end'],
        ['key' => 'total', 'label' => 'Total', 'sortable' => false, 'align' => 'end'],
        ['key' => 'pedido', 'label' => 'Pedido', 'sortable' => false, 'align' => 'center'],
        ['key' => 'dav', 'label' => 'Nº Dav', 'sortable' => false, 'align' => 'center'],
    ];

    if ($showSituacao) {
        $columns[] = ['key' => 'situacao', 'label' => 'Situação SEFAZ', 'sortable' => false, 'align' => 'start'];
    }
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
                    $checked = in_array($recordKey, $selectedTransmitIds, true);
                    $selected = (int) $highlightedRecordId === (int) $record->getKey();
                    $rowClass = 'fi-ta-row'
                        .($index % 2 === 1 ? ' fi-striped' : '')
                        .($checked ? ' erp-nfce-row--checked' : '')
                        .($selected ? ' erp-row-selected' : '');
                    $cells = $formatter->format($record, $showSituacao);
                    $podeMarcar = $isContingenciaTab
                        && $record->status === \App\Models\PdvVendaNfce::STATUS_CONTINGENCIA
                        && ! $record->simulada;
                @endphp
                <tr class="{{ $rowClass }}" data-record-key="{{ $recordKey }}">
                    <td class="fi-ta-cell text-center">
                        @if ($podeMarcar)
                            <input
                                type="checkbox"
                                class="erp-nfce__transmit-check"
                                value="{{ $recordKey }}"
                                @checked($checked)
                                wire:click.prevent.stop="toggleTransmit({{ (int) $recordKey }})"
                                title="Marcar para transmitir"
                            >
                        @endif
                    </td>
                    @foreach ($columns as $column)
                        @continue($column['key'] === 'transmitir')
                        <td @class(['fi-ta-cell', 'text-' . $column['align'] => filled($column['align'] ?? null)])>
                            <div class="fi-size-sm fi-font-semibold fi-ta-text-item {{ ($column['align'] ?? '') === 'center' ? 'fi-align-center' : '' }} {{ ($column['align'] ?? '') === 'end' ? 'fi-align-end' : '' }}">
                                {!! $cells[$column['key']] ?? '—' !!}
                            </div>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="fi-ta-row">
                    <td class="fi-ta-cell" colspan="{{ count($columns) }}">
                        Nenhuma NFC-e encontrada
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

    @if ($this->statusFilter === \App\Models\PdvVendaNfce::TAB_INUTILIZADOS)
        <section class="erp-nfce-inut" aria-labelledby="erp-nfce-inut-title">
            <header class="erp-nfce-inut__header">
                <h3 id="erp-nfce-inut-title" class="erp-nfce-inut__title">Faixas inutilizadas na SEFAZ</h3>
                <span class="erp-nfce-inut__hint">Inclui números que não chegaram a gerar NFC-e · período pela data da inutilização</span>
            </header>

            @if ($inutilizacoesFaixas === [])
                <p class="erp-nfce-inut__empty">Nenhuma faixa inutilizada no período.</p>
            @else
                <div class="erp-nfce-inut__scroll">
                    <table class="erp-nfce-inut__table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th class="num">Série</th>
                                <th class="num">Faixa</th>
                                <th>Protocolo</th>
                                <th class="num">cStat</th>
                                <th>Ambiente</th>
                                <th>Usuário</th>
                                <th>Justificativa</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inutilizacoesFaixas as $faixa)
                                @php
                                    $ini = (int) $faixa['numero_inicial'];
                                    $fim = (int) $faixa['numero_final'];
                                @endphp
                                <tr wire:key="nfce-inut-{{ $faixa['id'] }}">
                                    <td>{{ $faixa['created_at'] ? \Illuminate\Support\Carbon::parse($faixa['created_at'])->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="num">{{ $faixa['serie'] }}</td>
                                    <td class="num">{{ $ini === $fim ? $ini : $ini.' a '.$fim }}</td>
                                    <td>{{ $faixa['protocolo'] ?: '—' }}</td>
                                    <td class="num">{{ $faixa['status_codigo'] ?: '—' }}</td>
                                    <td>{{ (string) $faixa['ambiente'] === '1' ? 'Produção' : 'Homologação' }}</td>
                                    <td>{{ $faixa['usuario'] ?: '—' }}</td>
                                    <td class="erp-nfce-inut__just" title="{{ $faixa['justificativa'] }}">{{ $faixa['justificativa'] ?: '—' }}</td>
                                    <td class="erp-nfce-inut__acao">
                                        @if ($faixa['tem_xml'])
                                            <button
                                                type="button"
                                                class="erp-nfce-inut__xml"
                                                wire:click="baixarXmlInutilizacao({{ (int) $faixa['id'] }})"
                                                wire:loading.attr="disabled"
                                            >XML</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
