@php
    $tipos = \App\Models\FormaPagamento::tipoLabels();
    $movimentos = \App\Models\FormaPagamento::tipoMovimentoLabels();
    $movimentoHints = \App\Models\FormaPagamento::tipoMovimentoHints();
    $modosPrazo = \App\Models\FormaPagamento::modoPrazoLabels();
    $modoPrazoHints = \App\Models\FormaPagamento::modoPrazoHints();
    $contas = $this->contaDestinoOptions();
    $tipoAtual = (string) ($this->form['tipo'] ?? '');
    $ui = \App\Models\FormaPagamento::uiCamposPorTipo($tipoAtual);
    $contaDestinoId = (int) ($this->form['conta_destino_id'] ?? 0);
    $mostraBandeiras = $ui['bandeiras'];
    $mostraParcelamentoFinanceiro = $ui['taxa_cartao'] || $ui['prazo_cartao'] || $ui['max_parcelas'] || $ui['intervalo_parcelas'];
    $modoPrazoAtual = \App\Models\FormaPagamento::normalizeModoPrazo(
        (string) ($this->form['modo_prazo'] ?? \App\Models\FormaPagamento::MODO_PRAZO_TABELA)
    );
    // Só carnê aplica exclusão mútua; cartão/canhoto permanece livre.
    $financeiroCamposBloqueados = $ui['modo_prazo'] && $modoPrazoAtual === \App\Models\FormaPagamento::MODO_PRAZO_TABELA;
    $tabelasPrazoBloqueadas = $ui['modo_prazo'] && $modoPrazoAtual === \App\Models\FormaPagamento::MODO_PRAZO_FINANCEIRO;
    $avisos = $this->formaPagamentoAvisos();
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
                        <fieldset class="erp-fpgto-fieldset">
                            <legend>Identificação</legend>

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
                                <span class="erp-fpgto-field__label">Atalho</span>
                                <input type="text" maxlength="5" wire:model="form.atalho" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                            </label>
                        </fieldset>

                        <fieldset class="erp-fpgto-fieldset">
                            <legend>Financeiro</legend>

                            @if ($ui['conta_destino'])
                                <label class="erp-fpgto-field" title="Quando configurada, esta conta pode prevalecer sobre o caixa padrão do usuário/vendedor.">
                                    <span class="erp-fpgto-field__label">Conta de Destino</span>
                                    <select wire:model.live="form.conta_destino_id" class="erp-fpgto-field__input">
                                        <option value="">— Selecione —</option>
                                        @foreach ($contas as $id => $nome)
                                            <option value="{{ $id }}">{{ $nome }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @error('form.conta_destino_id') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                                @if ($contaDestinoId > 0)
                                    <p class="erp-fpgto-hint erp-fpgto-hint--info">
                                        Quando configurada, esta conta pode prevalecer sobre o caixa padrão do usuário/vendedor.
                                    </p>
                                @endif
                            @endif

                            @if ($mostraParcelamentoFinanceiro)
                                @if ($ui['taxa_cartao'])
                                    <label class="erp-fpgto-field">
                                        <span class="erp-fpgto-field__label">Taxa Cartão</span>
                                        <input type="number" step="0.01" min="0" wire:model="form.taxa_cartao" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                                    </label>
                                    @error('form.taxa_cartao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                                @endif

                                @if ($ui['prazo_cartao'])
                                    <label class="erp-fpgto-field">
                                        <span class="erp-fpgto-field__label">Prazo Cartão</span>
                                        <input type="number" min="0" wire:model="form.prazo_cartao" class="erp-fpgto-field__input erp-fpgto-field__input--num">
                                    </label>
                                    @error('form.prazo_cartao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                                @endif

                                @if ($ui['modo_prazo'])
                                    <div class="erp-fpgto-modo-prazo">
                                        <span class="erp-fpgto-field__label">Forma de definir o prazo</span>
                                        <div class="erp-fpgto-radio-grid erp-fpgto-radio-grid--inline">
                                            @foreach ($modosPrazo as $value => $label)
                                                <label class="erp-fpgto-check" title="{{ $modoPrazoHints[$value] ?? '' }}">
                                                    <input type="radio" wire:model.live="form.modo_prazo" value="{{ $value }}">
                                                    <span>{{ $label }}</span>
                                                    <span class="erp-fpgto-info" title="{{ $modoPrazoHints[$value] ?? '' }}" aria-label="Ajuda">ⓘ</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('form.modo_prazo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                <div class="erp-fpgto-modo-bloco @if ($financeiroCamposBloqueados) erp-fpgto-modo-bloco--disabled @endif"
                                     @if ($financeiroCamposBloqueados) aria-disabled="true" @endif>
                                    @if ($ui['max_parcelas'])
                                        <label class="erp-fpgto-field">
                                            <span class="erp-fpgto-field__label">Nº Máximo de Parcelas</span>
                                            <input type="number" min="0" wire:model="form.max_parcelas"
                                                   class="erp-fpgto-field__input erp-fpgto-field__input--num"
                                                   @if ($financeiroCamposBloqueados) disabled tabindex="-1" @endif>
                                        </label>
                                        @error('form.max_parcelas') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                                    @endif

                                    @if ($ui['intervalo_parcelas'])
                                        <label class="erp-fpgto-field">
                                            <span class="erp-fpgto-field__label">Intervalo entre Parcelas</span>
                                            <input type="number" min="0" wire:model="form.intervalo_parcelas"
                                                   class="erp-fpgto-field__input erp-fpgto-field__input--num"
                                                   @if ($financeiroCamposBloqueados) disabled tabindex="-1" @endif>
                                        </label>
                                        @error('form.intervalo_parcelas') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            @endif
                        </fieldset>
                    </div>

                    <div class="erp-fpgto-modal__col erp-fpgto-modal__col--side">
                        <fieldset class="erp-fpgto-fieldset">
                            <legend title="Define se a forma gera Caixa, Contas a Receber ou nenhum lançamento.">Tipo de Movimento</legend>
                            <div class="erp-fpgto-radio-grid">
                                @foreach ($movimentos as $value => $label)
                                    <label class="erp-fpgto-check" title="{{ $movimentoHints[$value] ?? '' }}">
                                        <input type="radio" wire:model.live="form.tipo_movimento" value="{{ $value }}"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('form.tipo_movimento') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                            @if ($avisos !== [])
                                <div class="erp-fpgto-avisos" role="status">
                                    @foreach ($avisos as $aviso)
                                        <p class="erp-fpgto-hint erp-fpgto-hint--warn">{{ $aviso }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </fieldset>

                        @if ($ui['tabelas_prazo'])
                            <fieldset class="erp-fpgto-fieldset erp-fpgto-parcelas @if ($tabelasPrazoBloqueadas) erp-fpgto-modo-bloco--disabled @endif"
                                      @if ($tabelasPrazoBloqueadas) aria-disabled="true" @endif>
                                <legend>Tabelas de Prazo</legend>
                                <div class="erp-fpgto-parcelas__table" @if ($tabelasPrazoBloqueadas) inert @endif>
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
                                <div class="erp-fpgto-parcelas__quick" @if ($tabelasPrazoBloqueadas) inert @endif>
                                    <input type="text" wire:model="prazosRapidos" placeholder="Ex.: 30,60,90"
                                           wire:keydown.enter.prevent="gerarPrazosRapidos"
                                           class="erp-fpgto-field__input erp-fpgto-parcelas__quick-input"
                                           @if ($tabelasPrazoBloqueadas) disabled tabindex="-1" @endif>
                                    <button type="button" class="erp-fpgto-parcelas__btn erp-fpgto-parcelas__btn--apply"
                                            wire:click="gerarPrazosRapidos"
                                            @if ($tabelasPrazoBloqueadas) disabled tabindex="-1" @endif>Adicionar tabela</button>
                                </div>
                            </fieldset>
                        @endif
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
                    <legend>Uso</legend>
                    <div class="erp-fpgto-acoes-grid">
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.ativo"> Ativo</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model.live="form.disponivel_mobile"> Disponível Mobile</label>
                        <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.aparece_venda"> Aparece na Venda</label>
                        @if ($ui['aparece_contas_receber'])
                            <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.aparece_contas_receber"> Aparece no Contas à Receber</label>
                        @endif
                        @if ($ui['usa_tef'])
                            <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.usa_tef"> Usa TEF</label>
                        @endif
                        @if ($ui['usa_super_tef'])
                            <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.usa_super_tef"> Usa SuperTEF</label>
                        @endif
                        @if ($ui['nfce'])
                            <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.nfce"> NFC-e</label>
                        @endif
                        @if ($ui['gerar_qrcode_pdv'])
                            <label class="erp-fpgto-check"><input type="checkbox" wire:model="form.gerar_qrcode_pdv"> Gerar QR Code na tela do PDV</label>
                        @endif
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
