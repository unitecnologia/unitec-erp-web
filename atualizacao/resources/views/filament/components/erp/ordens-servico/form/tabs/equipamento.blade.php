<div class="erp-os-panel erp-os-panel--fill">
    <h3 class="erp-os-panel__title">Equipamento</h3>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-serie">Nº Série</label>
            <input id="os-serie" type="text" wire:model="numeroSerie" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao">Descrição</label>
            <input id="os-descricao" type="text" wire:model="descricao" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao2">Descrição 2</label>
            <input id="os-descricao2" type="text" wire:model="descricao2" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-marca">Marca</label>
            <input id="os-marca" type="text" wire:model="marca" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-modelo">Modelo</label>
            <input id="os-modelo" type="text" wire:model="modelo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-ano">Ano</label>
            <input id="os-ano" type="text" wire:model="ano" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-placa">Placa</label>
            <input id="os-placa" type="text" wire:model="placa" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-km">KM</label>
            <input id="os-km" type="text" wire:model="km" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <h3 class="erp-os-panel__title" style="margin-top:0.35rem;">Veículo (opcional)</h3>
    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-marca-v">Marca</label>
            <input id="os-marca-v" type="text" wire:model="marcaVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-modelo-v">Modelo</label>
            <input id="os-modelo-v" type="text" wire:model="modeloVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-placa-v">Placa</label>
            <input id="os-placa-v" type="text" wire:model="placaVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-cor-v">Cor</label>
            <input id="os-cor-v" type="text" wire:model="corVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-chassi-v">Chassi</label>
            <input id="os-chassi-v" type="text" wire:model="chassiVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>
</div>
