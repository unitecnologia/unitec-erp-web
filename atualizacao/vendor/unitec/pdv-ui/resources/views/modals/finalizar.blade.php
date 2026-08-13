@if ($this->activeModal === 'finalizar')
    @php
        $finalizarBackdropAction = match (true) {
            $this->finalizarConfirmSair => 'cancelCloseFinalizar',
            $this->finalizarConfirmImprimir => 'cancelFinalizarImprimir',
            default => 'requestCloseFinalizar',
        };
    @endphp
    <div class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-modal--payment" role="dialog" aria-labelledby="erp-pdv-finalizar-title">
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

            <div
                class="erp-pdv-finalizar"
                data-cliente-consulta="{{ $this->finalizarClienteEmConsulta ? '1' : '0' }}"
            >
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
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->finalizarValorPorPessoa }}</span>
                                </span>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <span class="erp-pdv-finalizar__total-pagar-value" aria-live="polite">
                                    {{ $this->finalizarTotalAPagar }}
                                </span>
                            </label>
                        </div>
                    @else
                        <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <span class="erp-pdv-finalizar__total-pagar-value" aria-live="polite">
                                    {{ $this->finalizarTotalAPagar }}
                                </span>
                            </label>
                        </div>
                    @endif
                </div>

                @if ($this->finalizarCartaoCanhotoAberta)
                    <div class="erp-pdv-canhoto-overlay" role="dialog" aria-labelledby="erp-pdv-canhoto-title">
                        <div class="erp-pdv-parcelas erp-pdv-canhoto">
                            <header class="erp-pdv-parcelas__header">
                                <h3 id="erp-pdv-canhoto-title">Canhoto POS | Contas a Receber</h3>
                                <button type="button" class="erp-pdv-modal__close" wire:click="cancelFinalizarCartaoCanhoto" title="Fechar">✕</button>
                            </header>

                            <div class="erp-pdv-parcelas__toolbar">
                                <div class="erp-pdv-parcelas__avulso">
                                    <span class="erp-pdv-parcelas__avulso-title">Dados do canhoto</span>
                                    <div class="erp-pdv-parcelas__avulso-fields">
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Total</span>
                                            <input type="text" value="{{ \Unitec\PdvUi\Support\PdvMoney::formatBr($this->finalizarCartaoTotalValor) }}" readonly tabindex="-1">
                                        </label>
                                        <label class="erp-pdv-parcelas__field">
                                            <span>NSU</span>
                                            <input id="erp-pdv-canhoto-nsu" type="text" wire:model="finalizarCartaoNsu" autocomplete="off">
                                        </label>
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Autorização</span>
                                            <input id="erp-pdv-canhoto-autorizacao" type="text" wire:model="finalizarCartaoAutorizacao" autocomplete="off">
                                        </label>
                                        <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                                            <span>Maquininha</span>
                                            <select id="erp-pdv-canhoto-maquininha" wire:model="finalizarCartaoMaquininha">
                                                <option value="">— Selecione —</option>
                                                @php
                                                    $maqs = $this->cartaoMaquininhaOptions
                                                        ?? (class_exists(\App\Models\CartaoMaquininha::class)
                                                            ? \App\Models\CartaoMaquininha::optionsAtivas()
                                                            : []);
                                                @endphp
                                                @foreach ($maqs as $nome)
                                                    <option value="{{ $nome }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                                            <span>Bandeira</span>
                                            <select id="erp-pdv-canhoto-bandeira" wire:model="finalizarCartaoBandeira">
                                                <option value="">— Selecione —</option>
                                                @php
                                                    $bands = $this->cartaoBandeiraOptions
                                                        ?? (class_exists(\App\Models\CartaoBandeira::class)
                                                            ? \App\Models\CartaoBandeira::optionsAtivas()
                                                            : []);
                                                @endphp
                                                @foreach ($bands as $nome)
                                                    <option value="{{ $nome }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                    <div class="erp-pdv-parcelas__avulso-fields" style="margin-top:0.5rem;">
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Qtd. Parcelas</span>
                                            <input id="erp-pdv-canhoto-qtd" type="text" wire:model="finalizarCartaoParcelasQtd" data-mask="integer" autocomplete="off">
                                        </label>
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Intervalo (dias)</span>
                                            <input id="erp-pdv-canhoto-intervalo" type="text" wire:model="finalizarCartaoIntervalo" data-mask="integer" autocomplete="off">
                                        </label>
                                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarParcelasCartaoCanhoto">
                                            <kbd>F2</kbd> Gerar
                                        </button>
                                    </div>
                                </div>

                                <div class="erp-pdv-parcelas__toolbar-actions">
                                    <button type="button" class="erp-pdv-modal__btn" wire:click="cancelFinalizarCartaoCanhoto">
                                        <kbd>F4</kbd> Cancelar
                                    </button>
                                </div>
                            </div>

                            <div class="erp-pdv-parcelas__grid-wrap" id="erp-pdv-finalizar-cartao-canhoto">
                                <table class="erp-pdv__grid erp-pdv-parcelas__grid">
                                    <thead>
                                        <tr>
                                            <th>Documento</th>
                                            <th>Vencimento</th>
                                            <th class="erp-pdv__grid-col-num">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->finalizarCartaoParcelasRows as $index => $row)
                                            <tr
                                                wire:click="selectFinalizarCartaoParcelaRow({{ $index }})"
                                                wire:key="pdv-cartao-parcela-{{ $index }}"
                                                id="erp-pdv-finalizar-canhoto-row-{{ $index }}"
                                                @class([
                                                    'erp-pdv__grid-row',
                                                    'erp-pdv__grid-row--selected' => $this->selectedFinalizarCartaoParcelaIndex === $index,
                                                ])
                                            >
                                                <td>{{ $row['documento'] ?? '—' }}</td>
                                                <td>{{ $row['vencimento'] ?? '—' }}</td>
                                                <td class="erp-pdv__grid-col-num">R$ {{ $row['valor'] ?? '0,00' }}</td>
                                            </tr>
                                        @empty
                                            <tr class="erp-pdv__grid-empty">
                                                <td colspan="3">Informe NSU/Autorização/Bandeira e use F2 | Gerar parcelas.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <footer class="erp-pdv-parcelas__footer">
                                <div class="erp-pdv-parcelas__total">
                                    <span>Total Parcelas</span>
                                    <strong>{{ $this->finalizarCartaoParcelasTotalLabel !== '' ? 'R$ '.$this->finalizarCartaoParcelasTotalLabel : '' }}</strong>
                                </div>
                                <div class="erp-pdv-parcelas__footer-actions">
                                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirCartaoCanhoto">
                                        <kbd>F7</kbd> Concluir
                                    </button>
                                </div>
                            </footer>
                        </div>
                    </div>
                @elseif ($this->finalizarTabelaPrazoEmConsulta)
                    <div class="erp-pdv-parcelas-overlay" role="dialog" aria-labelledby="erp-pdv-parcelas-title">
                        <div class="erp-pdv-parcelas">
                            <header class="erp-pdv-parcelas__header">
                                <h3 id="erp-pdv-parcelas-title">Contas Receber | Parcelas</h3>
                                <button type="button" class="erp-pdv-modal__close" wire:click="cancelFinalizarTabelaPrazoConsulta" title="Fechar">✕</button>
                            </header>

                            <div class="erp-pdv-parcelas__toolbar">
                                <div class="erp-pdv-parcelas__avulso">
                                    <span class="erp-pdv-parcelas__avulso-title">Avulso</span>
                                    <div class="erp-pdv-parcelas__avulso-fields">
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Total</span>
                                            <input type="text" value="{{ \Unitec\PdvUi\Support\PdvMoney::formatBr($this->finalizarCrediarioTotalValor) }}" readonly tabindex="-1">
                                        </label>
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Parcelas</span>
                                            <input
                                                id="erp-pdv-parcelas-qtd"
                                                type="text"
                                                wire:model="finalizarParcelasQtd"
                                                data-mask="integer"
                                                autocomplete="off"
                                            >
                                        </label>
                                        <label class="erp-pdv-parcelas__field">
                                            <span>Intervalo</span>
                                            <input
                                                id="erp-pdv-parcelas-intervalo"
                                                type="text"
                                                wire:model="finalizarParcelasIntervalo"
                                                data-mask="integer"
                                                autocomplete="off"
                                            >
                                        </label>
                                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarParcelasCrediario">
                                            <kbd>F2</kbd> Gerar
                                        </button>
                                    </div>
                                </div>

                                <div class="erp-pdv-parcelas__toolbar-actions">
                                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--info" wire:click="abrirTabelasPrazoPredefinidas">
                                        <kbd>F8</kbd> Tabelas
                                    </button>
                                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger" wire:click="excluirParcelaCrediario">
                                        <kbd>F3</kbd> Excluir
                                    </button>
                                    <button type="button" class="erp-pdv-modal__btn" wire:click="cancelFinalizarTabelaPrazoConsulta">
                                        <kbd>F4</kbd> Cancelar
                                    </button>
                                </div>
                            </div>

                            @if ($this->finalizarTabelasPrazoListaAberta)
                                <div class="erp-pdv-parcelas__tabelas" id="erp-pdv-parcelas-tabelas">
                                    <div class="erp-pdv-parcelas__tabelas-head">
                                        <strong>Prazos pré-definidos</strong>
                                        <button type="button" class="erp-pdv-parcelas__tabelas-close" wire:click="fecharTabelasPrazoPredefinidas" title="Fechar">✕</button>
                                    </div>
                                    <div class="erp-pdv-parcelas__tabelas-list">
                                        <table class="erp-pdv__grid erp-pdv-parcelas__tabelas-grid">
                                            <thead>
                                                <tr>
                                                    <th>Dias</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($this->finalizarTabelasPrazoPredefinidas as $index => $tabela)
                                                    <tr
                                                        wire:click="selectFinalizarTabelaPredefinida({{ $index }})"
                                                        wire:dblclick="aplicarTabelaPrazoPredefinida"
                                                        wire:key="pdv-tabela-predef-{{ $index }}-{{ $tabela['tabela_prazo_id'] }}"
                                                        id="erp-pdv-tabela-predef-row-{{ $index }}"
                                                        @class([
                                                            'erp-pdv__grid-row',
                                                            'erp-pdv__grid-row--selected' => $this->selectedFinalizarTabelaPredefinidaIndex === $index,
                                                        ])
                                                    >
                                                        <td>{{ $tabela['label'] ?? $tabela['dias'] ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="erp-pdv-parcelas__tabelas-actions">
                                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="aplicarTabelaPrazoPredefinida">
                                            Enter | Usar tabela
                                        </button>
                                        <button type="button" class="erp-pdv-modal__btn" wire:click="fecharTabelasPrazoPredefinidas">
                                            ESC | Voltar
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div class="erp-pdv-parcelas__grid-wrap" id="erp-pdv-finalizar-tabela-prazo">
                                <table class="erp-pdv__grid erp-pdv-parcelas__grid @if ($this->finalizarParcelasEhCheque) erp-pdv-parcelas__grid--cheque @endif">
                                    <thead>
                                        <tr>
                                            <th>Documento</th>
                                            <th>Vencimento</th>
                                            <th class="erp-pdv__grid-col-num">Valor</th>
                                            @if ($this->finalizarParcelasEhCheque)
                                                <th>Nº Cheque</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->finalizarParcelasRows as $index => $row)
                                            <tr
                                                wire:click="selectFinalizarParcelaRow({{ $index }})"
                                                wire:key="pdv-parcela-{{ $index }}"
                                                id="erp-pdv-finalizar-prazo-row-{{ $index }}"
                                                @class([
                                                    'erp-pdv__grid-row',
                                                    'erp-pdv__grid-row--selected' => $this->selectedFinalizarParcelaIndex === $index,
                                                ])
                                            >
                                                <td>{{ $row['documento'] ?? '—' }}</td>
                                                <td>{{ $row['vencimento'] ?? '—' }}</td>
                                                <td class="erp-pdv__grid-col-num">R$ {{ $row['valor'] ?? '0,00' }}</td>
                                                @if ($this->finalizarParcelasEhCheque)
                                                    <td>
                                                        <input
                                                            type="text"
                                                            class="erp-pdv-parcelas__cheque-input"
                                                            id="erp-pdv-parcela-cheque-{{ $index }}"
                                                            wire:model.live="finalizarParcelasRows.{{ $index }}.numero_cheque"
                                                            wire:click.stop
                                                            autocomplete="off"
                                                            maxlength="40"
                                                            placeholder="Nº"
                                                        >
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr class="erp-pdv__grid-empty">
                                                <td colspan="{{ $this->finalizarParcelasEhCheque ? 4 : 3 }}">Avulso: Parcelas/Intervalo + F2 | Gerar — ou F8 | Tabelas pré-definidas.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <footer class="erp-pdv-parcelas__footer">
                                <div class="erp-pdv-parcelas__total">
                                    <span>Total Parcelas</span>
                                    <strong>{{ $this->finalizarParcelasTotalLabel !== '' ? 'R$ '.$this->finalizarParcelasTotalLabel : '' }}</strong>
                                </div>
                                <div class="erp-pdv-parcelas__footer-actions">
                                    @if ($this->finalizarParcelasEhBoleto)
                                        <button type="button" class="erp-pdv-modal__btn" disabled title="Em breve">
                                            <kbd>F5</kbd> Boleto
                                        </button>
                                    @endif
                                    @if ($this->finalizarParcelasEhCrediario)
                                        <button type="button" id="erp-pdv-carne-btn" class="erp-pdv-modal__btn" wire:click="abrirCarneImpressao">
                                            <kbd>F6</kbd> Carnê
                                        </button>
                                    @endif
                                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirParcelasCrediario">
                                        <kbd>F7</kbd> Concluir
                                    </button>
                                </div>
                            </footer>

                            @if ($this->finalizarCarneImpressaoAberta)
                                <div class="erp-pdv-carne-print" role="dialog" aria-labelledby="erp-pdv-carne-print-title">
                                    <div class="erp-pdv-carne-print__backdrop" wire:click="fecharCarneImpressao"></div>
                                    <div class="erp-pdv-carne-print__window">
                                        <header class="erp-pdv-carne-print__header">
                                            <h3 id="erp-pdv-carne-print-title">Impressão</h3>
                                            <button type="button" class="erp-pdv-modal__close" wire:click="fecharCarneImpressao" title="Fechar">✕</button>
                                        </header>
                                        <div class="erp-pdv-carne-print__body">
                                            <div class="erp-pdv-carne-print__icon" aria-hidden="true">🖨</div>
                                            <div class="erp-pdv-carne-print__options">
                                                <button type="button" id="erp-pdv-carne-print-a4-capa" class="erp-pdv-carne-print__option" wire:click="escolherCarneImpressaoA4ComCapa">
                                                    <kbd>1</kbd> A4 com capa
                                                </button>
                                                <button type="button" id="erp-pdv-carne-print-a4" class="erp-pdv-carne-print__option" wire:click="escolherCarneImpressaoA4">
                                                    <kbd>2</kbd> A4 só parcelas
                                                </button>
                                                <button type="button" id="erp-pdv-carne-print-bobina" class="erp-pdv-carne-print__option" wire:click="escolherCarneImpressaoBobina80">
                                                    <kbd>3</kbd> bobina 80
                                                </button>
                                                <button type="button" id="erp-pdv-carne-print-sair" class="erp-pdv-carne-print__option erp-pdv-carne-print__option--exit" wire:click="fecharCarneImpressao">
                                                    Sair
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif (filled($this->finalizarTabelaPrazoLabel))
                    <div class="erp-pdv-finalizar__prazo-resumo">
                        <span>Parcelas:</span>
                        <strong>{{ $this->finalizarTabelaPrazoLabel }}</strong>
                    </div>
                @endif

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
                                                \Unitec\PdvUi\Support\PdvFormas::isFormaCrediario($forma) => 'wallet',
                                                str_contains($forma, 'CHEQUE') => 'cheque',
                                                str_contains($forma, 'BOLETO') => 'boleto',
                                                str_contains($forma, 'TRANSFER'), str_contains($forma, 'DEP'), str_contains($forma, 'TEF') => 'transfer',
                                                str_contains($forma, 'VALE'), str_contains($forma, 'TROCA') => 'voucher',
                                                default => 'cash',
                                            },
                                        };
                                        $temValor = \Unitec\PdvUi\Support\PdvMoney::parseBr($pagamento['valor'] ?? '0') > 0;
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
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->finalizarSubtotal }}</span>
                                </span>
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
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->finalizarValorRestante }}</span>
                                </span>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                                <span class="erp-pdv-finalizar__label">Troco:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->finalizarTroco }}</span>
                                </span>
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
                <button type="button" wire:click="requestCloseFinalizar" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger">
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
                <div
                    class="erp-pdv-finalizar-aviso"
                    role="alertdialog"
                    aria-labelledby="erp-pdv-finalizar-aviso-title"
                    data-opened-at="{{ (int) round(microtime(true) * 1000) }}"
                    data-foco="{{ $this->finalizarAlertaFoco ?? 'pagamento' }}"
                >
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
