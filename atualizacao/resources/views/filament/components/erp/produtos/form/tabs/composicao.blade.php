<div class="erp-produtos-child-grid">
    <div class="erp-produtos-composicao__form">
        <div class="erp-produtos-composicao__fields">
            <div class="erp-produtos-composicao__field">
                <label for="pprod-comp-codigo">Código Produto</label>
                <input id="pprod-comp-codigo" type="text" wire:model="compositionProductCodigo" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-composicao__field">
                <label for="pprod-comp-qtd">Quantidade</label>
                <input id="pprod-comp-qtd" type="text" wire:model="compositionQuantidade" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
            </div>
            <div class="erp-produtos-composicao__field">
                <label for="pprod-comp-preco">Preço</label>
                <input id="pprod-comp-preco" type="text" wire:model="compositionPreco" data-mask="money-br" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
            </div>
            <div class="erp-produtos-composicao__actions">
                <button type="button" wire:click="addCompositionItem" class="erp-produtos-child-grid__btn">✓ Incluir</button>
                <button type="button" wire:click="deleteCompositionItem" class="erp-produtos-child-grid__btn">− Excluir</button>
            </div>
        </div>
        <p class="erp-produtos-child-grid__hint">Total composição: R$ {{ number_format($this->compositionRowsTotal(), 2, ',', '.') }}</p>
    </div>

    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Preço</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->compositionRows as $index => $row)
                    <tr
                        wire:click="selectCompositionRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedCompositionIndex === $index])
                    >
                        <td>{{ $row['codigo'] ?? '—' }}</td>
                        <td>{{ $row['descricao'] ?? '—' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['quantidade'] ?? '0' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['preco'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['total'] ?? '0,00' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="erp-produtos-child-grid__empty">Nenhum item de composição.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
