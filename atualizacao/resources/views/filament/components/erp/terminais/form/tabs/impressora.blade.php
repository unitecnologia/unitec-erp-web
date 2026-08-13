<div class="erp-pcad-form erp-terminais-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-nome">Nome do Terminal *</label>
        <input id="term-nome" type="text" wire:model="data.nome" class="erp-pcad-form__input">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-ip">IP</label>
        <input id="term-ip" type="text" wire:model="data.ip" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-fab">Fabricante</label>
        <input id="term-fab" type="text" wire:model="data.fab_impressora" class="erp-pcad-form__input erp-pcad-form__input--sm">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-modelo">Modelo</label>
        <input id="term-modelo" type="text" wire:model="data.modelo" class="erp-pcad-form__input">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-porta">Porta *</label>
        <input id="term-porta" type="text" wire:model="data.porta" class="erp-pcad-form__input">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-vel">Velocidade *</label>
        <select id="term-vel" wire:model="data.velocidade" class="erp-pcad-form__select erp-pcad-form__select--sm">
            <option value="">Selecione...</option>
            @foreach ([9600, 19200, 38400, 57600, 115200] as $speed)
                <option value="{{ $speed }}">{{ $speed }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-nvias">Vias</label>
        <input id="term-nvias" type="text" wire:model="data.nvias" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-serie">Série</label>
        <input id="term-serie" type="text" wire:model="data.serie" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-impressora">Impressora Windows</label>
        <input id="term-impressora" type="text" wire:model="data.impressora_nome" class="erp-pcad-form__input">
    </div>

    <fieldset class="erp-terminais-form__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.imprime"> Imprime cupom</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usa_gaveta"> Usa gaveta</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usar_numero_inicial"> Usar numeração inicial</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.meia_folha"> Meia folha</label>
    </fieldset>
</div>
