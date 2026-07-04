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
            @foreach ($this->localClienteResults as $index => $row)
                <tr
                    wire:key="local-cliente-{{ $row['id'] }}"
                    wire:click="highlightLocalClienteResult({{ $index }})"
                    wire:dblclick.prevent="selectLocalClienteResult({{ $index }})"
                    @class(['erp-cliente-filter-lookup__row', 'erp-cliente-filter-lookup__row--active' => $this->selectedLocalClienteIndex === $index])
                >
                    <td>{{ $row['nome'] }}</td>
                    <td>{{ $row['fantasia'] ?: '—' }}</td>
                    <td>{{ $row['cpf_cnpj'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
