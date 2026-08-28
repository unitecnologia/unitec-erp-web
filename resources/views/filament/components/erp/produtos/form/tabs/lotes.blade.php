@php
    $controla = (bool) ($this->data['controla_lote_validade'] ?? false);
@endphp

<div class="erp-produtos-estoques" wire:key="produto-lotes-panel">
    <p class="erp-produtos-estoques__hint">
        Lotes e validade deste produto. A venda baixa automaticamente pelo FEFO (vence primeiro).
    </p>

    @if (! $controla)
        <p class="erp-produtos-estoques__empty" style="padding:.85rem;">
            Marque <strong>Controla lote/validade</strong> nos parâmetros para usar lotes neste produto.
        </p>
    @elseif (! $this->record?->id)
        <p class="erp-produtos-estoques__empty" style="padding:.85rem;">
            Grave o produto primeiro; depois use <strong>Incluir</strong> nesta aba (ou finalize uma compra).
        </p>
    @else
        <div style="display:flex;flex-wrap:wrap;gap:.65rem;align-items:center;margin-bottom:.75rem;">
            <button
                type="button"
                class="erp-compras-lancamento-modal__tool-btn"
                style="padding:.4rem .75rem;font-size:.8rem;"
                wire:click="openProductLoteCreate"
            >Incluir</button>
            <button
                type="button"
                class="erp-compras-lancamento-modal__tool-btn"
                style="padding:.4rem .75rem;font-size:.8rem;"
                wire:click="openProductLoteEdit"
                @disabled(! $this->productLoteSelectedId)
            >Editar</button>
        </div>

        <div class="erp-produtos-estoques__grid-wrap">
            <table class="erp-produtos-estoques__grid">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Validade</th>
                        <th class="erp-produtos-estoques__col-num">Dias</th>
                        <th class="erp-produtos-estoques__col-num">Estoque</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->productLotesRows as $row)
                        @php
                            $tone = match ($row['situacao']) {
                                'vencido' => '#b91c1c',
                                'critico' => '#c2410c',
                                'atencao' => '#a16207',
                                default => '#047857',
                            };
                            $selected = (int) ($this->productLoteSelectedId ?? 0) === (int) $row['id'];
                        @endphp
                        <tr
                            wire:key="produto-lote-{{ $row['id'] }}"
                            @class([
                                'erp-produtos-estoques__row',
                                'erp-produtos-estoques__row--selected' => $selected,
                            ])
                            wire:click="selectProductLote({{ (int) $row['id'] }})"
                            style="cursor:pointer;"
                        >
                            <td title="{{ $row['lote'] }}">{{ $row['lote'] }}</td>
                            <td>{{ $row['validade'] }}</td>
                            <td class="erp-produtos-estoques__col-num" style="font-weight:650;color:{{ $tone }};">
                                {{ $row['dias'] === null ? '—' : $row['dias'] }}
                            </td>
                            <td class="erp-produtos-estoques__col-num">{{ $row['estoque'] }}</td>
                            <td>
                                <span style="display:inline-block;padding:.1rem .45rem;border-radius:999px;font-size:.72rem;font-weight:650;color:{{ $tone }};border:1px solid {{ $tone }}33;">
                                    {{ $row['situacao_label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="erp-produtos-estoques__empty">
                                Nenhum lote cadastrado. Use Incluir para adicionar lote e validade.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($this->productLoteModalOpen)
        <div
            class="erp-lookup-modal erp-produto-lote-modal"
            wire:keydown.escape.window="closeProductLoteModal"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="closeProductLoteModal"></div>
            <div
                class="erp-lookup-modal__window"
                role="dialog"
                aria-modal="true"
                aria-labelledby="erp-produto-lote-modal-title"
                style="max-width:26rem;"
            >
                <div class="erp-lookup-modal__titlebar">
                    <span id="erp-produto-lote-modal-title">
                        {{ $this->productLoteModalMode === 'edit' ? 'Editar lote / validade' : 'Incluir lote / validade' }}
                    </span>
                    <button type="button" class="erp-lookup-modal__close" wire:click="closeProductLoteModal" title="Fechar">✕</button>
                </div>

                <div class="erp-lookup-modal__body" style="display:grid;gap:.75rem;padding:1rem 1.1rem;">
                    <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;color:#334155;">
                        Lote
                        <input
                            type="text"
                            maxlength="60"
                            wire:model="productLoteDraftLote"
                            class="erp-pcad-form__input"
                            autofocus
                        >
                    </label>
                    <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;color:#334155;">
                        Validade
                        <div class="erp-prod-date-wrap">
                            <input
                                type="date"
                                wire:model="productLoteDraftValidade"
                                data-erp-native-date
                                class="erp-pcad-form__input erp-pcad-form__input--date"
                                onclick="try{this.showPicker()}catch(e){}"
                            >
                            <span class="erp-prod-date-icon" aria-hidden="true"></span>
                        </div>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;color:#334155;">
                        Quantidade
                        <input
                            type="text"
                            wire:model="productLoteDraftQuantidade"
                            inputmode="decimal"
                            class="erp-pcad-form__input erp-pcad-form__input--num"
                        >
                    </label>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="saveProductLote" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label">Salvar</span>
                    </button>
                    <button type="button" wire:click="closeProductLoteModal" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label">Cancelar</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
