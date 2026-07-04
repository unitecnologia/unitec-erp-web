<p class="erp-cardex-modal__product">{{ $this->cardexProdutoLabel }}</p>

<div class="erp-cardex-modal__layout">
    <section class="erp-cardex-modal__panel erp-cardex-modal__panel--entradas">
        <h3 class="erp-cardex-modal__section-title">Entradas</h3>
        <fieldset class="erp-cardex-modal__fieldset">
            <legend class="erp-cardex-modal__legend">Compras</legend>
            <div class="erp-lookup-modal__grid-wrap erp-cardex-modal__grid-wrap--tall">
                <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                    <thead>
                        <tr>
                            <th>Compra</th>
                            <th>Dt. Entrada</th>
                            <th>Fornecedor</th>
                            <th class="erp-cardex-modal__num">Qtde</th>
                            <th class="erp-cardex-modal__num">Valor</th>
                            <th class="erp-cardex-modal__num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->cardexData['compras'] as $row)
                            <tr>
                                <td>{{ $row['compra'] }}</td>
                                <td>{{ $row['data_entrada'] }}</td>
                                <td>{{ $row['fornecedor'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="erp-lookup-modal__empty">Nenhuma compra encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </fieldset>
    </section>

    <section class="erp-cardex-modal__panel erp-cardex-modal__panel--saidas">
        <h3 class="erp-cardex-modal__section-title">Saídas</h3>

        <fieldset class="erp-cardex-modal__fieldset">
            <legend class="erp-cardex-modal__legend">Vendas</legend>
            <div class="erp-lookup-modal__grid-wrap">
                <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Dt. Emissão</th>
                            <th>Cliente</th>
                            <th class="erp-cardex-modal__num">Qtde</th>
                            <th class="erp-cardex-modal__num">Valor</th>
                            <th class="erp-cardex-modal__num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->cardexData['vendas'] as $row)
                            <tr>
                                <td>{{ $row['venda'] }}</td>
                                <td>{{ $row['data_emissao'] }}</td>
                                <td>{{ $row['cliente'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="erp-lookup-modal__empty">Nenhuma venda encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset class="erp-cardex-modal__fieldset">
            <legend class="erp-cardex-modal__legend">NF-e</legend>
            <div class="erp-lookup-modal__grid-wrap erp-cardex-modal__grid-wrap--fiscal">
                <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                    <thead>
                        <tr>
                            <th>NFe</th>
                            <th>Número</th>
                            <th>Venda</th>
                            <th>Dt. Emissão</th>
                            <th>Hrs. Emissão</th>
                            <th>Cliente</th>
                            <th class="erp-cardex-modal__num">Qtde</th>
                            <th class="erp-cardex-modal__num">Valor</th>
                            <th class="erp-cardex-modal__num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->cardexData['nfe'] as $row)
                            <tr>
                                <td>{{ $row['nfe'] }}</td>
                                <td>{{ $row['numero'] }}</td>
                                <td>{{ $row['venda'] }}</td>
                                <td>{{ $row['data_emissao'] }}</td>
                                <td>{{ $row['hora_emissao'] }}</td>
                                <td>{{ $row['cliente'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="erp-lookup-modal__empty">Nenhuma NF-e encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset class="erp-cardex-modal__fieldset">
            <legend class="erp-cardex-modal__legend">NFC-e</legend>
            <div class="erp-lookup-modal__grid-wrap erp-cardex-modal__grid-wrap--fiscal">
                <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                    <thead>
                        <tr>
                            <th>NFCe</th>
                            <th>Número</th>
                            <th>Venda</th>
                            <th>Dt. Emissão</th>
                            <th>Hrs. Emissão</th>
                            <th>Cliente</th>
                            <th class="erp-cardex-modal__num">Qtde</th>
                            <th class="erp-cardex-modal__num">Valor</th>
                            <th class="erp-cardex-modal__num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->cardexData['nfce'] as $row)
                            <tr>
                                <td>{{ $row['nfce'] }}</td>
                                <td>{{ $row['numero'] }}</td>
                                <td>{{ $row['venda'] }}</td>
                                <td>{{ $row['data_emissao'] }}</td>
                                <td>{{ $row['hora_emissao'] }}</td>
                                <td>{{ $row['cliente'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="erp-lookup-modal__empty">Nenhuma NFC-e encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </fieldset>
    </section>
</div>

<fieldset class="erp-cardex-modal__totals">
    <legend class="erp-cardex-modal__legend">Totais Gerais</legend>
    <div class="erp-cardex-modal__totals-row">
        <span><strong>Compras:</strong> {{ $this->cardexData['totais']['compras'] }}</span>
        <span><strong>Vendas:</strong> {{ $this->cardexData['totais']['vendas'] }}</span>
        <span><strong>NFe:</strong> {{ $this->cardexData['totais']['nfe'] }}</span>
        <span><strong>NFCe:</strong> {{ $this->cardexData['totais']['nfce'] }}</span>
        <span class="erp-cardex-modal__totals-highlight"><strong>Total Geral Vendas:</strong> {{ $this->cardexData['totais']['total_vendas'] }}</span>
    </div>
</fieldset>
