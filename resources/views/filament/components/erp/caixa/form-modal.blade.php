@if ($this->caixaFormOpen)
    @php
        $err = is_array($this->caixaFormErrors ?? null) ? $this->caixaFormErrors : [];
    @endphp
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
                    <label @class(['is-invalid' => isset($err['emissao'])])>
                        <span>Emissão <em>*</em></span>
                        <input type="date" wire:model="caixaForm.emissao" required>
                    </label>
                    <label>
                        <span>Pedido</span>
                        <input type="text" wire:model="caixaForm.documento" maxlength="40" placeholder="Opcional">
                    </label>
                    <label class="erp-caixa-form-modal__field--full">
                        <span>Empresa</span>
                        <input
                            type="text"
                            value="{{ \App\Support\Erp\ErpContext::currentEmpresa()?->razao_social ?? \App\Support\Erp\ErpContext::currentEmpresa()?->nome_fantasia ?? 'Empresa atual' }}"
                            data-erp-locked="1"
                            readonly
                            tabindex="-1"
                            aria-readonly="true"
                        >
                    </label>
                    <label @class(['is-invalid' => isset($err['plano_conta_id'])])>
                        <span>Plano de contas <em>*</em></span>
                        <select wire:model="caixaForm.plano_conta_id" required>
                            <option value="">Selecione…</option>
                            @foreach (\App\Models\PlanoConta::query()->where('ativo', true)->orderBy('codigo')->orderBy('descricao')->get() as $plano)
                                <option value="{{ $plano->id }}">{{ str_pad((string) $plano->codigo, 3, '0', STR_PAD_LEFT) }} — {{ $plano->descricao }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label @class(['is-invalid' => isset($err['caixa_conta_id'])])>
                        <span>Conta <em>*</em></span>
                        <select wire:model="caixaForm.caixa_conta_id" required>
                            <option value="">Selecione…</option>
                            @foreach (\App\Models\CaixaConta::query()->visiveisNaEmpresa()->where('ativo', true)->orderBy('nome')->get() as $conta)
                                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="erp-caixa-form-modal__row-valores">
                        <label class="erp-caixa-form-modal__meio" @class(['is-invalid' => isset($err['forma_pagamento_id'])])>
                            <span>Meio de pagamento <em>*</em></span>
                            <select wire:model.live="caixaForm.forma_pagamento_id" required>
                                <option value="">Selecione…</option>
                                @foreach (\App\Models\FormaPagamento::query()->where('ativo', true)->orderBy('codigo')->orderBy('descricao')->get() as $forma)
                                    <option value="{{ $forma->id }}">{{ str_pad((string) $forma->codigo, 2, '0', STR_PAD_LEFT) }} — {{ $forma->descricao }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="erp-caixa-form-modal__money" @class(['is-invalid' => isset($err['entrada'])])>
                            <span>Entrada <em>*</em></span>
                            <input
                                type="text"
                                wire:model="caixaForm.entrada"
                                data-mask="money-br"
                                inputmode="decimal"
                                x-on:focus="$event.target.select()"
                                x-on:click="$event.target.select()"
                            >
                        </label>
                        <label class="erp-caixa-form-modal__money" @class(['is-invalid' => isset($err['saida'])])>
                            <span>Saída <em>*</em></span>
                            <input
                                type="text"
                                wire:model="caixaForm.saida"
                                data-mask="money-br"
                                inputmode="decimal"
                                x-on:focus="$event.target.select()"
                                x-on:click="$event.target.select()"
                            >
                        </label>
                    </div>
                    <label class="erp-caixa-form-modal__field--full" @class(['is-invalid' => isset($err['historico'])])>
                        <span>Histórico <em>*</em></span>
                        <input type="text" wire:model.live="caixaForm.historico" data-erp-uppercase maxlength="500" placeholder="Descreva o movimento" required>
                    </label>
                    <p class="erp-caixa-form-modal__hint erp-caixa-form-modal__field--full">
                        Preencha <strong>Entrada</strong> ou <strong>Saída</strong> (apenas uma, com valor &gt; 0). Pedido é opcional.
                    </p>
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
