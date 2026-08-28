<div class="erp-produtos-child-grid">
    <div class="erp-produtos-child-grid__wrap">
        <table class="erp-produtos-child-grid__table">
            <thead>
                <tr>
                    <th>Custo</th>
                    <th>Varejo</th>
                    <th>Atacado</th>
                    <th>Especial</th>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Forma de Alteração</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->priceHistoryRows as $row)
                    <tr class="erp-produtos-child-grid__row">
                        <td class="erp-produtos-child-grid__num">R$ {{ $row['preco_custo'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">R$ {{ $row['ultimo_preco'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">R$ {{ $row['preco_atacado'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">R$ {{ $row['preco_especial'] ?? '0,00' }}</td>
                        <td>{{ $row['registrado_em'] ?? '—' }}</td>
                        <td>{{ $row['usuario'] ?? '—' }}</td>
                        <td>{{ $row['forma_alteracao'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="erp-produtos-child-grid__empty">Nenhum histórico de preço registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
