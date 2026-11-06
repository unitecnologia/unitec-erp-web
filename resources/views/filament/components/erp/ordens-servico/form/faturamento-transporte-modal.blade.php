@if ($this->osTransporteModalOpen)
@teleport('body')
<div
    class="erp-pdv-modal erp-pdv-modal--centered erp-fv-transporte-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-os-transporte-title"
    wire:keydown.escape.window="fecharOsTransporteModal"
    x-data
    x-on:erp-os-focus-transporte-codigo.window="$nextTick(() => document.getElementById('erp-os-transporte-codigo')?.focus())"
    x-on:erp-os-scroll-transporte-sugestao.window="
        $nextTick(() => document.getElementById('erp-os-transporte-sug-' + ($event.detail.index ?? 0))?.scrollIntoView({ block: 'nearest' }))
    "
>
    <div class="erp-pdv-modal__backdrop" wire:click="fecharOsTransporteModal"></div>
    <div class="erp-pdv-modal__window erp-fv-transporte-modal__window" wire:click.stop>
        <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
            <h2 id="erp-os-transporte-title">Transportadora / Volumes</h2>
            <button type="button" class="erp-pdv-modal__close" wire:click="fecharOsTransporteModal" title="Fechar">✕</button>
        </header>

        <div class="erp-pdv-modal__body erp-fv-transporte-modal__body">
            <div class="erp-fv-transporte__row">
                <div class="erp-fv-transporte__field erp-fv-transporte__field--transportador" @if ($this->osTransportadoraSugestoesOpen && $this->osTransportadoraSugestoes !== []) data-lookup-open="1" @endif>
                    <span class="erp-fv-transporte__label">Código</span>
                    <div class="erp-fv-transporte__transportador">
                        <input
                            id="erp-os-transporte-codigo"
                            type="text"
                            wire:model.live.debounce.250ms="osTransportadoraCodigo"
                            wire:keydown.enter.prevent="resolverOsTransportadoraPorCodigo"
                            class="erp-fv-transporte__input erp-fv-transporte__codigo"
                            inputmode="numeric"
                            autocomplete="off"
                            title="Código do transportador"
                        >
                        <div class="erp-fv-transporte__nome-wrap">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="osTransportadoraBusca"
                                wire:keydown.enter.prevent="confirmarOsTransportadoraBusca"
                                wire:keydown.escape.prevent="fecharOsSugestoesTransportadora"
                                wire:keydown.arrow-up.prevent="moverOsSugestaoTransportadora(-1)"
                                wire:keydown.arrow-down.prevent="moverOsSugestaoTransportadora(1)"
                                class="erp-fv-transporte__input erp-fv-transporte__nome"
                                autocomplete="off"
                                placeholder="Nome ou CNPJ — Enter"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->osTransportadoraSugestoesOpen && $this->osTransportadoraSugestoes !== [] ? 'true' : 'false' }}"
                                aria-controls="erp-os-transporte-sugestoes"
                            >
                            @if ($this->osTransportadoraSugestoesOpen && $this->osTransportadoraSugestoes !== [])
                                <ul id="erp-os-transporte-sugestoes" class="erp-fv-transporte__suggest" role="listbox" aria-label="Transportadoras encontradas">
                                    @foreach ($this->osTransportadoraSugestoes as $index => $sug)
                                        <li wire:key="os-transp-sug-{{ $sug['id'] }}" role="presentation">
                                            <button
                                                type="button"
                                                id="erp-os-transporte-sug-{{ $index }}"
                                                role="option"
                                                aria-selected="{{ (int) $this->osSelectedTransportadoraSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                                wire:click="selecionarOsTransportadora({{ $sug['id'] }})"
                                                @class(['is-selected' => (int) $this->osSelectedTransportadoraSugestaoIndex === (int) $index])
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
                    <select wire:model="osTipoFrete" class="erp-fv-transporte__input erp-fv-transporte__select">
                        @foreach ($this->osFretePorContaOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--placa">
                    <span class="erp-fv-transporte__label">Placa transporte</span>
                    <input type="text" wire:model="osPlacaTransporte" maxlength="8" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--uf">
                    <span class="erp-fv-transporte__label">UF</span>
                    <select wire:model="osUfPlacaTransporte" class="erp-fv-transporte__input erp-fv-transporte__select">
                        <option value="">—</option>
                        @foreach ($this->osUfPlacaTransporteOptions as $uf => $ufLabel)
                            <option value="{{ $uf }}">{{ $ufLabel }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="erp-fv-transporte__row erp-fv-transporte__row--volumes">
                <label class="erp-fv-transporte__field erp-fv-transporte__field--qtd">
                    <span class="erp-fv-transporte__label">Qtde Volume</span>
                    <input type="text" wire:model="osQvol" inputmode="numeric" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--especie">
                    <span class="erp-fv-transporte__label">Espécie</span>
                    <input type="text" wire:model="osEspecie" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--peso">
                    <span class="erp-fv-transporte__label">Peso Bruto</span>
                    <input type="text" wire:model="osPesoB" inputmode="decimal" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--peso">
                    <span class="erp-fv-transporte__label">Peso Líquido</span>
                    <input type="text" wire:model="osPesoL" inputmode="decimal" class="erp-fv-transporte__input erp-fv-transporte__num" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--marca">
                    <span class="erp-fv-transporte__label">Marca</span>
                    <input type="text" wire:model="osMarcaTransporte" data-erp-uppercase class="erp-fv-transporte__input" autocomplete="off">
                </label>

                <label class="erp-fv-transporte__field erp-fv-transporte__field--numero">
                    <span class="erp-fv-transporte__label">Número</span>
                    <input type="text" wire:model="osNvol" class="erp-fv-transporte__input" autocomplete="off">
                </label>
            </div>
        </div>

        <footer class="erp-pdv-modal__footer erp-fv-transporte-modal__footer">
            <button type="button" class="erp-pdv-modal__btn" wire:click="limparOsTransporte">Limpar</button>
            <button type="button" class="erp-pdv-modal__btn" wire:click="fecharOsTransporteModal">Cancelar</button>
            <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="confirmarOsTransporteModal">
                Confirmar
            </button>
        </footer>
    </div>
</div>
@endteleport
@endif
