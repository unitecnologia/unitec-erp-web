@if ($this->demoOpen)
    <div class="erp-lookup-modal erp-contador-form-modal erp-tef-liq-lote-form-modal erp-tef-liq-demo-modal" wire:keydown.escape.window="closeDemonstrativo">
        <div class="erp-lookup-modal__backdrop" wire:click="closeDemonstrativo"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-tef-liq-demo-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Demonstrativo de liquidações TEF</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeDemonstrativo">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <p class="erp-tef-liq-lote-form-modal__hint">
                    Provedor: <strong>{{ $this->demoMeta['provedor_label'] ?? 'SuperTEF' }}</strong>
                    — {{ $this->demoMeta['aviso'] ?? 'Somente controle.' }}
                    Previsto ≠ vínculo OFX ≠ contabilização.
                </p>

                <div class="erp-tef-liq-demo-modal__filters">
                    <label class="erp-pcad-form__label" for="demo-de">De</label>
                    <input id="demo-de" type="date" wire:model="demoPeriodoDe" class="erp-pcad-form__input">
                    <label class="erp-pcad-form__label" for="demo-ate">Até</label>
                    <input id="demo-ate" type="date" wire:model="demoPeriodoAte" class="erp-pcad-form__input">
                    <label class="erp-pcad-form__label" for="demo-sit">Situação</label>
                    <select id="demo-sit" wire:model="demoSituacao" class="erp-pcad-form__input">
                        <option value="ativos">Ativos (sem cancelados)</option>
                        <option value="conferidos">Conferidos</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="cancelados">Cancelados</option>
                        <option value="todos">Todos</option>
                    </select>
                    <label class="erp-pcad-form__label" for="demo-term">Terminal</label>
                    <select id="demo-term" wire:model="demoTerminalId" class="erp-pcad-form__input">
                        <option value="">Todos</option>
                        @foreach ($this->demoTerminais as $t)
                            <option value="{{ $t['id'] }}">{{ $t['nome'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="erp-contador-form-modal__btn" wire:click="consultarDemonstrativo">Consultar</button>
                </div>

                @if ($this->demoConsultado)
                    <div class="erp-tef-liq-demo-modal__totais">
                        <span>Lotes: <strong>{{ (int) ($this->demoTotais['qtd_lotes'] ?? 0) }}</strong></span>
                        <span>Bruto: <strong>{{ number_format((float) ($this->demoTotais['bruto'] ?? 0), 2, ',', '.') }}</strong></span>
                        <span>Taxas info.: <strong>{{ number_format((float) ($this->demoTotais['taxas_informadas'] ?? 0), 2, ',', '.') }}</strong>
                            @if ((int) ($this->demoTotais['lotes_taxa_nao_informada'] ?? 0) > 0)
                                <em>({{ (int) $this->demoTotais['lotes_taxa_nao_informada'] }} sem taxa)</em>
                            @endif
                        </span>
                        <span>Líquido prev.: <strong>{{ number_format((float) ($this->demoTotais['liquido_previsto'] ?? 0), 2, ',', '.') }}</strong></span>
                        <span>OFX vinc.: <strong>{{ number_format((float) ($this->demoTotais['vinculado_ofx'] ?? 0), 2, ',', '.') }}</strong></span>
                        <span>Pendente: <strong>{{ number_format((float) ($this->demoTotais['pendente_ofx'] ?? 0), 2, ',', '.') }}</strong></span>
                        <span>Dif. just.: <strong>{{ number_format((float) ($this->demoTotais['diferencas_justificadas'] ?? 0), 2, ',', '.') }}</strong></span>
                        <span>Contabilizado: <strong>0,00</strong></span>
                    </div>

                    <div class="erp-tef-liq-lote-form-modal__table-wrap erp-tef-liq-demo-modal__table-wrap">
                        <table class="erp-tef-liq-lote-form-modal__table">
                            <thead>
                                <tr>
                                    <th>Lote</th>
                                    <th>Provedor</th>
                                    <th>Terminal</th>
                                    <th>Adquirente</th>
                                    <th>Período</th>
                                    <th>Tipo</th>
                                    <th class="is-right">Qtd</th>
                                    <th class="is-right">Bruto</th>
                                    <th class="is-right">Taxas</th>
                                    <th class="is-right">Líquido</th>
                                    <th class="is-right">OFX</th>
                                    <th class="is-right">Pend.</th>
                                    <th class="is-right">Dif.</th>
                                    <th>Situação</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->demoRows as $row)
                                    <tr>
                                        <td>{{ $row['id'] }}</td>
                                        <td>{{ $row['provedor_label'] }}</td>
                                        <td>{{ $row['terminal'] }}</td>
                                        <td>{{ $row['adquirente'] }}</td>
                                        <td>{{ $row['periodo'] }}</td>
                                        <td>{{ $row['tipo'] }}</td>
                                        <td class="is-right">{{ $row['qtd_transacoes'] }}</td>
                                        <td class="is-right">{{ number_format((float) $row['bruto'], 2, ',', '.') }}</td>
                                        <td class="is-right">{{ $row['taxas_label'] }}</td>
                                        <td class="is-right">{{ number_format((float) $row['liquido_previsto'], 2, ',', '.') }}</td>
                                        <td class="is-right">{{ number_format((float) $row['vinculado_ofx'], 2, ',', '.') }}</td>
                                        <td class="is-right">{{ number_format((float) $row['pendente_ofx'], 2, ',', '.') }}</td>
                                        <td class="is-right">{{ number_format((float) $row['diferencas_justificadas'], 2, ',', '.') }}</td>
                                        <td>{{ $row['situacao_label'] }}</td>
                                        <td>
                                            <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="abrirDemoDetalhe({{ (int) $row['id'] }})">Detalhe</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="15">Nenhum lote no filtro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="erp-tef-liq-lote-form-modal__pager">
                        <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="demoPaginaAnterior" @disabled($this->demoPage <= 1)>Anterior</button>
                        <span>Pág. {{ $this->demoPage }} / {{ $this->demoLastPage }} ({{ $this->demoTotal }})</span>
                        <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="demoPaginaProxima" @disabled($this->demoPage >= $this->demoLastPage)>Próxima</button>
                    </div>

                    @if ($this->demoDetalheLote)
                        <div class="erp-tef-liq-demo-modal__detalhe">
                            <div class="erp-tef-liq-demo-modal__detalhe-head">
                                <strong>Detalhe lote #{{ $this->demoDetalheLote['id'] }}</strong>
                                <span>{{ $this->demoDetalheLote['ofx_situacao_label'] }} · {{ $this->demoDetalheLote['contabilizacao'] }}</span>
                                <button type="button" class="erp-lookup-modal__close" wire:click="fecharDemoDetalhe">✕</button>
                            </div>
                            <div class="erp-tef-liq-lote-form-modal__table-wrap" style="max-height:160px;">
                                <table class="erp-tef-liq-lote-form-modal__table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Tipo</th>
                                            <th class="is-right">Bruto</th>
                                            <th class="is-right">Parc.</th>
                                            <th>NSU</th>
                                            <th>Status int.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->demoDetalheItens as $it)
                                            <tr @class(['is-warn' => !empty($it['orfao_ou_cancelada'])])>
                                                <td>{{ $it['id'] }}</td>
                                                <td>{{ $it['tipo'] }}</td>
                                                <td class="is-right">{{ number_format((float) $it['bruto'], 2, ',', '.') }}</td>
                                                <td class="is-right">{{ $it['parcela'] }}/{{ $it['parcelas'] }}</td>
                                                <td>{{ $it['nsu'] }}</td>
                                                <td>{{ $it['intencao_status'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @else
                    <p class="erp-tef-liq-lote-form-modal__hint">Informe o período e clique em Consultar. Nada é carregado automaticamente.</p>
                @endif
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn" wire:click="closeDemonstrativo">Fechar</button>
            </div>
        </div>
    </div>
@endif
