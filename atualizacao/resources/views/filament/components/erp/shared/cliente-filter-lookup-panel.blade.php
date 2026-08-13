<div class="erp-cliente-filter-lookup">
    <table class="erp-cliente-filter-lookup__table">
        <thead>
            <tr>
                <th>Razão Social</th>
                <th>Fantasia</th>
                <th>CPF/CNPJ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->clienteResults as $index => $row)
                <tr
                    wire:key="cliente-filter-{{ $row['id'] }}"
                    wire:mousedown.prevent="selectClienteFilterResult({{ $index }})"
                    @class(['erp-cliente-filter-lookup__row', 'erp-cliente-filter-lookup__row--active' => $this->selectedClienteIndex === $index])
                >
                    <td>{{ $row['nome'] }}</td>
                    <td>{{ $row['fantasia'] ?: '—' }}</td>
                    <td>{{ $row['cpf_cnpj'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
