<div class="erp-produtos-child-grid">
    <div class="erp-produtos-tabpreco__form">
        <div class="erp-produtos-tabpreco__fields">
            <div class="erp-produtos-tabpreco__field">
                <label for="pprod-tabpreco-tabela">Tabela</label>
                <select id="pprod-tabpreco-tabela" wire:model="tabPrecoSelectedTableId" class="erp-pcad-form__select">
                    @foreach ($this->priceTableOptions as $option)
                        <option value="{{ $option['id'] }}">{{ $option['codigo'] }} - {{ $option['descricao'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="erp-produtos-tabpreco__field">
                <label for="pprod-tabpreco-valor">Valor</label>
                <input id="pprod-tabpreco-valor" type="text" wire:model="tabPrecoValor" data-mask="money-br" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
            </div>
            <div class="erp-produtos-tabpreco__field">
                <label for="pprod-tabpreco-fator">Fator</label>
                <input id="pprod-tabpreco-fator" type="text" wire:model="tabPrecoFator" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
            </div>
            <div class="erp-produtos-tabpreco__actions">
                <button type="button" wire:click="startPriceTableItem" class="erp-produtos-child-grid__btn">+ Novo</button>
                <button type="button" wire:click="savePriceTableItem" class="erp-produtos-child-grid__btn">✓ Gravar</button>
                <button type="button" wire:click="deletePriceTableItem" class="erp-produtos-child-grid__btn">− Excluir</button>
            </div>
        </div>
    </div>

    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>Tabela</th>
                    <th>Valor</th>
                    <th>Fator</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->priceTableRows as $index => $row)
                    <tr
                        wire:click="selectPriceTableRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedPriceTableRowIndex === $index])
                    >
                        <td>{{ $row['tabela'] ?? '—' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['valor'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['fator'] ?? '0' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="erp-produtos-child-grid__empty">Nenhum item de tabela de preço.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
