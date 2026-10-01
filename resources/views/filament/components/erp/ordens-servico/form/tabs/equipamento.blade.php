<div class="erp-os-panel erp-os-panel--fill">
    <h3 class="erp-os-panel__title">Equipamento</h3>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao">Equipamento / Marca</label>
            <input id="os-descricao" type="text" wire:model="descricao" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-modelo">Modelo</label>
            <input id="os-modelo" type="text" wire:model="modelo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-ano">Ano</label>
            <input id="os-ano" type="text" wire:model="ano" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-serie">Nº Série / IMEI</label>
            <input id="os-serie" type="text" wire:model="numeroSerie" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-placa">Placa</label>
            <input id="os-placa" type="text" wire:model="placa" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-km">KM</label>
            <input id="os-km" type="text" wire:model="km" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-cor">Cor</label>
            <input id="os-cor" type="text" wire:model="corVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-chassi">Chassi</label>
            <input id="os-chassi" type="text" wire:model="chassiVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao2">Descrição / Complemento</label>
            <input id="os-descricao2" type="text" wire:model="descricao2" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>
</div>
