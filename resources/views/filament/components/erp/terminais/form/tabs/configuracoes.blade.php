@php

    use App\Support\Erp\Terminais\TerminalFormOptions;

@endphp



<div class="erp-pcad-form erp-terminais-form erp-terminais-form--config">

    <input type="hidden" wire:model="data.velocidade">

    <input type="hidden" wire:model="data.nome">

    <input type="hidden" wire:model="data.ip">



    <fieldset class="erp-pcad__group">

        <legend class="erp-pcad__group-title">Tipo de Impressora</legend>

        <div class="erp-terminais-form__printer-row">

            <div class="erp-terminais-form__radios">

                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="0"> Pedido A4</label>

                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="1"> ESC/POS</label>

                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="2"> Gráfico</label>

            </div>

            <div class="erp-terminais-form__inline-fields">

                <label class="erp-pcad-form__label" for="term-num-ini">Nº Inicial</label>

                <input id="term-num-ini" type="text" wire:model="data.numeracao_inicial" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">

                <label class="erp-pcad-form__label" for="term-nvias">Nº Vias</label>

                <input id="term-nvias" type="text" wire:model="data.nvias" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">

                <label class="erp-pcad-form__label" for="term-serie">Série</label>

                <input id="term-serie" type="text" wire:model="data.serie" class="erp-pcad-form__input erp-pcad-form__input--xs">

                <label class="erp-pcad__check erp-pcad__check--inline"><input type="checkbox" wire:model="data.usar_numero_inicial"> Usar Nº Inicial</label>

            </div>

        </div>

    </fieldset>



    @if (in_array((string) ($this->data['tipo_impressora'] ?? '0'), ['1', '0'], true))

        <fieldset class="erp-pcad__group">

            <legend class="erp-pcad__group-title">Configurações ESC/POS</legend>

            <div class="erp-pcad-form__row erp-terminais-form__escpos-row">

                <label class="erp-pcad-form__label" for="term-modelo">Modelo da Impressora</label>

                <select id="term-modelo" wire:model="data.modelo" class="erp-pcad-form__select">

                    @foreach (TerminalFormOptions::modelosEscPos() as $modelo)

                        <option value="{{ $modelo }}">{{ $modelo }}</option>

                    @endforeach

                </select>

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-porta">Caminho Padrão</label>

                <div class="erp-terminais-form__porta-wrap">

                    <select id="term-porta" wire:model="data.porta" class="erp-pcad-form__select erp-pcad-form__select--sm">

                        @foreach (TerminalFormOptions::portasImpressora() as $porta)

                            <option value="{{ $porta }}">{{ $porta }}</option>

                        @endforeach

                    </select>

                    <button type="button" wire:click="moduleStubListaImpressoras" class="erp-terminais-form__porta-btn" title="Atualizar portas">🖨</button>

                </div>

            </div>

        </fieldset>

    @endif



    <fieldset class="erp-pcad__group">

        <legend class="erp-pcad__group-title">Caminho Impressora Gráfico</legend>

        <div class="erp-pcad-form__row erp-terminais-form__path-row">

            <input id="term-impressora" type="text" wire:model="data.impressora_nome" class="erp-pcad-form__input">

            <button type="button" wire:click="moduleStubBrowseImpressora" class="erp-terminais-form__browse-btn" title="Localizar impressora">📁</button>

        </div>

    </fieldset>



    <fieldset class="erp-pcad__group">

        <legend class="erp-pcad__group-title">Tipo de Fechamento Caixa</legend>

        <div class="erp-terminais-form__radios">

            <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="0"> A4 - Padrão</label>

            <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="1"> A4 - Detalhado</label>

            <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="2"> Bobina - Detalhado</label>

            <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="3"> Bobina - Sintético</label>

        </div>

        @if (in_array((string) ($this->data['tipo_fechamento'] ?? '0'), ['0', 0], true))

            <label class="erp-pcad__check erp-terminais-form__meia-folha"><input type="checkbox" wire:model="data.meia_folha"> Modo Economia — Meia Folha</label>

        @endif

    </fieldset>



    <div class="erp-terminais-form__bottom">

        <fieldset class="erp-pcad__group erp-terminais-form__bottom-left">

            <legend class="erp-pcad__group-title">Tipo de Operação padrão</legend>

            <div class="erp-terminais-form__operacao-grid">

                <div class="erp-terminais-form__operacao-select">

                    <label class="erp-pcad-form__label" for="term-tipo-op">Selecione o tipo</label>

                    <select id="term-tipo-op" wire:model="data.tipo_operacao_padrao" class="erp-pcad-form__select">

                        @foreach (TerminalFormOptions::tiposOperacaoPadrao() as $value => $label)

                            <option value="{{ $value }}">{{ $label }}</option>

                        @endforeach

                    </select>

                </div>

                <ul class="erp-terminais-form__operacao-list">

                    @foreach (TerminalFormOptions::botoesOperacaoPadrao() as $field => $label)

                        <li>

                            <label class="erp-pcad__check">

                                <input type="checkbox" wire:model="data.{{ $field }}"> {{ $label }}

                            </label>

                        </li>

                    @endforeach

                </ul>

            </div>

        </fieldset>



        <div class="erp-terminais-form__bottom-right">

            <fieldset class="erp-pcad__group">

                <legend class="erp-pcad__group-title">Configurações do PDV</legend>

                <div class="erp-terminais-form__checks erp-terminais-form__checks--stack">

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usa_gaveta"> Usa Gaveta</label>

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.eh_caixa"> Usa controle de Caixa</label>

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.imprime"> Perguntar se quer Imprimir</label>

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.preview_impressao"> Preview da Impressão (Modo Gráfico)</label>

                </div>

            </fieldset>



            <fieldset class="erp-pcad__group">

                <legend class="erp-pcad__group-title">Quais abas exibir no PDV?</legend>

                <div class="erp-terminais-form__checks erp-terminais-form__checks--stack">

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.pdv"> Exibe — PDV</label>

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.delivery"> Exibe — Delivery</label>

                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.restaurante"> Exibe — Mesas</label>

                </div>

            </fieldset>

        </div>

    </div>

</div>

