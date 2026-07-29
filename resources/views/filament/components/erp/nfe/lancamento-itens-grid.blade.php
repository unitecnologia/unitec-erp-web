<div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--itens">
    <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--itens">
        <colgroup>
            <col class="erp-nfe-col-item">
            <col class="erp-nfe-col-codigo">
            <col class="erp-nfe-col-produto">
            <col class="erp-nfe-col-cfop">
            <col class="erp-nfe-col-cst">
            <col class="erp-nfe-col-preco">
            <col class="erp-nfe-col-qtd">
            <col class="erp-nfe-col-unid">
            <col class="erp-nfe-col-total">
            <col class="erp-nfe-col-pedido">
        </colgroup>
        <thead>
            <tr>
                <th>Item</th>
                <th>Cód.</th>
                <th>Produto</th>
                <th class="erp-nfe-lancamento-modal__center">CFOP</th>
                <th class="erp-nfe-lancamento-modal__center">CST</th>
                <th class="erp-nfe-lancamento-modal__num">Preço</th>
                <th class="erp-nfe-lancamento-modal__num">Qtd.</th>
                <th class="erp-nfe-lancamento-modal__center">Unid.</th>
                <th class="erp-nfe-lancamento-modal__num">Total</th>
                <th class="erp-nfe-lancamento-modal__center">Pedido</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->nfeModalRows as $index => $row)
                <tr
                    wire:key="{{ $row['key'] ?? ('nfe-item-' . $index) }}"
                    wire:click="selectNfeRow({{ $index }})"
                    @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])
                >
                    <td class="erp-nfe-lancamento-modal__center">{{ $row['item'] ?? ($index + 1) }}</td>
                    <td class="erp-nfe-lancamento-modal__cell-text erp-nfe-lancamento-modal__cell-text--codigo">{{ $row['codigo'] ?? '' }}</td>
                    <td class="erp-nfe-lancamento-modal__cell-text erp-nfe-lancamento-modal__cell-text--produto" title="{{ $row['descricao'] ?? '' }}">{{ $row['descricao'] ?? '' }}</td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.cfop"
                            wire:keydown.enter.prevent="resolveNfeItemCfop({{ $index }}, $event.target.value)"
                            wire:click.stop
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                            title="Digite o CFOP e pressione Enter"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.cst"
                            wire:click.stop
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.valor_unitario"
                            wire:click.stop
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.quantidade"
                            wire:click.stop
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.unidade"
                            wire:click.stop
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                        >
                    </td>
                    <td class="erp-nfe-lancamento-modal__num">{{ $row['total'] ?? '0,00' }}</td>
                    <td class="erp-nfe-lancamento-modal__center">—</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="erp-lookup-modal__empty">
                        Nenhum item. Informe código, barras ou nome na barra Produto acima e pressione Enter.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
