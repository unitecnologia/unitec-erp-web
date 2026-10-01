@if ($this->nfseModalOpen)
    <div
        @class([
            'erp-lookup-modal erp-nfse-lancamento-modal',
            'erp-nfse-lancamento-modal--somente-leitura' => $this->nfseSomenteLeitura(),
        ])
        wire:keydown.escape.window="closeNfseModal"
        @unless ($this->nfseSomenteLeitura())
            wire:keydown.f2.window.prevent="gravarNfse"
            wire:keydown.f6.window.prevent="openNfseImportOs"
        @endunless
        @if ($this->nfseId && ! $this->nfseSomenteLeitura())
            wire:keydown.f3.window.prevent="transmitirNfse"
        @endif
        x-data="{ mainTab: 'servicos', detailTab: 'totais' }"
        x-init="$nextTick(() => { setTimeout(() => { const el = document.getElementById('nfse-tomador-busca'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        @keydown.window="
            if ($event.key !== 'Delete') return;
            if ($el.classList.contains('erp-nfse-lancamento-modal--somente-leitura')) return;
            if (document.querySelector('.erp-nfse-servico-delete-modal, .erp-nfse-producao-modal')) return;
            const alvo = $event.target;
            if (! alvo || alvo.nodeType !== 1) return;
            if (alvo.closest('input, textarea, select')) return;
            if (alvo.closest('[role=&quot;textbox&quot;], [role=&quot;combobox&quot;], [role=&quot;searchbox&quot;], [role=&quot;spinbutton&quot;]')) return;
            const editavel = alvo.closest('[contenteditable]');
            if (editavel && (editavel.getAttribute('contenteditable') || '').toLowerCase() !== 'false') return;
            if (alvo.isContentEditable) return;
            $event.preventDefault();
            $wire.solicitarExclusaoNfseServico();
        "
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfseModal"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nfse-lancamento-title"
        >
            <div class="erp-lookup-modal__titlebar erp-nfe-lancamento-modal__titlebar">
                <span id="erp-nfse-lancamento-title">Emissão de NFS-e</span>
                <div class="erp-nfe-lancamento-modal__titlebar-badges">
                    <div class="erp-nfe-lancamento-modal__status-box erp-nfe-lancamento-modal__status-box--titlebar">{{ $this->nfseStatusLabel() }}</div>
                </div>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfseModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-lancamento-modal__body">
                <div class="erp-nfe-lancamento-modal__header">
                    <section class="erp-nfe-cliente" @if ($this->nfseTomadorSugestoesOpen && $this->nfseTomadorSugestoes !== []) data-lookup-open="1" @endif>
                        <div class="erp-nfe-cliente__box">
                            <span class="erp-nfe-cliente__legend">Tomador</span>

                            <div class="erp-nfe-cliente__row erp-nfe-cliente__row--primary">
                                <label class="erp-nfe-cliente__field erp-nfse-field--serie">
                                    <span>Série DPS</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseSerieDps !== '' ? $this->nfseSerieDps : '—' }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true" title="Série gerada automaticamente — não editável">
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfse-field--numero">
                                    <span>Nº DPS</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseNumeroDps !== '' ? $this->nfseNumeroDps : '—' }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true" title="Número gerado automaticamente — não editável">
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfse-field--numero">
                                    <span>Nº NFS-e</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseNumeroNfse !== '' ? $this->nfseNumeroNfse : '—' }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true" title="Número da NFS-e — preenchido após a autorização">
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--empresa">
                                    <span>Empresa</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->empresaNome }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--grow erp-nfe-cliente__field--suggest">
                                    <span>Tomador</span>
                                    <input
                                        id="nfse-tomador-busca"
                                        class="erp-nfe-cliente__input erp-nfe-cliente__input--editable"
                                        type="text"
                                        wire:model.live.debounce.250ms="nfseTomador"
                                        wire:keydown.enter.prevent="confirmarNfseTomador"
                                        wire:keydown.arrow-up.prevent="moverNfseTomadorSugestao(-1)"
                                        wire:keydown.arrow-down.prevent="moverNfseTomadorSugestao(1)"
                                        data-erp-uppercase
                                        autocapitalize="characters"
                                        autocomplete="off"
                                        placeholder="Código, nome ou CNPJ"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="{{ $this->nfseTomadorSugestoesOpen && $this->nfseTomadorSugestoes !== [] ? 'true' : 'false' }}"
                                        aria-controls="nfse-tomador-sugestoes"
                                    >
                                    @if ($this->nfseTomadorSugestoesOpen && $this->nfseTomadorSugestoes !== [])
                                        <ul id="nfse-tomador-sugestoes" class="erp-nfe-cliente__suggest" role="listbox" aria-label="Tomadores encontrados">
                                            @foreach ($this->nfseTomadorSugestoes as $index => $sug)
                                                <li wire:key="nfse-tomador-sug-{{ $sug['id'] }}" role="presentation">
                                                    <button
                                                        type="button"
                                                        id="nfse-tomador-sug-{{ $index }}"
                                                        role="option"
                                                        aria-selected="{{ (int) $this->nfseTomadorSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                        wire:mousedown.prevent="selecionarNfseTomador({{ $sug['id'] }})"
                                                        @class(['is-selected' => (int) $this->nfseTomadorSugestaoIndex === (int) $index])
                                                    >
                                                        <span class="erp-nfe-cliente__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                                        <span class="erp-nfe-cliente__suggest-nome">{{ $sug['nome'] }}</span>
                                                        @if (filled($sug['cpf_cnpj'] ?? null))
                                                            <span @class([
                                                                'erp-nfe-cliente__suggest-doc',
                                                                'is-cnpj' => ($sug['doc_tipo'] ?? '') === 'cnpj',
                                                                'is-cpf' => ($sug['doc_tipo'] ?? '') === 'cpf',
                                                            ])>{{ $sug['cpf_cnpj'] }}</span>
                                                        @endif
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--doc">
                                    <span>CPF/CNPJ</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorCpfCnpj }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>

                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--fone">
                                    <span>Telefone</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorTelefone }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                            </div>

                            <div class="erp-nfe-cliente__row erp-nfe-cliente__row--secondary">
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--end">
                                    <span>Endereço</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorEndereco }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--num">
                                    <span>Nº</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorNumero }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--bairro">
                                    <span>Bairro</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorBairro }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--cep">
                                    <span>CEP</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorCep }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--cidade">
                                    <span>Cidade</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorCidade }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                                <label class="erp-nfe-cliente__field erp-nfe-cliente__field--uf">
                                    <span>UF</span>
                                    <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfseTomadorUf }}" data-erp-locked="1" readonly tabindex="-1" aria-readonly="true">
                                </label>
                            </div>
                        </div>
                    </section>

                    <div class="erp-nfe-lancamento-modal__form-row erp-nfe-lancamento-modal__form-row--ops">
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label" for="nfse-competencia">Competência</label>
                            <input id="nfse-competencia" type="month" wire:model="nfseCompetencia" class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date">
                        </div>
                        <div class="erp-nfe-lancamento-modal__form-group erp-nfse-field--municipio" @if ($this->nfseMunicipioSugestoesOpen) data-lookup-open="1" @endif>
                            <label class="erp-nfe-lancamento-modal__form-label" for="nfse-municipio">Município da prestação</label>
                            <div class="erp-nfse-municipio">
                                <input id="nfse-municipio-codigo" type="text" value="{{ $this->nfseMunicipioCodigo }}" class="erp-nfe-lancamento-modal__form-input erp-nfse-municipio__codigo" readonly tabindex="-1" aria-readonly="true" title="Código IBGE" inputmode="numeric">
                                <div class="erp-nfse-municipio__nome-wrap">
                                    <input
                                        id="nfse-municipio"
                                        type="text"
                                        wire:model.live.debounce.250ms="nfseMunicipioNome"
                                        wire:keydown.enter.prevent="confirmarNfseMunicipio"
                                        wire:keydown.arrow-up.prevent="moverNfseMunicipioSugestao(-1)"
                                        wire:keydown.arrow-down.prevent="moverNfseMunicipioSugestao(1)"
                                        class="erp-nfe-lancamento-modal__form-input"
                                        autocomplete="off"
                                        data-erp-uppercase
                                        placeholder="Cidade"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="{{ $this->nfseMunicipioSugestoesOpen ? 'true' : 'false' }}"
                                        aria-controls="nfse-municipio-sugestoes"
                                    >
                                    @if ($this->nfseMunicipioSugestoesOpen && $this->nfseMunicipioSugestoes !== [])
                                        <ul id="nfse-municipio-sugestoes" class="erp-nfse-municipio__suggest" role="listbox" aria-label="Municípios encontrados">
                                            @foreach ($this->nfseMunicipioSugestoes as $index => $sug)
                                                <li wire:key="nfse-mun-sug-{{ $sug['codigo'] }}" role="presentation">
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        aria-selected="{{ (int) $this->nfseMunicipioSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                        wire:click="selecionarNfseMunicipio('{{ $sug['codigo'] }}', @js($sug['nome']), '{{ $sug['uf'] }}')"
                                                        @class(['is-selected' => (int) $this->nfseMunicipioSugestaoIndex === (int) $index])
                                                    >
                                                        <span>{{ $sug['codigo'] }}</span>
                                                        <span>{{ $sug['nome'] }}</span>
                                                        <span>{{ $sug['uf'] }}</span>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <input id="nfse-municipio-uf" type="text" value="{{ $this->nfseMunicipioUf }}" class="erp-nfe-lancamento-modal__form-input erp-nfse-municipio__uf" readonly tabindex="-1" aria-readonly="true" title="UF">
                            </div>
                        </div>
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label" for="nfse-data-emissao">Data de emissão</label>
                            <input id="nfse-data-emissao" type="date" wire:model="nfseDataEmissao" class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--date">
                        </div>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__section-tabs erp-nfe-lancamento-modal__section-tabs--main" role="tablist" aria-label="Abas da grade">
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': mainTab === 'servicos', 'erp-nfe-lancamento-modal__section-tab--active': mainTab === 'servicos' }" :aria-selected="mainTab === 'servicos' ? 'true' : 'false'" @click="mainTab = 'servicos'">Serviços</button>
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': mainTab === 'tributos', 'erp-nfe-lancamento-modal__section-tab--active': mainTab === 'tributos' }" :aria-selected="mainTab === 'tributos' ? 'true' : 'false'" @click="mainTab = 'tributos'">Tributos</button>
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': mainTab === 'pagamento', 'erp-nfe-lancamento-modal__section-tab--active': mainTab === 'pagamento' }" :aria-selected="mainTab === 'pagamento' ? 'true' : 'false'" @click="mainTab = 'pagamento'">Pagamento</button>
                </div>

                <div class="erp-nfe-lancamento-modal__itens-area" x-show="mainTab === 'servicos'" x-cloak>
                    <section class="erp-nfe-inclusao" @if ($this->nfseServicoSugestoesOpen) data-lookup-open="1" @endif>
                        <div class="erp-nfe-inclusao__box">
                            <span class="erp-nfe-inclusao__legend">Serviço</span>
                            <div class="erp-nfe-inclusao__row">
                                <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--barcode">
                                    <span>Código / descrição / nome</span>
                                    <div class="erp-nfe-inclusao__barcode-wrap">
                                        <input
                                            id="nfse-servico-busca"
                                            class="erp-nfe-inclusao__input erp-nfe-inclusao__input--barcode"
                                            type="text"
                                            wire:model.live.debounce.280ms="nfseServicoBusca"
                                            wire:keydown.enter.prevent="confirmarNfseServicoBusca"
                                            wire:keydown.arrow-up.prevent="moverNfseServicoSugestao(-1)"
                                            wire:keydown.arrow-down.prevent="moverNfseServicoSugestao(1)"
                                            data-erp-uppercase
                                            autocapitalize="characters"
                                            autocomplete="off"
                                            placeholder="Código, descrição ou nome — Enter"
                                            role="combobox"
                                            aria-autocomplete="list"
                                            aria-expanded="{{ $this->nfseServicoSugestoesOpen ? 'true' : 'false' }}"
                                            aria-controls="nfse-servico-sugestoes"
                                            @disabled($this->nfseSomenteLeitura())
                                        >
                                        @if ($this->nfseServicoSugestoesOpen && $this->nfseServicoSugestoes !== [])
                                            <div class="erp-nfe-inclusao__suggest-wrap">
                                                <ul id="nfse-servico-sugestoes" class="erp-nfe-inclusao__suggest erp-nfse-servico-suggest" role="listbox" aria-label="Serviços encontrados">
                                                    @foreach ($this->nfseServicoSugestoes as $index => $sug)
                                                        <li wire:key="nfse-servico-sug-{{ $sug['id'] }}" role="presentation">
                                                            <button
                                                                type="button"
                                                                id="nfse-servico-sug-{{ $index }}"
                                                                role="option"
                                                                aria-selected="{{ (int) $this->nfseServicoSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                                wire:click="selecionarNfseServico({{ $sug['id'] }})"
                                                                @class(['is-selected' => (int) $this->nfseServicoSugestaoIndex === (int) $index])
                                                            >
                                                                <span class="erp-nfe-inclusao__suggest-code">{{ $sug['codigo'] !== '' ? $sug['codigo'] : '—' }}</span>
                                                                <span class="erp-nfe-inclusao__suggest-nome">{{ $sug['descricao'] }}</span>
                                                                <span class="erp-nfe-inclusao__suggest-preco">R$ {{ $sug['preco'] }}</span>
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @elseif ($this->nfseServicoSugestoesOpen)
                                            <div class="erp-nfe-inclusao__suggest erp-nfe-inclusao__suggest--empty">Nenhum serviço encontrado.</div>
                                        @endif
                                    </div>
                                </label>

                                <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--qtd">
                                    <span>Qtde</span>
                                    @if ($this->nfseServicoId && ! $this->nfseSomenteLeitura())
                                        <input
                                            id="nfse-servico-qtd"
                                            class="erp-nfe-inclusao__input"
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            wire:model.live.debounce.200ms="nfseServicoQuantidade"
                                            wire:keydown.enter.prevent="focoNfseServicoValorAposQtd($event.target.value)"
                                        >
                                    @else
                                        <input class="erp-nfe-inclusao__input" type="text" value="{{ $this->nfseServicoQuantidade !== '' ? $this->nfseServicoQuantidade : '0,000' }}" readonly tabindex="-1" aria-readonly="true">
                                    @endif
                                </label>

                                <div class="erp-nfe-inclusao__field erp-nfe-inclusao__field--preco">
                                    <span>Vlr. unit.</span>
                                    <div class="erp-nfe-inclusao__preco-wrap">
                                        @if ($this->nfseServicoId && ! $this->nfseSomenteLeitura())
                                            <input
                                                id="nfse-servico-valor"
                                                class="erp-nfe-inclusao__input"
                                                type="text"
                                                inputmode="decimal"
                                                autocomplete="off"
                                                wire:model.live.debounce.200ms="nfseServicoValor"
                                                wire:keydown.enter.prevent="confirmarNfseServicoInclusao(document.getElementById('nfse-servico-qtd')?.value, $event.target.value)"
                                            >
                                        @else
                                            <input class="erp-nfe-inclusao__input" type="text" value="{{ $this->nfseServicoValor !== '' ? $this->nfseServicoValor : '0,00' }}" readonly tabindex="-1" aria-readonly="true">
                                        @endif
                                        <button
                                            type="button"
                                            class="erp-nfe-inclusao__btn-pct"
                                            wire:click="abrirNfseModalDescontoItem('form')"
                                            title="Desconto / Acréscimo (Ctrl+D)"
                                            @disabled($this->nfseSomenteLeitura())
                                        >
                                            %
                                        </button>
                                    </div>
                                </div>

                                <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--total">
                                    <span>Total item</span>
                                    <input class="erp-nfe-inclusao__input erp-nfe-inclusao__input--total" type="text" value="{{ $this->nfseServicoTotalPendente() }}" readonly tabindex="-1" aria-readonly="true">
                                </label>
                            </div>
                        </div>
                    </section>

                    <div
                        id="nfse-servicos-grade"
                        class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--itens erp-nfse-servicos-grade"
                        tabindex="-1"
                    >
                        <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--itens erp-nfse-servicos-grid">
                            <colgroup>
                                <col class="erp-nfse-col-item">
                                <col class="erp-nfse-col-codigo">
                                <col class="erp-nfse-col-descricao">
                                <col class="erp-nfse-col-qtd">
                                <col class="erp-nfse-col-valor">
                                <col class="erp-nfse-col-desc">
                                <col class="erp-nfse-col-acre">
                                <col class="erp-nfse-col-total">
                                <col class="erp-nfse-col-acoes">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ITEM</th>
                                    <th>CÓDIGO</th>
                                    <th>DESCRIÇÃO</th>
                                    <th class="erp-nfe-lancamento-modal__num">QTD.</th>
                                    <th class="erp-nfe-lancamento-modal__num">VLR. UNIT.</th>
                                    <th class="erp-nfe-lancamento-modal__num">DESC.</th>
                                    <th class="erp-nfe-lancamento-modal__num">ACRE.</th>
                                    <th class="erp-nfe-lancamento-modal__num">TOTAL</th>
                                    <th class="erp-nfe-lancamento-modal__center" title="Ações">AÇÕES</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->nfseServicos as $index => $linha)
                                    @php
                                        $linhaKey = (string) ($linha['key'] ?? ('idx-'.$index));
                                        $somenteLeitura = $this->nfseSomenteLeitura();
                                    @endphp
                                    <tr
                                        wire:key="nfse-servico-linha-{{ $linhaKey }}"
                                        wire:click="selecionarNfseServicoLinha({{ $index }})"
                                        x-on:click="if ($event.target.closest('input, textarea, select, button')) return; $nextTick(() => document.getElementById('nfse-servicos-grade')?.focus({ preventScroll: true }))"
                                        @class([
                                            'erp-nfe-lancamento-modal__row',
                                            'erp-nfe-lancamento-modal__row--selected' => $this->nfseServicoLinhaIndex === $index,
                                            'erp-lookup-modal__row--selected' => $this->nfseServicoLinhaIndex === $index,
                                        ])
                                    >
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--center">
                                                {{ $index + 1 }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--codigo" title="{{ $linha['codigo'] }}">
                                                {{ $linha['codigo'] }}
                                            </div>
                                        </td>
                                        <td class="erp-nfse-servicos-grid__desc">
                                            <div class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--desc" title="{{ $linha['descricao'] }}">
                                                {{ $linha['descricao'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <input
                                                id="nfse-servico-{{ $linhaKey }}-qtd"
                                                class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"
                                                type="text"
                                                inputmode="decimal"
                                                autocomplete="off"
                                                value="{{ $linha['quantidade'] }}"
                                                wire:key="nfse-servico-{{ $linhaKey }}-qtd-{{ $linha['rev'] ?? 0 }}-{{ $linha['quantidade'] }}"
                                                wire:blur="alterarNfseServicoLinha({{ $index }}, 'quantidade', $event.target.value)"
                                                wire:keydown.enter.prevent="alterarNfseServicoLinha({{ $index }}, 'quantidade', $event.target.value)"
                                                wire:click.stop
                                                @disabled($somenteLeitura)
                                                x-on:focus="$el.removeAttribute('readonly'); $el.select()"
                                                x-on:click.stop="$el.removeAttribute('readonly'); $el.select()"
                                            >
                                        </td>
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                                                <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                                                <input
                                                    id="nfse-servico-{{ $linhaKey }}-valor"
                                                    class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                                    type="text"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                    value="{{ $linha['valor'] }}"
                                                    wire:key="nfse-servico-{{ $linhaKey }}-valor-{{ $linha['rev'] ?? 0 }}-{{ $linha['valor'] }}"
                                                    wire:blur="alterarNfseServicoLinha({{ $index }}, 'valor', $event.target.value)"
                                                    wire:keydown.enter.prevent="alterarNfseServicoLinha({{ $index }}, 'valor', $event.target.value)"
                                                    wire:click.stop
                                                    @disabled($somenteLeitura)
                                                    x-on:focus="$el.removeAttribute('readonly'); $el.select()"
                                                    x-on:click.stop="$el.removeAttribute('readonly'); $el.select()"
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                                                <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                                                <input
                                                    id="nfse-servico-{{ $linhaKey }}-desconto"
                                                    class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                                    type="text"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                    value="{{ $linha['desconto'] ?? '0,00' }}"
                                                    wire:key="nfse-servico-{{ $linhaKey }}-desconto-{{ $linha['rev'] ?? 0 }}-{{ $linha['desconto'] ?? '0,00' }}"
                                                    wire:blur="alterarNfseServicoLinha({{ $index }}, 'desconto', $event.target.value)"
                                                    wire:keydown.enter.prevent="alterarNfseServicoLinha({{ $index }}, 'desconto', $event.target.value)"
                                                    wire:click.stop
                                                    @disabled($somenteLeitura)
                                                    x-on:focus="$el.removeAttribute('readonly'); $el.select()"
                                                    x-on:click.stop="$el.removeAttribute('readonly'); $el.select()"
                                                    title="Desconto do serviço"
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                                                <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                                                <input
                                                    id="nfse-servico-{{ $linhaKey }}-acrescimo"
                                                    class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                                    type="text"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                    value="{{ $linha['acrescimo'] ?? '0,00' }}"
                                                    wire:key="nfse-servico-{{ $linhaKey }}-acrescimo-{{ $linha['rev'] ?? 0 }}-{{ $linha['acrescimo'] ?? '0,00' }}"
                                                    wire:blur="alterarNfseServicoLinha({{ $index }}, 'acrescimo', $event.target.value)"
                                                    wire:keydown.enter.prevent="alterarNfseServicoLinha({{ $index }}, 'acrescimo', $event.target.value)"
                                                    wire:click.stop
                                                    @disabled($somenteLeitura)
                                                    x-on:focus="$el.removeAttribute('readonly'); $el.select()"
                                                    x-on:click.stop="$el.removeAttribute('readonly'); $el.select()"
                                                    title="Acréscimo do serviço"
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                                                <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                                                <input
                                                    class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input erp-nfe-lancamento-modal__cell-input--readonly"
                                                    type="text"
                                                    value="{{ $linha['total'] }}"
                                                    readonly
                                                    tabindex="-1"
                                                    title="Total do serviço (somente leitura)"
                                                    autocomplete="off"
                                                >
                                            </div>
                                        </td>
                                        <td class="erp-nfse-servicos-grid__acoes">
                                            @unless ($somenteLeitura)
                                                <button
                                                    type="button"
                                                    class="erp-nfse-servicos-grid__trash"
                                                    wire:click.stop="solicitarExclusaoNfseServico({{ $index }})"
                                                    title="Excluir serviço"
                                                    aria-label="Excluir serviço"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M3 6h18" />
                                                        <path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6" />
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                    </svg>
                                                </button>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="erp-lookup-modal__empty">Nenhum serviço. Informe código, descrição ou nome na barra Serviço acima e pressione Enter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__panel" x-show="mainTab === 'tributos'" x-cloak>
                    <p class="erp-nfe-lancamento-modal__panel-text">Tributos — em implementação.</p>
                </div>

                <div class="erp-nfe-lancamento-modal__panel" x-show="mainTab === 'pagamento'" x-cloak>
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
                                <tr>
                                    <td colspan="3" class="erp-lookup-modal__empty">Nenhuma parcela.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__section-tabs erp-nfe-lancamento-modal__section-tabs--detail" role="tablist" aria-label="Abas de detalhes">
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': detailTab === 'totais', 'erp-nfe-lancamento-modal__section-tab--active': detailTab === 'totais' }" :aria-selected="detailTab === 'totais' ? 'true' : 'false'" @click="detailTab = 'totais'">Totais</button>
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': detailTab === 'discriminacao', 'erp-nfe-lancamento-modal__section-tab--active': detailTab === 'discriminacao' }" :aria-selected="detailTab === 'discriminacao' ? 'true' : 'false'" @click="detailTab = 'discriminacao'">Discriminação</button>
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': detailTab === 'iss', 'erp-nfe-lancamento-modal__section-tab--active': detailTab === 'iss' }" :aria-selected="detailTab === 'iss' ? 'true' : 'false'" @click="detailTab = 'iss'">ISS</button>
                    <button type="button" role="tab" class="erp-nfe-tab-btn erp-nfe-lancamento-modal__section-tab" :class="{ 'erp-nfe-tab-btn--active': detailTab === 'observacoes', 'erp-nfe-lancamento-modal__section-tab--active': detailTab === 'observacoes' }" :aria-selected="detailTab === 'observacoes' ? 'true' : 'false'" @click="detailTab = 'observacoes'">Observações</button>
                </div>

                <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--totais" x-show="detailTab === 'totais'" x-cloak>
                    <div class="erp-nfe-lancamento-modal__totais-grid" role="group" aria-label="Totais da NFS-e">
                        <div class="erp-nfe-lancamento-modal__totais-row">
                            <div class="erp-nfe-lancamento-modal__total-field">
                                <span class="erp-nfe-lancamento-modal__total-label">Valor dos serviços</span>
                                <span class="erp-nfe-lancamento-modal__total-value">{{ $this->nfseServicosValorBrutoFormatado() }}</span>
                            </div>
                            <div class="erp-nfe-lancamento-modal__total-field">
                                <span class="erp-nfe-lancamento-modal__total-label">Desconto</span>
                                <span class="erp-nfe-lancamento-modal__total-value">{{ $this->nfseServicosDescontoFormatado() }}</span>
                            </div>
                            <div class="erp-nfe-lancamento-modal__total-field">
                                <span class="erp-nfe-lancamento-modal__total-label">ISS</span>
                                <span class="erp-nfe-lancamento-modal__total-value">0,00</span>
                            </div>
                            <div class="erp-nfe-lancamento-modal__total-field">
                                <span class="erp-nfe-lancamento-modal__total-label">Total</span>
                                <span class="erp-nfe-lancamento-modal__total-value erp-nfe-lancamento-modal__total-value--strong">{{ $this->nfseServicosSomaFormatada() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs" x-show="detailTab === 'discriminacao'" x-cloak>
                    <label class="erp-nfe-lancamento-modal__form-label" for="nfse-discriminacao">Discriminação dos serviços</label>
                    <textarea id="nfse-discriminacao" class="erp-nfe-lancamento-modal__textarea" rows="3" readonly tabindex="-1" aria-readonly="true"></textarea>
                </div>

                <div class="erp-nfe-lancamento-modal__detail-panel" x-show="detailTab === 'iss'" x-cloak>
                    <div class="erp-nfe-lancamento-modal__form-row">
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label" for="nfse-trib-issqn">Tributação ISSQN</label>
                            <select id="nfse-trib-issqn" wire:model="nfseTribIssqn" class="erp-nfe-lancamento-modal__form-input">
                                @foreach (\App\Models\Nfse::tributacoesIssqn() as $value => $label)
                                    <option value="{{ $value }}">{{ $value }} {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <label class="erp-nfe-lancamento-modal__form-label" for="nfse-ret-issqn">Retenção do ISSQN</label>
                            <select id="nfse-ret-issqn" wire:model="nfseTpRetIssqn" class="erp-nfe-lancamento-modal__form-input">
                                @foreach (\App\Models\Nfse::retencoesIssqn() as $value => $label)
                                    <option value="{{ $value }}">{{ $value }} {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="erp-nfe-lancamento-modal__form-row">
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <span class="erp-nfe-lancamento-modal__form-label">Alíquota</span>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--info erp-nfe-lancamento-modal__form-input--sm">0,00</span>
                        </div>
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <span class="erp-nfe-lancamento-modal__form-label">Base de cálculo</span>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--info erp-nfe-lancamento-modal__form-input--sm">0,00</span>
                        </div>
                        <div class="erp-nfe-lancamento-modal__form-group">
                            <span class="erp-nfe-lancamento-modal__form-label">Valor ISS</span>
                            <span class="erp-nfe-lancamento-modal__form-input erp-nfe-lancamento-modal__form-input--info erp-nfe-lancamento-modal__form-input--sm">0,00</span>
                        </div>
                    </div>
                </div>

                <div class="erp-nfe-lancamento-modal__detail-panel erp-nfe-lancamento-modal__detail-panel--obs" x-show="detailTab === 'observacoes'" x-cloak>
                    <label class="erp-nfe-lancamento-modal__form-label" for="nfse-observacoes">Observações</label>
                    <textarea id="nfse-observacoes" class="erp-nfe-lancamento-modal__textarea" rows="3" readonly tabindex="-1" aria-readonly="true"></textarea>
                </div>

                @if ($this->nfseSefinMensagem !== '')
                    <p class="erp-nfse-sefin-mensagem" role="status">{{ $this->nfseSefinMensagem }}</p>
                @endif

                <div class="erp-nfe-lancamento-modal__toolbar erp-nfe-lancamento-modal__toolbar--bottom">
                    <div class="erp-nfe-actions erp-nfe-lancamento-modal__toolbar-actions">
                        <button
                            type="button"
                            @unless ($this->nfseSomenteLeitura()) wire:click="gravarNfse" @endunless
                            class="erp-nfe-actions__btn erp-nfe-lancamento-modal__tool-btn--save"
                            title="Gravar"
                            data-erp-key="F2"
                            @disabled($this->nfseSomenteLeitura())
                        >
                            <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">+</span>
                            <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Gravar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="transmitirNfse"
                            wire:loading.attr="disabled"
                            wire:target="transmitirNfse,confirmarTransmissaoProducao"
                            class="erp-nfe-actions__btn"
                            data-erp-nfse-transmit-btn
                            title="{{ $this->podeTransmitirNfse() ? 'Transmitir' : ($this->motivoBloqueioTransmissaoUi() ?? 'Transmitir') }}"
                            data-erp-key="F3"
                            @disabled(! $this->nfseId || $this->nfseSomenteLeitura())
                        >
                            <span class="erp-nfe-actions__icon">📡</span>
                            <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Transmitir</span>
                        </button>

                        <button
                            type="button"
                            @unless ($this->nfseSomenteLeitura()) wire:click="openNfseImportOs" @endunless
                            class="erp-nfe-actions__btn"
                            title="Importar Ordem de Serviço"
                            data-erp-key="F6"
                            @disabled($this->nfseSomenteLeitura())
                        >
                            <span class="erp-nfe-actions__icon">↓</span>
                            <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Importar</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openNfseEspelhoFromModal"
                            class="erp-nfe-actions__btn"
                            @disabled($this->nfseStatus !== \App\Models\Nfse::STATUS_ABERTA)
                            title="Espelho da NFS-e (sem validade fiscal)"
                        >
                            <span class="erp-nfe-actions__icon">📄</span>
                            <span class="erp-nfe-actions__label">Espelho da NFS-e</span>
                        </button>

                        <button
                            type="button"
                            class="erp-nfe-actions__btn erp-nfe-actions__btn--close"
                            wire:click="closeNfseModal"
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

        @if ($this->nfseServicoExcluirIndex !== null)
            @php
                $servicoExcluir = $this->nfseServicos[$this->nfseServicoExcluirIndex] ?? null;
                $servicoExcluirLabel = $servicoExcluir
                    ? trim(($servicoExcluir['codigo'] ?? '').' — '.($servicoExcluir['descricao'] ?? ''), ' —')
                    : '';
            @endphp
            <div
                class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--warning erp-nfse-servico-delete-modal"
                role="alertdialog"
                aria-labelledby="erp-nfse-servico-delete-title"
                aria-modal="true"
                x-data="{ enviando: false }"
                x-init="$nextTick(() => document.getElementById('erp-nfse-servico-delete-sim')?.focus())"
                @keydown.enter.window.capture.prevent="
                    if (enviando) return;
                    if ($event.target.closest('input, textarea, select')) return;
                    enviando = true;
                    $wire.confirmarExclusaoNfseServico();
                "
            >
                <div class="erp-nfe-fiscal-overlay__box">
                    <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>
                    <h2 id="erp-nfse-servico-delete-title" class="erp-nfe-fiscal-overlay__title">Excluir serviço</h2>
                    @if ($servicoExcluirLabel !== '')
                        <p class="erp-nfe-fiscal-overlay__codigo">{{ $servicoExcluirLabel }}</p>
                    @endif
                    <div class="erp-nfe-fiscal-overlay__text">Deseja excluir este serviço?</div>
                    <div class="erp-nfe-fiscal-overlay__actions">
                        <button
                            type="button"
                            id="erp-nfse-servico-delete-sim"
                            class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm"
                            x-on:click.prevent="if (enviando) return; enviando = true; $wire.confirmarExclusaoNfseServico()"
                        >Sim</button>
                        <button
                            type="button"
                            id="erp-nfse-servico-delete-nao"
                            class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                            wire:click="cancelarExclusaoNfseServico"
                        >Não</button>
                    </div>
                    <p class="erp-nfe-fiscal-overlay__hint">Enter confirma · Esc cancela</p>
                </div>
            </div>
        @endif

        @include('filament.components.erp.nfse.lancamento-desconto-item')
    </div>
@endif
