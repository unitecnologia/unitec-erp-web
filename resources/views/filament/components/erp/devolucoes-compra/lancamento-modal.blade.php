@php
    use App\Models\DevolucaoCompra;
    use App\Support\Erp\ErpMoney;

    $editable = $this->formSituacao === DevolucaoCompra::SITUACAO_ABERTA;
    $statusLabel = match ($this->formSituacao) {
        DevolucaoCompra::SITUACAO_FINALIZADA => 'FINALIZADA',
        DevolucaoCompra::SITUACAO_CANCELADA => 'CANCELADA',
        default => 'ABERTO',
    };
@endphp

@if ($this->lancamentoModalOpen)
    <div
        class="erp-lookup-modal erp-devcompra-lancamento-modal"
        wire:keydown.escape.window="closeLancamentoModal"
        x-data
        x-on:keydown.window="
            if (! $wire.lancamentoModalOpen) return;
            if (event.key === 'F2') { event.preventDefault(); $wire.saveLancamento(false); }
            if (event.key === 'F3') { event.preventDefault(); $wire.finalizeLancamento(); }
            if (event.ctrlKey && (event.key === 'Delete' || event.key === 'Del')) {
                event.preventDefault();
                $wire.removeSelectedItem();
            }
        "
    >
        <div class="erp-lookup-modal__backdrop erp-devcompra-lancamento-modal__backdrop" wire:click="closeLancamentoModal"></div>

        <div
            class="erp-lookup-modal__window erp-devcompra-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-devcompra-lancamento-title"
            wire:click.stop
        >
            <div class="erp-lookup-modal__titlebar erp-devcompra-lancamento-modal__titlebar">
                <span id="erp-devcompra-lancamento-title">Lançamento de Devolução de Compra</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeLancamentoModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-devcompra-lancamento-modal__body">
                <div class="erp-devcompra-lancamento-modal__toolbar">
                    <div class="erp-devcompra-lancamento-modal__toolbar-actions">
                        <button
                            type="button"
                            class="erp-devcompra-lancamento-modal__tool-btn"
                            wire:click="saveLancamento(false)"
                            @disabled(! $editable)
                            title="Gravar"
                        >
                            <span class="erp-devcompra-lancamento-modal__tool-icon">💾</span>
                            <span class="erp-devcompra-lancamento-modal__tool-label"><kbd>F2</kbd> | Gravar</span>
                        </button>
                        <button
                            type="button"
                            class="erp-devcompra-lancamento-modal__tool-btn"
                            wire:click="finalizeLancamento"
                            @disabled(! $editable)
                            title="Finalizar"
                        >
                            <span class="erp-devcompra-lancamento-modal__tool-icon">✓</span>
                            <span class="erp-devcompra-lancamento-modal__tool-label"><kbd>F3</kbd> | Finalizar</span>
                        </button>
                        <button
                            type="button"
                            class="erp-devcompra-lancamento-modal__tool-btn erp-devcompra-lancamento-modal__tool-btn--exit"
                            wire:click="closeLancamentoModal"
                            title="Sair"
                        >
                            <span class="erp-devcompra-lancamento-modal__tool-icon">✕</span>
                            <span class="erp-devcompra-lancamento-modal__tool-label"><kbd>ESC</kbd> | Sair</span>
                        </button>
                    </div>

                    <div @class([
                        'erp-devcompra-lancamento-modal__status-box',
                        'erp-devcompra-lancamento-modal__status-box--aberta' => $this->formSituacao === DevolucaoCompra::SITUACAO_ABERTA,
                        'erp-devcompra-lancamento-modal__status-box--finalizada' => $this->formSituacao === DevolucaoCompra::SITUACAO_FINALIZADA,
                        'erp-devcompra-lancamento-modal__status-box--cancelada' => $this->formSituacao === DevolucaoCompra::SITUACAO_CANCELADA,
                    ])>
                        {{ $statusLabel }}
                    </div>
                </div>

                <div class="erp-devcompra-lancamento-modal__header">
                    <div class="erp-devcompra-lancamento-modal__form-row">
                        <div class="erp-devcompra-lancamento-modal__form-group erp-devcompra-lancamento-modal__compra-lookup">
                            <label class="erp-devcompra-lancamento-modal__form-label" for="erp-devcompra-compra-numero">Compra nº</label>
                            <div class="erp-devcompra-lancamento-modal__compra-field">
                                <input
                                    id="erp-devcompra-compra-numero"
                                    type="text"
                                    class="erp-devcompra-lancamento-modal__form-input erp-devcompra-lancamento-modal__form-input--compra"
                                    wire:model.live.debounce.300ms="formCompraNumero"
                                    wire:keydown.enter.prevent="importarCompra"
                                    @disabled(! $editable)
                                    autocomplete="off"
                                >
                                <button
                                    type="button"
                                    class="erp-devcompra-lancamento-modal__import-btn"
                                    wire:click="importarCompra"
                                    @disabled(! $editable)
                                >Importar</button>

                                @if ($this->compraLookupOpen && count($this->compraLookupResults) > 0)
                                    <div class="erp-devcompra-lancamento-modal__lookup">
                                        @foreach ($this->compraLookupResults as $result)
                                            <button
                                                type="button"
                                                class="erp-devcompra-lancamento-modal__lookup-item"
                                                wire:click="selectCompra({{ $result['id'] }})"
                                            >
                                                <span class="erp-devcompra-lancamento-modal__lookup-num">{{ $result['numero'] }}</span>
                                                <span class="erp-devcompra-lancamento-modal__lookup-meta">{{ $result['data'] }}</span>
                                                <span class="erp-devcompra-lancamento-modal__lookup-name">{{ $result['fornecedor'] }}</span>
                                                <span class="erp-devcompra-lancamento-modal__lookup-total">R$ {{ $result['total'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="erp-devcompra-lancamento-modal__form-group">
                            <label class="erp-devcompra-lancamento-modal__form-label">Empresa</label>
                            <span class="erp-devcompra-lancamento-modal__form-input erp-devcompra-lancamento-modal__form-input--empresa" aria-readonly="true">
                                {{ $this->formEmpresa !== '' ? $this->formEmpresa : '—' }}
                            </span>
                        </div>

                        <div class="erp-devcompra-lancamento-modal__form-group erp-devcompra-lancamento-modal__form-group--grow">
                            <label class="erp-devcompra-lancamento-modal__form-label">Fornecedor</label>
                            <span class="erp-devcompra-lancamento-modal__form-input erp-devcompra-lancamento-modal__form-input--fornecedor" aria-readonly="true">
                                {{ $this->formFornecedor !== '' ? $this->formFornecedor : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="erp-devcompra-lancamento-modal__form-row">
                        <div class="erp-devcompra-lancamento-modal__form-group erp-devcompra-lancamento-modal__form-group--grow">
                            <label class="erp-devcompra-lancamento-modal__form-label" for="erp-devcompra-obs">Observação</label>
                            <input
                                id="erp-devcompra-obs"
                                type="text"
                                class="erp-devcompra-lancamento-modal__form-input erp-devcompra-lancamento-modal__form-input--obs"
                                wire:model="formObservacoes"
                                maxlength="250"
                                @disabled(! $editable)
                            >
                        </div>

                        <div class="erp-devcompra-lancamento-modal__form-group">
                            <label class="erp-devcompra-lancamento-modal__form-label" for="erp-devcompra-data">Data</label>
                            <input
                                id="erp-devcompra-data"
                                type="date"
                                class="erp-devcompra-lancamento-modal__form-input erp-devcompra-lancamento-modal__form-input--date"
                                wire:model="formData"
                                @disabled(! $editable)
                            >
                        </div>
                    </div>
                </div>

                <p class="erp-devcompra-lancamento-modal__grid-hint">
                    Clique nas teclas <strong>CTRL + Delete</strong> para excluir ITEM
                </p>

                <div class="erp-lookup-modal__grid-wrap erp-devcompra-lancamento-modal__grid-wrap">
                    <table class="erp-lookup-modal__grid erp-devcompra-lancamento-modal__grid">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descrição</th>
                                <th class="erp-devcompra-lancamento-modal__num">Quantidade</th>
                                <th class="erp-devcompra-lancamento-modal__num">Qtd Devolvida</th>
                                <th class="erp-devcompra-lancamento-modal__num">Preço</th>
                                <th class="erp-devcompra-lancamento-modal__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->formItens as $index => $item)
                                <tr
                                    wire:key="devcompra-item-{{ $index }}-{{ $item['compra_item_id'] ?? $index }}"
                                    wire:click="selectItem({{ $index }})"
                                    @class(['erp-lookup-modal__row--selected' => $this->selectedItemIndex === $index])
                                >
                                    <td class="erp-devcompra-lancamento-modal__center">{{ $item['produto_codigo'] ?: '—' }}</td>
                                    <td>{{ $item['produto_descricao'] ?: '—' }}</td>
                                    <td class="erp-devcompra-lancamento-modal__num">
                                        {{ number_format((float) ($item['qtd_comprada'] ?? 0), 3, ',', '.') }}
                                    </td>
                                    <td class="erp-devcompra-lancamento-modal__num">
                                        @if ($editable)
                                            <input
                                                type="text"
                                                class="erp-devcompra-lancamento-modal__qty-input"
                                                value="{{ number_format((float) ($item['qtd'] ?? 0), 3, ',', '.') }}"
                                                wire:change="updateItemQtd({{ $index }}, $event.target.value)"
                                                inputmode="decimal"
                                                autocomplete="off"
                                            >
                                        @else
                                            {{ number_format((float) ($item['qtd'] ?? 0), 3, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="erp-devcompra-lancamento-modal__num">
                                        {{ ErpMoney::formatBr((float) ($item['preco'] ?? 0)) }}
                                    </td>
                                    <td class="erp-devcompra-lancamento-modal__num">
                                        {{ ErpMoney::formatBr((float) ($item['total'] ?? 0)) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="erp-lookup-modal__empty">
                                        Importe uma compra para carregar os itens.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="erp-devcompra-lancamento-modal__footer">
                    <span class="erp-devcompra-lancamento-modal__footer-label">TOTAL</span>
                    <span class="erp-devcompra-lancamento-modal__footer-value">
                        R$ {{ number_format((float) $this->formTotal, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
@endif
