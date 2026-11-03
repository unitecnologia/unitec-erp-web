@if ($this->caixaTransferOpen)
    <div class="erp-caixa-form-modal" x-data x-on:keydown.escape.window="$wire.closeCaixaTransferencia()">
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaTransferencia"></div>
        <section class="erp-caixa-form-modal__dialog" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Livro Caixa</span>
                    <h2>Transferência entre contas</h2>
                </div>
                <button type="button" wire:click="closeCaixaTransferencia" title="Fechar">×</button>
            </header>

            <div class="erp-caixa-form-modal__body">
                @if ($this->caixaTransferAlert !== '')
                    <div class="erp-caixa-form-modal__alert" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Atenção</strong>
                            <p>{{ $this->caixaTransferAlert }}</p>
                        </div>
                        <button type="button" wire:click="$set('caixaTransferAlert', '')" aria-label="Fechar aviso">×</button>
                    </div>
                @endif

                <div class="erp-caixa-form-modal__grid">
                    <label>
                        <span>Conta origem</span>
                        <select wire:model="caixaTransferForm.origem_id">
                            <option value="">Selecione…</option>
                            @foreach ($this->contasTransferenciaOptions as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Conta destino</span>
                        <select wire:model="caixaTransferForm.destino_id">
                            <option value="">Selecione…</option>
                            @foreach ($this->contasTransferenciaOptions as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Data</span>
                        <input type="date" wire:model="caixaTransferForm.emissao">
                    </label>
                    <label class="erp-caixa-form-modal__money">
                        <span>Valor</span>
                        <input type="text" wire:model="caixaTransferForm.valor" data-mask="money-br" inputmode="decimal">
                    </label>
                    <label class="erp-caixa-form-modal__field--full">
                        <span>Histórico / observação</span>
                        <input type="text" wire:model="caixaTransferForm.historico" data-erp-uppercase maxlength="400" placeholder="Motivo da transferência">
                    </label>
                </div>
            </div>

            <footer class="erp-caixa-form-modal__footer">
                <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaTransferencia">Cancelar</button>
                <button
                    type="button"
                    class="erp-caixa-form-modal__save"
                    wire:click="confirmarCaixaTransferencia"
                    wire:loading.attr="disabled"
                    wire:target="confirmarCaixaTransferencia"
                >
                    ✓ Confirmar
                </button>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaTransferDeleteOpen)
    <div class="erp-caixa-confirm-modal" x-data x-on:keydown.escape.window="$wire.cancelDeleteCaixaTransferencia()">
        <div class="erp-caixa-confirm-modal__backdrop" wire:click="cancelDeleteCaixaTransferencia"></div>
        <section class="erp-caixa-confirm-modal__dialog" role="alertdialog" aria-modal="true" wire:click.stop>
            <div class="erp-caixa-confirm-modal__icon">!</div>
            <div>
                <h2>Estornar transferência?</h2>
                <p>Serão excluídos os dois lançamentos do par (origem e destino) com documento {{ $this->caixaTransferDeleteDocumento }}. Não é possível excluir apenas um lado.</p>
            </div>
            <footer>
                <button type="button" class="erp-caixa-confirm-modal__cancel" wire:click="cancelDeleteCaixaTransferencia">Não, manter</button>
                <button type="button" class="erp-caixa-confirm-modal__delete" wire:click="confirmDeleteCaixaTransferencia">Sim, estornar par</button>
            </footer>
        </section>
    </div>
@endif
