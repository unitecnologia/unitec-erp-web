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
        wire:keydown.escape.window="handleNfeModalEscape"
        wire:keydown.f6.prevent="importNfeModal"
        x-on:erp-nfe-focus-item-produto.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-produto'); el?.focus(); el?.select?.(); })"
        x-on:erp-nfe-focus-item-codigo.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-produto'); el?.focus(); el?.select?.(); })"
        x-on:erp-nfe-focus-item-quantidade.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-qtd'); if (!el) return; setTimeout(() => { el.focus(); el.select?.(); }, 30); })"
        x-on:erp-nfe-focus-item-preco.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-preco'); if (!el) return; setTimeout(() => { el.focus(); el.select?.(); }, 30); })"
        x-on:erp-nfe-focus-desconto-item.window="$nextTick(() => { const el = document.getElementById('erp-nfe-desconto-preco'); el?.focus(); el?.select?.(); })"
        x-on:keydown.ctrl.d.window.prevent="if (!$wire.nfeDescontoModalOpen) $wire.abrirNfeModalDescontoItem()"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeModal"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nfe-lancamento-title"
        >
            <div class="erp-lookup-modal__titlebar erp-nfe-lancamento-modal__titlebar">
                <span id="erp-nfe-lancamento-title">Emissão de NF-e</span>
                <div @class([
                    'erp-nfe-lancamento-modal__status-box',
                    'erp-nfe-lancamento-modal__status-box--titlebar',
                    'erp-nfe-lancamento-modal__status-box--transmitida' => in_array($this->nfeModalStatus, ['TRANSMITIDA', 'AUTORIZADA'], true),
                    'erp-nfe-lancamento-modal__status-box--cancelada' => $this->nfeModalStatus === 'CANCELADA',
                ])>
                    {{ $this->nfeModalStatus }}
                </div>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfeModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-lancamento-modal__body">
                <div class="erp-nfe-lancamento-modal__header">
                    @include('filament.components.erp.nfe.lancamento-cliente')

                    <div class="erp-nfe-lancamento-modal__form-row erp-nfe-lancamento-modal__form-row--ops">
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
                            <select wire:model.live="nfeForm.movimento" class="erp-nfe-lancamento-modal__form-select erp-nfe-lancamento-modal__form-input--combo">
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

                        <div
                            class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--natureza erp-nfe-lancamento-modal__form-group--suggest"
                            @if ($this->nfeNaturezaSugestoesOpen && $this->nfeNaturezaSugestoes !== []) data-cfop-open @endif
                        >
                            <label class="erp-nfe-lancamento-modal__form-label">Natureza da Operação</label>
                            <input
                                id="nfe-natureza-busca"
                                type="text"
                                wire:model.live.debounce.200ms="nfeForm.natureza_operacao"
                                wire:focus="abrirNfeSugestoesNatureza"
                                wire:keydown.enter.prevent="confirmarNfeNaturezaBusca($event.target.value)"
                                wire:keydown.escape.prevent="fecharNfeSugestoesNatureza"
                                wire:keydown.arrow-up.prevent="moverNfeSugestaoNatureza(-1)"
                                wire:keydown.arrow-down.prevent="moverNfeSugestaoNatureza(1)"
                                class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--natureza"
                                autocomplete="off"
                                placeholder="Digite o CFOP ou a descrição"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->nfeNaturezaSugestoesOpen && $this->nfeNaturezaSugestoes !== [] ? 'true' : 'false' }}"
                                aria-controls="nfe-natureza-sugestoes"
                            >
                            @if ($this->nfeNaturezaSugestoesOpen && $this->nfeNaturezaSugestoes !== [])
                                <ul id="nfe-natureza-sugestoes" class="erp-nfe-natureza__suggest" role="listbox" aria-label="CFOPs encontrados">
                                    @foreach ($this->nfeNaturezaSugestoes as $index => $sug)
                                        <li wire:key="nfe-nat-sug-{{ $sug['codigo'] }}-{{ $index }}" role="presentation">
                                            <button
                                                type="button"
                                                role="option"
                                                aria-selected="{{ (int) $this->nfeSelectedNaturezaIndex === (int) $index ? 'true' : 'false' }}"
                                                wire:mousedown.prevent="selecionarNfeNatureza({{ \Illuminate\Support\Js::from($sug['label']) }})"
                                                @class(['is-selected' => (int) $this->nfeSelectedNaturezaIndex === (int) $index])
                                            >
                                                <span class="erp-nfe-natureza__suggest-code">{{ $sug['codigo'] }}</span>
                                                <span class="erp-nfe-natureza__suggest-nome">{{ $sug['descricao'] ?? $sug['label'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @php
                            $nfePedidoInfo = trim((string) ($this->nfeForm['numero_pedido'] ?? ''));
                            $nfeDataEmissaoInfo = filled($this->nfeForm['data_emissao'] ?? null)
                                ? \Illuminate\Support\Carbon::parse($this->nfeForm['data_emissao'])->format('d/m/Y')
                                : '—';
                            $nfeDataSaidaInfo = filled($this->nfeForm['data_saida'] ?? null)
                                ? \Illuminate\Support\Carbon::parse($this->nfeForm['data_saida'])->format('d/m/Y')
                                : '—';
                        @endphp

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Nº Pedido</label>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--sm erp-nfe-lancamento-modal__form-input--info">{{ $nfePedidoInfo !== '' ? $nfePedidoInfo : '—' }}</span>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Data Emissão</label>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date erp-nfe-lancamento-modal__form-input--info">{{ $nfeDataEmissaoInfo }}</span>
                        </div>

                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label">Data Saída</label>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date erp-nfe-lancamento-modal__form-input--info">{{ $nfeDataSaidaInfo }}</span>
                        </div>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__section-tabs erp-nfe-lancamento-modal__section-tabs--main">
                    @foreach ($mainTabs as $value => $label)
                        <button
                            type="button"
                            wire:click="setNfeModalMainTab('{{ $value }}')"
                            @class(['erp-nfe-lancamento-modal__section-tab', 'erp-nfe-lancamento-modal__section-tab--active' => $this->nfeModalMainTab === $value])
                        >{{ $label }}</button>
                    @endforeach
                </div>

                @if ($this->nfeModalMainTab === 'itens')
                    <div class="erp-nfe-lancamento-modal__itens-area">
                        @include('filament.components.erp.nfe.lancamento-produto-inclusao')
                        @include('filament.components.erp.nfe.lancamento-itens-grid')
                    </div>
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
                    <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações adicionais de interesse do Fisco</label>
                        <textarea wire:model="nfeForm.obs_fisco" class="erp-nfe-lancamento-modal__textarea" rows="3"></textarea>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'contribuinte')
                    <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações complementares de interesse do contribuinte</label>
                        <textarea wire:model="nfeForm.obs_contribuinte" class="erp-nfe-lancamento-modal__textarea" rows="3"></textarea>
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
                    <div class="erp-nfe-actions erp-nfe-lancamento-modal__toolbar-actions">
                        <button
                            type="button"
                            wire:click="saveNfe"
                            class="erp-nfe-actions__btn erp-nfe-lancamento-modal__tool-btn--save"
                            title="Gravar"
                            data-erp-key="F2"
                        >
                            <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">+</span>
                            <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Gravar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="transmitNfe"
                            wire:loading.attr="disabled"
                            wire:target="transmitNfe"
                            class="erp-nfe-actions__btn"
                            @disabled(! $this->nfeModalRecordId || $this->nfeModalStatus !== 'ABERTA')
                            title="Transmitir"
                            data-erp-key="F3"
                        >
                            <span class="erp-nfe-actions__icon">📡</span>
                            <span wire:loading.remove wire:target="transmitNfe" class="erp-nfe-actions__label"><kbd>F3</kbd> | Transmitir</span>
                            <span wire:loading wire:target="transmitNfe" class="erp-nfe-actions__label">Transmitindo…</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-actions__btn"
                            disabled
                            title="Imprimir"
                            data-erp-key="F4"
                        >
                            <span class="erp-nfe-actions__icon">🖨</span>
                            <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Imprimir</span>
                        </button>

                        <button
                            type="button"
                            wire:click="importNfeModal"
                            class="erp-nfe-actions__btn"
                            title="Importar"
                            data-erp-key="F6"
                        >
                            <span class="erp-nfe-actions__icon">↓</span>
                            <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Importar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openNfeProdutos"
                            class="erp-nfe-actions__btn"
                            title="Produtos"
                            data-erp-key="F8"
                        >
                            <span class="erp-nfe-actions__icon">📦</span>
                            <span class="erp-nfe-actions__label"><kbd>F8</kbd> | Produtos</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-actions__btn"
                            disabled
                            title="Em breve — selecione o cliente no Destinatário"
                            data-erp-key="F9"
                        >
                            <span class="erp-nfe-actions__icon">👤</span>
                            <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Pessoas</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-actions__btn"
                            disabled
                            title="Em breve"
                            data-erp-key="F10"
                        >
                            <span class="erp-nfe-actions__icon">🚚</span>
                            <span class="erp-nfe-actions__label"><kbd>F10</kbd> | Transp.</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-actions__btn erp-nfe-actions__btn--close"
                            wire:click="closeNfeModal"
                            title="Sair"
                            data-erp-key="Escape"
                        >
                            <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">✕</span>
                            <span class="erp-nfe-actions__label"><kbd>ESC</kbd> | Sair</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('filament.components.erp.nfe.lancamento-desconto-item')
    @include('filament.components.erp.nfe.fiscal-progress')
    @include('filament.components.erp.nfe.fiscal-erro-overlay')
    @include('filament.components.erp.nfe.fiscal-sucesso-overlay')
    @include('filament.components.erp.nfe.fiscal-info-overlay')
    @include('filament.components.erp.nfe.import-menu-modal')
    @include('filament.components.erp.nfe.import-list-modal')
@endif
