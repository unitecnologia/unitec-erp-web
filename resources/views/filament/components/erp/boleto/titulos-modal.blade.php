@if ($this->titulosModalOpen)
    <div class="erp-lookup-modal" wire:keydown.escape.window="closeTitulosModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeTitulosModal"></div>
        <div
            class="erp-lookup-modal__window"
            role="dialog"
            aria-modal="true"
            wire:click.stop
            style="width:min(920px,96vw); max-height:80vh;"
        >
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->titulosModalTitulo }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeTitulosModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body" style="overflow:auto; max-height:calc(80vh - 3rem);">
                @if ($this->titulosModalRows === [])
                    <p style="margin:1rem 0; font-weight:600;">Nenhum título neste registro.</p>
                @else
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#e8eef5; text-align:left;">
                                <th style="padding:6px 8px;">Data</th>
                                <th style="padding:6px 8px; text-align:right;">Valor</th>
                                <th style="padding:6px 8px;">Cliente / Ocorrência</th>
                                <th style="padding:6px 8px;">Documento / NN</th>
                                <th style="padding:6px 8px;">Nº</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->titulosModalRows as $row)
                                <tr style="border-top:1px solid #cfd8e3;">
                                    <td style="padding:6px 8px;">{{ $row['vencimento'] }}</td>
                                    <td style="padding:6px 8px; text-align:right;">{{ $row['valor'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['cliente'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['documento'] }}</td>
                                    <td style="padding:6px 8px;">{{ $row['numero'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endif
