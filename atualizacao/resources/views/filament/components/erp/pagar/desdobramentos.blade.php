@if ($this->viewTab === 'desdobramentos')
    @php
        $selecionadas = count($this->desdobramentoSelectedIds);
        $t = $this->desdobramentoTitulo;
    @endphp
    <div class="erp-pagar-desdobramentos">
        <div class="erp-pagar-desdobramentos__panel">
            <div class="erp-pagar-desdobramentos__toolbar">
                <div class="erp-pagar-desdobramentos__toolbar-left">
                    <strong>Desdobramentos de Parcelas</strong>
                    <span class="erp-pagar-desdobramentos__badge">Título {{ $t['numero'] }}</span>
                    @if ($selecionadas > 0)
                        <span class="erp-pagar-desdobramentos__badge erp-pagar-desdobramentos__badge--sel">
                            {{ $selecionadas }} marcada{{ $selecionadas === 1 ? '' : 's' }}
                        </span>
                    @endif
                </div>
                <span class="erp-pagar-desdobramentos__hint">Flag + F8 Estornar</span>
            </div>

            <div class="erp-pagar-desdobramentos__resumo">
                <div class="erp-pagar-desdobramentos__fornecedor">
                    <span>Fornecedor</span>
                    <strong>{{ $t['fornecedor'] }}</strong>
                </div>
                <div class="erp-pagar-desdobramentos__meta">
                    <span><em>Emissão</em> {{ $t['emissao'] }}</span>
                    <span><em>Documento</em> {{ $t['documento'] }}</span>
                    <span><em>Vencimento</em> {{ $t['vencimento'] }}</span>
                    <span><em>Valor</em> {{ $t['valor'] }}</span>
                    <span><em>Desconto</em> {{ $t['desconto'] }}</span>
                    <span><em>Juros</em> {{ $t['juros'] }}</span>
                    <span><em>Valor Pago</em> {{ $t['valor_pago'] }}</span>
                    <span><em>Saldo</em> {{ $t['saldo'] }}</span>
                    <span class="is-wide"><em>Histórico</em> {{ $t['historico'] }}</span>
                </div>
            </div>

            <div class="erp-pagar-desdobramentos__table-wrap">
                @if ($this->desdobramentoRows === [])
                    <p class="erp-pagar-desdobramentos__empty">Nenhuma parcela paga neste título.</p>
                @else
                    <table class="erp-pagar-desdobramentos__table">
                        <thead>
                            <tr>
                                <th class="is-flag">Flag</th>
                                <th>Data do Pagamento</th>
                                <th class="is-num">Valor Parcela</th>
                                <th class="is-num">Juros</th>
                                <th class="is-num">Desconto</th>
                                <th class="is-num">Valor Pago</th>
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
                                            class="erp-pagar-desdobramentos__flag"
                                            value="{{ $rowId }}"
                                            wire:model.live="desdobramentoSelectedIds"
                                            aria-label="Selecionar parcela {{ $row['data'] }}"
                                        >
                                    </td>
                                    <td>{{ $row['data'] }}</td>
                                    <td class="is-num">{{ $row['valor_parcela'] }}</td>
                                    <td class="is-num">{{ $row['juros'] }}</td>
                                    <td class="is-num">{{ $row['desconto'] }}</td>
                                    <td class="is-num">{{ $row['valor_pago'] }}</td>
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
