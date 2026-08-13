<div class="erp-orc-produto-lookup">
    <table class="erp-orc-produto-lookup__table">
        <thead>
            <tr>
                <th>Cód.</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->produtoResults as $index => $row)
                <tr
                    wire:key="orc-produto-{{ $row['id'] }}"
                    wire:mousedown.prevent="selectProdutoResult({{ $index }})"
                    @class(['erp-orc-produto-lookup__row', 'erp-orc-produto-lookup__row--active' => $this->selectedProdutoIndex === $index])
                >
                    <td>{{ $row['codigo'] ?: '—' }}</td>
                    <td>{{ $row['descricao'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
