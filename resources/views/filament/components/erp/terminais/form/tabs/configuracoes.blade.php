@php
    use App\Support\Erp\Terminais\TerminalFormOptions;
@endphp

<div class="erp-pcad-form erp-terminais-form erp-terminais-form--config">
    <input type="hidden" wire:model="data.velocidade">
    <input type="hidden" wire:model="data.empresa_id">
    <input type="hidden" wire:model="data.ativo">
    <input type="hidden" wire:model="data.ip">

    <fieldset class="erp-pcad__group erp-terminais-form__ativo-bar">
        <legend class="erp-pcad__group-title">{{ $this->terminalConfigGrupoTitulo() }}</legend>
        <div class="erp-terminais-form__ativo-grid">
            <div class="erp-terminais-form__field erp-terminais-form__field--nome">
                <label class="erp-pcad-form__label" for="term-nome-pdv">Nome no PDV</label>
                <input
                    id="term-nome-pdv"
                    type="text"
                    wire:model.live="data.nome"
                    class="erp-pcad-form__input"
                    placeholder="Ex.: pdv1"
                    title="Mesmo valor do campo PDV/Terminal no caixa offline"
                    autocomplete="off"
                >
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--narrow">
                <label class="erp-pcad-form__label" for="term-num-logico">Nº lógico</label>
                <input
                    id="term-num-logico"
                    type="text"
                    wire:model="data.numero_logico_terminal"
                    data-mask="integer"
                    class="erp-pcad-form__input"
                    placeholder="1"
                    title="Use este número no campo PDV/Terminal do caixa offline"
                >
            </div>
            <p class="erp-terminais-form__hint erp-terminais-form__hint--inline">
                Liberar/bloquear na grade (flag <strong>Ativo</strong>). Detalhes no <strong>olhinho</strong>.
                @if ($this->terminalConfigEhPdvOffline())
                    Offline: <strong>Nome</strong> ou <strong>nº lógico</strong>.
                @endif
            </p>
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Configurações de Impressão</legend>
        @php($printerReadonly = $this->terminalConfigEhPdvOffline())
        <div class="erp-terminais-form__printer-compact @if ($printerReadonly) erp-terminais-form__printer--readonly @endif">
            <div class="erp-terminais-form__radios erp-terminais-form__radios--2x2">
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="0" @disabled($printerReadonly)> Pedido A4</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="1" @disabled($printerReadonly)> ESC/POS</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="2" @disabled($printerReadonly)> Gráfico</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="3" @disabled($printerReadonly)> NFC-e - A4</label>
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--nvias">
                <label class="erp-pcad-form__label" for="term-nvias">Nº Vias</label>
                <input id="term-nvias" type="text" wire:model="data.nvias" data-mask="integer" class="erp-pcad-form__input" @disabled($printerReadonly)>
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--modelo">
                <label class="erp-pcad-form__label" for="term-modelo">Modelo</label>
                <select id="term-modelo" wire:model="data.modelo" class="erp-pcad-form__select" @disabled($printerReadonly)>
                    @foreach (TerminalFormOptions::modelosEscPos() as $modelo)
                        <option value="{{ $modelo }}">{{ $modelo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--caminho">
                <label class="erp-pcad-form__label" for="term-porta">Caminho</label>
                <div class="erp-terminais-form__porta-wrap">
                    <select id="term-porta" wire:model.live="data.porta" class="erp-pcad-form__select" @disabled($printerReadonly)>
                        @if ($printerReadonly)
                            <option value="">—</option>
                        @endif
                        @foreach (($this->portasImpressoraLista ?: TerminalFormOptions::portasImpressora()) as $porta)
                            <option value="{{ $porta }}">{{ $porta }}</option>
                        @endforeach
                    </select>
                    @unless ($printerReadonly)
                        <button type="button" wire:click="moduleStubListaImpressoras" class="erp-terminais-form__porta-btn" title="Listar impressoras do Windows (RAW)">🖨</button>
                    @endunless
                </div>
            </div>
            <input type="hidden" wire:model="data.impressora_nome">
        </div>
        @if ($printerReadonly)
            <p class="erp-terminais-form__hint">
                Impressora deste caixa: altere na <strong>engrenagem do PDV offline</strong>. Estes campos atualizam na carga.
            </p>
        @else
            <p class="erp-terminais-form__hint">
                🖨 lista as impressoras deste Windows como <strong>RAW:Nome</strong> (ex.: RAW:POS-80C). A impressão silenciosa usa o Device Service do PC.
            </p>
        @endif
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Tipo de Fechamento Caixa</legend>
        <div class="erp-terminais-form__fechamento-row">
            <div class="erp-terminais-form__radios">
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="0"> A4 - Padrão</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="1"> A4 - Detalhado</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="2"> Bobina - Detalhado</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="3"> Bobina - Sintético</label>
            </div>
        </div>
    </fieldset>

    <div class="erp-terminais-form__bottom">
        <fieldset class="erp-pcad__group erp-terminais-form__bottom-left">
            <legend class="erp-pcad__group-title">Tipo de Operação padrão</legend>
            <div class="erp-terminais-form__operacao-grid">
                <div class="erp-terminais-form__field">
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
                <div class="erp-terminais-form__checks erp-terminais-form__checks--compact">
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usa_gaveta"> Usa Gaveta</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.eh_caixa"> Controle de Caixa</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.imprime"> Perguntar Imprimir</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.preview_impressao"> Preview Gráfico</label>
                </div>
            </fieldset>

            <fieldset class="erp-pcad__group">
                <legend class="erp-pcad__group-title">Abas no PDV</legend>
                <div class="erp-terminais-form__checks erp-terminais-form__checks--compact">
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.pdv"> PDV</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.delivery"> Delivery</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.restaurante"> Mesas</label>
                </div>
            </fieldset>
        </div>
    </div>
</div>
