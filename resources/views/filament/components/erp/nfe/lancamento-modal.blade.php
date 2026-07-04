@if ($this->nfeModalOpen)
    @php
        $mainTabs = [
            'itens' => 'Itens',
            'impostos' => 'Impostos / Outros',
            'pagamento' => 'Pagamento',
        ];

        $detailTabs = [
            'totais' => 'Totais',
            'volumes' => 'Volumes',
            'fisco' => 'Informações do Fisco',
            'contribuinte' => 'Informações do Contribuinte',
            'transportadora' => 'Transportadora',
            'referencia' => 'Referência',
            'contingencia' => 'Contingência',
        ];

        $totaisLabels = [
            ['key' => 'subtotal', 'label' => 'Sub Total'],
            ['key' => 'base_cofins', 'label' => 'Base Cofins'],
            ['key' => 'valor_cofins', 'label' => 'Valor Cofins'],
            ['key' => 'base_pis', 'label' => 'Base PIS'],
            ['key' => 'valor_pis', 'label' => 'Valor PIS'],
            ['key' => 'base_ipi', 'label' => 'Base de IPI'],
            ['key' => 'valor_ipi', 'label' => 'Valor de IPI'],
            ['key' => 'frete', 'label' => 'Frete'],
            ['key' => 'seguro', 'label' => 'Seguro'],
            ['key' => 'outras', 'label' => 'Outras'],
            ['key' => 'desconto', 'label' => 'Desconto'],
            ['key' => 'desoneracao', 'label' => 'Desoneração'],
            ['key' => 'base_icms', 'label' => 'Base de ICMS'],
            ['key' => 'valor_icms', 'label' => 'Valor de ICMS'],
            ['key' => 'base_st', 'label' => 'Base de ICMS ST'],
            ['key' => 'valor_st', 'label' => 'Valor de ICMS ST'],
            ['key' => 'total', 'label' => 'Total'],
        ];
    @endphp

    <div
        class="erp-lookup-modal erp-nfe-lancamento-modal"
        wire:keydown.escape.window="closeNfeModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeModal"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nfe-lancamento-title"
        >
            <div class="erp-lookup-modal__titlebar erp-nfe-lancamento-modal__titlebar">
                <span id="erp-nfe-lancamento-title">Emissão de NFe</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfeModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-lancamento-modal__body">
                <div class="erp-nfe-lancamento-modal__header">
                    <div class="erp-nfe-lancamento-modal__header-top">
                        <div class="erp-nfe-lancamento-modal__form-row">
                            <div class="erp-nfe-lancamento-modal__form-group">
                                <label class="erp-nfe-lancamento-modal__form-label">Nº Nota</label>
                                <input
                                    type="text"
                                    wire:model="nfeForm.numero"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--xs"
                                    readonly
                                >
                            </div>

                            <div class="erp-nfe-lancamento-modal__form-group">
                                <label class="erp-nfe-lancamento-modal__form-label">Empresa</label>
                                <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--empresa">{{ $this->nfeForm['empresa'] ?? '—' }}</span>
                            </div>

                            <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                                <label class="erp-nfe-lancamento-modal__form-label">Cliente/Fornecedor</label>
                                <select wire:model.live="nfeForm.cliente_id" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--fornecedor">
                                    <option value="">Selecione...</option>
                                    @foreach ($this->clientesOptions as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-nfe-lancamento-modal__form-group">
                                <label class="erp-nfe-lancamento-modal__form-label">UF</label>
                                <input
                                    type="text"
                                    wire:model="nfeForm.uf"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--uf"
                                    maxlength="2"
                                    readonly
                                >
                            </div>

                            <div class="erp-nfe-lancamento-modal__form-group">
                                <label class="erp-nfe-lancamento-modal__form-label">CNPJ</label>
                                <input
                                    type="text"
                                    wire:model="nfeForm.cnpj"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--doc"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="erp-nfe-lancamento-modal__status-box">
                            {{ $this->nfeModalStatus }}
                        </div>
                    </div>

                    <div class="erp-nfe-lancamento-modal__form-row">
                        <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                            <label class="erp-nfe-lancamento-modal__form-label">Natureza da Operação</label>
                            <select wire:model="nfeForm.natureza_operacao" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--natureza">
                                <option value="5102 - VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS">5102 - VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS</option>
                                <option value="5101 - VENDA DE PRODUCAO DO ESTABELECIMENTO">5101 - VENDA DE PRODUCAO DO ESTABELECIMENTO</option>
                                <option value="5405 - VENDA DE MERCADORIA SUJEITA A ST">5405 - VENDA DE MERCADORIA SUJEITA A ST</option>
                            </select>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Nº Pedido</label>
                            <input
                                type="text"
                                wire:model="nfeForm.numero_pedido"
                                class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--sm"
                            >
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Data Emissão</label>
                            <input
                                type="date"
                                wire:model="nfeForm.data_emissao"
                                class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date"
                            >
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Data Saída</label>
                            <input
                                type="date"
                                wire:model="nfeForm.data_saida"
                                class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date"
                            >
                        </div>

                        <label class="erp-nfe-lancamento-modal__check">
                            <input type="checkbox" wire:model="nfeForm.consumidor_final">
                            <span>Consumidor Final</span>
                        </label>
                    </div>

                    <div class="erp-nfe-lancamento-modal__form-row">
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Finalidade</label>
                            <select wire:model="nfeForm.finalidade" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--combo">
                                <option value="normal">NORMAL</option>
                                <option value="complementar">COMPLEMENTAR</option>
                                <option value="ajuste">AJUSTE</option>
                                <option value="devolucao">DEVOLUÇÃO</option>
                            </select>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Movimento</label>
                            <select wire:model="nfeForm.movimento" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--combo">
                                <option value="saida">SAÍDA</option>
                                <option value="entrada">ENTRADA</option>
                            </select>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Forma de Pgto</label>
                            <select wire:model="nfeForm.forma_pgto" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--combo">
                                <option value="a_vista">À VISTA</option>
                                <option value="a_prazo">À PRAZO</option>
                                <option value="outros">OUTROS</option>
                            </select>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Meio de Pgto</label>
                            <select wire:model="nfeForm.meio_pgto" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--combo">
                                <option value="dinheiro">DINHEIRO</option>
                                <option value="cartao">CARTÃO</option>
                                <option value="boleto">BOLETO</option>
                                <option value="pix">PIX</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__section-tabs">
                    @foreach ($mainTabs as $value => $label)
                        <button
                            type="button"
                            wire:click="setNfeModalMainTab('{{ $value }}')"
                            @class(['erp-nfe-lancamento-modal__section-tab', 'erp-nfe-lancamento-modal__section-tab--active' => $this->nfeModalMainTab === $value])
                        >{{ $label }}</button>
                    @endforeach
                </div>

                @if ($this->nfeModalMainTab === 'itens')
                    @include('filament.components.erp.nfe.lancamento-itens-grid')
                @elseif ($this->nfeModalMainTab === 'impostos')
                    @include('filament.components.erp.nfe.lancamento-impostos-grid')
                @else
                    <div class="erp-nfe-lancamento-modal__panel">
                        <div class="erp-nfe-lancamento-modal__parcelas-actions">
                            <button type="button" wire:click="gerarNfeParcelas(1)" class="erp-nfe-lancamento-modal__tool-btn">1x</button>
                            <button type="button" wire:click="gerarNfeParcelas(3)" class="erp-nfe-lancamento-modal__tool-btn">3x</button>
                            <button type="button" wire:click="gerarNfeParcelas(6)" class="erp-nfe-lancamento-modal__tool-btn">6x</button>
                            <button type="button" wire:click="gerarNfeParcelas(10)" class="erp-nfe-lancamento-modal__tool-btn">10x</button>
                        </div>
                        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap">
                            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid">
                                <thead>
                                    <tr>
                                        <th>Parcela</th>
                                        <th>Vencimento</th>
                                        <th class="erp-nfe-lancamento-modal__num">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->nfeModalFaturas as $fatura)
                                        <tr>
                                            <td class="erp-nfe-lancamento-modal__center">{{ $fatura['numero'] }}</td>
                                            <td class="erp-nfe-lancamento-modal__center">{{ $fatura['data_vencimento'] }}</td>
                                            <td class="erp-nfe-lancamento-modal__num">{{ $fatura['valor'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="erp-lookup-modal__empty">Nenhuma parcela. Use os botões acima ou forma à vista.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="erp-nfe-lancamento-modal__section-tabs erp-nfe-lancamento-modal__section-tabs--detail">
                    @foreach ($detailTabs as $value => $label)
                        <button
                            type="button"
                            wire:click="setNfeModalDetailTab('{{ $value }}')"
                            @class(['erp-nfe-lancamento-modal__section-tab', 'erp-nfe-lancamento-modal__section-tab--active' => $this->nfeModalDetailTab === $value])
                        >{{ $label }}</button>
                    @endforeach
                </div>

                @if ($this->nfeModalDetailTab === 'totais')
                    <div class="erp-nfe-lancamento-modal__detail-panel">
                        <div class="erp-nfe-lancamento-modal__totais-grid">
                            @foreach ($totaisLabels as $item)
                                <div class="erp-nfe-lancamento-modal__total-field">
                                    <span class="erp-nfe-lancamento-modal__total-label">{{ $item['label'] }}</span>
                                    <span @class([
                                        'erp-nfe-lancamento-modal__total-value',
                                        'erp-nfe-lancamento-modal__total-value--strong' => $item['key'] === 'total',
                                    ])>{{ $this->nfeModalTotais[$item['key']] ?? '0,00' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'fisco')
                    <div class="erp-nfe-lancamento-modal__detail-panel">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações adicionais de interesse do Fisco</label>
                        <textarea wire:model="nfeForm.obs_fisco" class="erp-nfe-lancamento-modal__textarea" rows="4"></textarea>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'contribuinte')
                    <div class="erp-nfe-lancamento-modal__detail-panel">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações complementares de interesse do contribuinte</label>
                        <textarea wire:model="nfeForm.obs_contribuinte" class="erp-nfe-lancamento-modal__textarea" rows="4"></textarea>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'referencia')
                    <div class="erp-nfe-lancamento-modal__detail-panel">
                        <div class="erp-nfe-lancamento-modal__item-bar">
                            <label class="erp-nfe-lancamento-modal__form-label">Chave NF-e (44 dígitos)</label>
                            <input type="text" wire:model="nfeReferenciaInput" maxlength="44" class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--chave">
                            <button type="button" wire:click="addNfeReferencia" class="erp-nfe-lancamento-modal__tool-btn">Incluir</button>
                        </div>
                        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap">
                            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid">
                                <thead>
                                    <tr>
                                        <th>Chave referenciada</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->nfeModalReferencias as $index => $ref)
                                        <tr>
                                            <td>{{ $ref['referencia'] }}</td>
                                            <td class="erp-nfe-lancamento-modal__center">
                                                <button type="button" wire:click="removeNfeReferencia({{ $index }})" class="erp-nfe-lancamento-modal__tool-btn">Remover</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="erp-lookup-modal__empty">Nenhuma chave referenciada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="erp-nfe-lancamento-modal__detail-panel">
                        <p class="erp-nfe-lancamento-modal__panel-text">{{ $detailTabs[$this->nfeModalDetailTab] ?? 'Detalhes' }} — em implementação.</p>
                    </div>
                @endif

                <div class="erp-nfe-lancamento-modal__toolbar erp-nfe-lancamento-modal__toolbar--bottom">
                    <div class="erp-nfe-lancamento-modal__toolbar-actions">
                        <button
                            type="button"
                            wire:click="saveNfe"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--save"
                            title="Gravar"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--save">+</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F2</kbd> | Gravar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="transmitNfe"
                            class="erp-nfe-lancamento-modal__tool-btn"
                            disabled
                            title="Transmitir"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon">📡</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F3</kbd> | Transmitir</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-lancamento-modal__tool-btn"
                            disabled
                            title="Imprimir"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon">🖨</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F4</kbd> | Imprimir</span>
                        </button>

                        <button
                            type="button"
                            wire:click="importNfeModal"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--import"
                            title="Importar"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--import">↓</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F6</kbd> | Importar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openNfeProdutos"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--products"
                            title="Produtos"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--products">📦</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F8</kbd> | Produtos</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openNfePessoas"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--people"
                            title="Pessoas"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--people">👤</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F9</kbd> | Pessoas</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openNfeTransportadora"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--transp"
                            title="Transportadora"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--transp">🚚</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>F10</kbd> | Transp.</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-lancamento-modal__tool-btn erp-nfe-lancamento-modal__tool-btn--exit"
                            wire:click="closeNfeModal"
                            title="Sair"
                        >
                            <span class="erp-nfe-lancamento-modal__tool-icon erp-nfe-lancamento-modal__tool-icon--exit">✕</span>
                            <span class="erp-nfe-lancamento-modal__tool-label"><kbd>ESC</kbd> | Sair</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@once
    @php($nfeLancamentoJsVersion = @filemtime(public_path('js/erp-nfe-lancamento.js')) ?: time())
    <script src="{{ asset('js/erp-nfe-lancamento.js') }}?v={{ $nfeLancamentoJsVersion }}" defer></script>
@endonce
