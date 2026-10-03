@if ($this->historicoOpen)
    <div
        class="erp-lookup-modal erp-os-veiculo-historico"
        wire:keydown.escape.window="fecharHistoricoOuRelatorio"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeOsVeiculoHistorico"></div>

        <div
            class="erp-lookup-modal__window erp-os-veiculo-historico__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-os-veiculo-historico-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-veiculo-historico-title">Histórico de Manutenções</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeOsVeiculoHistorico" title="Fechar">✕</button>
            </div>

            <div class="erp-os-veiculo-historico__toolbar">
                <p class="erp-os-veiculo-historico__resumo">
                    <strong>{{ $this->historicoResumo['placa'] }}</strong>
                    @if (filled($this->historicoResumo['descricao']))
                        <span>{{ $this->historicoResumo['descricao'] }}</span>
                    @endif
                    @if (filled($this->historicoResumo['modelo']))
                        <span>{{ $this->historicoResumo['modelo'] }}</span>
                    @endif
                </p>
                <div class="erp-os-veiculo-historico__toolbar-actions">
                    @if ($this->historicoRelatorioUrl() !== '')
                        <button
                            type="button"
                            class="erp-os-veiculo-historico__link"
                            wire:click="abrirHistoricoRelatorio"
                        >Histórico resumido</button>
                    @endif
                    <button type="button" class="erp-os-veiculo-historico__link erp-os-veiculo-historico__link--ghost" wire:click="closeOsVeiculoHistorico">Fechar</button>
                </div>
            </div>

            <div class="erp-lookup-modal__body erp-os-veiculo-historico__body">
                @if ($this->historicoLinhas === [])
                    <p class="erp-os-veiculo-historico__empty">Nenhuma manutenção encontrada para este veículo.</p>
                @else
                    <div class="erp-os-veiculo-historico__table-wrap">
                        <table class="erp-os-veiculo-historico__table">
                            <thead>
                                <tr>
                                    <th>Nº da OS</th>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>KM</th>
                                    <th>Técnico</th>
                                    <th>Defeito/Problema</th>
                                    <th>Situação</th>
                                    <th>Peças</th>
                                    <th>Serviços</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->historicoLinhas as $linha)
                                    <tr
                                        wire:key="os-veiculo-hist-{{ $linha['id'] }}"
                                        wire:click="abrirHistoricoOs({{ $linha['id'] }})"
                                        @class([
                                            'erp-os-veiculo-historico__row',
                                            'erp-os-veiculo-historico__row--active' => $this->historicoOsId === $linha['id'],
                                        ])
                                    >
                                        <td>{{ $linha['numero'] }}</td>
                                        <td>{{ $linha['data'] }}</td>
                                        <td>{{ $linha['cliente'] }}</td>
                                        <td>{{ $linha['km'] }}</td>
                                        <td>{{ $linha['tecnico'] }}</td>
                                        <td class="erp-os-veiculo-historico__problema" title="{{ $linha['problema'] }}">{{ $linha['problema'] }}</td>
                                        <td>{{ $linha['situacao'] }}</td>
                                        <td>{{ $linha['pecas'] }}</td>
                                        <td>{{ $linha['servicos'] }}</td>
                                        <td>{{ $linha['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($this->historicoLastPage > 1)
                        <div class="erp-os-veiculo-historico__pager">
                            <button type="button" wire:click="historicoPagina({{ $this->historicoPage - 1 }})" @disabled($this->historicoPage <= 1)>Anterior</button>
                            <span>{{ $this->historicoPage }} / {{ $this->historicoLastPage }}</span>
                            <button type="button" wire:click="historicoPagina({{ $this->historicoPage + 1 }})" @disabled($this->historicoPage >= $this->historicoLastPage)>Próxima</button>
                        </div>
                    @endif
                @endif

                @if ($this->historicoDetalhe !== [])
                    @php($detalhe = $this->historicoDetalhe)
                    <section class="erp-os-veiculo-historico__detalhe">
                        <h3>OS {{ $detalhe['numero'] }} · {{ $detalhe['situacao'] }}</h3>
                        <dl>
                            <div><dt>Equipamento</dt><dd>{{ $detalhe['equipamento'] }}</dd></div>
                            <div><dt>Cliente</dt><dd>{{ $detalhe['cliente'] }}</dd></div>
                            <div><dt>KM</dt><dd>{{ $detalhe['km'] }}</dd></div>
                            <div><dt>Técnico</dt><dd>{{ $detalhe['tecnico'] }}</dd></div>
                            <div><dt>Início</dt><dd>{{ $detalhe['inicio'] }}</dd></div>
                            <div><dt>Previsão</dt><dd>{{ $detalhe['previsao'] }}</dd></div>
                            <div><dt>Término</dt><dd>{{ $detalhe['termino'] }}</dd></div>
                            <div><dt>Entrega</dt><dd>{{ $detalhe['entrega'] }}</dd></div>
                            <div class="erp-os-veiculo-historico__detalhe-largo"><dt>Defeito/Problema</dt><dd>{{ $detalhe['problema'] }}</dd></div>
                            <div class="erp-os-veiculo-historico__detalhe-largo"><dt>Diagnóstico/Execução</dt><dd>{{ $detalhe['laudo'] }}</dd></div>
                            <div class="erp-os-veiculo-historico__detalhe-largo"><dt>Observações</dt><dd>{{ $detalhe['observacoes'] }}</dd></div>
                        </dl>

                        <h4>Peças / produtos</h4>
                        @if ($detalhe['pecas'] === [])
                            <p class="erp-os-veiculo-historico__empty">Nenhuma peça lançada.</p>
                        @else
                            <table class="erp-os-veiculo-historico__table erp-os-veiculo-historico__table--itens">
                                <thead>
                                    <tr><th>Descrição</th><th>Qtd</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalhe['pecas'] as $item)
                                        <tr>
                                            <td>{{ $item['descricao'] }}</td>
                                            <td>{{ $item['qtd'] }}</td>
                                            <td>{{ $item['total'] !== '' ? $item['total'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <h4>Serviços</h4>
                        @if ($detalhe['servicos'] === [])
                            <p class="erp-os-veiculo-historico__empty">Nenhum serviço lançado.</p>
                        @else
                            <table class="erp-os-veiculo-historico__table erp-os-veiculo-historico__table--itens">
                                <thead>
                                    <tr><th>Descrição</th><th>Qtd</th><th>Técnico</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalhe['servicos'] as $item)
                                        <tr>
                                            <td>{{ $item['descricao'] }}</td>
                                            <td>{{ $item['qtd'] }}</td>
                                            <td>{{ $item['tecnico'] !== '' ? $item['tecnico'] : '—' }}</td>
                                            <td>{{ $item['total'] !== '' ? $item['total'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <p class="erp-os-veiculo-historico__totais">
                            Peças {{ $detalhe['total_pecas'] }}
                            · Serviços {{ $detalhe['total_servicos'] }}
                            · Total {{ $detalhe['total_geral'] }}
                        </p>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($this->historicoRelatorioOpen && $this->historicoRelatorioUrl() !== '')
    <div
        class="erp-os-veiculo-historico-preview"
        role="dialog"
        aria-modal="true"
        aria-label="Histórico resumido"
        data-livewire-id="{{ $this->getId() }}"
    >
        <div class="erp-os-veiculo-historico-preview__backdrop" wire:click="fecharHistoricoRelatorio"></div>
        <div class="erp-os-veiculo-historico-preview__panel">
            <iframe
                src="{{ $this->historicoRelatorioUrl() }}"
                class="erp-os-veiculo-historico-preview__iframe"
                title="Histórico resumido"
            ></iframe>
        </div>
    </div>
@endif

<script data-navigate-track>
    if (! window.__erpOsVeiculoHistoricoPreviewCloseBound) {
        window.__erpOsVeiculoHistoricoPreviewCloseBound = true;
        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-os-veiculo-historico-preview-close') {
                return;
            }
            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('close-os-veiculo-historico-preview');
                return;
            }
            const overlay = document.querySelector('.erp-os-veiculo-historico-preview');
            const componentId = overlay?.dataset.livewireId;
            if (componentId && window.Livewire?.find) {
                window.Livewire.find(componentId)?.call('fecharHistoricoRelatorio');
            }
        });
    }
</script>
