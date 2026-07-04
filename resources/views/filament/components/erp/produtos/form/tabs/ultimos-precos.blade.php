<div class="erp-produtos-child-grid">
    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>Último Preço</th>
                    <th>Data</th>
                    <th>Usuário</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->priceHistoryRows as $row)
                    <tr class="erp-produtos-child-grid__row">
                        <td class="erp-produtos-child-grid__num">R$ {{ $row['ultimo_preco'] ?? '0,00' }}</td>
                        <td>{{ $row['registrado_em'] ?? '—' }}</td>
                        <td>{{ $row['usuario'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="erp-produtos-child-grid__empty">Nenhum histórico de preço registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
