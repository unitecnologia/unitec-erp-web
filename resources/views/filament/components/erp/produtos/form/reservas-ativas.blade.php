@if ($this->record?->exists)
    <div class="erp-produtos-form__reservas">
        <div class="erp-produtos-form__reservas-summary">
            <span class="erp-produtos-form__reservas-chip erp-produtos-form__reservas-chip--reserved">
                Reservado: <strong>{{ $this->productEstoqueReservadoLabel }}</strong>
            </span>
            <span class="erp-produtos-form__reservas-chip erp-produtos-form__reservas-chip--available">
                Disponível: <strong>{{ $this->productEstoqueDisponivelLabel }}</strong>
            </span>
            @if (count($this->productReservasAtivas) > 0)
                <details class="erp-produtos-form__reservas-details">
                    <summary>{{ count($this->productReservasAtivas) }} reserva(s) ativa(s) — ver detalhes</summary>
                    <div class="erp-produtos-form__reservas-table-wrap">
                        <table class="erp-produtos-form__reservas-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Plat.</th>
                                    <th>Qtd</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->productReservasAtivas as $row)
                                    <tr>
                                        <td>{{ $row['pedido'] }}</td>
                                        <td>{{ $row['cliente'] }}</td>
                                        <td>{{ $row['vendedor'] }}</td>
                                        <td>{{ $row['plataforma'] }}</td>
                                        <td class="erp-produtos-form__reservas-num">{{ $row['quantidade'] }}</td>
                                        <td>{{ $row['data'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @else
                <span class="erp-produtos-form__reservas-empty">Sem reservas ativas no app</span>
            @endif
        </div>
    </div>
@endif
