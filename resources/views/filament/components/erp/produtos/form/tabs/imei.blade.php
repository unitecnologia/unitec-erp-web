<div class="erp-produtos-child-grid">
    <div class="erp-produtos-child-grid__toolbar">
        <button type="button" wire:click="addImeiRow" class="erp-produtos-child-grid__btn">+ Novo</button>
        <button type="button" wire:click="deleteImeiRow" class="erp-produtos-child-grid__btn">− Excluir</button>
        <span class="erp-produtos-child-grid__hint">Cadastre os IMEI vinculados ao produto.</span>
    </div>

    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>IMEI</th>
                    <th>Fornecedor (ID)</th>
                    <th>Ativo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->imeiRows as $index => $row)
                    <tr
                        wire:click="selectImeiRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedImeiIndex === $index])
                    >
                        <td>
                            <input type="text" wire:model.blur="imeiRows.{{ $index }}.imei" maxlength="250" class="erp-pcad-form__input" onclick="event.stopPropagation()">
                        </td>
                        <td>
                            <input type="text" wire:model.blur="imeiRows.{{ $index }}.fornecedor_id" data-mask="integer" class="erp-pcad-form__input erp-produtos-child-grid__input-num" onclick="event.stopPropagation()">
                        </td>
                        <td class="erp-produtos-child-grid__check">
                            <input type="checkbox" wire:model="imeiRows.{{ $index }}.ativo" onclick="event.stopPropagation()">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="erp-produtos-child-grid__empty">Nenhum IMEI cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
