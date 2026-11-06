@if ($this->viewModalOpen)
    @php
        $temDetalhe = (bool) ($this->viewModalData['tem_detalhe'] ?? false);
        $detalhes = $this->viewModalData['detalhes'] ?? [];
        $ehGrupo = (bool) ($this->viewModalData['eh_grupo'] ?? false);
        $membrosGrupo = $this->viewModalData['membros'] ?? [];
    @endphp
    <div
        class="erp-lookup-modal erp-caixa-view-modal"
        wire:keydown.escape.window="closeCaixaView"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCaixaView"></div>

        <div
            class="erp-lookup-modal__window erp-caixa-view-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-caixa-view-modal-title"
        >
                <div class="erp-lookup-modal__titlebar">
                <span id="erp-caixa-view-modal-title">{{ $ehGrupo ? 'Pagamento misto (Caixa)' : 'Lançamento de Caixa' }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeCaixaView"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-caixa-view-modal__body">
                <div class="erp-caixa-view-modal__grid">
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Código</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['codigo'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Emissão</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['emissao'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Pedido</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['documento'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field erp-caixa-view-modal__field--wide">
                        <span class="erp-caixa-view-modal__label">Histórico</span>
                        <span class="erp-caixa-view-modal__value">{{ preg_replace('/^\[MANUAL\]\s*/iu', '', (string) ($this->viewModalData['historico'] ?? '—')) }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Plano de Contas</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['plano_contas'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Conta</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['conta'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Entrada</span>
                        <span class="erp-caixa-view-modal__value erp-caixa-view-modal__value--entrada">{{ $this->viewModalData['entrada'] ?? '0,00' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Saída</span>
                        <span class="erp-caixa-view-modal__value erp-caixa-view-modal__value--saida">{{ $this->viewModalData['saida'] ?? '0,00' }}</span>
                    </div>
                </div>

                @if ($ehGrupo && is_array($membrosGrupo) && $membrosGrupo !== [])
                    <div class="erp-caixa-view-modal__origem">
                        <h4 class="erp-caixa-view-modal__origem-title">Lançamentos individuais</h4>
                        <div class="erp-caixa-view-modal__membros">
                            @foreach ($membrosGrupo as $membro)
                                <div
                                    class="erp-caixa-view-modal__membro"
                                    title="{{ ($membro['plano_contas'] ?? '') !== '—' ? ($membro['plano_contas'] ?? '') : '' }}"
                                >
                                    <span class="erp-caixa-view-modal__membro-cod">{{ $membro['codigo'] ?? '—' }}</span>
                                    <span class="erp-caixa-view-modal__membro-hist">{{ $membro['historico'] ?? '—' }}</span>
                                    <span class="erp-caixa-view-modal__membro-valores">
                                        E {{ $membro['entrada'] ?? '0,00' }}
                                        @if ((string) ($membro['saida'] ?? '0,00') !== '0,00')
                                            · S {{ $membro['saida'] }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($temDetalhe && is_array($detalhes) && $detalhes !== [])
                    <div class="erp-caixa-view-modal__origem">
                        <h4 class="erp-caixa-view-modal__origem-title">Detalhes da origem</h4>
                        <div class="erp-caixa-view-modal__grid erp-caixa-view-modal__grid--origem">
                            @foreach ($detalhes as $linha)
                                <div @class([
                                    'erp-caixa-view-modal__field',
                                    'erp-caixa-view-modal__field--wide' => ! empty($linha['wide']),
                                ])>
                                    <span class="erp-caixa-view-modal__label">{{ $linha['label'] ?? '' }}</span>
                                    <span @class([
                                        'erp-caixa-view-modal__value',
                                        'erp-caixa-view-modal__value--money' => ! empty($linha['money']),
                                        'erp-caixa-view-modal__value--estorno' => ($linha['destaque'] ?? null) === 'estorno',
                                    ])>{{ $linha['value'] ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="erp-pcad-actions erp-caixa-view-modal__actions">
                    @if (! empty($this->viewModalData['pode_ver_itens']))
                        <button
                            type="button"
                            wire:click="abrirCaixaItensVenda"
                            class="erp-pcad-actions__btn erp-caixa-view-modal__btn-itens"
                            title="Ver itens da venda"
                        >
                            <span class="erp-pcad-actions__label">Ver itens da venda</span>
                        </button>
                    @endif
                    <button type="button" wire:click="closeCaixaView" class="erp-pcad-actions__btn erp-caixa-view-modal__btn-sair" data-erp-key="Escape">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($this->caixaItensModalOpen)
    <div
        class="erp-lookup-modal erp-caixa-itens-modal"
        wire:keydown.escape.window="closeCaixaItensVenda"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCaixaItensVenda"></div>
        <div
            class="erp-lookup-modal__window erp-caixa-itens-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-caixa-itens-title"
        >
            <div class="erp-caixa-itens-modal__header">
                <div class="erp-caixa-itens-modal__heading">
                    <span id="erp-caixa-itens-title" class="erp-caixa-itens-modal__title">{{ $this->caixaItensModalTitulo }}</span>
                    @if (filled($this->caixaItensModalCliente))
                        <span class="erp-caixa-itens-modal__cliente">{{ $this->caixaItensModalCliente }}</span>
                    @endif
                </div>
                <button type="button" class="erp-caixa-itens-modal__close" wire:click="closeCaixaItensVenda" title="Fechar" aria-label="Fechar">✕</button>
            </div>
            <div class="erp-caixa-itens-modal__body">
                <div class="erp-caixa-itens-modal__grid-wrap">
                    <table class="erp-caixa-itens-modal__grid">
                        <colgroup>
                            <col class="erp-caixa-itens-modal__col-item">
                            <col class="erp-caixa-itens-modal__col-codigo">
                            <col class="erp-caixa-itens-modal__col-produto">
                            <col class="erp-caixa-itens-modal__col-qtd">
                            <col class="erp-caixa-itens-modal__col-preco">
                            <col class="erp-caixa-itens-modal__col-desc">
                            <col class="erp-caixa-itens-modal__col-total">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Código</th>
                                <th>Produto</th>
                                <th class="erp-caixa-itens-modal__num">Qtd</th>
                                <th class="erp-caixa-itens-modal__num">Preço</th>
                                <th class="erp-caixa-itens-modal__num">Desc.</th>
                                <th class="erp-caixa-itens-modal__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->caixaItensModalRows as $row)
                                <tr>
                                    <td class="erp-caixa-itens-modal__center">
                                        <span class="erp-caixa-itens-modal__badge">{{ $row['item'] }}</span>
                                    </td>
                                    <td class="erp-caixa-itens-modal__center erp-caixa-itens-modal__codigo">{{ $row['codigo'] }}</td>
                                    <td class="erp-caixa-itens-modal__produto">{{ $row['produto'] }}</td>
                                    <td class="erp-caixa-itens-modal__num">{{ $row['qtd'] }}</td>
                                    <td class="erp-caixa-itens-modal__num">{{ $row['preco'] }}</td>
                                    <td class="erp-caixa-itens-modal__num">{{ $row['desconto'] }}</td>
                                    <td class="erp-caixa-itens-modal__num erp-caixa-itens-modal__linha-total">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="erp-lookup-modal__empty">Nenhum item encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="erp-caixa-itens-modal__footer">
                    <div class="erp-caixa-itens-modal__total">
                        <span class="erp-caixa-itens-modal__total-label">Total</span>
                        <strong class="erp-caixa-itens-modal__total-value">{{ $this->caixaItensModalTotal }}</strong>
                    </div>
                    <button type="button" class="erp-caixa-itens-modal__btn-sair" wire:click="closeCaixaItensVenda">
                        <kbd>ESC</kbd>
                        <span>Voltar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
