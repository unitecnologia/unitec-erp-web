<div class="erp-cliente-filter-lookup erp-cliente-filter-lookup--local">
    <table class="erp-cliente-filter-lookup__table">
        <thead>
            <tr>
                <th>Razão Social</th>
                <th>Fantasia</th>
                <th>CPF/CNPJ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->localFornecedorResults as $index => $row)
                <tr
                    wire:key="local-fornecedor-{{ $row['id'] }}"
                    wire:click="highlightLocalFornecedorResult({{ $index }})"
                    x-on:dblclick.prevent="
                        const panel = $el.closest('.erp-cliente-filter-lookup');
                        if (panel) panel.style.display = 'none';
                        $wire.selectLocalFornecedorResult({{ $index }});
                    "
                    @class(['erp-cliente-filter-lookup__row', 'erp-cliente-filter-lookup__row--active' => $this->selectedLocalFornecedorIndex === $index])
                >
                    <td>{{ $row['nome'] }}</td>
                    <td>{{ $row['fantasia'] ?: '—' }}</td>
                    <td>{{ $row['cpf_cnpj'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
