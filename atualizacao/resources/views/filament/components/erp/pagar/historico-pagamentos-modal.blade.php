@if ($this->historicoPagamentosOpen)
    <div
        class="erp-lookup-modal"
        wire:keydown.escape.window="closeHistoricoPagamentos"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeHistoricoPagamentos"></div>

        <div
            class="erp-lookup-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-pagar-historico-title"
            wire:click.stop
            style="width: min(920px, 96vw); max-height: 80vh;"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-pagar-historico-title">{{ $this->historicoPagamentosTitulo }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeHistoricoPagamentos"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body" style="overflow:auto; max-height: calc(80vh - 3rem);">
                @if ($this->historicoPagamentosRows === [])
                    <p style="margin: 1rem 0; font-weight: 600;">Nenhuma baixa registrada para este título.</p>
                @else
                    <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background:#e8eef5; text-align:left;">
                                <th style="padding:6px 8px;">Data</th>
                                <th style="padding:6px 8px; text-align:right;">Valor pago</th>
                                <th style="padding:6px 8px; text-align:right;">Juros</th>
                                <th style="padding:6px 8px; text-align:right;">Desconto</th>
                                <th style="padding:6px 8px;">Forma</th>
                                <th style="padding:6px 8px;">Plano</th>
                                <th style="padding:6px 8px;">Conta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->historicoPagamentosRows as $row)
                                <tr style="border-top:1px solid #cfd8e3;">
                                    <td style="padding:6px 8px;">{{ $row['data'] }}</td>
                                    <td style="padding:6px 8px; text-align:right;">{{ $row['valor_pago'] }}</td>
                                    <td style="padding:6px 8px; text-align:right;">{{ $row['juros'] }}</td>
                                    <td style="padding:6px 8px; text-align:right;">{{ $row['desconto'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['forma'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['plano'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['conta'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endif
