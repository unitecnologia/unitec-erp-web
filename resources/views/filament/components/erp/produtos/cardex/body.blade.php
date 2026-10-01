<p class="erp-cardex-modal__product">{{ $this->cardexProdutoLabel }}</p>

<div class="erp-cardex-modal__periodo" data-erp-date-group data-erp-allow-browser-hints="1">
    <label for="cardex-periodo-de">De</label>
    <input
        id="cardex-periodo-de"
        type="text"
        class="erp-pcad-form__input"
        data-erp-date
        data-wire-field="cardexPeriodoDe"
        data-erp-date-wire="iso"
        data-erp-date-initial="{{ $this->cardexPeriodoDe }}"
        value="{{ $this->cardexPeriodoDe ? \Illuminate\Support\Carbon::parse($this->cardexPeriodoDe)->format('d/m/Y') : '' }}"
        placeholder="dd/mm/aaaa"
        inputmode="numeric"
        autocomplete="off"
    >
    <label for="cardex-periodo-ate">Até</label>
    <input
        id="cardex-periodo-ate"
        type="text"
        class="erp-pcad-form__input"
        data-erp-date
        data-wire-field="cardexPeriodoAte"
        data-erp-date-wire="iso"
        data-erp-date-initial="{{ $this->cardexPeriodoAte }}"
        value="{{ $this->cardexPeriodoAte ? \Illuminate\Support\Carbon::parse($this->cardexPeriodoAte)->format('d/m/Y') : '' }}"
        placeholder="dd/mm/aaaa"
        inputmode="numeric"
        autocomplete="off"
    >
</div>

<div class="erp-cardex-modal__resumo">
    <div class="erp-cardex-modal__resumo-field">
        <label for="cardex-e-medio">Estoque Médio (R$)</label>
        <input id="cardex-e-medio" type="text" value="{{ $this->cardexData['resumo']['e_medio'] ?? '0,000' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
    </div>
    <div class="erp-cardex-modal__resumo-field">
        <label for="cardex-ult-compra">Última Compra (R$)</label>
        <input id="cardex-ult-compra" type="text" value="{{ $this->cardexData['resumo']['ult_compra'] ?? '0,00' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
    </div>
    <div class="erp-cardex-modal__resumo-field">
        <label for="cardex-ult-compra-ant">Últ. Compra Anterior (R$)</label>
        <input id="cardex-ult-compra-ant" type="text" value="{{ $this->cardexData['resumo']['ult_compra_anterior'] ?? '0,00' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
    </div>
</div>

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
            <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['compras'] }}</div>
        </fieldset>
    </section>

    @if ($this->cardexPrestador)
        <section class="erp-cardex-modal__panel erp-cardex-modal__panel--saidas erp-cardex-modal__panel--saidas-abas">
            <h3 class="erp-cardex-modal__section-title">Saídas</h3>
            <fieldset class="erp-cardex-modal__fieldset">
                <div class="erp-cardex-modal__abas" role="tablist">
                    <button type="button" class="is-active" role="tab" aria-selected="true" data-cardex-tab="vendas" onclick="window.erpCardexAba && window.erpCardexAba(this)">Vendas ({{ count($this->cardexData['vendas'] ?? []) }})</button>
                    <button type="button" role="tab" aria-selected="false" data-cardex-tab="nfe" onclick="window.erpCardexAba && window.erpCardexAba(this)">NF-e ({{ count($this->cardexData['nfe'] ?? []) }})</button>
                    <button type="button" role="tab" aria-selected="false" data-cardex-tab="nfce" onclick="window.erpCardexAba && window.erpCardexAba(this)">NFC-e ({{ count($this->cardexData['nfce'] ?? []) }})</button>
                    <button type="button" role="tab" aria-selected="false" data-cardex-tab="os" onclick="window.erpCardexAba && window.erpCardexAba(this)">OS ({{ count($this->cardexData['os'] ?? []) }})</button>
                    <button type="button" role="tab" aria-selected="false" data-cardex-tab="nfse" onclick="window.erpCardexAba && window.erpCardexAba(this)">NFS-e ({{ count($this->cardexData['nfse'] ?? []) }})</button>
                </div>
                <div class="erp-cardex-modal__abas-grade" role="tabpanel" data-cardex-panel="vendas">
                    <div class="erp-cardex-modal__abas-scroll">
                    <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                        <thead>
                            <tr>
                                <th>Venda</th>
                                <th>Dt. Emissão</th>
                                <th>Cliente</th>
                                <th class="erp-cardex-modal__num">Qtde</th>
                                <th class="erp-cardex-modal__num">Valor</th>
                                <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                    <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="erp-lookup-modal__empty">Nenhuma venda encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['vendas'] }}</div>
                </div>
                <div class="erp-cardex-modal__abas-grade" role="tabpanel" data-cardex-panel="nfe" hidden>
                    <div class="erp-cardex-modal__abas-scroll">
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
                                <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                    <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="erp-lookup-modal__empty">Nenhuma NF-e encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['nfe'] }}</div>
                </div>
                <div class="erp-cardex-modal__abas-grade" role="tabpanel" data-cardex-panel="nfce" hidden>
                    <div class="erp-cardex-modal__abas-scroll">
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
                                <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                    <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="erp-lookup-modal__empty">Nenhuma NFC-e encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['nfce'] }}</div>
                </div>
                <div class="erp-cardex-modal__abas-grade" role="tabpanel" data-cardex-panel="os" hidden>
                    <div class="erp-cardex-modal__abas-scroll">
                    <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                        <thead>
                            <tr>
                                <th>OS</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th class="erp-cardex-modal__num">Qtde</th>
                                <th class="erp-cardex-modal__num">Valor</th>
                                <th class="erp-cardex-modal__num">Desc./Acrés.</th>
                                <th class="erp-cardex-modal__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->cardexData['os'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['os'] }}</td>
                                    <td>{{ $row['data'] }}</td>
                                    <td>{{ $row['cliente'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="erp-lookup-modal__empty">Nenhuma OS encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['os'] ?? 'R$ 0,00' }}</div>
                </div>
                <div class="erp-cardex-modal__abas-grade" role="tabpanel" data-cardex-panel="nfse" hidden>
                    <div class="erp-cardex-modal__abas-scroll">
                    <table class="erp-lookup-modal__grid erp-cardex-modal__grid">
                        <thead>
                            <tr>
                                <th>NFS-e</th>
                                <th>OS</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th class="erp-cardex-modal__num">Qtde</th>
                                <th class="erp-cardex-modal__num">Valor</th>
                                <th class="erp-cardex-modal__num">Desc./Acrés.</th>
                                <th class="erp-cardex-modal__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->cardexData['nfse'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['nfse'] }}</td>
                                    <td>{{ $row['os'] }}</td>
                                    <td>{{ $row['data'] }}</td>
                                    <td>{{ $row['cliente'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['quantidade'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['valor'] }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                    <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="erp-lookup-modal__empty">Nenhuma NFS-e encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['nfse'] ?? 'R$ 0,00' }}</div>
                </div>
            </fieldset>
            <script>
                window.erpCardexAba = function (button) {
                    var root = button.closest('.erp-cardex-modal__panel--saidas-abas');
                    if (!root) {
                        return;
                    }
                    var name = button.getAttribute('data-cardex-tab');
                    root.querySelectorAll('[data-cardex-tab]').forEach(function (tab) {
                        var on = tab === button;
                        tab.classList.toggle('is-active', on);
                        tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    root.querySelectorAll('[data-cardex-panel]').forEach(function (panel) {
                        panel.hidden = panel.getAttribute('data-cardex-panel') !== name;
                    });
                };
            </script>
        </section>
    @else
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
                            <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="erp-lookup-modal__empty">Nenhuma venda encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['vendas'] }}</div>
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
                            <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="erp-lookup-modal__empty">Nenhuma NF-e encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['nfe'] }}</div>
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
                            <th class="erp-cardex-modal__num">Desc./Acrés.</th>
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
                                <td class="erp-cardex-modal__num">{{ $row['desconto_acrescimo'] ?? '—' }}</td>
                                <td class="erp-cardex-modal__num">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="erp-lookup-modal__empty">Nenhuma NFC-e encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="erp-cardex-modal__grade-total">Total: {{ $this->cardexData['totais']['nfce'] }}</div>
        </fieldset>
    </section>
    @endif
</div>
