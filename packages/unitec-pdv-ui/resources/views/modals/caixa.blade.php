@if (in_array($this->activeModal, ['abrir_caixa', 'fechar_caixa'], true))
    @php
        $isAbrir = $this->activeModal === 'abrir_caixa';
        $ctx = $this->caixaModalContexto ?? [
            'usuario' => '—',
            'operador' => '—',
            'empresa' => '—',
            'terminal' => '—',
        ];
    @endphp
    <div class="erp-pdv-modal erp-pdv-caixa-modal" role="dialog" aria-labelledby="erp-pdv-caixa-title" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-caixa-modal__window {{ $isAbrir ? 'is-abrir' : 'is-fechar' }}">
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
                    <h2 id="erp-pdv-caixa-title">{{ $isAbrir ? 'Abrir Caixa' : 'Fechar Caixa' }}</h2>
                    <p class="erp-pdv-caixa-modal__subtitle">
                        {{ $isAbrir
                            ? 'Confira o operador e informe o fundo de troco.'
                            : 'Nenhuma venda poderá ser feita até reabrir.' }}
                    </p>
                </div>
                <button type="button" class="erp-pdv-caixa-modal__x" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-caixa-modal__body">
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

                @if ($isAbrir)
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
                                inputmode="numeric"
                                name="pdv_abertura_{{ uniqid() }}"
                                wire:model.blur="aberturaForm.valor"
                                data-mask="money"
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
                    <div class="erp-pdv-caixa-modal__alert" role="status">
                        <strong>Confirmar fechamento?</strong>
                        <p>O caixa será encerrado e o saldo em dinheiro irá para o Livro Caixa. Cartões continuam no Contas a Receber.</p>
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
                    <button type="button" wire:click="confirmFecharCaixa" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--danger">
                        <kbd>F2</kbd> Fechar caixa
                    </button>
                @endif
            </footer>
        </div>
    </div>
@endif
