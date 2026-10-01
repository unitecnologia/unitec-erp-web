@if ($this->boletoContaModalOpen)
    <div
        class="erp-lookup-modal erp-boleto-conta-modal"
        wire:key="boleto-conta-modal"
        wire:keydown.escape.window="closeBoletoContaModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBoletoContaModal"></div>

        <div
            class="erp-lookup-modal__window erp-boleto-conta-modal__window"
            wire:click.stop
            role="dialog"
            aria-modal="true"
            aria-labelledby="boleto-conta-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="boleto-conta-modal-title">
                    {{ $this->boletoContaEditId ? 'Editar conta de cobrança' : 'Nova conta de cobrança' }}
                </span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeBoletoContaModal" aria-label="Fechar" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-boleto-conta-modal__body">
                <div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--boleto">
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Nome (opcional)</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.nome">
                    </div>

                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Banco</label>
                        <select class="erp-pcad-form__select erp-pcad-form__select--md" wire:model.live="boletoContaForm.banco">
                            @foreach ($bancos as $value => $rotulo)
                                @if ($value !== '')
                                    <option value="{{ $value }}">{{ $rotulo }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Ambiente</label>
                        <select class="erp-pcad-form__select erp-pcad-form__select--md" wire:model="boletoContaForm.ambiente">
                            @foreach ($ambientes as $value => $rotulo)
                                <option value="{{ $value }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="erp-pcad__check">
                        <input type="checkbox" wire:model="boletoContaForm.ativo">
                        <span>Ativa</span>
                    </label>
                    <label class="erp-pcad__check">
                        <input type="checkbox" wire:model="boletoContaForm.padrao">
                        <span>Conta padrão</span>
                    </label>
                    <label class="erp-pcad__check">
                        <input type="checkbox" wire:model="boletoContaForm.pix_hibrido">
                        <span>{{ $isSicrediForm ? 'Boleto híbrido (barras + QR)' : 'BolePix (Boleto + Pix)' }}</span>
                    </label>
                </div>

                @if ($isAilosForm)
                    <p class="erp-empresas-parametros__hint">Credenciais Ailos V2</p>
                    <div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--boleto">
                        @foreach ([
                            'client_id' => 'Consumer Key',
                            'client_secret' => 'Consumer Secret',
                            'dev_app_key' => 'UUID do Desenvolvedor',
                            'agencia' => 'Código da Cooperativa',
                            'conta' => 'Código da Conta',
                            'senha_api' => 'Senha API Ailos',
                            'convenio' => 'Número do Convênio',
                            'carteira' => 'Código da Carteira',
                            'api_url' => 'URL base da API (opcional)',
                        ] as $field => $label)
                            <div class="erp-empresas-parametros__field">
                                <label class="erp-pcad-form__label">{{ $label }}</label>
                                <input
                                    type="{{ in_array($field, ['client_secret', 'senha_api'], true) ? 'password' : 'text' }}"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    data-erp-preserve-case
                                    wire:model="boletoContaForm.{{ $field }}"
                                    @if (in_array($field, ['client_secret', 'senha_api'], true)) autocomplete="off" @endif
                                >
                            </div>
                        @endforeach
                        <div class="erp-empresas-parametros__field erp-empresas-parametros__field--full">
                            <label class="erp-pcad-form__label">URL de Callback</label>
                            <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" readonly value="{{ $ailosCallbackUrl }}">
                        </div>
                    </div>
                @elseif ($isSicrediForm)
                    <p class="erp-empresas-parametros__hint">Credenciais Sicredi (portal + Internet Banking)</p>
                    <div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--boleto">
                        @foreach ([
                            'dev_app_key' => 'Access Token (x-api-key) — obrigatório',
                            'client_id' => 'Client ID (portal)',
                            'client_secret' => 'Client Secret (portal)',
                            'agencia' => 'Cooperativa',
                            'agencia_dv' => 'Posto',
                            'beneficiario_codigo' => 'Código do Beneficiário',
                            'senha_api' => 'Código de acesso (Internet Banking)',
                            'api_url' => 'URL base da API (opcional)',
                        ] as $field => $label)
                            <div class="erp-empresas-parametros__field">
                                <label class="erp-pcad-form__label">{{ $label }}</label>
                                <input
                                    type="{{ in_array($field, ['senha_api', 'client_secret', 'dev_app_key'], true) ? 'password' : 'text' }}"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    data-erp-preserve-case
                                    wire:model="boletoContaForm.{{ $field }}"
                                    @if (in_array($field, ['senha_api', 'client_secret', 'dev_app_key'], true)) autocomplete="off" @endif
                                >
                            </div>
                        @endforeach
                    </div>
                    <p class="erp-empresas-parametros__hint">
                        O Access Token (x-api-key) NÃO é o Client ID. No portal Sicredi: Minhas Apps → ver detalhes da app
                        de Cobrança Sandbox e copie o Access Token. Se ainda não aparece, abra chamado no suporte do portal.
                        Código de acesso: Internet Banking → Cobrança → Código de Acesso.
                    </p>
                @endif

                <p class="erp-empresas-parametros__hint">Juros, multa e pós-vencimento</p>
                <div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--boleto">
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Após vencimento</label>
                        <select class="erp-pcad-form__select erp-pcad-form__select--md" wire:model.live="boletoContaForm.pos_vencimento">
                            @foreach ($posVencimento as $value => $rotulo)
                                <option value="{{ $value }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Dias após o vencimento</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.protesto_dias">
                    </div>
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Espécie</label>
                        <select class="erp-pcad-form__select erp-pcad-form__select--md" wire:model="boletoContaForm.especie_documento">
                            @foreach ($especies as $value => $rotulo)
                                <option value="{{ $value }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Juros (% ao mês)</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.juros_pct">
                    </div>
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Multa (%)</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.multa_pct">
                    </div>
                    @if ($isAilosForm)
                        <div class="erp-empresas-parametros__field">
                            <label class="erp-pcad-form__label">Desconto (%)</label>
                            <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.desconto_pct">
                        </div>
                    @endif
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Mensagem linha 1</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.instrucao1">
                    </div>
                    <div class="erp-empresas-parametros__field">
                        <label class="erp-pcad-form__label">Mensagem linha 2</label>
                        <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" wire:model="boletoContaForm.instrucao2">
                    </div>
                </div>
            </div>

            <div class="erp-boleto-conta-modal__footer erp-pcad-actions">
                <button type="button" class="erp-pcad-actions__btn" wire:click="closeBoletoContaModal" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Cancelar</span>
                </button>
                <button type="button" class="erp-pcad-actions__btn" wire:click="salvarBoletoConta" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Salvar conta</span>
                </button>
            </div>
        </div>
    </div>
@endif
