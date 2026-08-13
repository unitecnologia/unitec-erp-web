@if ($this->activeModal === 'remover_itens')
    <div class="erp-pdv-modal" role="dialog" aria-label="Remover itens">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelRemoverItens"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--form erp-pdv-remover">
            <header class="erp-pdv-modal__header">
                <h2>F11 — Remover Itens</h2>
            </header>

            @if ($this->removerItensConfirmando && $this->removerItensItem)
                @php($item = $this->removerItensItem)
                <div class="erp-pdv-modal__body erp-pdv-remover__body">
                    <p class="erp-pdv-remover__question">
                        Confirma a exclusão de <strong>1 unidade</strong>?
                    </p>

                    <table class="erp-pdv-remover__table">
                        <tbody>
                            <tr>
                                <th scope="row">Produto</th>
                                <td>{{ $item['descricao'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <th scope="row">Código</th>
                                <td>{{ $item['codigo'] ?? '—' }}</td>
                            </tr>
                            @if (filled($item['codigo_barras'] ?? null))
                                <tr>
                                    <th scope="row">Barras</th>
                                    <td>{{ $item['codigo_barras'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th scope="row">Qtde no cupom</th>
                                <td>
                                    {{ $this->formatCupomQuantidade((float) ($item['quantidade'] ?? 0)) }}
                                    {{ $item['unidade'] ?? 'UN' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer class="erp-pdv-modal__footer">
                    <button
                        type="button"
                        wire:click="confirmRemoverItens"
                        class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                        id="erp-pdv-remover-itens-sim"
                    >Sim</button>
                    <button
                        type="button"
                        wire:click="cancelRemoverItensConfirm"
                        class="erp-pdv-modal__btn"
                        id="erp-pdv-remover-itens-nao"
                    >Não</button>
                </footer>
            @else
                <div class="erp-pdv-modal__body erp-pdv-remover__body">
                    <label class="erp-pdv-modal__label" for="erp-pdv-remover-itens-search">
                        Código / código de barras
                    </label>
                    <input
                        id="erp-pdv-remover-itens-search"
                        type="text"
                        wire:model.live="removerItensSearch"
                        class="erp-pdv-modal__input erp-pdv-remover__input"
                        data-erp-uppercase
                        data-erp-pdv-clickable
                        autocomplete="off"
                        placeholder="Bipe o produto do cupom…"
                    >
                    <p class="erp-pdv-modal__hint">
                        Localiza o item no cupom, confirma com <strong>Sim</strong> e remove 1 unidade. A tela fecha em seguida.
                    </p>
                </div>

                <footer class="erp-pdv-modal__footer">
                    <button type="button" wire:click="cancelRemoverItens" class="erp-pdv-modal__btn">Cancelar</button>
                </footer>
            @endif
        </div>
    </div>
@endif
