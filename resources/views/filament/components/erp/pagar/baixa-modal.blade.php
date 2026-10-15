@if ($this->baixaModalOpen)
    <div
        class="erp-pagar-baixa-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeBaixaModal(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.confirmarBaixaConta(); }
        "
    >
        <div class="erp-pagar-baixa-modal__backdrop" wire:click="closeBaixaModal"></div>

        <div
            class="erp-pagar-baixa-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-pagar-baixa-modal-title"
            wire:click.stop
        >
            <header class="erp-pagar-baixa-modal__titlebar">
                <span id="erp-pagar-baixa-modal-title">Baixar — Contas a Pagar</span>
                <button
                    type="button"
                    class="erp-pagar-baixa-modal__close"
                    wire:click="closeBaixaModal"
                    title="ESC | Sair"
                    aria-label="Fechar"
                >&times;</button>
            </header>

            <div class="erp-pagar-baixa-modal__body">
                <section class="erp-pagar-baixa-modal__card">
                    <h4 class="erp-pagar-baixa-modal__card-title">Dados da conta</h4>

                    <div class="erp-pagar-baixa-modal__kv erp-pagar-baixa-modal__kv--fornecedor">
                        <span>Fornecedor</span>
                        <strong>{{ $this->baixaDados['fornecedor'] }}</strong>
                    </div>

                    <div class="erp-pagar-baixa-modal__resumo">
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Documento</span>
                            <strong>{{ $this->baixaDados['documento'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Valor</span>
                            <strong>{{ $this->baixaDados['valor'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Emissão</span>
                            <strong>{{ $this->baixaDados['emissao'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Juros pago</span>
                            <strong>{{ $this->baixaDados['juros_pago'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Vencimento</span>
                            <strong>{{ $this->baixaDados['vencimento'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Multa paga</span>
                            <strong>{{ $this->baixaDados['multa_paga'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Desconto concedido</span>
                            <strong>{{ $this->baixaDados['desconto_recebido'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv">
                            <span>Valor pago</span>
                            <strong>{{ $this->baixaDados['valor_pago_acumulado'] }}</strong>
                        </div>
                        <div class="erp-pagar-baixa-modal__kv erp-pagar-baixa-modal__kv--destaque">
                            <span>Valor a pagar</span>
                            <strong>{{ $this->baixaDados['valor_a_pagar_titulo'] }}</strong>
                        </div>
                    </div>
                </section>

                <section class="erp-pagar-baixa-modal__form">
                    <label class="erp-pagar-baixa-modal__field">
                        <span>Plano de contas</span>
                        <select class="erp-pagar-baixa-modal__input" wire:model="baixaPlanoContaId">
                            <option value="">— Selecione —</option>
                            @foreach ($this->baixaPlanosOptions as $plano)
                                <option value="{{ $plano['id'] }}">{{ $plano['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Conta de destino</span>
                        @if ($this->baixaMovimentaCaixa)
                            <select class="erp-pagar-baixa-modal__input" wire:model="baixaCaixaContaId">
                                @foreach ($this->baixaCaixasOptions as $caixa)
                                    <option value="{{ $caixa['id'] }}">{{ $caixa['label'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--ro" value="Não movimenta o caixa" readonly tabindex="-1">
                        @endif
                    </label>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Meio de pagamento</span>
                        <select class="erp-pagar-baixa-modal__input" wire:model.live="baixaFormaPagamentoId" autofocus>
                            @foreach ($this->baixaFormasOptions as $forma)
                                <option value="{{ $forma['id'] }}">{{ $forma['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Saldo</span>
                        <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--ro" value="{{ $this->baixaSaldo }}" readonly tabindex="-1">
                    </label>

                    <div class="erp-pagar-baixa-modal__field erp-pagar-baixa-modal__field--dual">
                        <span>Juros</span>
                        <div class="erp-pagar-baixa-modal__dual">
                            <div class="erp-pagar-baixa-modal__pair erp-pagar-baixa-modal__pair--compact">
                                <div class="erp-pagar-baixa-modal__pct">
                                    <input type="text" class="erp-pagar-baixa-modal__input" wire:model.live.blur="baixaPercJuros" inputmode="decimal">
                                    <em>%</em>
                                </div>
                                <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--money" wire:model.live.blur="baixaJuros" inputmode="decimal">
                            </div>
                            <div class="erp-pagar-baixa-modal__dual-item">
                                <span>Multa</span>
                                <div class="erp-pagar-baixa-modal__pair erp-pagar-baixa-modal__pair--compact">
                                    <div class="erp-pagar-baixa-modal__pct">
                                        <input type="text" class="erp-pagar-baixa-modal__input" wire:model.live.blur="baixaPercMulta" inputmode="decimal">
                                        <em>%</em>
                                    </div>
                                    <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--money" wire:model.live.blur="baixaMulta" inputmode="decimal">
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Saldo c/ juros/multa</span>
                        <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--ro erp-pagar-baixa-modal__input--money" value="{{ $this->baixaSaldoComJuros }}" readonly tabindex="-1">
                    </label>

                    <div class="erp-pagar-baixa-modal__field erp-pagar-baixa-modal__field--pair">
                        <span>Desconto</span>
                        <div class="erp-pagar-baixa-modal__pair">
                            <div class="erp-pagar-baixa-modal__pct">
                                <input type="text" class="erp-pagar-baixa-modal__input" wire:model.live.blur="baixaPercDesconto" inputmode="decimal">
                                <em>%</em>
                            </div>
                            <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--money" wire:model.live.blur="baixaDesconto" inputmode="decimal">
                        </div>
                    </div>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Valor a pagar</span>
                        <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--ro erp-pagar-baixa-modal__input--money erp-pagar-baixa-modal__input--accent" value="{{ $this->baixaValorAPagar }}" readonly tabindex="-1">
                    </label>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Valor pago</span>
                        <input type="text" class="erp-pagar-baixa-modal__input erp-pagar-baixa-modal__input--money" wire:model.blur="baixaValorPago" inputmode="decimal">
                    </label>

                    <label class="erp-pagar-baixa-modal__field">
                        <span>Pago em</span>
                        <input type="date" class="erp-pagar-baixa-modal__input" wire:model="baixaPagoEm">
                    </label>

                    @if ($this->baixaExigeCheque)
                        <label class="erp-pagar-baixa-modal__field">
                            <span>Nº do cheque</span>
                            <input type="text" class="erp-pagar-baixa-modal__input" wire:model="baixaCheque" maxlength="40">
                        </label>
                    @endif
                </section>
            </div>

            <footer class="erp-pagar-baixa-modal__footer">
                <button
                    type="button"
                    class="erp-pagar-baixa-modal__btn erp-pagar-baixa-modal__btn--save"
                    wire:click="confirmarBaixaConta"
                    wire:loading.attr="disabled"
                >
                    <kbd>F5</kbd> | Salvar
                </button>
                <button
                    type="button"
                    class="erp-pagar-baixa-modal__btn erp-pagar-baixa-modal__btn--exit"
                    wire:click="closeBaixaModal"
                >
                    <kbd>ESC</kbd> | Sair
                </button>
            </footer>
        </div>
    </div>
@endif
