@php
    $columns = [
        'numero' => 'Número',
        'os' => 'OS',
        'data_emissao' => 'Dt.Emissão',
        'competencia' => 'Competência',
        'tomador' => 'Tomador',
        'chave' => 'Chave',
        'municipio' => 'Município',
        'status' => 'Situação',
        'total' => 'Total',
    ];
    $registros = $this->nfseRegistros;
@endphp

<div class="fi-ta erp-nfse-grid">
    <div class="fi-ta-ctn">
        <div class="fi-ta-content-ctn">
            <table class="fi-ta-table">
                <thead>
                    <tr>
                        @foreach ($columns as $key => $label)
                            <th class="fi-ta-header-cell fi-ta-header-cell-{{ $key }}" scope="col">
                                <span class="fi-ta-header-cell-label">{{ $label }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registros as $nfse)
                        @php
                            $osNumero = trim((string) ($nfse->ordemServico?->numero ?? ''));
                            if ($osNumero !== '') {
                                $osDigits = (int) preg_replace('/\D/', '', $osNumero);
                                $osNumero = $osDigits > 0 ? (string) $osDigits : $osNumero;
                            } else {
                                $osNumero = '—';
                            }
                        @endphp
                        <tr class="fi-ta-row" data-record-key="{{ $nfse->getKey() }}" wire:key="nfse-lista-{{ $nfse->getKey() }}">
                            <td class="fi-ta-cell">{{ $nfse->numero_dps }}</td>
                            <td class="fi-ta-cell">{{ $osNumero }}</td>
                            <td class="fi-ta-cell">{{ $nfse->data_emissao?->format('d/m/Y') ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ $nfse->competencia?->format('m/Y') ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ $nfse->tomador_nome }}</td>
                            <td class="fi-ta-cell">{{ $nfse->chave ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ $nfse->municipio_incidencia ?: '—' }}</td>
                            <td class="fi-ta-cell">
                                <span @class(['erp-nfe__status-chip', 'erp-nfe__status-chip--'.$nfse->status])>{{ $nfse->statusLabel() }}</span>
                            </td>
                            <td class="fi-ta-cell">
                                <span class="erp-nfse-total-cell">
                                    <span class="erp-nfse-total-cell__currency">R$</span>
                                    <span class="erp-nfse-total-cell__amount">{{ $this->formatarNfseListagemDinheiro($nfse->total) }}</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($registros->isEmpty())
            <div class="fi-ta-empty-state" role="status">
                <div class="fi-ta-empty-state-content">
                    <p class="fi-ta-empty-state-heading">Nenhuma NFS-e encontrada</p>
                </div>
            </div>
        @endif
    </div>
</div>
