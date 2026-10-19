@if (in_array($this->activeModal, ['abrir_caixa', 'fechar_caixa'], true))
    @php
        $isAbrir = $this->activeModal === 'abrir_caixa';
        $ctx = $this->caixaModalContexto;
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
                            ? 'Selecione o usuário, informe a senha e o fundo de troco.'
                            : 'Nenhuma venda poderá ser feita até reabrir.' }}
                    </p>
                </div>
                <button type="button" class="erp-pdv-caixa-modal__x" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-caixa-modal__body">
                @if ($isAbrir)
                    <div class="erp-pdv-caixa-modal__meta" aria-label="Contexto da sessão">
                        <div class="erp-pdv-caixa-modal__meta-item erp-pdv-caixa-modal__meta-item--editable">
                            <label for="erp-pdv-abertura-usuario">Usuário</label>
                            <select
                                id="erp-pdv-abertura-usuario"
                                class="erp-pdv-caixa-modal__select"
                                wire:model.live="aberturaForm.usuario_id"
                            >
                                @foreach ($this->aberturaUsuariosOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Operador</span>
                            <strong>{{ $this->aberturaOperadorLabel }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Empresa</span>
                            <strong>{{ $ctx['empresa'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>PDV</span>
                            <strong>{{ $ctx['terminal'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item erp-pdv-caixa-modal__meta-item--caixa">
                            <span>Caixa</span>
                            <strong>{{ $this->aberturaCaixaLabel }}</strong>
                        </div>
                    </div>

                    <div class="erp-pdv-caixa-modal__field">
                        <label class="erp-pdv-caixa-modal__label" for="erp-pdv-abertura-senha">
                            Senha do usuário
                            <small>Obrigatória para abrir o caixa</small>
                        </label>
                        <div class="erp-pdv-caixa-modal__password-wrap">
                            <input
                                id="erp-pdv-abertura-senha"
                                type="password"
                                wire:model="aberturaForm.senha"
                                wire:keydown.enter.prevent="confirmAbrirCaixa"
                                class="erp-pdv-caixa-modal__text-input erp-pdv-caixa-modal__text-input--password"
                                autocomplete="new-password"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                data-form-type="other"
                                placeholder="Digite a senha"
                            >
                            <button
                                type="button"
                                class="erp-pdv-caixa-modal__password-toggle"
                                data-erp-password-toggle="erp-pdv-abertura-senha"
                                aria-label="Exibir senha"
                                title="Exibir senha"
                            >
                                <svg class="erp-pdv-caixa-modal__password-icon erp-pdv-caixa-modal__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <svg class="erp-pdv-caixa-modal__password-icon erp-pdv-caixa-modal__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
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
                    <div class="erp-pdv-caixa-modal__meta" aria-label="Contexto da sessão">
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Usuário</span>
                            <strong>{{ $ctx['usuario'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Operador</span>
                            <strong>{{ $ctx['operador'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>Empresa</span>
                            <strong>{{ $ctx['empresa'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item">
                            <span>PDV</span>
                            <strong>{{ $ctx['terminal'] }}</strong>
                        </div>
                        <div class="erp-pdv-caixa-modal__meta-item erp-pdv-caixa-modal__meta-item--caixa">
                            <span>Caixa</span>
                            <strong>{{ $ctx['caixa'] ?? 'CAIXA GERAL' }}</strong>
                        </div>
                    </div>

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
