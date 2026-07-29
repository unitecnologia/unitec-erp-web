@php
    $fields = [
        'banco' => 'Banco',
        'layout' => 'Layout / CNAB',
        'carteira' => 'Carteira',
        'tipo_carteira' => 'Tipo carteira',
        'especie_docto' => 'Espécie documento',
        'especie_moeda' => 'Espécie moeda',
        'aceite' => 'Aceite',
        'tipo_documento' => 'Tipo documento',
        'cnab_versao' => 'Versão CNAB',
        'local_pagamento' => 'Local de pagamento',
        'ben_agencia' => 'Agência',
        'ben_agencia_dv' => 'DV agência',
        'ben_conta' => 'Conta',
        'ben_conta_dv' => 'DV conta',
        'ben_convenio' => 'Convênio',
        'ben_modalidade' => 'Modalidade',
        'ben_cod_cedente' => 'Cód. cedente',
        'nosso_numero' => 'Nosso número (seq.)',
        'path_remessa' => 'Pasta remessa',
        'path_retorno' => 'Pasta retorno',
        'webservice_client_id' => 'Client ID (API)',
        'webservice_client_secret' => 'Client Secret (API)',
        'webservice_key_user' => 'Key User (API)',
    ];
@endphp

<div class="erp-unidades" wire:ignore.self>
    <div class="erp-unidades__locate" style="align-items:center; gap:1rem;">
        <span class="erp-unidades__locate-label">Boleto — Configuração</span>
        <label class="erp-pcad__check" style="margin:0;">
            <input type="checkbox" wire:model="form.homologacao">
            <span>Homologação</span>
        </label>
        <label class="erp-pcad__check" style="margin:0;">
            <input type="checkbox" wire:model="form.remover_acentuacao_remessa">
            <span>Remover acentuação na remessa</span>
        </label>
        <label class="erp-pcad__check" style="margin:0;">
            <input type="checkbox" wire:model="form.webservice_indicador_pix">
            <span>Indicador PIX</span>
        </label>
    </div>

    <div style="padding:0.75rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:0.75rem;">
        @foreach ($fields as $key => $label)
            <label style="display:flex; flex-direction:column; gap:0.25rem; font-size:12px; font-weight:700;">
                {{ $label }}
                <input
                    type="{{ in_array($key, ['webservice_client_secret'], true) ? 'password' : 'text' }}"
                    wire:model="form.{{ $key }}"
                    class="erp-unidades__input"
                    autocomplete="off"
                >
            </label>
        @endforeach
    </div>

    <div class="erp-unidades-actions">
        <button type="button" wire:click="save" class="erp-unidades-actions__btn" data-erp-key="F10">
            <span class="erp-unidades-actions__icon">💾</span>
            <span class="erp-unidades-actions__label"><kbd>F10</kbd> | Salvar</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
            <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
            <span class="erp-unidades-actions__label">Fechar</span>
        </button>
    </div>
</div>
