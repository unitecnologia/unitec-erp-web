<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--certificado">

    <fieldset class="erp-pcad__group erp-config-fiscais-form__import-group">
        <legend class="erp-pcad__group-title">Certificado digital A1</legend>

        <p class="erp-config-fiscais-form__hint">
            Selecione o arquivo <strong>.pfx</strong>, informe a senha e clique em <strong>Importar certificado</strong>.
            O arquivo ficará no servidor para assinatura e transmissão de NF-e.
        </p>

        <div class="erp-pcad-form__row erp-config-fiscais-form__field-row erp-config-fiscais-form__senha-row">
            <label class="erp-pcad-form__label" for="cfg-import-senha">Senha do .pfx</label>
            <div class="erp-config-fiscais-form__password-wrap">
                <input
                    id="cfg-import-senha"
                    type="password"
                    wire:model="form.senha_certificado"
                    class="erp-pcad-form__input erp-config-fiscais-form__password-input"
                    autocomplete="off"
                    placeholder="Senha do certificado"
                >
                <button
                    type="button"
                    class="erp-config-fiscais-form__password-toggle"
                    data-erp-password-toggle="cfg-import-senha"
                    aria-label="Mostrar senha"
                    title="Mostrar senha"
                >
                    <svg class="erp-config-fiscais-form__password-toggle-icon erp-config-fiscais-form__password-toggle-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg class="erp-config-fiscais-form__password-toggle-icon erp-config-fiscais-form__password-toggle-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="erp-config-fiscais-form__import-row">
            <input
                id="erp-config-fiscais-cert-file"
                type="file"
                wire:model="certificadoUpload"
                accept=".pfx,.p12"
                class="erp-config-fiscais-form__file-input"
            >
            <button
                type="button"
                class="erp-pcad-form__btn"
                onclick="document.getElementById('erp-config-fiscais-cert-file')?.click()"
            >
                Escolher arquivo .pfx…
            </button>

            <button
                type="button"
                wire:click="importarCertificado"
                wire:loading.attr="disabled"
                wire:target="certificadoUpload, importarCertificado"
                class="erp-pcad-form__btn erp-config-fiscais-form__import-btn"
            >
                <span wire:loading.remove wire:target="certificadoUpload, importarCertificado">Importar certificado</span>
                <span wire:loading wire:target="certificadoUpload, importarCertificado">Processando…</span>
            </button>
        </div>

        @if ($this->certificadoUpload)
            <p class="erp-config-fiscais-form__hint">
                Arquivo selecionado: {{ $this->certificadoUpload->getClientOriginalName() }}
            </p>
        @endif

        @if ($this->certificadoInfo)
            <div class="erp-config-fiscais-form__cert-info">
                <p class="erp-config-fiscais-form__cert-info-title">{{ $this->certificadoInfo['titulo'] }}</p>
                <p class="erp-config-fiscais-form__cert-info-meta">Emissor: {{ $this->certificadoInfo['emissor'] }}</p>
                <p class="erp-config-fiscais-form__cert-info-meta">
                    Válido de {{ $this->certificadoInfo['validade_inicio'] }} a {{ $this->certificadoInfo['validade'] }}
                </p>
                @if (filled($this->form['numero_serie_certificado'] ?? ''))
                    <p class="erp-config-fiscais-form__cert-info-meta">
                        Nº série: {{ $this->form['numero_serie_certificado'] }}
                    </p>
                @endif
            </div>
        @else
            <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--muted">
                Nenhum certificado importado nesta empresa.
            </p>
        @endif

        <div class="erp-config-fiscais-form__verify-wrap">
            <button type="button" wire:click="testCertificado" class="erp-config-fiscais-form__verify-btn">
                Verificar data de validade
            </button>
        </div>
    </fieldset>

</div>
