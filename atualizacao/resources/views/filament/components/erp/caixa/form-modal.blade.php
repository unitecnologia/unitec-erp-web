@if ($this->caixaFormOpen)
    <div class="erp-caixa-form-modal" x-data x-on:keydown.escape.window="$wire.closeCaixaForm()">
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaForm"></div>
        <section class="erp-caixa-form-modal__dialog" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Livro Caixa</span>
                    <h2>{{ $this->caixaFormLancamentoId ? 'Alterar lançamento' : 'Novo lançamento' }}</h2>
                </div>
                <button type="button" wire:click="closeCaixaForm" title="Fechar">×</button>
            </header>

            <div class="erp-caixa-form-modal__body">
                @if ($this->caixaFormAlert !== '')
                    <div class="erp-caixa-form-modal__alert" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Atenção</strong>
                            <p>{{ $this->caixaFormAlert }}</p>
                        </div>
                        <button type="button" wire:click="$set('caixaFormAlert', '')" aria-label="Fechar aviso">×</button>
                    </div>
                @endif
                <div class="erp-caixa-form-modal__grid">
                    <label>
                        <span>Emissão</span>
                        <input type="date" wire:model="caixaForm.emissao">
                    </label>
                    <label>
                        <span>Documento</span>
                        <input type="text" wire:model="caixaForm.documento" maxlength="40" placeholder="Opcional">
                    </label>
                    <label class="erp-caixa-form-modal__field--full">
                        <span>Empresa</span>
                        <input type="text" value="{{ \App\Support\Erp\ErpContext::currentEmpresa()?->razao_social ?? \App\Support\Erp\ErpContext::currentEmpresa()?->nome_fantasia ?? 'Empresa atual' }}" readonly>
                    </label>
                    <label>
                        <span>Plano de contas</span>
                        <select wire:model="caixaForm.plano_conta_id">
                            <option value="">Sem plano</option>
                            @foreach (\App\Models\PlanoConta::query()->where('ativo', true)->orderBy('codigo')->orderBy('descricao')->get() as $plano)
                                <option value="{{ $plano->id }}">{{ str_pad((string) $plano->codigo, 3, '0', STR_PAD_LEFT) }} — {{ $plano->descricao }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Conta</span>
                        <select wire:model="caixaForm.caixa_conta_id">
                            <option value="">Selecione…</option>
                            @foreach (\App\Models\CaixaConta::query()->where('ativo', true)->orderBy('nome')->get() as $conta)
                                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Forma de pagamento</span>
                        <select wire:model.live="caixaForm.forma_pagamento_id">
                            <option value="">Não informar</option>
                            @foreach (\App\Models\FormaPagamento::query()->where('ativo', true)->orderBy('codigo')->orderBy('descricao')->get() as $forma)
                                <option value="{{ $forma->id }}">{{ str_pad((string) $forma->codigo, 2, '0', STR_PAD_LEFT) }} — {{ $forma->descricao }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-caixa-form-modal__field--full">
                        <span>Histórico</span>
                        <input type="text" wire:model.live="caixaForm.historico" data-erp-uppercase maxlength="500" placeholder="Descreva o movimento">
                    </label>
                    <label class="erp-caixa-form-modal__money">
                        <span>Entrada</span>
                        <input type="text" wire:model="caixaForm.entrada" data-mask="money-br" inputmode="decimal">
                    </label>
                    <label class="erp-caixa-form-modal__money">
                        <span>Saída</span>
                        <input type="text" wire:model="caixaForm.saida" data-mask="money-br" inputmode="decimal">
                    </label>
                </div>
            </div>

            <footer class="erp-caixa-form-modal__footer">
                <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaForm">Cancelar</button>
                <button type="button" class="erp-caixa-form-modal__save" wire:click="saveCaixaLancamento">✓ Salvar lançamento</button>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaDeleteConfirmOpen)
    <div class="erp-caixa-confirm-modal" x-data x-on:keydown.escape.window="$wire.cancelDeleteCaixaLancamento()">
        <div class="erp-caixa-confirm-modal__backdrop" wire:click="cancelDeleteCaixaLancamento"></div>
        <section class="erp-caixa-confirm-modal__dialog" role="alertdialog" aria-modal="true" wire:click.stop>
            <div class="erp-caixa-confirm-modal__icon">!</div>
            <div>
                <h2>Excluir lançamento?</h2>
                <p>Deseja realmente excluir este lançamento manual? Esta ação não poderá ser desfeita.</p>
            </div>
            <footer>
                <button type="button" class="erp-caixa-confirm-modal__cancel" wire:click="cancelDeleteCaixaLancamento">Não, manter</button>
                <button type="button" class="erp-caixa-confirm-modal__delete" wire:click="confirmDeleteCaixaLancamento">Sim, excluir</button>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaAttentionMessage !== '')
    <div class="erp-caixa-confirm-modal" x-data x-on:keydown.escape.window="$wire.closeCaixaAttention()">
        <div class="erp-caixa-confirm-modal__backdrop" wire:click="closeCaixaAttention"></div>
        <section class="erp-caixa-confirm-modal__dialog erp-caixa-confirm-modal__dialog--attention" role="alertdialog" aria-modal="true" wire:click.stop>
            <div class="erp-caixa-confirm-modal__icon erp-caixa-confirm-modal__icon--attention">i</div>
            <div>
                <h2>Atenção</h2>
                <p>{{ $this->caixaAttentionMessage }}</p>
            </div>
            <footer>
                <button type="button" class="erp-caixa-confirm-modal__cancel" wire:click="closeCaixaAttention">Entendi</button>
            </footer>
        </section>
    </div>
@endif
