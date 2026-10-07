@if ($this->osEspelhoOpen)
    @php
        $h = $this->osEspelho['header'] ?? [];
        $totais = $this->osEspelho['totais'] ?? [];
        $tab = $this->osEspelhoTab;
        $fotos = $this->osEspelho['fotos'] ?? [];
        $assinatura = $this->osEspelho['assinatura'] ?? null;
    @endphp
    <div
        class="erp-lookup-modal erp-orc-view-modal erp-os-espelho-modal"
        wire:keydown.escape.window="closeOrdemServicoEspelho"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeOrdemServicoEspelho"></div>

        <div
            class="erp-lookup-modal__window erp-orc-view-modal__window erp-os-espelho-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-os-espelho-title"
        >
            <div class="erp-orcamentos-window erp-orc-view-modal__panel">
                <header class="erp-orcamentos-window__titlebar erp-lookup-modal__titlebar">
                    <span id="erp-os-espelho-title" class="erp-orcamentos-window__title">
                        Espelho da OS {{ $h['numero'] ?? '' }}
                    </span>
                    <button
                        type="button"
                        class="erp-orcamentos-window__close erp-lookup-modal__close"
                        wire:click="closeOrdemServicoEspelho"
                        aria-label="Fechar"
                        title="Sair (ESC)"
                    >&times;</button>
                </header>

                <div class="erp-orcamentos-window__body erp-orc-view-modal__body erp-os-espelho-modal__body">
                    <div class="erp-os-espelho-modal__header">
                        <div class="erp-os-espelho-modal__meta">
                            <div class="erp-os-espelho-modal__meta-item">
                                <span>Cliente</span>
                                <strong title="{{ $h['cliente'] ?? '—' }}">{{ $h['cliente'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__meta-item">
                                <span>Documento</span>
                                <strong>{{ $h['documento'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__meta-item">
                                <span>Situação</span>
                                <strong class="erp-os-espelho-modal__situacao">{{ $h['situacao'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__meta-item">
                                <span>Técnico</span>
                                <strong title="{{ $h['atendente'] ?? '—' }}">{{ $h['atendente'] ?? '—' }}</strong>
                            </div>
                        </div>

                        <div class="erp-os-espelho-modal__dates">
                            <div class="erp-os-espelho-modal__date-item">
                                <span>Abertura</span>
                                <strong>{{ $h['abertura'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__date-item">
                                <span>Fechamento</span>
                                <strong>{{ $h['fechamento'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__date-item">
                                <span>Previsão</span>
                                <strong>{{ $h['previsao'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-os-espelho-modal__date-item">
                                <span>Entrega</span>
                                <strong>{{ $h['entrega'] ?? '—' }}</strong>
                            </div>
                        </div>

                        <div class="erp-os-espelho-modal__totais">
                            <div class="erp-os-espelho-modal__totais-line">
                                <span>Peças <b>{{ $totais['pecas'] ?? 'R$ 0,00' }}</b></span>
                                <span class="erp-os-espelho-modal__totais-sep" aria-hidden="true">|</span>
                                <span>Serviços <b>{{ $totais['servicos'] ?? 'R$ 0,00' }}</b></span>
                                <span class="erp-os-espelho-modal__totais-sep" aria-hidden="true">|</span>
                                <span>Desconto <b>{{ $totais['desconto'] ?? 'R$ 0,00' }}</b></span>
                            </div>
                            <div class="erp-os-espelho-modal__total">
                                <span>Total</span>
                                <strong>{{ $totais['geral'] ?? 'R$ 0,00' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="erp-os-espelho-modal__tabs" role="tablist">
                        @foreach ([
                            'resumo' => 'Resumo',
                            'itens' => 'Peças / Serviços',
                            'midias' => 'Fotos / Assinatura',
                            'financeiro' => 'Financeiro / Notas',
                            'historico' => 'Histórico',
                        ] as $key => $label)
                            <button
                                type="button"
                                role="tab"
                                wire:click="setOrdemServicoEspelhoTab('{{ $key }}')"
                                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                                @class(['erp-os-espelho-modal__tab', 'is-active' => $tab === $key])
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    <div class="erp-os-espelho-modal__content">
                        @if ($tab === 'resumo')
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Equipamento</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body">
                                    @forelse (($this->osEspelho['equipamento'] ?? []) as $linha)
                                        <div class="erp-os-espelho-modal__equip-row">
                                            @foreach ($linha as $campo)
                                                <div class="erp-os-espelho-modal__equip-cell">
                                                    <span>{{ $campo['label'] }}</span>
                                                    <strong>{{ $campo['value'] }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    @empty
                                        <p class="erp-os-espelho-modal__empty">Nenhum dado de equipamento.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Problema / defeito</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body">
                                    <p>{{ $this->osEspelho['textos']['problema'] ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Observações</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body">
                                    <p>{{ $this->osEspelho['textos']['observacoes'] ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Laudo</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body">
                                    <p>{{ $this->osEspelho['textos']['laudo'] ?? '—' }}</p>
                                </div>
                            </div>

                        @elseif ($tab === 'itens')
                            <div class="erp-os-espelho-modal__section erp-os-espelho-modal__section--servicos">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Serviços</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Cód.</th>
                                                    <th>Descrição</th>
                                                    <th>Técnico</th>
                                                    <th class="num">Qtd</th>
                                                    <th class="num">Preço</th>
                                                    <th class="num">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['servicos'] ?? []) as $item)
                                                    <tr>
                                                        <td>{{ $item['codigo'] }}</td>
                                                        <td>
                                                            {{ $item['descricao'] }}
                                                            @if (($item['servico_prestado'] ?? '') !== '')
                                                                <div class="erp-os-espelho-modal__item-obs">{!! nl2br(e($item['servico_prestado'])) !!}</div>
                                                            @endif
                                                        </td>
                                                        <td>{{ $item['tecnico'] }}</td>
                                                        <td class="num">{{ $item['qtd'] }}</td>
                                                        <td class="num">{{ $item['preco'] }}</td>
                                                        <td class="num">{{ $item['total'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="erp-os-espelho-modal__empty">Nenhum serviço.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section erp-os-espelho-modal__section--pecas">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Peças</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Cód.</th>
                                                    <th>Descrição</th>
                                                    <th class="num">Qtd</th>
                                                    <th class="num">Preço</th>
                                                    <th class="num">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['pecas'] ?? []) as $item)
                                                    <tr>
                                                        <td>{{ $item['codigo'] }}</td>
                                                        <td>{{ $item['descricao'] }}</td>
                                                        <td class="num">{{ $item['qtd'] }}</td>
                                                        <td class="num">{{ $item['preco'] }}</td>
                                                        <td class="num">{{ $item['total'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5" class="erp-os-espelho-modal__empty">Nenhuma peça.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        @elseif ($tab === 'midias')
                            <div
                                class="erp-os-espelho-modal__midias"
                                x-data="{ preview: null }"
                            >
                                <div class="erp-os-espelho-modal__section erp-os-espelho-modal__section--fotos">
                                    <div class="erp-os-espelho-modal__section-head">
                                        <h3>Fotos</h3>
                                    </div>
                                    <div class="erp-os-espelho-modal__section-body">
                                        @if ($fotos === [])
                                            <div class="erp-os-espelho-modal__empty-box">
                                                Nenhuma foto anexada
                                            </div>
                                        @else
                                            <div class="erp-os-espelho-modal__gallery">
                                                @foreach ($fotos as $foto)
                                                    <button
                                                        type="button"
                                                        class="erp-os-espelho-modal__photo"
                                                        @click="preview = @js($foto['url'])"
                                                        title="Clique para ampliar"
                                                    >
                                                        <img src="{{ $foto['url'] }}" alt="Foto da OS" loading="lazy">
                                                        <span class="erp-os-espelho-modal__photo-meta">{{ $foto['em'] }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="erp-os-espelho-modal__section erp-os-espelho-modal__section--assinatura">
                                    <div class="erp-os-espelho-modal__section-head">
                                        <h3>Assinatura do cliente</h3>
                                    </div>
                                    <div class="erp-os-espelho-modal__section-body">
                                        @if (! empty($assinatura['url']))
                                            <figure class="erp-os-espelho-modal__assinatura">
                                                <div class="erp-os-espelho-modal__assinatura-frame">
                                                    <img src="{{ $assinatura['url'] }}" alt="Assinatura do cliente">
                                                </div>
                                                <figcaption>
                                                    @if (! empty($assinatura['em']))
                                                        <span class="erp-os-espelho-modal__assinatura-em">{{ $assinatura['em'] }}</span>
                                                    @endif
                                                    @if (! empty($h['cliente']) && ($h['cliente'] ?? '—') !== '—')
                                                        <strong class="erp-os-espelho-modal__assinatura-nome">{{ $h['cliente'] }}</strong>
                                                    @endif
                                                </figcaption>
                                            </figure>
                                        @else
                                            <div class="erp-os-espelho-modal__empty-box">
                                                Sem assinatura registrada
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div
                                    class="erp-os-espelho-modal__lightbox"
                                    x-show="preview"
                                    x-cloak
                                    x-transition.opacity
                                    @click="preview = null"
                                    @keydown.escape.window="
                                        if (preview) {
                                            preview = null;
                                            $event.stopImmediatePropagation();
                                        }
                                    "
                                    role="dialog"
                                    aria-modal="true"
                                    aria-label="Foto ampliada"
                                >
                                    <img :src="preview" alt="Foto ampliada" @click.stop>
                                    <button
                                        type="button"
                                        class="erp-os-espelho-modal__lightbox-close"
                                        @click="preview = null"
                                        title="Fechar"
                                        aria-label="Fechar"
                                    >&times;</button>
                                </div>
                            </div>

                        @elseif ($tab === 'financeiro')
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Contas a receber / caixa</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Nº</th>
                                                    <th>Emissão</th>
                                                    <th>Forma</th>
                                                    <th>Histórico</th>
                                                    <th class="num">Valor</th>
                                                    <th class="num">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['financeiro'] ?? []) as $row)
                                                    <tr>
                                                        <td>{{ $row['numero'] }}</td>
                                                        <td>{{ $row['emissao'] }}</td>
                                                        <td>{{ $row['forma'] }}</td>
                                                        <td>{{ $row['historico'] }}</td>
                                                        <td class="num">{{ $row['valor'] }}</td>
                                                        <td class="num">{{ $row['saldo'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="erp-os-espelho-modal__empty">Nenhum lançamento financeiro.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Boletos</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Nosso nº</th>
                                                    <th>Vencimento</th>
                                                    <th>Status</th>
                                                    <th class="num">Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['boletos'] ?? []) as $row)
                                                    <tr>
                                                        <td>{{ $row['nosso_numero'] }}</td>
                                                        <td>{{ $row['vencimento'] }}</td>
                                                        <td>{{ $row['status'] }}</td>
                                                        <td class="num">{{ $row['valor'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="erp-os-espelho-modal__empty">Nenhum boleto.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Notas (NFS-e relacionadas)</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Número</th>
                                                    <th>Emissão</th>
                                                    <th>Status</th>
                                                    <th class="num">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['notas'] ?? []) as $row)
                                                    <tr>
                                                        <td>{{ $row['tipo'] }}</td>
                                                        <td>{{ $row['numero'] }}</td>
                                                        <td>{{ $row['emissao'] }}</td>
                                                        <td>{{ $row['status'] }}</td>
                                                        <td class="num">{{ $row['total'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5" class="erp-os-espelho-modal__empty">Nenhuma NFS-e relacionada encontrada.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Baixas de estoque</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body erp-os-espelho-modal__section-body--flush">
                                    <div class="erp-os-espelho-modal__table-wrap">
                                        <table class="erp-os-espelho-modal__table">
                                            <thead>
                                                <tr>
                                                    <th>Quando</th>
                                                    <th>Tipo</th>
                                                    <th>Produto</th>
                                                    <th class="num">Qtd</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->osEspelho['estoque'] ?? []) as $row)
                                                    <tr>
                                                        <td>{{ $row['quando'] }}</td>
                                                        <td>{{ $row['tipo'] }}</td>
                                                        <td>{{ $row['produto'] }}</td>
                                                        <td class="num">{{ $row['qtd'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="erp-os-espelho-modal__empty">Sem movimentação de estoque.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        @else
                            <div class="erp-os-espelho-modal__section">
                                <div class="erp-os-espelho-modal__section-head">
                                    <h3>Histórico</h3>
                                </div>
                                <div class="erp-os-espelho-modal__section-body">
                                    <div class="erp-os-espelho-modal__timeline">
                                        @forelse (($this->osEspelho['historico'] ?? []) as $evento)
                                            <article class="erp-os-espelho-modal__event">
                                                <time>{{ $evento['quando'] }}</time>
                                                <h4>{{ $evento['titulo'] }}</h4>
                                                <p>{{ $evento['detalhe'] }}</p>
                                            </article>
                                        @empty
                                            <p class="erp-os-espelho-modal__empty">Sem histórico disponível.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="erp-pcad-actions erp-orc-actions erp-orc-view-modal__actions erp-os-espelho-modal__actions">
                        <button
                            type="button"
                            wire:click="closeOrdemServicoEspelho"
                            class="erp-os-espelho-modal__sair"
                            title="Sair (ESC)"
                        >
                            <span class="erp-os-espelho-modal__sair-icon" aria-hidden="true">✕</span>
                            <span>Sair</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
