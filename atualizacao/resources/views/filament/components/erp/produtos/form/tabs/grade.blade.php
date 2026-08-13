<div class="erp-produtos-child-grid">
    <div class="erp-produtos-child-grid__toolbar">
        <button type="button" wire:click="addGradeRow" class="erp-produtos-child-grid__btn">+ Novo</button>
        <button type="button" wire:click="deleteGradeRow" class="erp-produtos-child-grid__btn">− Excluir</button>
        <span class="erp-produtos-child-grid__hint">Total grade: {{ number_format($this->gradeRowsTotalQty(), 3, ',', '.') }}</span>
    </div>

    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Tamanho</th>
                    <th>Qtd</th>
                    <th>Preço</th>
                    <th>Pr. Atacado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->gradeRows as $index => $row)
                    <tr
                        wire:click="selectGradeRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedGradeIndex === $index])
                    >
                        <td>
                            <input type="text" wire:model.blur="gradeRows.{{ $index }}.descricao" class="erp-pcad-form__input" onclick="event.stopPropagation()">
                        </td>
                        <td>
                            <input type="text" wire:model.blur="gradeRows.{{ $index }}.tamanho" class="erp-pcad-form__input erp-produtos-child-grid__input-sm" onclick="event.stopPropagation()">
                        </td>
                        <td>
                            <input type="text" wire:model.blur="gradeRows.{{ $index }}.qtd" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num" onclick="event.stopPropagation()">
                        </td>
                        <td>
                            <input type="text" wire:model.blur="gradeRows.{{ $index }}.preco" data-mask="money-br" class="erp-pcad-form__input erp-produtos-child-grid__input-num" onclick="event.stopPropagation()">
                        </td>
                        <td>
                            <input type="text" wire:model.blur="gradeRows.{{ $index }}.preco_atacado" data-mask="money-br" class="erp-pcad-form__input erp-produtos-child-grid__input-num" onclick="event.stopPropagation()">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="erp-produtos-child-grid__empty">Nenhuma variação de grade cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
