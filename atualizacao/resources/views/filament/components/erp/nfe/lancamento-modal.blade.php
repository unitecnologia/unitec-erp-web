@if ($this->nfeModalOpen)
    @php
        $mainTabs = [
            'itens' => 'Itens',
            'impostos' => 'Impostos / Outros',
            'pagamento' => 'Pagamento',
        ];

        $detailTabs = [
            'totais' => 'Totais',
            'fisco' => 'Informações do Fisco',
            'contribuinte' => 'Informações do Contribuinte',
            'transportadora' => 'Transportadora / Volumes',
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

        $totaisRowSuperior = array_slice($totaisLabels, 0, 9);
        $totaisRowInferior = array_slice($totaisLabels, 9);
    @endphp

    <div
        class="erp-lookup-modal erp-nfe-lancamento-modal"
        wire:keydown.escape.window="handleNfeModalEscape"
        wire:keydown.f6.prevent="importNfeModal"
        x-on:erp-nfe-focus-cliente.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('nfe-cliente-busca'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:erp-nfe-focus-item-produto.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('nfe-inclusao-produto'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:erp-nfe-focus-item-codigo.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('nfe-inclusao-produto'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:erp-nfe-focus-item-quantidade.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-qtd'); if (!el) return; setTimeout(() => { el.focus(); el.select?.(); }, 30); })"
        x-on:erp-nfe-focus-item-preco.window="$nextTick(() => { const el = document.getElementById('nfe-inclusao-preco'); if (!el) return; setTimeout(() => { el.focus(); el.select?.(); }, 30); })"
        x-on:erp-nfe-focus-desconto-item.window="$nextTick(() => { const el = document.getElementById('erp-nfe-desconto-preco'); el?.focus(); el?.select?.(); })"
        x-on:erp-nfe-focus-info-adicionais.window="$nextTick(() => { const el = document.getElementById('erp-nfe-info-adicionais-texto'); el?.focus(); el?.select?.(); })"
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
                <div class="erp-nfe-lancamento-modal__titlebar-badges">
                    @if ($this->nfeModalHomologacao)
                        <div
                            class="erp-nfe-lancamento-modal__status-box erp-nfe-lancamento-modal__status-box--titlebar erp-nfe-lancamento-modal__status-box--homologacao"
                            title="Ambiente de Homologação (SEFAZ de testes). Notas emitidas aqui não têm validade fiscal."
                        >HOMOLOGAÇÃO</div>
                    @endif
                    <div @class([
                        'erp-nfe-lancamento-modal__status-box',
                        'erp-nfe-lancamento-modal__status-box--titlebar',
                        'erp-nfe-lancamento-modal__status-box--transmitida' => in_array($this->nfeModalStatus, ['TRANSMITIDA', 'AUTORIZADA'], true),
                        'erp-nfe-lancamento-modal__status-box--cancelada' => $this->nfeModalStatus === 'CANCELADA',
                    ])>
                        {{ $this->nfeModalStatus }}
                    </div>
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

                    @if ($this->nfeModalMainTab === 'impostos')
                        @php
                            $nfeImpostoProduto = $this->nfeModalRows[$this->nfeSelectedRowIndex] ?? null;
                            $nfeImpostoProdutoNome = trim((string) ($nfeImpostoProduto['descricao'] ?? ''));
                            $nfeImpostoProdutoCod = ltrim((string) ($nfeImpostoProduto['codigo'] ?? ''), '0') ?: (string) ($nfeImpostoProduto['codigo'] ?? '');
                        @endphp
                        @if ($nfeImpostoProdutoNome !== '')
                            <div class="erp-nfe-lancamento-modal__imposto-produto" title="{{ $nfeImpostoProdutoCod !== '' ? $nfeImpostoProdutoCod.' — ' : '' }}{{ $nfeImpostoProdutoNome }}">
                                @if ($nfeImpostoProdutoCod !== '')
                                    <span class="erp-nfe-lancamento-modal__imposto-produto-cod">{{ $nfeImpostoProdutoCod }}</span>
                                @endif
                                <span class="erp-nfe-lancamento-modal__imposto-produto-nome">{{ $nfeImpostoProdutoNome }}</span>
                            </div>
                        @endif
                    @endif
                </div>

                @if ($this->nfeModalMainTab === 'itens')
                    <div class="erp-nfe-lancamento-modal__itens-area">
                        @include('filament.components.erp.nfe.lancamento-produto-inclusao')
                        @include('filament.components.erp.nfe.lancamento-itens-grid')

                        {{-- Foto só enquanto a busca/lista está aberta; some ao confirmar e ir para Qtde. --}}
                        @if ($this->nfeProdutoLookupOpen || filled($this->nfeProdutoPreviewFotoUrl))
                            <div class="erp-nfe-produto-foto-fixa" aria-label="Foto do produto">
                                @if ($this->nfeProdutoPreviewFotoUrl)
                                    <img
                                        src="{{ $this->nfeProdutoPreviewFotoUrl }}"
                                        alt="Foto do produto"
                                        class="erp-nfe-produto-foto-fixa__img"
                                        wire:key="nfe-produto-foto-fixa-{{ md5($this->nfeProdutoPreviewFotoUrl) }}"
                                    >
                                @else
                                    <span class="erp-nfe-produto-foto-fixa__empty">Foto do produto</span>
                                @endif
                            </div>
                        @endif
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
                                            <td colspan="3" class="erp-lookup-modal__empty">Nenhuma parcela. Selecione <strong>À PRAZO</strong> e use os botões acima.</td>
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
                    @php
                        $nfeTotaisProduto = $this->nfeModalRows[$this->nfeSelectedRowIndex] ?? null;
                        $nfeTotaisProdutoNome = trim((string) ($nfeTotaisProduto['descricao'] ?? ''));
                        $nfeTotaisEditaveis = ['frete', 'seguro', 'outras', 'desconto'];
                        $nfePodeEditarTotais = $this->nfeModalStatus === 'ABERTA';
                    @endphp
                    <div class="erp-nfe-lancamento-modal__totais-bar">
                        <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--totais">
                            <div class="erp-nfe-lancamento-modal__totais-grid" role="group" aria-label="Totais da nota">
                                @foreach ([$totaisRowSuperior, $totaisRowInferior] as $totaisRow)
                                    <div class="erp-nfe-lancamento-modal__totais-row">
                                        @foreach ($totaisRow as $item)
                                            @php
                                                $nfeTotalEditavel = in_array($item['key'], $nfeTotaisEditaveis, true) && $nfePodeEditarTotais;
                                            @endphp
                                            <div class="erp-nfe-lancamento-modal__total-field">
                                                <span class="erp-nfe-lancamento-modal__total-label">{{ $item['label'] }}</span>
                                                @if ($nfeTotalEditavel)
                                                    <input
                                                        type="text"
                                                        inputmode="decimal"
                                                        value="{{ $this->nfeModalTotais[$item['key']] ?? '0,00' }}"
                                                        class="erp-nfe-lancamento-modal__total-value erp-nfe-lancamento-modal__total-value--editable"
                                                        data-erp-nfe-totais="{{ $item['key'] }}"
                                                        data-mask="money-br"
                                                        wire:keydown.enter.prevent="nfeTotaisEnter('{{ $item['key'] }}', $event.target.value)"
                                                        wire:blur="nfeTotaisBlur('{{ $item['key'] }}', $event.target.value)"
                                                        x-on:focus="$el.removeAttribute('readonly'); $el.select()"
                                                        x-on:click="$el.removeAttribute('readonly'); $el.select()"
                                                        @mouseup.prevent
                                                        autocomplete="off"
                                                        title="Rateia proporcionalmente nos itens da nota"
                                                    >
                                                @else
                                                    <span @class([
                                                        'erp-nfe-lancamento-modal__total-value',
                                                        'erp-nfe-lancamento-modal__total-value--strong' => $item['key'] === 'total',
                                                    ])>{{ $this->nfeModalTotais[$item['key']] ?? '0,00' }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--foto">
                            <span class="erp-nfe-lancamento-modal__foto-label">Foto do produto</span>
                            <div
                                class="erp-nfe-lancamento-modal__totais-foto"
                                aria-label="Foto do produto selecionado"
                                @if ($nfeTotaisProdutoNome !== '') title="{{ $nfeTotaisProdutoNome }}" @endif
                            >
                                @if ($this->nfeSelectedRowFotoUrl)
                                    <img
                                        src="{{ $this->nfeSelectedRowFotoUrl }}"
                                        alt="{{ $nfeTotaisProdutoNome !== '' ? $nfeTotaisProdutoNome : 'Foto do produto' }}"
                                        class="erp-nfe-lancamento-modal__totais-foto-img"
                                        wire:key="nfe-totais-foto-{{ md5($this->nfeSelectedRowFotoUrl) }}"
                                    >
                                @else
                                    <span class="erp-nfe-lancamento-modal__totais-foto-empty">
                                        @if ($nfeTotaisProduto !== null)
                                            Sem foto
                                        @else
                                            Selecione um item
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'fisco')
                    <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações adicionais de interesse do Fisco</label>
                        <textarea wire:model="nfeForm.obs_fisco" class="erp-nfe-lancamento-modal__textarea" rows="3"></textarea>
                    </div>
                @elseif ($this->nfeModalDetailTab === 'contribuinte')
                    <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs erp-nfe-contribuinte">
                        <label class="erp-nfe-lancamento-modal__form-label">Informações complementares de interesse do contribuinte</label>
                        <textarea
                            wire:model.live="nfeForm.obs_contribuinte"
                            class="erp-nfe-lancamento-modal__textarea"
                            rows="4"
                        ></textarea>
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
                @elseif ($this->nfeModalDetailTab === 'transportadora')
                    <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-transportadora">
                        <div class="erp-nfe-transportadora__row">
                            <div class="erp-nfe-transportadora__field erp-nfe-transportadora__field--transportador" @if ($this->nfeTransportadoraSugestoesOpen && $this->nfeTransportadoraSugestoes !== []) data-lookup-open="1" @endif>
                                <span class="erp-nfe-lancamento-modal__form-label">Código</span>
                                <div class="erp-nfe-transportadora__transportador">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.250ms="nfeForm.transportadora_codigo"
                                        wire:keydown.enter.prevent="resolverNfeTransportadoraPorCodigo"
                                        class="erp-nfe-lancamento-modal__form-input erp-nfe-transportadora__codigo"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        title="Código do transportador"
                                    >
                                    <div class="erp-nfe-transportadora__nome-wrap">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.250ms="nfeTransportadoraBusca"
                                            wire:keydown.enter.prevent="confirmarNfeTransportadoraBusca"
                                            wire:keydown.escape.prevent="fecharNfeSugestoesTransportadora"
                                            wire:keydown.arrow-up.prevent="moverNfeSugestaoTransportadora(-1)"
                                            wire:keydown.arrow-down.prevent="moverNfeSugestaoTransportadora(1)"
                                            class="erp-nfe-lancamento-modal__form-input erp-nfe-transportadora__nome"
                                            autocomplete="off"
                                            placeholder="Nome ou CNPJ — Enter"
                                            role="combobox"
                                            aria-autocomplete="list"
                                            aria-expanded="{{ $this->nfeTransportadoraSugestoesOpen && $this->nfeTransportadoraSugestoes !== [] ? 'true' : 'false' }}"
                                            aria-controls="nfe-transportadora-sugestoes"
                                        >
                                        @if ($this->nfeTransportadoraSugestoesOpen && $this->nfeTransportadoraSugestoes !== [])
                                            <ul id="nfe-transportadora-sugestoes" class="erp-nfe-transportadora__suggest" role="listbox" aria-label="Transportadoras encontradas">
                                                @foreach ($this->nfeTransportadoraSugestoes as $index => $sug)
                                                    <li wire:key="nfe-transp-sug-{{ $sug['id'] }}" role="presentation">
                                                        <button
                                                            type="button"
                                                            role="option"
                                                            aria-selected="{{ (int) $this->nfeSelectedTransportadoraSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                            wire:click="selecionarNfeTransportadora({{ $sug['id'] }})"
                                                            @class(['is-selected' => (int) $this->nfeSelectedTransportadoraSugestaoIndex === (int) $index])
                                                        >
                                                            <span class="erp-nfe-transportadora__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                                            <span class="erp-nfe-transportadora__suggest-nome">{{ $sug['nome'] }}</span>
                                                            @if (filled($sug['cpf_cnpj'] ?? null))
                                                                <span @class([
                                                                    'erp-nfe-transportadora__suggest-doc',
                                                                    'is-cnpj' => ($sug['doc_tipo'] ?? '') === 'cnpj',
                                                                    'is-cpf' => ($sug['doc_tipo'] ?? '') === 'cpf',
                                                                ])>{{ $sug['cpf_cnpj'] }}</span>
                                                            @endif
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <label class="erp-nfe-transportadora__field erp-nfe-transportadora__field--frete">
                                <span class="erp-nfe-lancamento-modal__form-label">Frete por Conta</span>
                                <select
                                    wire:model="nfeForm.tipo_frete"
                                    class="erp-nfe-lancamento-modal__form-select erp-nfe-transportadora__select"
                                >
                                    @foreach ($this->nfeFretePorContaOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="erp-nfe-transportadora__field erp-nfe-transportadora__field--placa">
                                <span class="erp-nfe-lancamento-modal__form-label">Placa veículo</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.placa"
                                    maxlength="8"
                                    data-erp-uppercase
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-transportadora__placa"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-transportadora__field erp-nfe-transportadora__field--uf">
                                <span class="erp-nfe-lancamento-modal__form-label">UF</span>
                                <select
                                    wire:model="nfeForm.uf_placa"
                                    class="erp-nfe-lancamento-modal__form-select erp-nfe-transportadora__uf"
                                >
                                    <option value="">—</option>
                                    @foreach ($this->nfeUfPlacaOptions as $uf => $ufLabel)
                                        <option value="{{ $uf }}">{{ $ufLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="erp-nfe-volumes__row erp-nfe-transportadora__volumes">
                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--qtd">
                                <span class="erp-nfe-lancamento-modal__form-label">Qtde Volume</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.qvol"
                                    inputmode="numeric"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-volumes__num"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--especie">
                                <span class="erp-nfe-lancamento-modal__form-label">Espécie</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.especie"
                                    data-erp-uppercase
                                    class="erp-nfe-lancamento-modal__form-input"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--peso">
                                <span class="erp-nfe-lancamento-modal__form-label">Peso Bruto</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.peso_b"
                                    inputmode="decimal"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-volumes__num"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--peso">
                                <span class="erp-nfe-lancamento-modal__form-label">Peso Líquido</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.peso_l"
                                    inputmode="decimal"
                                    class="erp-nfe-lancamento-modal__form-input erp-nfe-volumes__num"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--marca">
                                <span class="erp-nfe-lancamento-modal__form-label">Marca</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.marca"
                                    data-erp-uppercase
                                    class="erp-nfe-lancamento-modal__form-input"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-volumes__field erp-nfe-volumes__field--numero">
                                <span class="erp-nfe-lancamento-modal__form-label">Número</span>
                                <input
                                    type="text"
                                    wire:model="nfeForm.nvol"
                                    class="erp-nfe-lancamento-modal__form-input"
                                    autocomplete="off"
                                >
                            </label>
                        </div>

                        <div class="erp-nfe-transportadora__actions">
                            <button
                                type="button"
                                wire:click="imprimirNfeEtiquetaVolume"
                                class="erp-nfe-transportadora__etiqueta-btn"
                                title="Imprimir etiqueta de volume"
                            >
                                <span aria-hidden="true">🏷</span>
                                Imprimir Etiqueta
                            </button>
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
                            wire:click="openNfeEspelhoFromModal"
                            class="erp-nfe-actions__btn"
                            @disabled($this->nfeModalStatus !== 'ABERTA')
                            title="Espelho da NF-e (sem validade fiscal)"
                        >
                            <span class="erp-nfe-actions__icon">📄</span>
                            <span class="erp-nfe-actions__label">Espelho de NFE</span>
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
    @include('filament.components.erp.nfe.lancamento-info-adicionais-modal')
    @include('filament.components.erp.nfe.item-delete-confirm')
    @include('filament.components.erp.nfe.fiscal-progress')
    @include('filament.components.erp.nfe.fiscal-erro-overlay')
    @include('filament.components.erp.nfe.fiscal-sucesso-overlay')
    @include('filament.components.erp.nfe.fiscal-info-overlay')
    @include('filament.components.erp.nfe.import-menu-modal')
    @include('filament.components.erp.nfe.import-list-modal')
@endif
