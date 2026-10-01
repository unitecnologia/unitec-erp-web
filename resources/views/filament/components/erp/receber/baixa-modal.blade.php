@if ($this->baixaModalOpen)
    <div
        class="erp-lookup-modal erp-receber-baixa-modal"
        wire:keydown.escape.window="closeBaixaModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBaixaModal"></div>

        <div
            class="erp-lookup-modal__window erp-receber-baixa-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-receber-baixa-modal-title"
            wire:click.stop
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-receber-baixa-modal-title">Baixar conta a receber</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeBaixaModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-receber-baixa-modal__body">
                    <section class="erp-receber-baixa-modal__card">
                        <h4 class="erp-receber-baixa-modal__card-title">Dados do título</h4>
                        <div class="erp-receber-baixa-modal__kv erp-receber-baixa-modal__kv--full">
                            <span>Cliente</span>
                            <strong>{{ $this->baixaDados['cliente'] ?? '—' }}</strong>
                        </div>
                        <div class="erp-receber-baixa-modal__facts">
                            <div class="erp-receber-baixa-modal__kv">
                                <span>Documento</span>
                                <strong>{{ $this->baixaDados['documento'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-receber-baixa-modal__kv">
                                <span>Emissão</span>
                                <strong>{{ $this->baixaDados['emissao'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-receber-baixa-modal__kv">
                                <span>Vencimento</span>
                                <strong>{{ $this->baixaDados['vencimento'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-receber-baixa-modal__kv">
                                <span>Valor do título</span>
                                <strong>{{ $this->baixaDados['valor'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-receber-baixa-modal__kv">
                                <span>Valor já recebido</span>
                                <strong>{{ $this->baixaDados['valor_recebido'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-receber-baixa-modal__kv erp-receber-baixa-modal__kv--saldo">
                                <span>Saldo atual</span>
                                <strong>{{ $this->baixaDados['saldo'] ?? '—' }}</strong>
                            </div>
                        </div>
                    </section>

                    <div class="erp-receber-baixa-modal__grid">
                        <label class="erp-receber-baixa-modal__field erp-receber-baixa-modal__field--wide">
                            <span class="erp-receber-baixa-modal__label">Plano de contas</span>
                            <select class="erp-receber-baixa-modal__select" wire:model="baixaPlanoContaId">
                                @foreach ($this->baixaPlanosOptions as $plano)
                                    <option value="{{ $plano['id'] }}">{{ $plano['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Meio de pagamento</span>
                            <select class="erp-receber-baixa-modal__select" wire:model.live="baixaFormaPagamentoId" autofocus>
                                @foreach ($this->baixaFormasOptions as $forma)
                                    <option value="{{ $forma['id'] }}">{{ $forma['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Conta de destino</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro" value="{{ $this->baixaContaDestino }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Saldo</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro" value="{{ $this->baixaSaldo }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Dias de atraso</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro" value="{{ $this->baixaDiasAtraso }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Juros %</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaPercJuros" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Juros R$</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaJuros" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Multa %</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro" value="{{ $this->baixaMultaPct }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Multa R$</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaMulta" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field erp-receber-baixa-modal__field--wide">
                            <span class="erp-receber-baixa-modal__label">Saldo com juros</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro" value="{{ $this->baixaSaldoComJuros }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Desconto %</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaPercDesconto" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Desconto R$</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaDesconto" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Valor a receber</span>
                            <input type="text" class="erp-receber-baixa-modal__select erp-receber-baixa-modal__select--ro erp-receber-baixa-modal__select--accent" value="{{ $this->baixaValorAReceber }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Valor recebido</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model.blur="baixaValorRecebido" inputmode="decimal">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Data do pagamento</span>
                            <input type="date" class="erp-receber-baixa-modal__select" wire:model.live="baixaData">
                        </label>
                        <label class="erp-receber-baixa-modal__field">
                            <span class="erp-receber-baixa-modal__label">Nº do cheque</span>
                            <input type="text" class="erp-receber-baixa-modal__select" wire:model="baixaCheque" maxlength="40">
                        </label>
                    </div>
            </div>

            <div class="erp-receber-baixa-modal__footer">
                <button
                    type="button"
                    class="erp-receber-baixa-modal__btn erp-receber-baixa-modal__btn--cancel"
                    wire:click="closeBaixaModal"
                >Cancelar</button>
                <button
                    type="button"
                    class="erp-receber-baixa-modal__btn erp-receber-baixa-modal__btn--ok"
                    wire:click="confirmarBaixaConta"
                    wire:loading.attr="disabled"
                >OK</button>
            </div>
        </div>
    </div>
@endif
