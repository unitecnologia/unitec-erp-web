@if ($this->activeModal === 'finalizar')
    @php
        $finalizarBackdropAction = match (true) {
            $this->finalizarConfirmSair => 'cancelCloseFinalizar',
            $this->finalizarConfirmImprimir => 'cancelFinalizarImprimir',
            default => 'requestCloseFinalizar',
        };
    @endphp
    <div class="erp-pdv-modal erp-pdv-modal--centered" role="dialog" aria-labelledby="erp-pdv-finalizar-title">
        <div
            class="erp-pdv-modal__backdrop"
            wire:click="{{ $finalizarBackdropAction }}"
        ></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--finalizar">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-pdv-finalizar-title">Forma de Pagamento</h2>
                <button
                    type="button"
                    class="erp-pdv-modal__close"
                    wire:click="{{ $finalizarBackdropAction }}"
                    title="Fechar"
                >✕</button>
            </header>

            <div class="erp-pdv-finalizar">
                <div class="erp-pdv-finalizar__top">
                    <div class="erp-pdv-finalizar__cliente">
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--cliente">
                            <span class="erp-pdv-finalizar__label"><u>F2</u> | Selecione o Cliente</span>
                            <input
                                id="erp-pdv-finalizar-cliente"
                                type="text"
                                wire:model.live.debounce.150ms="finalizarClienteSearch"
                                wire:keydown.enter.prevent="confirmFinalizarCliente"
                                class="erp-pdv-finalizar__input"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>

                        @if ($this->finalizarClienteEmConsulta)
                            <div class="erp-pdv-finalizar__cliente-list">
                                <table class="erp-pdv__grid erp-pdv-finalizar__cliente-grid">
                                    <colgroup>
                                        <col class="erp-pdv-finalizar__col-cliente-nome">
                                        <col class="erp-pdv-finalizar__col-cliente-doc">
                                    </colgroup>
                                    <tbody>
                                        @forelse ($this->finalizarClienteResults as $index => $cliente)
                                            <tr
                                                wire:click="selectFinalizarClienteResult({{ $index }})"
                                                wire:dblclick="confirmFinalizarCliente"
                                                wire:key="pdv-finalizar-cliente-{{ $index }}-{{ $cliente['person_id'] ?? 'consumidor' }}"
                                                id="erp-pdv-finalizar-cliente-row-{{ $index }}"
                                                @class([
                                                    'erp-pdv__grid-row',
                                                    'erp-pdv__grid-row--selected' => $this->selectedFinalizarClienteIndex === $index,
                                                ])
                                            >
                                                <td class="erp-pdv__grid-col-descricao">{{ $cliente['nome'] ?? '—' }}</td>
                                                <td class="erp-pdv__grid-col-num erp-pdv-finalizar__cliente-doc">{{ $cliente['cpf_cnpj'] ?? '' }}</td>
                                            </tr>
                                        @empty
                                            <tr class="erp-pdv__grid-empty">
                                                <td colspan="2">Nenhum cliente encontrado.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($this->finalizarLimiteClienteResumo)
                        <div class="erp-pdv-finalizar__limite">
                            <span>Limite: R$ {{ $this->finalizarLimiteClienteResumo['limite'] }}</span>
                            <span>Em aberto: R$ {{ $this->finalizarLimiteClienteResumo['aberto'] }}</span>
                            <span>Disponível: R$ {{ $this->finalizarLimiteClienteResumo['disponivel'] }}</span>
                        </div>
                    @endif

                    @if ($this->pdvRateioPessoa)
                        <div class="erp-pdv-finalizar__split">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--split">
                                <span class="erp-pdv-finalizar__label">Dividir a conta por:</span>
                                <input
                                    type="text"
                                    wire:model.live="finalizarForm.dividir_por"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                    data-mask="integer"
                                    autocomplete="off"
                                >
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--split">
                                <span class="erp-pdv-finalizar__label">Valor por Pessoa:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarValorPorPessoa }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarTotalAPagar }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--total-pagar"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                        </div>
                    @else
                        <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarTotalAPagar }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--total-pagar"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                        </div>
                    @endif
                </div>

                <div class="erp-pdv-finalizar__body">
                    <div class="erp-pdv-finalizar__grid-wrap">
                        <table class="erp-pdv__grid erp-pdv-finalizar__grid">
                            <colgroup>
                                <col class="erp-pdv-finalizar__col-forma">
                                <col class="erp-pdv-finalizar__col-valor">
                                <col class="erp-pdv-finalizar__col-atalho">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><u>F8</u> Forma de Pagamento</th>
                                    <th class="erp-pdv__grid-col-num">Valor</th>
                                    <th class="erp-pdv__grid-col-center">Atalho</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->finalizarPagamentos as $index => $pagamento)
                                    @php
                                        $forma = mb_strtoupper($pagamento['forma'] ?? '', 'UTF-8');
                                        $tipo = strtolower($pagamento['tipo'] ?? '');
                                        $icone = match ($tipo) {
                                            'dinheiro' => 'cash',
                                            'pix' => 'pix',
                                            'cartao_debito' => 'debit',
                                            'cartao_credito' => 'credit',
                                            'crediario' => 'wallet',
                                            'cheque' => 'cheque',
                                            'boleto' => 'boleto',
                                            'deposito', 'tef' => 'transfer',
                                            'troca' => 'voucher',
                                            default => match (true) {
                                                str_contains($forma, 'DINHEIRO') => 'cash',
                                                str_contains($forma, 'PIX') => 'pix',
                                                str_contains($forma, 'DÉBITO'), str_contains($forma, 'DEBITO') => 'debit',
                                                str_contains($forma, 'CRÉDITO'), str_contains($forma, 'CREDITO') => 'credit',
                                                str_contains($forma, 'CREDI') => 'wallet',
                                                str_contains($forma, 'CHEQUE') => 'cheque',
                                                str_contains($forma, 'BOLETO') => 'boleto',
                                                str_contains($forma, 'TRANSFER'), str_contains($forma, 'DEP'), str_contains($forma, 'TEF') => 'transfer',
                                                str_contains($forma, 'VALE'), str_contains($forma, 'TROCA') => 'voucher',
                                                default => 'cash',
                                            },
                                        };
                                        $aPrazo = str_contains($forma, 'CREDI') || str_contains($forma, 'CHEQUE') || str_contains($forma, 'BOLETO');
                                        $temValor = \App\Support\Erp\ErpMoney::parseBr($pagamento['valor'] ?? '0') > 0;
                                    @endphp
                                    <tr
                                        wire:click="selectPagamentoRow({{ $index }})"
                                        wire:key="pdv-pagamento-{{ $index }}"
                                        id="erp-pdv-finalizar-row-{{ $index }}"
                                        data-icone="{{ $icone }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv-finalizar__pay-row',
                                            'erp-pdv-finalizar__pay-row--filled' => $temValor,
                                            'erp-pdv__grid-row--selected' => $this->selectedPagamentoIndex === $index,
                                        ])
                                    >
                                        <td>
                                            <span class="erp-pdv-finalizar__forma">
                                                <span class="erp-pdv-finalizar__forma-icon erp-pdv-finalizar__forma-icon--{{ $icone }}" aria-hidden="true"></span>
                                                <span class="erp-pdv-finalizar__forma-nome">{{ $pagamento['forma'] }}</span>
                                                @if ($aPrazo)
                                                    <span class="erp-pdv-finalizar__forma-tag">a prazo</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="erp-pdv__grid-col-num">
                                            <span class="erp-pdv-finalizar__valor-wrap">
                                                <span class="erp-pdv-finalizar__valor-rs">R$</span>
                                                <input
                                                    id="erp-pdv-finalizar-valor-{{ $index }}"
                                                    type="text"
                                                    wire:model.live.debounce.300ms="finalizarPagamentos.{{ $index }}.valor"
                                                    wire:focus="selectPagamentoRow({{ $index }})"
                                                    class="erp-pdv-finalizar__grid-input"
                                                    data-mask="money"
                                                    autocomplete="off"
                                                >
                                            </span>
                                        </td>
                                        <td
                                            class="erp-pdv__grid-col-center erp-pdv-finalizar__atalho"
                                            wire:click.stop="selectPagamentoByAtalho('{{ $pagamento['atalho'] }}')"
                                        ><kbd class="erp-pdv-finalizar__kbd">{{ $pagamento['atalho'] }}</kbd></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <aside class="erp-pdv-finalizar__totais-panel" aria-label="Totais da venda">
                        <div class="erp-pdv-finalizar__totais">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                <span class="erp-pdv-finalizar__label">Subtotal:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarSubtotal }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                            @if ($this->pdvHabilitarDescontoVenda)
                                <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                    <span class="erp-pdv-finalizar__label">Desconto:</span>
                                    <input
                                        type="text"
                                        wire:model.live="finalizarForm.desconto_venda"
                                        class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                        data-mask="money"
                                        autocomplete="off"
                                    >
                                </label>
                            @endif
                            @if ($this->pdvHabilitarAcrescimoVenda)
                                <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                    <span class="erp-pdv-finalizar__label">Acréscimo:</span>
                                    <input
                                        type="text"
                                        wire:model.live="finalizarForm.acrescimo_venda"
                                        class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                        data-mask="money"
                                        autocomplete="off"
                                    >
                                </label>
                            @endif
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                <span class="erp-pdv-finalizar__label">Valor Restante:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarValorRestante }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                                <span class="erp-pdv-finalizar__label">Troco:</span>
                                <input
                                    type="text"
                                    value="{{ $this->finalizarTroco }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num"
                                    readonly
                                    tabindex="-1"
                                >
                            </label>
                        </div>
                    </aside>
                </div>

                <div class="erp-pdv-finalizar__footer">
                    <div class="erp-pdv-finalizar__footer-main erp-pdv-finalizar__footer-row">
                        <div class="erp-pdv-finalizar__cpf-wrap">
                            <span class="erp-pdv-finalizar__section-title"><u>F6</u> - CPF na Nota</span>
                            <div class="erp-pdv-finalizar__cpf-input-wrap">
                                <input
                                    id="erp-pdv-finalizar-cpf"
                                    type="text"
                                    wire:model="finalizarForm.cpf_nota"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--cpf erp-pdv-finalizar__cpf-input"
                                    data-mask="cpf-cnpj"
                                    data-mask-pessoa="fisica"
                                    inputmode="numeric"
                                    maxlength="14"
                                    autocomplete="off"
                                >
                            </div>
                        </div>
                        <div class="erp-pdv-finalizar__informacoes-col">
                            <span class="erp-pdv-finalizar__section-title">Informações Adicionais</span>
                            <div class="erp-pdv-finalizar__informacoes-wrap">
                                <textarea
                                    id="erp-pdv-finalizar-informacoes"
                                    wire:model="finalizarForm.informacoes_adicionais"
                                    class="erp-pdv-finalizar__informacoes"
                                    spellcheck="false"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer
                class="erp-pdv-modal__footer erp-pdv-finalizar__footer-actions"
                data-operacao-unica="{{ $this->pdvFinalizarOperacaoUnica ?? '' }}"
            >
                <div class="erp-pdv-finalizar__operacao-botoes">
                    @foreach ($this->pdvFinalizarOperacaoBotoes as $botao)
                        <button
                            type="button"
                            wire:click="confirmFinalizarComOperacao('{{ $botao['key'] }}')"
                            @class([
                                'erp-pdv-modal__btn',
                                'erp-pdv-finalizar__operacao-btn',
                                'erp-pdv-finalizar__operacao-btn--pedido' => ! $botao['fiscal'],
                                'erp-pdv-finalizar__operacao-btn--fiscal' => $botao['fiscal'],
                                'erp-pdv-modal__btn--primary' => $botao['primary'],
                            ])
                            data-operacao="{{ $botao['key'] }}"
                            data-atalho="{{ $botao['atalho'] }}"
                            id="erp-pdv-finalizar-op-{{ $botao['key'] }}"
                        >
                            <kbd>{{ $botao['atalho'] }}</kbd>
                            <span>{{ $botao['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <button type="button" wire:click="requestCloseFinalizar" class="erp-pdv-modal__btn">
                    <kbd>Esc</kbd> Cancelar
                </button>
            </footer>

            @if ($this->finalizarConfirmImprimir)
                <div class="erp-pdv-finalizar__confirm" role="dialog" aria-labelledby="erp-pdv-finalizar-imprimir-title">
                    <div class="erp-pdv-finalizar__confirm-window erp-pdv-modal__window erp-pdv-modal__window--small">
                        <header class="erp-pdv-modal__header">
                            <h2 id="erp-pdv-finalizar-imprimir-title">Impressão</h2>
                        </header>
                        <div class="erp-pdv-modal__body">
                            <p class="erp-pdv-modal__confirm-text">
                                Deseja imprimir o documento?
                            </p>
                        </div>
                        <footer class="erp-pdv-modal__footer">
                            <button
                                type="button"
                                wire:click="confirmFinalizarImprimir(true)"
                                class="erp-pdv-modal__btn"
                                id="erp-pdv-finalizar-imprimir-sim"
                            >Sim</button>
                            <button
                                type="button"
                                wire:click="confirmFinalizarImprimir(false)"
                                class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                                id="erp-pdv-finalizar-imprimir-nao"
                            >Não</button>
                        </footer>
                    </div>
                </div>
            @endif

            @if ($this->finalizarConfirmSair)
                <div class="erp-pdv-finalizar__confirm" role="dialog" aria-labelledby="erp-pdv-finalizar-sair-title">
                    <div class="erp-pdv-finalizar__confirm-window erp-pdv-modal__window erp-pdv-modal__window--small">
                        <header class="erp-pdv-modal__header">
                            <h2 id="erp-pdv-finalizar-sair-title">Confirmação</h2>
                        </header>
                        <div class="erp-pdv-modal__body">
                            <p class="erp-pdv-modal__confirm-text">
                                Tem certeza de que deseja sair da tela de forma de pagamento?
                            </p>
                        </div>
                        <footer class="erp-pdv-modal__footer">
                            <button
                                type="button"
                                wire:click="confirmCloseFinalizar"
                                class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                                id="erp-pdv-finalizar-sair-sim"
                            >Sim</button>
                            <button
                                type="button"
                                wire:click="cancelCloseFinalizar"
                                class="erp-pdv-modal__btn"
                                id="erp-pdv-finalizar-sair-nao"
                            >Não</button>
                        </footer>
                    </div>
                </div>
            @endif

            @if (filled($this->finalizarAlertaTitulo))
                <div class="erp-pdv-finalizar-aviso" role="alertdialog" aria-labelledby="erp-pdv-finalizar-aviso-title">
                    <div class="erp-pdv-naoencontrado__box">
                        <div class="erp-pdv-naoencontrado__icon" aria-hidden="true">!</div>
                        <h2 id="erp-pdv-finalizar-aviso-title" class="erp-pdv-naoencontrado__title">
                            {{ $this->finalizarAlertaTitulo }}
                        </h2>
                        @if (filled($this->finalizarAlertaDetalhe))
                            <p class="erp-pdv-naoencontrado__codigo">{{ $this->finalizarAlertaDetalhe }}</p>
                        @endif
                        <button
                            type="button"
                            wire:click="fecharFinalizarAlerta"
                            class="erp-pdv-naoencontrado__btn"
                            id="erp-pdv-finalizar-aviso-ok"
                        >OK</button>
                        <p class="erp-pdv-naoencontrado__hint">
                            Preencha o pagamento e clique em OK para continuar.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
