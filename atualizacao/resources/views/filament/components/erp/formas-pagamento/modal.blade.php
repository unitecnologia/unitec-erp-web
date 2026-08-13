@php
    $tipos = \App\Models\FormaPagamento::tipoLabels();
    $movimentos = \App\Models\FormaPagamento::tipoMovimentoLabels();
    $contas = $this->contaDestinoOptions();
    $tipoAtual = (string) ($this->form['tipo'] ?? '');
    $mostraBandeiras = in_array($tipoAtual, ['cartao_credito', 'cartao_debito', 'tef'], true);
@endphp

@if ($this->showForm)
    <div class="erp-fpgto-modal" x-data
         x-on:keydown.escape.window="
            if ($wire.showBandeiraForm) { $wire.closeBandeiraForm(); }
            else if ($wire.showMaquininhaForm) { $wire.closeMaquininhaForm(); }
            else { $wire.closeForm(); }
         "
         x-on:keydown.window="
            if ($wire.showBandeiraForm || $wire.showMaquininhaForm) { return; }
            if ($event.key === 'F2') { $event.preventDefault(); $wire.saveFormaPagamento(); }
         ">
        <div class="erp-fpgto-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-fpgto-modal__dialog @if ($mostraBandeiras) erp-fpgto-modal__dialog--wide @endif" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>Formas de Pagamento</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body">
                <div class="erp-fpgto-modal__grid">
                    <div class="erp-fpgto-modal__col">
                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Código</span>
                            <input type="number" min="1" wire:model="form.codigo" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                        </label>
                        @error('form.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Nome</span>
                            <input type="text" wire:model="form.descricao" maxlength="120" class="erp-fpgto-field__input" autofocus>
                        </label>
                        @error('form.descricao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Conta de Destino</span>
                            <select wire:model="form.conta_destino_id" class="erp-fpgto-field__input">
                                <option value="">— Selecione —</option>
                                @foreach ($contas as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('form.conta_destino_id') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Tipo</span>
                            <select wire:model.live="form.tipo" class="erp-fpgto-field__input">
                                <option value="">— Selecione —</option>
                                @foreach ($tipos as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('form.tipo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Taxa Cartão</span>
                            <input type="number" step="0.01" min="0" wire:model="form.taxa_cartao" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                        </label>
                        @error('form.taxa_cartao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Prazo Cartão</span>
                            <input type="number" min="0" wire:model="form.prazo_cartao" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                        </label>
                        @error('form.prazo_cartao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Nº Máximo de Parcelas</span>
                            <input type="number" min="0" wire:model="form.max_parcelas" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                        </label>
                        @error('form.max_parcelas') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Intervalo entre Parcelas</span>
                            <input type="number" min="0" wire:model="form.intervalo_parcelas" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                        </label>
                        @error('form.intervalo_parcelas') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Atalho</span>
                            <input type="text" maxlength="5" wire:model="form.atalho" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                        </label>
                    </div>

                    <div class="erp-fpgto-modal__col erp-fpgto-modal__col--side">
                        <fieldset class="erp-fpgto-fieldset">
                            <legend>Tipo de Movimento</legend>
                            <div class="erp-fpgto-radio-grid">
                                @foreach ($movimentos as $value => $label)
                                    <label class="erp-fpgto-check">
                                        <input type="radio" wire:model="form.tipo_movimento" value="{{ $value }}"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="erp-fpgto-fieldset erp-fpgto-parcelas">
                            <legend>Tabelas de Prazo</legend>
                            <div class="erp-fpgto-parcelas__table">
                                <div class="erp-fpgto-parcelas__head">
                                    <span>Tabela</span>
                                    <span>Parcelas</span>
                                    <span>Prazos (dias)</span>
                                    <span></span>
                                </div>
                                <div class="erp-fpgto-parcelas__body">
                                    @forelse ($this->form['parcelas'] ?? [] as $i => $tabela)
                                        <div class="erp-fpgto-parcelas__row" wire:key="tabela-{{ $i }}"
                                             x-data="{ dias: @js((string) $tabela) }">
                                            <span class="erp-fpgto-parcelas__num">{{ $i + 1 }}</span>
                                            <span class="erp-fpgto-parcelas__qtd"
                                                  x-text="dias.split(',').filter(d => d.trim() !== '').length + 'x'">0x</span>
                                            <input type="text" wire:model="form.parcelas.{{ $i }}" x-on:input="dias = $event.target.value" placeholder="Ex.: 30,60,90" class="erp-fpgto-field__input erp-fpgto-parcelas__dias">
                                            <button type="button" class="erp-fpgto-parcelas__del" wire:click="removerParcela({{ $i }})" title="Remover">&times;</button>
                                        </div>
                                    @empty
                                        <div class="erp-fpgto-parcelas__empty">&lt;Não há dados para mostrar&gt;</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="erp-fpgto-parcelas__quick">
                                <input type="text" wire:model="prazosRapidos" placeholder="Ex.: 30,60,90"
                                       wire:keydown.enter.prevent="gerarPrazosRapidos"
                                       class="erp-fpgto-field__input erp-fpgto-parcelas__quick-input">
                                <button type="button" class="erp-fpgto-parcelas__btn erp-fpgto-parcelas__btn--apply" wire:click="gerarPrazosRapidos">Adicionar tabela</button>
                            </div>
                        </fieldset>
                    </div>
                </div>

                @if ($mostraBandeiras)
                    <div class="erp-fpgto-cartao-lookups">
                        <fieldset class="erp-fpgto-fieldset erp-fpgto-bandeiras">
                            <legend>Bandeiras de Cartão</legend>
                            <div class="erp-fpgto-bandeiras__toolbar">
                                <p class="erp-fpgto-bandeiras__hint">Cadastre aqui as bandeiras usadas no canhoto do PDV (POS).</p>
                                <button type="button" class="erp-fpgto-parcelas__btn erp-fpgto-parcelas__btn--apply" wire:click="createBandeira">
                                    + Nova bandeira
                                </button>
                            </div>
                            <div class="erp-fpgto-bandeiras__grid-wrap">
                                <table class="erp-pdv__grid erp-fpgto-bandeiras__grid">
                                    <thead>
                                        <tr>
                                            <th style="width:4.5rem;">Código</th>
                                            <th>Nome</th>
                                            <th style="width:4rem;" class="erp-pdv__grid-col-center">Ativo</th>
                                            <th style="width:7rem;" class="erp-pdv__grid-col-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->bandeirasLista as $bandeira)
                                            <tr wire:key="fpgto-bandeira-{{ $bandeira['id'] }}" class="erp-pdv__grid-row">
                                                <td>{{ $bandeira['codigo'] }}</td>
                                                <td>{{ $bandeira['nome'] }}</td>
                                                <td class="erp-pdv__grid-col-center">{{ $bandeira['ativo'] ? 'Sim' : 'Não' }}</td>
                                                <td class="erp-pdv__grid-col-center erp-bandeiras-modal__actions">
                                                    <button type="button" class="erp-bandeiras-modal__link" wire:click="editBandeira({{ $bandeira['id'] }})">Alterar</button>
                                                    <button
                                                        type="button"
                                                        class="erp-bandeiras-modal__link erp-bandeiras-modal__link--danger"
                                                        wire:click="deleteBandeira({{ $bandeira['id'] }})"
                                                        wire:confirm="Excluir a bandeira {{ $bandeira['nome'] }}?"
                                                    >Excluir</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="erp-pdv__grid-empty">
                                                <td colspan="4">Nenhuma bandeira cadastrada. Clique em Nova bandeira.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>

                        <fieldset class="erp-fpgto-fieldset erp-fpgto-bandeiras">
                            <legend>Maquininhas (POS)</legend>
                            <div class="erp-fpgto-bandeiras__toolbar">
                                <p class="erp-fpgto-bandeiras__hint">Cadastre as adquirentes/maquininhas usadas nas vendas com cartão.</p>
                                <button type="button" class="erp-fpgto-parcelas__btn erp-fpgto-parcelas__btn--apply" wire:click="createMaquininha">
                                    + Nova maquininha
                                </button>
                            </div>
                            <div class="erp-fpgto-bandeiras__grid-wrap">
                                <table class="erp-pdv__grid erp-fpgto-bandeiras__grid">
                                    <thead>
                                        <tr>
                                            <th style="width:4.5rem;">Código</th>
                                            <th>Nome</th>
                                            <th style="width:4rem;" class="erp-pdv__grid-col-center">Ativo</th>
                                            <th style="width:7rem;" class="erp-pdv__grid-col-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->maquininhasLista as $maquininha)
                                            <tr wire:key="fpgto-maquininha-{{ $maquininha['id'] }}" class="erp-pdv__grid-row">
                                                <td>{{ $maquininha['codigo'] }}</td>
                                                <td>{{ $maquininha['nome'] }}</td>
                                                <td class="erp-pdv__grid-col-center">{{ $maquininha['ativo'] ? 'Sim' : 'Não' }}</td>
                                                <td class="erp-pdv__grid-col-center erp-bandeiras-modal__actions">
                                                    <button type="button" class="erp-bandeiras-modal__link" wire:click="editMaquininha({{ $maquininha['id'] }})">Alterar</button>
                                                    <button
                                                        type="button"
                                                        class="erp-bandeiras-modal__link erp-bandeiras-modal__link--danger"
                                                        wire:click="deleteMaquininha({{ $maquininha['id'] }})"
                                                        wire:confirm="Excluir a maquininha {{ $maquininha['nome'] }}?"
                                                    >Excluir</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="erp-pdv__grid-empty">
                                                <td colspan="4">Nenhuma maquininha cadastrada. Clique em Nova maquininha.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                    </div>
                @endif

                <fieldset class="erp-fpgto-fieldset erp-fpgto-fieldset--acoes">
                    <legend>Ações</legend>
                    <div class="erp-fpgto-acoes-grid">
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.ativo"> Ativo</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.usa_tef"> Usa TEF</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.aparece_venda"> Aparece na Venda</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.usa_super_tef"> Usa SuperTEF</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.aparece_contas_receber"> Aparece no Contas à Receber</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.nfce"> NFC-e</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.disponivel_mobile"> Disponível Mobile</label>
                    </div>
                </fieldset>
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save" wire:click="saveFormaPagamento">
                    <span class="erp-fpgto-modal__btn-icon">✓</span> <kbd>F2</kbd> | Gravar
                </button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel" wire:click="closeForm">
                    <span class="erp-fpgto-modal__btn-icon">✕</span> <kbd>ESC</kbd> | Sair
                </button>
            </div>
        </div>
    </div>
@endif
