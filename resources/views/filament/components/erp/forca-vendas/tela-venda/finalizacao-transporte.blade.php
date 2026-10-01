@if ($this->fvTransporteModalOpen)
@teleport('body')
<div
    class="erp-pdv-modal erp-pdv-modal--centered erp-fv-transporte-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-fv-transporte-title"
    wire:keydown.escape.window="fecharFvTransporteModal"
    x-data
    x-on:erp-fv-focus-transporte-codigo.window="$nextTick(() => document.getElementById('erp-fv-transporte-codigo')?.focus())"
    x-on:erp-fv-scroll-transporte-sugestao.window="
        $nextTick(() => document.getElementById('erp-fv-transporte-sug-' + ($event.detail.index ?? 0))?.scrollIntoView({ block: 'nearest' }))
    "
>
    <div class="erp-pdv-modal__backdrop" wire:click="fecharFvTransporteModal"></div>
    <div class="erp-pdv-modal__window erp-fv-transporte-modal__window" wire:click.stop>
        <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
            <h2 id="erp-fv-transporte-title">Transportadora / Volumes</h2>
            <button type="button" class="erp-pdv-modal__close" wire:click="fecharFvTransporteModal" title="Fechar">✕</button>
        </header>

        <div class="erp-pdv-modal__body erp-fv-transporte-modal__body">
            <div class="erp-fv-transporte__row">
                <div class="erp-fv-transporte__field erp-fv-transporte__field--transportador" @if ($this->fvTransportadoraSugestoesOpen && $this->fvTransportadoraSugestoes !== []) data-lookup-open="1" @endif>
                    <span class="erp-fv-transporte__label">Código</span>
                    <div class="erp-fv-transporte__transportador">
                        <input
                            id="erp-fv-transporte-codigo"
                            type="text"
                            wire:model.live.debounce.250ms="fvTransportadoraCodigo"
                            wire:keydown.enter.prevent="resolverFvTransportadoraPorCodigo"
                            class="erp-fv-transporte__input erp-fv-transporte__codigo"
                            inputmode="numeric"
                            autocomplete="off"
                            title="Código do transportador"
                        >
                        <div class="erp-fv-transporte__nome-wrap">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="fvTransportadoraBusca"
                                wire:keydown.enter.prevent="confirmarFvTransportadoraBusca"
                                wire:keydown.escape.prevent="fecharFvSugestoesTransportadora"
                                wire:keydown.arrow-up.prevent="moverFvSugestaoTransportadora(-1)"
                                wire:keydown.arrow-down.prevent="moverFvSugestaoTransportadora(1)"
                                class="erp-fv-transporte__input erp-fv-transporte__nome"
                                autocomplete="off"
                                placeholder="Nome ou CNPJ — Enter"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->fvTransportadoraSugestoesOpen && $this->fvTransportadoraSugestoes !== [] ? 'true' : 'false' }}"
                                aria-controls="erp-fv-transporte-sugestoes"
                            >
                            @if ($this->fvTransportadoraSugestoesOpen && $this->fvTransportadoraSugestoes !== [])
                                <ul id="erp-fv-transporte-sugestoes" class="erp-fv-transporte__suggest" role="listbox" aria-label="Transportadoras encontradas">
                                    @foreach ($this->fvTransportadoraSugestoes as $index => $sug)
                                        <li wire:key="fv-transp-sug-{{ $sug['id'] }}" role="presentation">
                                            <button
                                                type="button"
                                                id="erp-fv-transporte-sug-{{ $index }}"
                                                role="option"
                                                aria-selected="{{ (int) $this->fvSelectedTransportadoraSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                wire:click="selecionarFvTransportadora({{ $sug['id'] }})"
                                                @class(['is-selected' => (int) $this->fvSelectedTransportadoraSugestaoIndex === (int) $index])
                                            >
                                                <span class="erp-fv-transporte__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                                <span class="erp-fv-transporte__suggest-nome">{{ $sug['nome'] }}</span>
                                                @if (filled($sug['cpf_cnpj'] ?? null))
                                                    <span @class([
                                                        'erp-fv-transporte__suggest-doc',
                                                        'is-cnpj' => ($sug['doc_tipo'] ?? '') === 'cnpj',
                                                        'is-cpf' => ($sug['doc_tipo'] ?? '') === 'cpf',
                                                    ])>{{ $sug['cpf_cnpj'] }}</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--frete">
                    <span class="erp-fv-transporte__label">Frete por Conta</span>
                    <select wire:model="fvTipoFrete" class="erp-fv-transporte__input erp-fv-transporte__select">
                        @foreach ($this->fvFretePorContaOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--placa">
                    <span class="erp-fv-transporte__label">Placa veículo</span>
                    <input type="text" wire:model="fvPlaca" maxlength="8" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--uf">
                    <span class="erp-fv-transporte__label">UF</span>
                    <select wire:model="fvUfPlaca" class="erp-fv-transporte__input erp-fv-transporte__select">
                        <option value="">—</option>
                        @foreach ($this->fvUfPlacaOptions as $uf => $ufLabel)
                            <option value="{{ $uf }}">{{ $ufLabel }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="erp-fv-transporte__row erp-fv-transporte__row--volumes">
                <label class="erp-fv-transporte__field erp-fv-transporte__field--qtd">
                    <span class="erp-fv-transporte__label">Qtde Volume</span>
                    <input type="text" wire:model="fvQvol" inputmode="numeric" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--especie">
                    <span class="erp-fv-transporte__label">Espécie</span>
                    <input type="text" wire:model="fvEspecie" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--peso">
                    <span class="erp-fv-transporte__label">Peso Bruto</span>
                    <input type="text" wire:model="fvPesoB" inputmode="decimal" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--peso">
                    <span class="erp-fv-transporte__label">Peso Líquido</span>
                    <input type="text" wire:model="fvPesoL" inputmode="decimal" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--marca">
                    <span class="erp-fv-transporte__label">Marca</span>
                    <input type="text" wire:model="fvMarca" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--numero">
                    <span class="erp-fv-transporte__label">Número</span>
                    <input type="text" wire:model="fvNvol" class="erp-fv-transporte__input" autocomplete="off">
                </label>
            </div>
        </div>

        <footer class="erp-pdv-modal__footer erp-fv-transporte-modal__footer">
            <button type="button" class="erp-pdv-modal__btn" wire:click="limparFvTransporte">Limpar</button>
            <button type="button" class="erp-pdv-modal__btn" wire:click="fecharFvTransporteModal">Cancelar</button>
            <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="confirmarFvTransporteModal">
                Confirmar
            </button>
        </footer>
    </div>
</div>
@endteleport
@endif
