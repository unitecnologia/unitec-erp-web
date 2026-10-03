<div class="erp-pagar erp-parcelas-portal">
    <form class="erp-pagar__filter-block" wire:submit.prevent="filtrar">
        <span class="erp-pagar__filter-title">Filtro</span>

        <label class="erp-pagar__period-label">
            Pesquisa
            <input
                type="search"
                class="erp-pagar__input erp-parcelas-portal__search"
                wire:model="q"
                placeholder="Cliente, CNPJ ou descrição"
                autocomplete="off"
                maxlength="120"
            >
        </label>

        <label class="erp-pagar__period-label">
            Situação
            <select class="erp-pagar__select" wire:model="status">
                <option value="">Todas</option>
                <option value="pending">Pendente</option>
                <option value="overdue">Atrasada</option>
                <option value="paid">Paga</option>
            </select>
        </label>

        <label class="erp-pagar__period-label">
            Vencimento de
            <input type="date" class="erp-pagar__period-input" wire:model="vencimentoDe">
        </label>

        <label class="erp-pagar__period-label">
            até
            <input type="date" class="erp-pagar__period-input" wire:model="vencimentoAte">
        </label>

        <button type="submit" class="erp-pagar__btn">Consultar</button>
        <button type="button" class="erp-parcelas-portal__btn-sec" wire:click="limparFiltros">Limpar</button>
    </form>

    <section class="erp-parcelas-portal__panel" aria-live="polite">
        <div class="erp-parcelas-portal__loading" wire:loading.flex wire:target="filtrar,limparFiltros,irParaPagina,consultar">
            Consultando o portal…
        </div>

        @if ($resultado === 'auth')
            <div class="erp-parcelas-portal__state erp-parcelas-portal__state--auth">
                <strong>Falha de autenticação</strong>
                <p>{{ $mensagem }}</p>
            </div>
        @elseif ($resultado === 'unavailable')
            <div class="erp-parcelas-portal__state erp-parcelas-portal__state--off">
                <strong>Portal indisponível</strong>
                <p>{{ $mensagem }}</p>
                <button type="button" class="erp-pagar__btn" wire:click="consultar">Tentar de novo</button>
            </div>
        @elseif ($resultado === 'filtro')
            <div class="erp-parcelas-portal__state">
                <p>{{ $mensagem }}</p>
            </div>
        @elseif ($resultado === 'empty')
            <div class="erp-parcelas-portal__state">
                <strong>Nenhum resultado</strong>
                <p>{{ $mensagem }}</p>
            </div>
        @elseif ($resultado === 'loading')
            <div class="erp-parcelas-portal__state">
                <p>Consultando o portal…</p>
            </div>
        @else
            <div class="erp-parcelas-portal__scroll">
                <table class="erp-parcelas-portal__table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>CNPJ</th>
                            <th>Documento/Descrição</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Situação</th>
                            <th>Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $row)
                            <tr wire:key="parcela-portal-{{ $page }}-{{ $index }}">
                                <td>{{ $row['cliente'] }}</td>
                                <td class="erp-parcelas-portal__mono">{{ $row['cnpj'] }}</td>
                                <td>{{ $row['documento'] }}</td>
                                <td>{{ $row['vencimento'] }}</td>
                                <td class="erp-parcelas-portal__num">{{ $row['valor'] }}</td>
                                <td>
                                    <span class="erp-parcelas-portal__badge erp-parcelas-portal__badge--{{ $row['situacao_tom'] }}">
                                        {{ $row['situacao'] }}
                                    </span>
                                </td>
                                <td>{{ $row['pagamento'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="erp-parcelas-portal__empty-row">Nenhuma parcela nesta página.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="erp-parcelas-portal__pager">
                <span>{{ $total }} {{ $total === 1 ? 'parcela' : 'parcelas' }} · página {{ $page }} de {{ $this->ultimaPagina() }}</span>
                <div class="erp-parcelas-portal__pager-actions">
                    <button type="button" class="erp-parcelas-portal__btn-sec" wire:click="irParaPagina({{ max(1, $page - 1) }})" @disabled($page <= 1)>Anterior</button>
                    <button type="button" class="erp-parcelas-portal__btn-sec" wire:click="irParaPagina({{ $page + 1 }})" @disabled($page >= $this->ultimaPagina())>Próxima</button>
                </div>
            </footer>
        @endif
    </section>
</div>
