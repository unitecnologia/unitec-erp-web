@if ($this->nfeInfoAdicionaisModalOpen)
    @php
        $rowIndex = $this->nfeInfoAdicionaisRowIndex;
        $row = $rowIndex !== null ? ($this->nfeModalRows[$rowIndex] ?? null) : null;
        $codigo = $row ? (ltrim((string) ($row['codigo'] ?? ''), '0') ?: '—') : '—';
        $descricao = trim((string) ($row['descricao'] ?? ''));
    @endphp
    <div class="erp-pdv-modal erp-nfe-info-adicionais" role="dialog" aria-modal="true" aria-label="Informações adicionais do produto">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharNfeInfoAdicionaisModal"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small erp-nfe-info-adicionais__window">
            <header class="erp-pdv-modal__header">
                <h2>Informações adicionais do produto</h2>
            </header>
            <div class="erp-pdv-modal__body">
                @if ($descricao !== '')
                    <p class="erp-pdv-modal__hint">
                        <strong>{{ $codigo }}</strong> — {{ $descricao }}
                    </p>
                @endif

                <div class="erp-nfe-info-adicionais__toolbar">
                    <label class="erp-pdv-modal__label" for="erp-nfe-info-adicionais-texto">
                        Texto para o item na NF-e
                    </label>
                    <button
                        type="button"
                        wire:click="carregarNfeInfoAdicionaisDoProduto"
                        class="erp-pdv-modal__btn erp-nfe-info-adicionais__load-btn"
                        title="Carregar o texto salvo no cadastro do produto"
                    >
                        Carregar do cadastro
                    </button>
                </div>
                <textarea
                    id="erp-nfe-info-adicionais-texto"
                    wire:model="nfeInfoAdicionaisDraft"
                    wire:keydown.escape.prevent="fecharNfeInfoAdicionaisModal"
                    class="erp-nfe-lancamento-modal__textarea erp-nfe-info-adicionais__textarea"
                    rows="6"
                    maxlength="100"
                    placeholder="Digite informações complementares do produto, se necessário."
                    autocomplete="off"
                ></textarea>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmarNfeInfoAdicionaisModal" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Confirmar</button>
                <button type="button" wire:click="fecharNfeInfoAdicionaisModal" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
