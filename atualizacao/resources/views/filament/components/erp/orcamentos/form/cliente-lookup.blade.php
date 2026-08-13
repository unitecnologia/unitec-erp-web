<div class="erp-orc-cliente-lookup">
    <table class="erp-orc-cliente-lookup__table">
        <thead>
            <tr>
                <th>Razão Social</th>
                <th>Nome Fantasia</th>
                <th>CPF/CNPJ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->clienteResults as $index => $row)
                <tr
                    wire:key="orc-cliente-{{ $row['id'] }}"
                    wire:mousedown.prevent="selectClienteResult({{ $index }})"
                    @class(['erp-orc-cliente-lookup__row', 'erp-orc-cliente-lookup__row--active' => $this->selectedClienteIndex === $index])
                >
                    <td>{{ $row['nome'] }}</td>
                    <td>{{ $row['fantasia'] ?: '—' }}</td>
                    <td>{{ $row['cpf_cnpj'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
