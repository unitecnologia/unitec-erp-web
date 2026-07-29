<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfce">
    <div class="erp-config-fiscais-form__nfce-top">
        <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
            <legend class="erp-pcad__group-title">CSC — Código de Segurança do Contribuinte</legend>

            <div class="erp-config-fiscais-form__nfce-csc-row">
                <label class="erp-pcad-form__label" for="cfg-nfce-id-token">ID Token</label>
                <input
                    id="cfg-nfce-id-token"
                    type="text"
                    wire:model="form.id_token"
                    class="erp-pcad-form__input erp-config-fiscais-form__input--token-id"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="1"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfce-token">Token (CSC)</label>
                <input
                    id="cfg-nfce-token"
                    type="text"
                    wire:model="form.token"
                    class="erp-pcad-form__input erp-config-fiscais-form__input--csc"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Token informado pela SEFAZ SC"
                >
            </div>
        </fieldset>

        <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
            <legend class="erp-pcad__group-title">Numeração e emissão</legend>

            <div class="erp-config-fiscais-form__nfce-inline-row">
                <label class="erp-pcad-form__label" for="cfg-nfce-serie">Série</label>
                <input
                    id="cfg-nfce-serie"
                    type="text"
                    wire:model.live="form.serie"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                    maxlength="3"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfce-numero">Próx. nº</label>
                <input
                    id="cfg-nfce-numero"
                    type="number"
                    wire:model="form.numero"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                    min="1"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline">Últ. NFC-e</label>
                <output class="erp-config-fiscais-form__ult-nfce" aria-live="polite">{{ $this->ultimaNfceNumeroLabel() }}</output>
            </div>

            <div class="erp-config-fiscais-form__nfce-inline-row erp-config-fiscais-form__nfce-inline-row--emissao">
                <label class="erp-pcad-form__label" for="cfg-nfce-tipo-emissao">Emissão</label>
                <select id="cfg-nfce-tipo-emissao" wire:model="form.tipo_emissao" class="erp-pcad-form__select erp-config-fiscais-form__select--emissao">
                    @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::tipoEmissaoNfceOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfce-versao-qrcode">QR Code</label>
                <select id="cfg-nfce-versao-qrcode" wire:model="form.versao_qrcode" class="erp-pcad-form__select erp-config-fiscais-form__select--qrcode">
                    @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::versaoQrcodeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <span class="erp-config-fiscais-form__nfce-badge">
                    {{ strtoupper((string) ($this->form['uf'] ?? 'SC')) }}
                </span>
                <span class="erp-config-fiscais-form__nfce-badge erp-config-fiscais-form__nfce-badge--amb">
                    {{ ((int) ($this->form['ambiente'] ?? 1)) === 0 ? 'Produção' : 'Homologação' }}
                </span>
            </div>
        </fieldset>
    </div>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group erp-config-fiscais-form__nfce-group--resumo">
        <legend class="erp-pcad__group-title">Resumo</legend>

        <table class="erp-config-fiscais-form__nfce-grid" aria-label="Resumo NFC-e">
            <thead>
                <tr>
                    <th>Série</th>
                    <th>ID Token</th>
                    <th>Token</th>
                    <th>Próx.</th>
                    <th>Últ.</th>
                    <th>UF</th>
                    <th>Amb.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $this->form['serie'] ?? '—' }}</td>
                    <td>{{ filled($this->form['id_token'] ?? null) ? $this->form['id_token'] : '—' }}</td>
                    <td class="erp-config-fiscais-form__nfce-grid-token">
                        @if (filled($this->form['token'] ?? null))
                            {{ \Illuminate\Support\Str::limit($this->form['token'], 24) }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $this->form['numero'] ?? '—' }}</td>
                    <td>{{ $this->ultimaNfceNumeroLabel() }}</td>
                    <td>{{ strtoupper((string) ($this->form['uf'] ?? '—')) }}</td>
                    <td>{{ ((int) ($this->form['ambiente'] ?? 1)) === 0 ? 'Prod.' : 'Hom.' }}</td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</div>
