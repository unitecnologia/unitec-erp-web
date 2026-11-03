@if (! empty($this->impostoPadraoRegimeDiff))
    <div class="erp-imp-padrao-regime-diff" style="max-height: 12rem; overflow: auto; font-size: 0.8rem; text-align: left;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 0.2rem 0.35rem;">Campo</th>
                    <th style="text-align: left; padding: 0.2rem 0.35rem;">Atual</th>
                    <th style="text-align: left; padding: 0.2rem 0.35rem;">Sugestão</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->impostoPadraoRegimeDiff as $row)
                    <tr>
                        <td style="padding: 0.15rem 0.35rem; font-family: ui-monospace, monospace;">{{ $row['campo'] }}</td>
                        <td style="padding: 0.15rem 0.35rem;">{{ $row['atual'] }}</td>
                        <td style="padding: 0.15rem 0.35rem;">{{ $row['sugerido'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
