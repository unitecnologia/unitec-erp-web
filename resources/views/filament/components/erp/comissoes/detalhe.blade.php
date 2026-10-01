@php
    $linhas = $this->snapshotLinhas();
@endphp

@if ($this->highlightedRecordId && count($linhas) > 0)
    <div class="erp-comissoes__detalhe">
        <div class="erp-comissoes__detalhe-title">Snapshot das vendas (auditoria)</div>
        <div class="erp-comissoes__detalhe-table-wrap">
            <table class="erp-comissoes__detalhe-table">
                <thead>
                    <tr>
                        <th>Venda</th>
                        <th>Data</th>
                        <th>Base</th>
                        <th>Tipo</th>
                        <th>%</th>
                        <th>Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($linhas as $l)
                        <tr>
                            <td>{{ $l['numero'] }}</td>
                            <td>{{ $l['data'] }}</td>
                            <td class="erp-comissoes__num">{{ $l['base'] }}</td>
                            <td>{{ $l['tipo'] }}</td>
                            <td class="erp-comissoes__num">{{ $l['percentual'] }}</td>
                            <td class="erp-comissoes__num">{{ $l['comissao'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
