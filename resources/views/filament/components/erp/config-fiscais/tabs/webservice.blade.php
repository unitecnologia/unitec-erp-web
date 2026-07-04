<div class="erp-pcad-form erp-config-fiscais-form">
    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Ambiente</legend>
        <label class="erp-pcad__check">
            <input type="radio" wire:model="form.ambiente" value="0">
            <span>Produção</span>
        </label>
        <label class="erp-pcad__check">
            <input type="radio" wire:model="form.ambiente" value="1">
            <span>Homologação</span>
        </label>
    </fieldset>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="cfg-uf">UF de destino</label>
        <select id="cfg-uf" wire:model="form.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
            @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::ufOptions() as $uf)
                <option value="{{ $uf }}">{{ $uf }}</option>
            @endforeach
        </select>
    </div>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Retorno de envio NF-e</legend>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-aguardar">Aguardar (s)</label>
            <input id="cfg-aguardar" type="number" wire:model="form.aguardar" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-intervalo">Intervalo (s)</label>
            <input id="cfg-intervalo" type="number" wire:model="form.intervalo" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-tentativas">Tentativas</label>
            <input id="cfg-tentativas" type="number" wire:model="form.tentativas" class="erp-pcad-form__input erp-pcad-form__input--xs">
        </div>
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="form.ajustar_auto">
            <span>Ajustar automaticamente</span>
        </label>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Proxy WebService</legend>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-proxy-host">Host</label>
            <input id="cfg-proxy-host" type="text" wire:model="form.proxy_host" class="erp-pcad-form__input erp-pcad-form__input--grow">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-proxy-porta">Porta</label>
            <input id="cfg-proxy-porta" type="text" wire:model="form.proxy_porta" class="erp-pcad-form__input erp-pcad-form__input--xs">
        </div>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-proxy-user">Usuário</label>
            <input id="cfg-proxy-user" type="text" wire:model="form.proxy_usuario" class="erp-pcad-form__input erp-pcad-form__input--grow">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-proxy-pass">Senha</label>
            <input id="cfg-proxy-pass" type="password" wire:model="form.proxy_senha" class="erp-pcad-form__input erp-pcad-form__input--grow" autocomplete="off">
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Numeração NF-e</legend>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-numero">Próximo número</label>
            <input id="cfg-numero" type="number" wire:model="form.numero" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-serie">Série</label>
            <input id="cfg-serie" type="text" wire:model="form.serie" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-serie-nfe">Série NF-e</label>
            <input id="cfg-serie-nfe" type="number" wire:model="form.serie_nfe" class="erp-pcad-form__input erp-pcad-form__input--xs">
        </div>
    </fieldset>
</div>
