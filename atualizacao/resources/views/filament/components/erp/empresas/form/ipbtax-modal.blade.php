@if ($this->ipbtaxModalOpen)
    <div
        class="erp-lookup-modal erp-ipbtax-modal"
        wire:keydown.escape.window="closeIpbtaxModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeIpbtaxModal"></div>

        <div
            class="erp-lookup-modal__window erp-ipbtax-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-ipbtax-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-ipbtax-title">IBPT</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeIpbtaxModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-ipbtax-modal__body">
                <div class="erp-ipbtax-modal__row erp-ipbtax-modal__row--file">
                    <label class="erp-pcad-form__label" for="ipbtax-caminho">Caminho do Arquivo</label>
                    <input
                        id="ipbtax-caminho"
                        type="text"
                        wire:model="ipbtaxCaminhoArquivo"
                        class="erp-pcad-form__input"
                        readonly
                        placeholder="Selecione o arquivo TabelaIBPTax (.csv / .txt)"
                    >
                    <label class="erp-pcad-form__btn erp-ipbtax-modal__browse" title="Selecionar arquivo">
                        <input
                            type="file"
                            wire:model="ipbtaxUpload"
                            accept=".csv,.txt,text/csv,text/plain"
                            class="erp-empresas-parametros__import-file"
                        >
                        <span wire:loading.remove wire:target="ipbtaxUpload">…</span>
                        <span wire:loading wire:target="ipbtaxUpload">…</span>
                    </label>
                    <button
                        type="button"
                        class="erp-pcad-form__btn erp-ipbtax-modal__btn-atualizar"
                        wire:click="atualizarIpbtaxArquivo"
                        wire:loading.attr="disabled"
                        wire:target="atualizarIpbtaxArquivo,ipbtaxUpload"
                        title="Atualizar prévia"
                    >
                        <svg class="erp-ipbtax-modal__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>Atualizar</span>
                    </button>
                </div>

                <div class="erp-ipbtax-modal__meta">
                    <div class="erp-ipbtax-modal__meta-item">
                        <span class="erp-ipbtax-modal__meta-label">Versão</span>
                        <span class="erp-ipbtax-modal__meta-value">{{ $this->ipbtaxMeta['versao'] !== '' ? $this->ipbtaxMeta['versao'] : '—' }}</span>
                    </div>
                    <div class="erp-ipbtax-modal__meta-item">
                        <span class="erp-ipbtax-modal__meta-label">Quantidade de itens</span>
                        <span class="erp-ipbtax-modal__meta-value">{{ number_format((int) $this->ipbtaxMeta['quantidade'], 0, ',', '.') }}</span>
                    </div>
                    <div class="erp-ipbtax-modal__meta-item">
                        <span class="erp-ipbtax-modal__meta-label">Vigência</span>
                        <span class="erp-ipbtax-modal__meta-value">{{ $this->ipbtaxMeta['vigencia'] !== '' ? $this->ipbtaxMeta['vigencia'] : '—' }}</span>
                    </div>
                    <div class="erp-ipbtax-modal__meta-item">
                        <span class="erp-ipbtax-modal__meta-label">Chave</span>
                        <span class="erp-ipbtax-modal__meta-value" title="{{ $this->ipbtaxMeta['chave'] }}">{{ $this->ipbtaxMeta['chave'] !== '' ? $this->ipbtaxMeta['chave'] : '—' }}</span>
                    </div>
                    <div class="erp-ipbtax-modal__meta-item">
                        <span class="erp-ipbtax-modal__meta-label">Fonte</span>
                        <span class="erp-ipbtax-modal__meta-value">{{ $this->ipbtaxMeta['fonte'] !== '' ? $this->ipbtaxMeta['fonte'] : '—' }}</span>
                    </div>
                </div>

                <p class="erp-ipbtax-modal__hint">
                    Baixe a tabela em
                    <a href="https://deolhonoimposto.ibpt.org.br/" target="_blank" rel="noopener noreferrer">deolhonoimposto.ibpt.org.br</a>,
                    salve o <strong>TabelaIBPTax.csv</strong>, selecione com o botão … e clique em <strong>Atualizar</strong>.
                </p>

                <div class="erp-ipbtax-modal__tabs">
                    <button
                        type="button"
                        wire:click="setIpbtaxActiveTab('dados')"
                        @class([
                            'erp-pcad__tab',
                            'erp-pcad__tab--active' => $this->ipbtaxActiveTab === 'dados',
                        ])
                    >Dados Importados</button>
                    <button
                        type="button"
                        wire:click="setIpbtaxActiveTab('erros')"
                        @class([
                            'erp-pcad__tab',
                            'erp-pcad__tab--active' => $this->ipbtaxActiveTab === 'erros',
                        ])
                    >Erros @if (count($this->ipbtaxErrors) > 0)({{ count($this->ipbtaxErrors) }})@endif</button>
                </div>

                <div class="erp-lookup-modal__grid-wrap erp-ipbtax-modal__grid-wrap">
                    @if ($this->ipbtaxActiveTab === 'dados')
                        <table class="erp-lookup-modal__grid erp-ipbtax-modal__grid">
                            <thead>
                                <tr>
                                    <th>NCM</th>
                                    <th>Ex</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th>Nacional</th>
                                    <th>Importado</th>
                                    <th>Estadual</th>
                                    <th>Municipal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->ipbtaxPreviewRows as $index => $row)
                                    <tr wire:key="ipbtax-row-{{ $index }}">
                                        <td>{{ $row['ncm'] }}</td>
                                        <td>{{ $row['ex'] !== '' ? $row['ex'] : '—' }}</td>
                                        <td>{{ $row['tipo'] !== '' ? $row['tipo'] : '—' }}</td>
                                        <td class="erp-ipbtax-modal__desc" title="{{ $row['descricao'] }}">{{ $row['descricao'] !== '' ? $row['descricao'] : '—' }}</td>
                                        <td class="erp-ipbtax-modal__num">{{ $row['nacional'] }}</td>
                                        <td class="erp-ipbtax-modal__num">{{ $row['importado'] }}</td>
                                        <td class="erp-ipbtax-modal__num">{{ $row['estadual'] }}</td>
                                        <td class="erp-ipbtax-modal__num">{{ $row['municipal'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="erp-lookup-modal__empty">
                                            Nenhum dado carregado. Selecione o arquivo e clique em <strong>Atualizar</strong>.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="erp-lookup-modal__grid erp-ipbtax-modal__grid">
                            <thead>
                                <tr>
                                    <th class="erp-ipbtax-modal__col-linha">Linha</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->ipbtaxErrors as $index => $error)
                                    <tr wire:key="ipbtax-err-{{ $index }}">
                                        <td class="erp-ipbtax-modal__col-linha">{{ $error['linha'] }}</td>
                                        <td>{{ $error['mensagem'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="erp-lookup-modal__empty">Nenhum erro na leitura.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="erp-ipbtax-modal__footer">
                    @php
                        $ipbtaxBusyTargets = 'atualizarIpbtaxArquivo,confirmarIpbtaxImportacao,ipbtaxUpload';
                        $ipbtaxPct = max(0, min(100, (int) $this->ipbtaxProgress));
                    @endphp
                    <div
                        class="erp-ipbtax-modal__progress-wrap"
                        wire:loading.class="erp-ipbtax-modal__progress-wrap--busy"
                        wire:target="{{ $ipbtaxBusyTargets }}"
                    >
                        <div class="erp-ipbtax-modal__status">
                            <span
                                class="erp-ipbtax-modal__spinner"
                                wire:loading
                                wire:target="{{ $ipbtaxBusyTargets }}"
                                aria-hidden="true"
                            ></span>

                            <span wire:loading.remove wire:target="{{ $ipbtaxBusyTargets }}">
                                {{ $this->ipbtaxStatus !== '' ? $this->ipbtaxStatus : 'Pronto' }}
                            </span>

                            <span wire:loading.flex wire:target="ipbtaxUpload">Enviando arquivo…</span>
                            <span wire:loading.flex wire:target="atualizarIpbtaxArquivo">Lendo e processando arquivo IPBTAX…</span>
                            <span wire:loading.flex wire:target="confirmarIpbtaxImportacao">Gravando tabela na base…</span>

                            <span class="erp-ipbtax-modal__status-pct" wire:loading.remove wire:target="{{ $ipbtaxBusyTargets }}">
                                {{ $ipbtaxPct }}%
                            </span>
                            <span class="erp-ipbtax-modal__status-pct" wire:loading wire:target="{{ $ipbtaxBusyTargets }}">…</span>
                        </div>

                        <div
                            class="erp-ipbtax-modal__progress"
                            role="progressbar"
                            aria-valuenow="{{ $ipbtaxPct }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-busy="{{ $this->ipbtaxBusy ? 'true' : 'false' }}"
                        >
                            <div
                                class="erp-ipbtax-modal__progress-bar"
                                wire:loading.class="erp-ipbtax-modal__progress-bar--indeterminate"
                                wire:target="{{ $ipbtaxBusyTargets }}"
                                style="width: {{ $ipbtaxPct > 0 ? $ipbtaxPct : ($this->ipbtaxBusy ? 18 : 0) }}%"
                            ></div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="erp-pcad-form__btn erp-ipbtax-modal__btn-confirma"
                        wire:click="confirmarIpbtaxImportacao"
                        wire:loading.attr="disabled"
                        wire:target="{{ $ipbtaxBusyTargets }}"
                        @disabled(! $this->ipbtaxReadyToConfirm || $this->ipbtaxBusy)
                    >
                        <svg class="erp-ipbtax-modal__icon erp-ipbtax-modal__icon--lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span wire:loading.remove wire:target="confirmarIpbtaxImportacao">Confirma</span>
                        <span wire:loading wire:target="confirmarIpbtaxImportacao">Gravando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
