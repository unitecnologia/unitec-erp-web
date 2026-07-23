<x-filament-panels::page>
    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => $produtoId ? 'Editar produto' : 'Produtos',
                'subtitle' => trim(($this->empresaNome() ?: '').' · '.$this->usuarioNome()),
                'eyebrow' => 'Ajuste rápido',
            ])

            @if ($produtoId)
                <section class="gestor-edit" aria-label="Edição do produto">
                    <button type="button" class="gestor-back" wire:click="voltar">← Voltar à busca</button>

                    <p class="gestor-prod-code">Cód. {{ $codigo }}</p>

                    <div class="gestor-edit__card">
                        <label class="gestor-field">
                            <span class="gestor-field__label">Nome</span>
                            <input type="text" class="gestor-field__input" wire:model="descricao" @disabled(! $this->canEditCadastro()) autocomplete="off" autocapitalize="characters">
                        </label>

                        <div class="gestor-prod-grid">
                            <label class="gestor-field">
                                <span class="gestor-field__label">Grupo</span>
                                <input type="text" class="gestor-field__input" wire:model="grupo" @disabled(! $this->canEditCadastro()) autocomplete="off" autocapitalize="characters">
                            </label>
                            <label class="gestor-field">
                                <span class="gestor-field__label">Marca</span>
                                <input type="text" class="gestor-field__input" wire:model="marca" @disabled(! $this->canEditCadastro()) autocomplete="off" autocapitalize="characters">
                            </label>
                            <label class="gestor-field">
                                <span class="gestor-field__label">Unidade</span>
                                <input type="text" class="gestor-field__input" wire:model="unidade" @disabled(! $this->canEditCadastro()) autocomplete="off" autocapitalize="characters">
                            </label>
                            <label class="gestor-field">
                                <span class="gestor-field__label">NCM</span>
                                <input
                                    type="text"
                                    class="gestor-field__input"
                                    wire:model="ncm"
                                    wire:keydown.enter.prevent="buscarNcm"
                                    wire:blur="buscarNcm"
                                    inputmode="numeric"
                                    maxlength="8"
                                    @disabled(! $this->canEditCadastro())
                                    autocomplete="off"
                                    placeholder="8 DÍGITOS + ENTER"
                                >
                            </label>
                        </div>

                        <label class="gestor-field">
                            <span class="gestor-field__label">Descrição NCM</span>
                            <input type="text" class="gestor-field__input" wire:model="ncmDescricao" readonly tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <div class="gestor-edit__card">
                        <p class="gestor-prod-section">Preços</p>
                        <div class="gestor-prod-grid gestor-prod-grid--prices">
                            <label class="gestor-field">
                                <span class="gestor-field__label">Varejo</span>
                                <div class="gestor-field__money">
                                    <span class="gestor-field__prefix">R$</span>
                                    <input type="text" class="gestor-field__input gestor-field__input--price gestor-field__input--price-sm" wire:model="precoVenda" data-mask="money-br" inputmode="decimal" @disabled(! $this->canEditPreco()) autocomplete="off">
                                </div>
                            </label>
                            <label class="gestor-field">
                                <span class="gestor-field__label">Atacado</span>
                                <div class="gestor-field__money">
                                    <span class="gestor-field__prefix">R$</span>
                                    <input type="text" class="gestor-field__input gestor-field__input--price gestor-field__input--price-sm" wire:model="precoAtacado" data-mask="money-br" inputmode="decimal" @disabled(! $this->canEditPreco()) autocomplete="off">
                                </div>
                            </label>
                            <label class="gestor-field">
                                <span class="gestor-field__label">Especial</span>
                                <div class="gestor-field__money">
                                    <span class="gestor-field__prefix">R$</span>
                                    <input type="text" class="gestor-field__input gestor-field__input--price gestor-field__input--price-sm" wire:model="precoEspecial" data-mask="money-br" inputmode="decimal" @disabled(! $this->canEditPreco()) autocomplete="off">
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="gestor-edit__card">
                        <p class="gestor-prod-section">Estoque</p>
                        <div class="gestor-prod-stock">
                            <div>
                                <span>Físico</span>
                                <strong>{{ $estoque }}</strong>
                            </div>
                            <div>
                                <span>Reservado</span>
                                <strong class="{{ $estoqueReservado > 0 ? 'is-neg' : '' }}">{{ $this->formatQtyPublic($estoqueReservado) }}</strong>
                            </div>
                            <div>
                                <span>Disponível</span>
                                <strong class="is-pos">{{ $this->formatQtyPublic($estoqueDisponivel) }}</strong>
                            </div>
                            <div>
                                <span>Mínimo</span>
                                <strong>{{ $estoqueMinimo }}</strong>
                            </div>
                        </div>

                        <label class="gestor-field" style="margin-top: 0.75rem; margin-bottom: 0;">
                            <span class="gestor-field__label">Ajustar estoque físico</span>
                            <input type="text" class="gestor-field__input" wire:model="estoque" inputmode="decimal" @disabled(! $this->canEditEstoque()) autocomplete="off">
                        </label>
                        <p class="gestor-note" style="margin-top: 0.45rem; margin-bottom: 0;">
                            Reservado = pedidos FV pendentes. Disponível = físico − reservado.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="gestor-save"
                        wire:click="salvar"
                        wire:loading.attr="disabled"
                        @disabled(! $this->canEditCadastro() && ! $this->canEditPreco() && ! $this->canEditEstoque())
                    >
                        <span wire:loading.remove wire:target="salvar">Salvar alterações</span>
                        <span wire:loading wire:target="salvar">Salvando…</span>
                    </button>
                </section>
            @else
                <section class="gestor-search" aria-label="Busca de produtos">
                    <label class="gestor-field">
                        <span class="gestor-field__label">Buscar produto</span>
                        <input type="search" class="gestor-field__input gestor-uppercase" wire:model.live.debounce.300ms="busca" placeholder="NOME, CÓDIGO OU BARRAS" autocomplete="off" autocapitalize="characters" enterkeyhint="search">
                    </label>

                    <p class="gestor-note">
                        @if (mb_strlen(trim($busca)) < 2)
                            Digite ao menos 2 caracteres.
                        @else
                            {{ count($this->resultados()) }} resultado(s)
                        @endif
                    </p>

                    <ul class="gestor-list" role="list">
                        @foreach ($this->resultados() as $item)
                            <li>
                                <button type="button" class="gestor-item" wire:click="selecionar({{ $item['id'] }})">
                                    <div class="gestor-item__main">
                                        <span class="gestor-item__name">{{ $item['descricao'] }}</span>
                                        <span class="gestor-item__code">
                                            Cód. {{ $item['codigo'] }}
                                            @if ($item['grupo'] !== '')
                                                · {{ $item['grupo'] }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="gestor-item__side">
                                        <span class="gestor-item__price">R$ {{ number_format($item['preco_venda'], 2, ',', '.') }}</span>
                                        <span class="gestor-item__stock">Est. {{ rtrim(rtrim(number_format($item['estoque'], 3, ',', '.'), '0'), ',') }}</span>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
        @include('filament.gestor.partials.bottom-nav')
    </div>
</x-filament-panels::page>
