<div
    class="erp-pdv__hot-path"
    data-erp-pdv-hot-path="1"
    wire:key="erp-pdv-hot-path"
>
    <div class="erp-pdv__grid-wrap" id="erp-pdv-grid-wrap">
        <table class="erp-pdv__grid erp-pdv__grid--cupom">
            <colgroup>
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
                        class="erp-pdv__grid-row"
                    >
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
                        <td colspan="8">&nbsp;</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="erp-pdv__product-line" id="erp-pdv-product-name" aria-live="polite">{{ $pdvPreviewProductName }}</div>

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
