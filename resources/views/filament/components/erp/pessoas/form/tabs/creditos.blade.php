<div class="erp-pessoa-credito">
    @if (! $this->personCreditoId())
        <p class="erp-pessoa-credito__empty">Salve a pessoa para consultar e lançar créditos.</p>
    @else
        <section class="erp-pessoa-credito__card">
            <div>
                <p class="erp-pessoa-credito__label">Saldo disponível</p>
                <p class="erp-pessoa-credito__saldo">R$ {{ $this->creditoClienteSaldo ?? '0,00' }}</p>
            </div>
            <div class="erp-pessoa-credito__acoes">
                <button type="button" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" wire:click="abrirGerarCredito">
                    + Gerar crédito
                </button>
                <button type="button" class="erp-pcad-actions__btn" wire:click="abrirUsarCredito">
                    Usar crédito
                </button>
            </div>
        </section>

        <section class="erp-pessoa-credito__historico">
            <h3 class="erp-pessoa-credito__titulo">Histórico</h3>

            <div class="erp-pessoa-credito__grid-wrap">
                <table class="erp-pessoa-credito__grid">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Origem</th>
                            <th class="is-num">Valor</th>
                            <th class="is-num">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->creditoClienteHistorico as $linha)
                            <tr wire:key="pessoa-credito-{{ $linha['id'] }}" wire:click="toggleCreditoDetalhe({{ $linha['id'] }})">
                                <td>{{ $linha['data'] }}</td>
                                <td>
                                    <span @class([
                                        'erp-pessoa-credito__tipo',
                                        'is-in' => $linha['positivo'],
                                        'is-out' => ! $linha['positivo'],
                                    ])>{{ $linha['tipo'] }}</span>
                                </td>
                                <td>{{ $linha['origem'] }}</td>
                                <td @class(['is-num', 'is-in' => $linha['positivo'], 'is-out' => ! $linha['positivo']])>{{ $linha['valor'] }}</td>
                                <td class="is-num">{{ $linha['saldo'] }}</td>
                                <td class="is-acao">
                                    @if ($linha['pode_estornar'])
                                        <button
                                            type="button"
                                            class="erp-pessoa-credito__estornar"
                                            wire:click.stop="abrirEstornarCredito({{ $linha['id'] }})"
                                        >Estornar</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($this->creditoDetalheId === $linha['id'])
                                <tr class="erp-pessoa-credito__detalhe" wire:key="pessoa-credito-det-{{ $linha['id'] }}">
                                    <td colspan="6">
                                        @if ($linha['codigo_publico'])
                                            <span>Vale: {{ $linha['codigo_publico'] }}</span>
                                        @endif
                                        <span>Empresa: {{ $linha['empresa'] }}</span>
                                        <span>Usuário: {{ $linha['usuario'] }}</span>
                                        <span>{{ $linha['hora'] }}</span>
                                        @if ($linha['observacao'] !== '')
                                            <span>{{ $linha['observacao'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="erp-pessoa-credito__vazio">Nenhum crédito lançado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

@if ($this->creditoModal !== '')
    <div class="erp-pessoa-credito-modal" role="dialog" aria-modal="true" aria-labelledby="erp-pessoa-credito-modal-title">
        <div class="erp-pessoa-credito-modal__backdrop" wire:click="fecharCreditoModal"></div>
        <div class="erp-pessoa-credito-modal__window">
            <header class="erp-pessoa-credito-modal__header">
                <h2 id="erp-pessoa-credito-modal-title">
                    @if ($this->creditoModal === 'gerar')
                        Gerar crédito
                    @elseif ($this->creditoModal === 'usar')
                        Usar crédito
                    @else
                        Estornar lançamento
                    @endif
                </h2>
                <button type="button" class="erp-pessoa-credito-modal__close" wire:click="fecharCreditoModal" title="Fechar">✕</button>
            </header>
            <div class="erp-pessoa-credito-modal__body">
                @if ($this->creditoModal === 'estornar')
                    <p class="erp-pessoa-credito-modal__texto">
                        O lançamento de R$ {{ $this->creditoValor }} será desfeito com um movimento inverso. O saldo não é editado.
                    </p>
                @else
                    <label class="erp-pessoa-credito-modal__field">
                        <span>Valor</span>
                        <input
                            type="text"
                            wire:model="creditoValor"
                            class="erp-pcad-form__input"
                            data-mask="money-br"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="0,00"
                        >
                    </label>
                @endif
                <label class="erp-pessoa-credito-modal__field">
                    <span>Observação</span>
                    <input
                        type="text"
                        wire:model="creditoObservacao"
                        class="erp-pcad-form__input"
                        maxlength="500"
                        autocomplete="off"
                    >
                </label>
            </div>
            <footer class="erp-pessoa-credito-modal__footer">
                <button type="button" class="erp-pcad-actions__btn" wire:click="fecharCreditoModal">Cancelar</button>
                <button type="button" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" wire:click="confirmarCreditoCliente">
                    @if ($this->creditoModal === 'estornar')
                        Estornar
                    @else
                        Confirmar
                    @endif
                </button>
            </footer>
        </div>
    </div>
@endif
