@if ($this->viewTab === 'desdobramentos')
    @php
        $t = $this->desdobramentoTitulo;
    @endphp
    <div class="erp-receber-desdobramentos">
        <div class="erp-receber-desdobramentos__panel">
            <div class="erp-receber-desdobramentos__head">
                <p class="erp-receber-desdobramentos__line">
                    <span>Título <strong>{{ $t['numero'] }}</strong></span>
                    <span class="erp-receber-desdobramentos__sep">|</span>
                    <strong class="erp-receber-desdobramentos__cliente">{{ $t['cliente'] }}</strong>
                </p>
                <p class="erp-receber-desdobramentos__line">
                    <span>Documento <strong>{{ $t['documento'] }}</strong></span>
                    <span class="erp-receber-desdobramentos__sep">|</span>
                    <span>Venc. <strong>{{ $t['vencimento'] }}</strong></span>
                    <span class="erp-receber-desdobramentos__sep">|</span>
                    <span class="is-valor">Valor <strong>R$ {{ $t['valor'] }}</strong></span>
                    <span class="erp-receber-desdobramentos__sep">|</span>
                    <span class="is-recebido">Recebido <strong>R$ {{ $t['valor_recebido'] }}</strong></span>
                    <span class="erp-receber-desdobramentos__sep">|</span>
                    <span class="is-saldo">Saldo <strong>R$ {{ $t['saldo'] }}</strong></span>
                </p>
            </div>

            <div class="erp-receber-desdobramentos__table-wrap">
                @if ($this->desdobramentoRows === [])
                    <p class="erp-receber-desdobramentos__empty">Nenhuma baixa neste título.</p>
                @else
                    <table class="erp-receber-desdobramentos__table">
                        <thead>
                            <tr>
                                <th class="is-flag">Flag</th>
                                <th>Data do Pagamento</th>
                                <th class="is-num">Valor Parcela</th>
                                <th class="is-num">Juros</th>
                                <th class="is-num">Multa</th>
                                <th class="is-num">Desconto</th>
                                <th class="is-num">Valor Recebido</th>
                                <th>Meio de Pagamento</th>
                                <th>Cheque</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->desdobramentoRows as $row)
                                @php
                                    $rowId = (int) $row['id'];
                                    $marcado = in_array($rowId, array_map('intval', $this->desdobramentoSelectedIds), true);
                                @endphp
                                <tr
                                    wire:click="toggleDesdobramentoFlag({{ $rowId }})"
                                    @class(['is-selected' => $marcado])
                                >
                                    <td class="is-flag" wire:click.stop>
                                        <input
                                            type="checkbox"
                                            class="erp-receber-desdobramentos__flag"
                                            value="{{ $rowId }}"
                                            wire:model.live="desdobramentoSelectedIds"
                                            aria-label="Selecionar baixa {{ $row['data'] }}"
                                        >
                                    </td>
                                    <td>{{ $row['data'] }}</td>
                                    <td class="is-num">{{ $row['valor_parcela'] }}</td>
                                    <td class="is-num">{{ $row['juros'] }}</td>
                                    <td class="is-num">{{ $row['multa'] }}</td>
                                    <td class="is-num">{{ $row['desconto'] }}</td>
                                    <td class="is-num">{{ $row['valor_recebido'] }}</td>
                                    <td>{{ $row['forma'] }}</td>
                                    <td>{{ $row['cheque'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
@endif
