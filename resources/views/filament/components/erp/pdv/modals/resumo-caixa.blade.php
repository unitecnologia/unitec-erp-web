@if ($this->activeModal === 'resumo')
    @php
        $resumo = $this->resumoCaixa;
    @endphp

    <div class="erp-pdv-modal" role="dialog" aria-labelledby="erp-pdv-resumo-title">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-pdv-resumo-title">
                    Resumo Caixa — Usuário: {{ strtoupper($this->pdvStatusBar['usuario']) }}
                </h2>
            </header>

            <div class="erp-pdv-modal__body erp-pdv-modal__body--flush">
                <div class="erp-pdv-resumo">
                    <div class="erp-pdv-resumo__top">
                        <div class="erp-pdv-resumo__icon" aria-hidden="true">💳</div>
                        <dl class="erp-pdv-resumo__summary">
                            <div><dt>Total de Entrada:</dt><dd>{{ $resumo['total_entrada'] }}</dd></div>
                            <div><dt>Total de Saída:</dt><dd>{{ $resumo['total_saida'] }}</dd></div>
                            <div><dt>Saldo Total:</dt><dd>{{ $resumo['saldo_total'] }}</dd></div>
                            <div><dt>Saldo em Dinheiro:</dt><dd>{{ $resumo['saldo_dinheiro'] }}</dd></div>
                        </dl>
                    </div>

                    <div class="erp-pdv-resumo__grid-wrap">
                        <table class="erp-pdv-resumo__grid">
                            <thead>
                                <tr>
                                    <th>HISTORICO</th>
                                    <th>ENTRADA</th>
                                    <th>SAIDA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($resumo['movimentos'] as $index => $movimento)
                                    <tr @class(['erp-pdv-resumo__row--selected' => $index === 0])>
                                        <td>{{ $movimento['historico'] }}</td>
                                        <td class="erp-pdv-resumo__money">{{ $movimento['entrada'] }}</td>
                                        <td class="erp-pdv-resumo__money">{{ $movimento['saida'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="erp-pdv-resumo__empty">Nenhum movimento.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="closePdvModal" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">
                    Fechar
                </button>
            </footer>
        </div>
    </div>
@endif
