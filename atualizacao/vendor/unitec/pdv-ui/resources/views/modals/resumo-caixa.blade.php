@if ($this->activeModal === 'resumo')
    @php
        $resumo = $this->resumoCaixa;
        $ctx = $this->caixaModalContexto ?? [
            'usuario' => strtoupper((string) ($this->pdvStatusBar['usuario'] ?? '—')),
            'operador' => '—',
            'empresa' => '—',
            'terminal' => '—',
        ];
    @endphp

    <div class="erp-pdv-modal erp-pdv-resumo-modal" role="dialog" aria-labelledby="erp-pdv-resumo-title" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-resumo-modal__window">
            <header class="erp-pdv-resumo-modal__hero">
                <div class="erp-pdv-resumo-modal__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2.5" y="6" width="19" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2.2"/>
                        <path d="M6 12h.01M18 12h.01"/>
                    </svg>
                </div>
                <div class="erp-pdv-resumo-modal__hero-text">
                    <p class="erp-pdv-resumo-modal__eyebrow">Sessão do caixa</p>
                    <h2 id="erp-pdv-resumo-title">Resumo do Caixa</h2>
                    <p class="erp-pdv-resumo-modal__subtitle">
                        {{ $ctx['usuario'] ?? '—' }} · {{ $ctx['terminal'] ?? 'PDV' }}
                    </p>
                </div>
                <button type="button" class="erp-pdv-resumo-modal__x" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-resumo-modal__body">
                <div class="erp-pdv-resumo-modal__kpis" aria-label="Totais da sessão">
                    <div class="erp-pdv-resumo-modal__kpi erp-pdv-resumo-modal__kpi--entrada">
                        <span>Entradas</span>
                        <strong>R$ {{ $resumo['total_entrada'] }}</strong>
                    </div>
                    <div class="erp-pdv-resumo-modal__kpi erp-pdv-resumo-modal__kpi--saida">
                        <span>Saídas</span>
                        <strong>R$ {{ $resumo['total_saida'] }}</strong>
                    </div>
                    <div class="erp-pdv-resumo-modal__kpi erp-pdv-resumo-modal__kpi--saldo">
                        <span>Saldo total</span>
                        <strong>R$ {{ $resumo['saldo_total'] }}</strong>
                    </div>
                    <div class="erp-pdv-resumo-modal__kpi erp-pdv-resumo-modal__kpi--dinheiro">
                        <span>Saldo em dinheiro</span>
                        <strong>R$ {{ $resumo['saldo_dinheiro'] }}</strong>
                    </div>
                </div>

                <div class="erp-pdv-resumo-modal__table-head">
                    <h3>Movimentos</h3>
                    <span>{{ count($resumo['movimentos'] ?? []) }} registro(s)</span>
                </div>

                <div class="erp-pdv-resumo-modal__table-wrap">
                    <table class="erp-pdv-resumo-modal__table">
                        <thead>
                            <tr>
                                <th>Histórico</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resumo['movimentos'] as $movimento)
                                <tr>
                                    <td>{{ $movimento['historico'] }}</td>
                                    <td class="erp-pdv-resumo-modal__money erp-pdv-resumo-modal__money--in">{{ $movimento['entrada'] }}</td>
                                    <td class="erp-pdv-resumo-modal__money erp-pdv-resumo-modal__money--out">{{ $movimento['saida'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="erp-pdv-resumo-modal__empty">Nenhum movimento nesta sessão.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="erp-pdv-resumo-modal__footer">
                <button type="button" wire:click="closePdvModal" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                    <kbd>Esc</kbd> Fechar
                </button>
            </footer>
        </div>
    </div>
@endif
