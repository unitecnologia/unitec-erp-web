@if (in_array($this->activeModal, ['abrir_caixa', 'fechar_caixa'], true))
    @php
        $isAbrir = $this->activeModal === 'abrir_caixa';
        $ctx = $this->caixaModalContexto ?? [
            'usuario' => '—',
            'operador' => '—',
            'empresa' => '—',
            'terminal' => '—',
        ];
        $resumo = $isAbrir ? null : ($this->resumoCaixa ?? []);
    @endphp
    <div class="erp-pdv-modal erp-pdv-caixa-modal" role="dialog" aria-labelledby="erp-pdv-caixa-title" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-caixa-modal__window {{ $isAbrir ? 'is-abrir' : 'is-fechar is-fechar-resumo' }}">
            <header class="erp-pdv-caixa-modal__hero">
                <div class="erp-pdv-caixa-modal__hero-icon" aria-hidden="true">
                    @if ($isAbrir)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="7" width="18" height="12" rx="2"/>
                            <path d="M8 7V5a4 4 0 0 1 8 0v2"/>
                            <circle cx="12" cy="13" r="1.4"/>
                        </svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="7" width="18" height="12" rx="2"/>
                            <path d="M8 7V5a4 4 0 0 1 8 0v2"/>
                            <path d="M9 13h6"/>
                        </svg>
                    @endif
                </div>
                <div class="erp-pdv-caixa-modal__hero-text">
                    <p class="erp-pdv-caixa-modal__eyebrow">{{ $isAbrir ? 'Início do turno' : 'Encerramento' }}</p>
                    <h2 id="erp-pdv-caixa-title">{{ $isAbrir ? 'Abrir Caixa' : 'Resumo do Caixa' }}</h2>
                    <p class="erp-pdv-caixa-modal__subtitle">
                        {{ $isAbrir
                            ? 'Confira o operador e informe o fundo de troco.'
                            : (($ctx['usuario'] ?? '—').' · '.($ctx['terminal'] ?? 'PDV')) }}
                    </p>
                </div>
                <button type="button" class="erp-pdv-caixa-modal__x" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-caixa-modal__body">
                @if ($isAbrir)
                    <div class="erp-pdv-caixa-modal__meta" aria-label="Contexto da sessão">
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Usuário</span>
                            <strong>{{ $ctx['usuario'] ?? '—' }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Operador</span>
                            <strong>{{ $ctx['operador'] ?? '—' }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Empresa</span>
                            <strong>{{ $ctx['empresa'] ?? '—' }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>PDV</span>
                            <strong>{{ $ctx['terminal'] ?? '—' }}</strong>
                        </div>
                    </div>

                    <div class="erp-pdv-caixa-modal__field">
                        <label class="erp-pdv-caixa-modal__label" for="erp-pdv-abertura-valor">
                            Valor de abertura
                            <small>Troco / fundo de caixa</small>
                        </label>
                        <div class="erp-pdv-caixa-modal__money">
                            <span class="erp-pdv-caixa-modal__currency">R$</span>
                            <input
                                id="erp-pdv-abertura-valor"
                                type="text"
                                name="pdv_abertura_{{ uniqid() }}"
                                wire:model.blur="aberturaForm.valor"
                                data-mask="money-br"
                                data-erp-pdv-money-input="1"
                                inputmode="decimal"
                                class="erp-pdv-caixa-modal__input"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                data-lpignore="true"
                                data-form-type="other"
                                placeholder="0,00"
                                title=""
                            >
                        </div>
                    </div>
                @else
                    <div class="erp-pdv-fechamento-kpis" aria-label="Totais da sessão">
                        <div class="erp-pdv-fechamento-kpi">
                            <span>Entradas</span>
                            <strong>R$ {{ $resumo['total_entrada'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-pdv-fechamento-kpi">
                            <span>Saídas</span>
                            <strong>R$ {{ $resumo['total_saida'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-pdv-fechamento-kpi">
                            <span>Saldo total</span>
                            <strong>R$ {{ $resumo['saldo_total'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-pdv-fechamento-kpi erp-pdv-fechamento-kpi--dinheiro">
                            <span>Saldo em dinheiro</span>
                            <strong>R$ {{ $resumo['saldo_dinheiro'] ?? '0,00' }}</strong>
                        </div>
                    </div>

                    <div class="erp-pdv-fechamento-grid">
                        <div class="erp-pdv-fechamento-panel">
                            <h3>Resumo por forma</h3>
                            <div class="erp-pdv-fechamento-table-wrap">
                                <table class="erp-pdv-fechamento-table">
                                    <thead>
                                        <tr>
                                            <th>Forma</th>
                                            <th>Entrada</th>
                                            <th>Saída</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse (($resumo['movimentos'] ?? []) as $movimento)
                                            <tr>
                                                <td>{{ $movimento['historico'] }}</td>
                                                <td class="is-in">{{ $movimento['entrada'] }}</td>
                                                <td class="is-out">{{ $movimento['saida'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="is-empty">Nenhum movimento.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="erp-pdv-fechamento-side">
                            <div class="erp-pdv-fechamento-panel">
                                <h3>Vendas canceladas <small>{{ $resumo['total_vendas_canceladas'] ?? '0,00' }}</small></h3>
                                <div class="erp-pdv-fechamento-table-wrap erp-pdv-fechamento-table-wrap--sm">
                                    <table class="erp-pdv-fechamento-table">
                                        <thead>
                                            <tr><th>Nº</th><th>Data/hora</th><th>Total</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse (($resumo['vendas_canceladas'] ?? []) as $v)
                                                <tr>
                                                    <td>{{ $v['numero'] }}</td>
                                                    <td class="is-dt">{{ $v['em'] ?? '—' }}</td>
                                                    <td class="is-out">{{ $v['total'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="is-empty">Nenhuma</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="erp-pdv-fechamento-panel">
                                <h3>Produtos cancelados <small>{{ $resumo['total_produtos_cancelados'] ?? '0,00' }}</small></h3>
                                <div class="erp-pdv-fechamento-table-wrap erp-pdv-fechamento-table-wrap--sm">
                                    <table class="erp-pdv-fechamento-table">
                                        <thead>
                                            <tr><th>Produto</th><th>Data/hora</th><th>Qtd</th><th>Total</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse (($resumo['produtos_cancelados'] ?? []) as $p)
                                                <tr>
                                                    <td class="is-produto" title="{{ trim(($p['codigo'] ?? '').' '.($p['descricao'] ?? '')) }}">
                                                        @if (filled($p['codigo'] ?? null))
                                                            <span class="erp-pdv-fechamento-cod">{{ $p['codigo'] }}</span>
                                                        @endif
                                                        {{ $p['descricao'] }}
                                                    </td>
                                                    <td class="is-dt">{{ $p['em'] ?? '—' }}</td>
                                                    <td>{{ $p['qtd'] }}</td>
                                                    <td class="is-out">{{ $p['total'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="is-empty">Nenhum</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="erp-pdv-fechamento-panel">
                                <h3>Espera descartadas <small>{{ $resumo['total_espera_descartadas'] ?? '0,00' }}</small></h3>
                                <div class="erp-pdv-fechamento-table-wrap erp-pdv-fechamento-table-wrap--sm">
                                    <table class="erp-pdv-fechamento-table">
                                        <thead>
                                            <tr><th>Nº</th><th>Data/hora</th><th>Cliente</th><th>It.</th><th>Total</th><th>Motivo</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse (($resumo['espera_descartadas'] ?? []) as $e)
                                                <tr>
                                                    <td>#{{ $e['numero'] }}</td>
                                                    <td class="is-dt">{{ $e['em'] ?? '—' }}</td>
                                                    <td class="is-produto" title="{{ $e['cliente'] ?? '—' }}">{{ $e['cliente'] ?? '—' }}</td>
                                                    <td>{{ $e['itens'] ?? 0 }}</td>
                                                    <td class="is-out">{{ $e['total'] }}</td>
                                                    <td title="{{ $e['motivo'] ?? '—' }}">{{ $e['motivo'] ?? '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="is-empty">Nenhuma</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="erp-pdv-fechamento-dinheiro">
                                <label for="erp-pdv-dinheiro-contado">Dinheiro contado</label>
                                <div class="erp-pdv-fechamento-dinheiro__row">
                                    <div class="erp-pdv-caixa-modal__money">
                                        <span class="erp-pdv-caixa-modal__currency">R$</span>
                                        <input
                                            id="erp-pdv-dinheiro-contado"
                                            type="text"
                                            wire:model.blur="fechamentoForm.dinheiro_informado"
                                            data-mask="money-br"
                                            data-erp-pdv-money-input="1"
                                            inputmode="decimal"
                                            class="erp-pdv-caixa-modal__input"
                                            autocomplete="off"
                                            placeholder="0,00"
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        class="erp-pdv-fechamento-moedas-btn"
                                        wire:click="abrirContarMoedas"
                                        title="Contar moedas e lançar no dinheiro contado"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="8" cy="12" r="4"/>
                                            <circle cx="16" cy="12" r="4"/>
                                        </svg>
                                        Contar moedas
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <footer class="erp-pdv-caixa-modal__footer">
                <button type="button" wire:click="closePdvModal" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                    <kbd>Esc</kbd> Cancelar
                </button>
                @if ($isAbrir)
                    <button type="button" wire:click="confirmAbrirCaixa" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                        <kbd>F2</kbd> Abrir caixa
                    </button>
                @else
                    <button type="button" wire:click="imprimirResumoCaixaFechamento" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                        <kbd>F2</kbd> Imprimir
                    </button>
                    <button type="button" wire:click="confirmFecharCaixa" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--danger">
                        <kbd>F10</kbd> Finalizar
                    </button>
                @endif
            </footer>
        </div>
    </div>
@endif
