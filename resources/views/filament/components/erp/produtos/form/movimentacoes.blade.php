<div class="erp-produtos-movimentacoes" wire:key="estoque-panel-movimentacoes">
    @if (! $this->isEditingProduct())
        <p class="erp-produtos-estoques__empty">Salve o produto para consultar as movimentações.</p>
    @else
        <div class="erp-produtos-movimentacoes__filters">
            <div class="erp-produtos-movimentacoes__field">
                <label for="mov-periodo-de">De</label>
                <input id="mov-periodo-de" type="date" wire:model="movFiltroPeriodoDe" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-movimentacoes__field">
                <label for="mov-periodo-ate">Até</label>
                <input id="mov-periodo-ate" type="date" wire:model="movFiltroPeriodoAte" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-movimentacoes__field erp-produtos-movimentacoes__field--tipo">
                <label for="mov-tipo">Tipo</label>
                <select id="mov-tipo" wire:model="movFiltroTipo" class="erp-pcad-form__input">
                    <option value="">Todos</option>
                    @foreach ($this->movimentacaoTiposFiltro as $tipoKey => $tipoLabel)
                        <option value="{{ $tipoKey }}">{{ $tipoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="erp-produtos-movimentacoes__actions">
                <button type="button" class="erp-pcad-actions__btn erp-produtos-movimentacoes__btn erp-produtos-movimentacoes__btn--primary" wire:click="aplicarFiltrosMovimentacoes">Filtrar</button>
                <button type="button" class="erp-pcad-actions__btn erp-produtos-movimentacoes__btn" wire:click="limparFiltrosMovimentacoes">Limpar</button>
            </div>
        </div>

        <div class="erp-produtos-estoques__grid-wrap erp-produtos-movimentacoes__grid-wrap">
            <table class="erp-produtos-estoques__grid erp-produtos-movimentacoes__grid">
                <thead>
                    <tr>
                        <th class="erp-produtos-movimentacoes__col-data">Data</th>
                        <th class="erp-produtos-movimentacoes__col-tipo">Movimento</th>
                        <th class="erp-produtos-estoques__col-num">Qtd.</th>
                        <th class="erp-produtos-movimentacoes__col-saldo">Saldo</th>
                        <th class="erp-produtos-movimentacoes__col-origem">Documento</th>
                        <th class="erp-produtos-movimentacoes__col-fiscal">Doc. fiscal</th>
                        <th class="erp-produtos-movimentacoes__col-usuario">Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->productMovimentacoes as $row)
                        <tr wire:key="mov-row-{{ $row['id'] }}">
                            <td class="erp-produtos-movimentacoes__col-data">{{ $row['data'] }}</td>
                            <td class="erp-produtos-movimentacoes__col-tipo" title="{{ $row['tipo_label'] }}">{{ $row['tipo_label'] }}</td>
                            <td @class([
                                'erp-produtos-estoques__col-num',
                                'erp-produtos-movimentacoes__qtd--in' => ($row['quantidade_sinal'] ?? '') === 'pos',
                                'erp-produtos-movimentacoes__qtd--out' => ($row['quantidade_sinal'] ?? '') === 'neg',
                            ])>{{ $row['quantidade'] }}</td>
                            <td class="erp-produtos-movimentacoes__col-saldo" title="Saldo global do produto">{{ $row['saldo'] }}</td>
                            <td class="erp-produtos-movimentacoes__col-origem" title="{{ $row['documento'] }}">{{ $row['documento'] }}</td>
                            <td class="erp-produtos-movimentacoes__col-fiscal" title="{{ $row['doc_fiscal'] ?? '—' }}">{{ $row['doc_fiscal'] ?? '—' }}</td>
                            <td class="erp-produtos-movimentacoes__col-usuario" title="{{ $row['usuario'] }}">{{ $row['usuario'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="erp-produtos-estoques__empty">
                                Nenhuma movimentação encontrada para este produto.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->movTotal > 0)
            <div class="erp-produtos-movimentacoes__pager">
                <span>
                    {{ $this->movTotal }} registro(s) · página {{ $this->movPage }} de {{ $this->movimentacoesLastPage }}
                </span>
                <div class="erp-produtos-movimentacoes__pager-btns">
                    <button
                        type="button"
                        class="erp-pcad-actions__btn erp-produtos-movimentacoes__btn"
                        wire:click="irPaginaMovimentacoes({{ max(1, $this->movPage - 1) }})"
                        @disabled($this->movPage <= 1 || $this->movimentacoesLastPage <= 1)
                    >Anterior</button>
                    <button
                        type="button"
                        class="erp-pcad-actions__btn erp-produtos-movimentacoes__btn"
                        wire:click="irPaginaMovimentacoes({{ min($this->movimentacoesLastPage, $this->movPage + 1) }})"
                        @disabled($this->movPage >= $this->movimentacoesLastPage || $this->movimentacoesLastPage <= 1)
                    >Próxima</button>
                </div>
            </div>
        @endif
    @endif
</div>
