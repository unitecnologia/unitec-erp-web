<div
    class="erp-pdv__hot-path"
    data-erp-pdv-hot-path="1"
    wire:key="erp-pdv-hot-path"
>
    <div class="erp-pdv__grid-wrap" id="erp-pdv-grid-wrap">
        <table class="erp-pdv__grid erp-pdv__grid--cupom">
            <colgroup>
                <col class="erp-pdv__col-excluir">
                <col class="erp-pdv__col-item">
                <col class="erp-pdv__col-codigo">
                <col class="erp-pdv__col-barras">
                <col class="erp-pdv__col-descricao">
                <col class="erp-pdv__col-qtd">
                <col class="erp-pdv__col-und">
                <col class="erp-pdv__col-preco">
                <col class="erp-pdv__col-total">
            </colgroup>
            <thead>
                <tr>
                    <th class="erp-pdv__grid-col-center erp-pdv__th-excluir" aria-label="Excluir"></th>
                    <th class="erp-pdv__grid-col-center">Item</th>
                    <th>Código</th>
                    <th>Cód. Barras</th>
                    <th>Descrição</th>
                    <th class="erp-pdv__grid-col-center">Qtd</th>
                    <th class="erp-pdv__grid-col-center">Und.</th>
                    <th class="erp-pdv__grid-col-num">Preço R$</th>
                    <th class="erp-pdv__grid-col-num">Total R$</th>
                </tr>
            </thead>
            <tbody>
                @forelse (array_reverse($cupomItens, true) as $index => $item)
                    <tr
                        wire:click="selectCupomItem({{ $index }})"
                        wire:key="pdv-hot-item-{{ $index }}-{{ $item['product_id'] ?? $index }}"
                        id="erp-pdv-cupom-row-{{ $index }}"
                        @class([
                            'erp-pdv__grid-row',
                            'erp-pdv__grid-row--selected' => $selectedCupomIndex === $index,
                        ])
                    >
                        <td class="erp-pdv__grid-col-center erp-pdv__td-excluir">
                            <button
                                type="button"
                                class="erp-pdv__item-del"
                                wire:click.stop="requestExcluirCupomItem({{ $index }})"
                                title="Excluir item"
                                aria-label="Excluir item {{ $index + 1 }}"
                            >
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                    <path d="M10 11v6M14 11v6"/>
                                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                </svg>
                            </button>
                        </td>
                        <td class="erp-pdv__grid-col-center">{{ $index + 1 }}</td>
                        <td class="erp-pdv__grid-col-codigo">{{ $item['codigo'] ?? '—' }}</td>
                        <td class="erp-pdv__grid-col-codigo">{{ ($item['codigo_barras'] ?? '') !== '' ? $item['codigo_barras'] : '—' }}</td>
                        <td class="erp-pdv__grid-col-descricao">{{ $item['descricao'] ?? '—' }}</td>
                        <td class="erp-pdv__grid-col-center">{{ $this->formatCupomQuantidade((float) ($item['quantidade'] ?? 0)) }}</td>
                        <td class="erp-pdv__grid-col-center">{{ $item['unidade'] ?? 'UN' }}</td>
                        <td class="erp-pdv__grid-col-num">
                            <span class="erp-pdv__preco-base">{{ number_format((float) ($item['preco_base'] ?? $item['preco'] ?? 0), 2, ',', '') }}</span>
                            @if (($item['desconto'] ?? 0) > 0)
                                <span class="erp-pdv__preco-dif erp-pdv__preco-dif--desconto">-{{ number_format((float) $item['desconto'], 2, ',', '') }}</span>
                            @elseif (($item['acrescimo'] ?? 0) > 0)
                                <span class="erp-pdv__preco-dif erp-pdv__preco-dif--acrescimo">+{{ number_format((float) $item['acrescimo'], 2, ',', '') }}</span>
                            @endif
                        </td>
                        <td class="erp-pdv__grid-col-num">{{ number_format((float) ($item['total'] ?? 0), 2, ',', '') }}</td>
                    </tr>
                @empty
                    <tr class="erp-pdv__grid-empty">
                        <td colspan="9">&nbsp;</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Flash espelhado no DOM lateral do pai via evento; totais aqui alimentam o data-* --}}
    <div
        class="erp-pdv__hot-path-meta"
        hidden
        data-cupom-total="{{ $this->cupomTotal }}"
        data-flash-qtd="{{ $pdvFlashQtd }}"
        data-flash-preco="{{ $pdvFlashPreco }}"
        data-flash-total="{{ $pdvFlashTotal }}"
        data-preview-foto="{{ $pdvPreviewFotoUrl }}"
    ></div>

    @if ($produtoNaoEncontradoCodigo !== null)
        @include('pdvui::modals.produto-nao-encontrado')
    @endif
</div>
