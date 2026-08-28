<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Filament\Concerns\ManagesCclassTribLookup;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\EmpresaParametros;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

trait ErpEmpresaFormPage
{
    use ManagesCclassTribLookup;
    use ManagesEmpresaBloquearEstoqueNegativo;
    use ManagesEmpresaEstoques;
    use ManagesEmpresaEmailConfig;
    use ManagesEmpresaFormUi;
    use ManagesEmpresaImpostoPadraoApply;
    use ManagesEmpresaImpostoTabelasImport;
    use ManagesEmpresaIpbtaxModal;
    use ManagesEmpresaLogo;
    use ManagesEmpresaLookup;
    use ManagesEmpresaPortalContadorLog;
    use ManagesEmpresaPortalContadorVinculo;
    use ManagesEmpresaMercadoLivreVinculo;
    use NormalizesErpUppercaseFormData;

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-empresas-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.empresas.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function saveForm(): void
    {
        $this->whatsAppQr = null;
        $this->data['cnpj_representante'] = static::normalizeOptionalCnpjRepresentante($this->data['cnpj_representante'] ?? null);
        // Só ajusta $this->data — não remonta o form Filament no hot path (evita ActionNotResolvable / travamento).
        $this->ensureEmpresaRequiredDefaults(syncForm: false);

        try {
            $this->validate(
                [
                    'data.codigo' => ['required'],
                    'data.razao_social' => ['required', 'string', 'max:255'],
                    'data.fantasia' => ['required', 'string', 'max:255'],
                    'data.cnpj' => ['required', 'string', 'max:20', new DocumentoBrasileiroValido(cnpjOnly: true)],
                    'data.cnpj_representante' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido(cnpjOnly: true)],
                ],
                [
                    'data.codigo.required' => 'Informe o código da empresa.',
                    'data.razao_social.required' => 'Informe a razão social / nome.',
                    'data.fantasia.required' => 'Informe o nome fantasia / apelido.',
                    'data.cnpj.required' => 'Informe o CNPJ da empresa.',
                ],
                [
                    'data.codigo' => 'Código',
                    'data.razao_social' => 'Razão social',
                    'data.fantasia' => 'Nome fantasia',
                    'data.cnpj' => 'CNPJ',
                    'data.cnpj_representante' => 'CNPJ do representante',
                ],
            );

            if ($this instanceof EditRecord) {
                // Commit do banco primeiro; efeitos colaterais (WhatsApp/.env/e-mail) depois do redirect controlado.
                $this->save(shouldRedirect: false, shouldSendSavedNotification: true);
                $this->runEmpresaPostSaveSideEffects();
                $this->redirect($this->getEmpresaListRedirectUrl());

                return;
            }

            /** @var CreateRecord $this */
            $this->create();
            // create() já redireciona; efeitos leves sem bloquear a resposta.
            $this->runEmpresaPostSaveSideEffects();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $body = collect($exception->errors())->flatten()->unique()->filter()->implode(' ');

            // Traduz chave crua do Laravel (ex.: validation.required) quando não houver locale.
            if ($body === '' || $body === 'validation.required') {
                $body = 'Preencha os campos obrigatórios (CNPJ, Razão Social e Nome Fantasia).';
            }

            \Filament\Notifications\Notification::make()
                ->title('Não foi possível gravar')
                ->body($body)
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            \Filament\Notifications\Notification::make()
                ->title('Não foi possível gravar')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Efeitos fora da transação Filament — nunca podem travar a gravação da empresa.
     * Credenciais ML (.env) NÃO são gravadas — tudo fica no banco.
     */
    protected function runEmpresaPostSaveSideEffects(): void
    {
        try {
            $empresa = $this->resolveEmpresaRecordForWhatsApp();

            if ($empresa) {
                app(\App\Support\Erp\WhatsApp\WhatsAppGatewayManager::class)
                    ->writeRuntimeConfig($empresa->fresh());
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            $this->persistEmpresaEmailConfigQuietly();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function ensureEmpresaRequiredDefaults(bool $syncForm = true): void
    {
        if (blank($this->data['codigo'] ?? null)) {
            $this->data['codigo'] = (string) Empresa::nextCodigo();
        }

        $razao = trim((string) ($this->data['razao_social'] ?? ''));
        $fantasia = trim((string) ($this->data['fantasia'] ?? ''));

        if ($fantasia === '' && $razao !== '') {
            $this->data['fantasia'] = $razao;
            $fantasia = $razao;
        }

        if ($razao === '' && $fantasia !== '') {
            $this->data['razao_social'] = $fantasia;
        }

        if (blank($this->data['nome'] ?? null)) {
            $this->data['nome'] = $fantasia !== '' ? $fantasia : $razao;
        }

        if ($syncForm) {
            $this->safeFillEmpresaForm();
        }
    }

    public function cancelForm(): void
    {
        ErpScreen::set('Empresa');

        $this->redirect(EmpresaResource::getUrl('index'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeLivewireFormData(array $data): array
    {
        $merged = array_merge($data, $this->data ?? []);
        $merged = ErpUppercase::normalizeFormData($merged);

        $razao = trim((string) ($merged['razao_social'] ?? ''));
        $fantasia = trim((string) ($merged['fantasia'] ?? ''));

        if ($fantasia === '' && $razao !== '') {
            $fantasia = $razao;
            $merged['fantasia'] = $razao;
        }

        if ($razao === '' && $fantasia !== '') {
            $merged['razao_social'] = $fantasia;
        }

        $merged['nome'] = $fantasia !== '' ? $fantasia : ($merged['nome'] ?? $razao);

        if (blank($merged['codigo'] ?? null)) {
            $merged['codigo'] = Empresa::nextCodigo();
        }

        if (isset($merged['codigo']) && $merged['codigo'] !== '') {
            $merged['codigo'] = (int) $merged['codigo'];
        }

        $merged = $this->normalizeEmpresaParametrosFormData($merged);
        $merged = $this->normalizeEmpresaDocumentFormData($merged);
        $merged = $this->normalizeEmpresaMercadoLivreFormData($merged);

        // Alias legado meli_env_* (se ainda vier do front) e parâmetros fiscais removidos.
        unset(
            $merged['meli_env_is_hub'],
            $merged['meli_env_app_url'],
            $merged['meli_env_hub_url'],
            $merged['param_fiscal_enviar_email_nfe'],
            $merged['param_fiscal_usar_credito_icms'],
            $merged['param_fiscal_recolhe_fcp'],
        );

        if (array_key_exists('param_ui_density', $merged)) {
            $raw = strtolower(trim((string) ($merged['param_ui_density'] ?? '14')));
            $px = match ($raw) {
                'compact', 'compacto' => 13,
                'large', 'grande' => 18,
                'normal', '' => 14,
                default => (int) preg_replace('/\D/', '', $raw),
            };
            $allowed = [12, 13, 14, 15, 16, 17, 18, 19, 20, 22];
            if (! in_array($px, $allowed, true)) {
                $px = max(12, min(22, $px > 0 ? $px : 14));
                if (! in_array($px, $allowed, true)) {
                    $px = 14;
                }
            }
            $merged['param_ui_density'] = (string) $px;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeEmpresaDocumentFormData(array $data): array
    {
        foreach (['cnpj', 'cnpj_representante', 'cep', 'telefone'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $data[$field] = preg_replace('/\D/', '', (string) $data[$field]);
        }

        $data['cnpj_representante'] = static::normalizeOptionalCnpjRepresentante($data['cnpj_representante'] ?? null);

        return $data;
    }

    protected static function normalizeOptionalCnpjRepresentante(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? '')) ?? '';

        if ($digits === '' || $digits === '00000000000000') {
            return null;
        }

        return $digits;
    }

    /**
     * Normaliza config ML para gravar no banco (empresas).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeEmpresaMercadoLivreFormData(array $data): array
    {
        // Compat: UI meli_env_* → colunas param_meli_* (banco).
        if (array_key_exists('meli_env_is_hub', $data)) {
            $data['param_meli_is_hub'] = filter_var($data['meli_env_is_hub'], FILTER_VALIDATE_BOOL);
        }

        if (array_key_exists('meli_env_app_url', $data)) {
            $data['param_meli_app_url'] = rtrim(trim((string) ($data['meli_env_app_url'] ?? '')), '/') ?: null;
        } elseif (array_key_exists('param_meli_app_url', $data)) {
            $data['param_meli_app_url'] = rtrim(trim((string) ($data['param_meli_app_url'] ?? '')), '/') ?: null;
        }

        if (array_key_exists('meli_env_hub_url', $data)) {
            $data['param_meli_hub_url'] = rtrim(trim((string) ($data['meli_env_hub_url'] ?? '')), '/') ?: null;
        } elseif (array_key_exists('param_meli_hub_url', $data)) {
            $data['param_meli_hub_url'] = rtrim(trim((string) ($data['param_meli_hub_url'] ?? '')), '/') ?: null;
        }

        foreach (['param_meli_client_id', 'param_meli_redirect_uri'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) ($data[$field] ?? '')) ?: null;
            }
        }

        // Não apagar segredos/tokens se o formulário veio em branco.
        $record = property_exists($this, 'record') && $this->record instanceof Empresa
            ? $this->record
            : null;

        foreach ([
            'param_meli_client_secret',
            'param_meli_access_token',
            'param_meli_refresh_token',
        ] as $secretField) {
            if (! array_key_exists($secretField, $data)) {
                continue;
            }

            if (filled($data[$secretField])) {
                continue;
            }

            if ($record && filled($record->{$secretField} ?? null)) {
                unset($data[$secretField]);
            } else {
                $data[$secretField] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeEmpresaParametrosFormData(array $data): array
    {
        foreach (EmpresaParametros::permissionFields() as $field => $meta) {
            if (($meta['tri'] ?? false) !== true || ! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '' || $value === 'padrao' || $value === null) {
                $data[$field] = null;

                continue;
            }

            $data[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        foreach (EmpresaParametros::numericFields() as $field => $meta) {
            if (! array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                if (($meta['type'] ?? '') === 'integer' && ($meta['default'] ?? null) === null) {
                    $data[$field] = null;
                }

                continue;
            }

            if (($meta['type'] ?? '') === 'integer') {
                $data[$field] = (int) $data[$field];
            } elseif (($meta['type'] ?? '') === 'decimal') {
                $data[$field] = BrDecimal::parse($data[$field] ?? 0, $meta['decimals'] ?? 2);
            }
        }

        if (array_key_exists('param_api_servicos_timeout', $data) && $data['param_api_servicos_timeout'] !== '') {
            $data['param_api_servicos_timeout'] = (int) $data['param_api_servicos_timeout'];
        }

        if (array_key_exists('param_licenca_api_timeout', $data)) {
            if ($data['param_licenca_api_timeout'] === '' || $data['param_licenca_api_timeout'] === null) {
                $data['param_licenca_api_timeout'] = 8;
            } else {
                $data['param_licenca_api_timeout'] = max(2, min(30, (int) $data['param_licenca_api_timeout']));
            }
        }

        unset($data['param_licenca_api_url']);

        if (array_key_exists('param_whatsapp_timeout', $data)) {
            if ($data['param_whatsapp_timeout'] === '' || $data['param_whatsapp_timeout'] === null) {
                $data['param_whatsapp_timeout'] = 30;
            } else {
                $data['param_whatsapp_timeout'] = (int) $data['param_whatsapp_timeout'];
            }
        }

        foreach ([
            'param_whatsapp_gateway_port' => 8091,
            'param_whatsapp_limite_dia' => 100,
            'param_whatsapp_msgs_hoje' => 0,
        ] as $field => $default) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === '' || $data[$field] === null) {
                $data[$field] = $default;
            } else {
                $data[$field] = (int) $data[$field];
            }
        }

        if (array_key_exists('param_whatsapp_msgs_data', $data)) {
            $msgsData = $data['param_whatsapp_msgs_data'];

            if ($msgsData === '' || $msgsData === null) {
                $data['param_whatsapp_msgs_data'] = null;
            } elseif ($msgsData instanceof \DateTimeInterface) {
                $data['param_whatsapp_msgs_data'] = $msgsData->format('Y-m-d');
            }
        }

        foreach (EmpresaParametros::expedicaoFields() as $field => $meta) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === '' || $data[$field] === null) {
                $data[$field] = (int) ($meta['default'] ?? 1);
            } else {
                $data[$field] = max(1, (int) $data[$field]);
            }
        }

        foreach (EmpresaParametros::whatsAppBooleanFields() as $field => $meta) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('param_whatsapp_status', $data)) {
            $data['param_whatsapp_status'] = \App\Support\Erp\WhatsApp\WhatsAppConfig::normalizeStatus($data['param_whatsapp_status']);
        }

        if (array_key_exists('param_whatsapp_numero', $data) && is_string($data['param_whatsapp_numero'])) {
            $data['param_whatsapp_numero'] = \App\Support\Erp\WhatsApp\WhatsAppPhone::normalize($data['param_whatsapp_numero'])
                ?? preg_replace('/\D/', '', $data['param_whatsapp_numero']);
        }

        if (array_key_exists('param_portal_contador_timeout', $data)) {
            if ($data['param_portal_contador_timeout'] === '' || $data['param_portal_contador_timeout'] === null) {
                $data['param_portal_contador_timeout'] = 30;
            } else {
                $data['param_portal_contador_timeout'] = (int) $data['param_portal_contador_timeout'];
            }
        }

        if (array_key_exists('param_portal_contador_contador_id', $data)) {
            if ($data['param_portal_contador_contador_id'] === '' || $data['param_portal_contador_contador_id'] === null) {
                $data['param_portal_contador_contador_id'] = null;
            } else {
                $data['param_portal_contador_contador_id'] = (int) $data['param_portal_contador_contador_id'];
            }
        }

        foreach (EmpresaParametros::impostoFields() as $field => $meta) {
            if (($meta['type'] ?? '') === 'decimal') {
                $data[$field] = BrDecimal::parse($data[$field] ?? ($meta['default'] ?? 0), $meta['decimals'] ?? 2);

                continue;
            }

            // Colunas string de imposto são NOT NULL no MySQL (default '').
            // Não converter vazio para null — isso quebra o INSERT.
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value !== ''
                ? $value
                : (string) ($meta['default'] ?? '');
        }

        return $data;
    }

    protected function normalizeEmpresaSlugFormData(): void
    {
        $slugFields = ['tipo_atividade', 'pessoa_tipo', 'regime_tributario', 'logo_path'];

        foreach ($slugFields as $field) {
            $value = $this->data[$field] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $this->data[$field] = mb_strtolower(trim($value), 'UTF-8');
        }

        $this->safeFillEmpresaForm();
    }

    protected function prepareEmpresaParametrosForForm(): void
    {
        $this->loadEmpresaEmailConfig();

        foreach (EmpresaParametros::permissionFields() as $field => $meta) {
            if (($meta['tri'] ?? false) !== true) {
                continue;
            }

            $value = $this->data[$field] ?? null;

            if ($value === true || $value === 1 || $value === '1') {
                $this->data[$field] = '1';
            } elseif ($value === false || $value === 0 || $value === '0') {
                $this->data[$field] = '0';
            } else {
                $this->data[$field] = '';
            }
        }

        foreach (EmpresaParametros::impostoFields() as $field => $meta) {
            if (($meta['type'] ?? '') !== 'decimal') {
                if (! array_key_exists($field, $this->data) || $this->data[$field] === null) {
                    $this->data[$field] = (string) ($meta['default'] ?? '');
                }

                continue;
            }

            $decimals = $meta['decimals'] ?? 2;
            $this->data[$field] = number_format(
                BrDecimal::parse($this->data[$field] ?? ($meta['default'] ?? 0), $decimals),
                $decimals,
                ',',
                '.',
            );
        }

        foreach (EmpresaParametros::numericFields() as $field => $meta) {
            if (($meta['type'] ?? '') !== 'decimal') {
                continue;
            }

            $decimals = $meta['decimals'] ?? 2;
            $this->data[$field] = number_format(
                BrDecimal::parse($this->data[$field] ?? ($meta['default'] ?? 0), $decimals),
                $decimals,
                ',',
                '.',
            );
        }

        $this->safeFillEmpresaForm();
        $this->hydrateCloudflareCredentialsFromDefaults();
        $this->hydrateUpdateDownloadUrlFromDefault();
    }

    protected function getEmpresaListRedirectUrl(): string
    {
        ErpScreen::set('Empresa');

        return EmpresaResource::getUrl('index');
    }

    /**
     * Evita ActionNotResolvableException do Filament ao sincronizar o form após modais/upload.
     */
    protected function purgeInvalidFilamentMountedActions(): void
    {
        if (property_exists($this, 'mountedActions') && is_array($this->mountedActions)) {
            $this->mountedActions = array_values(array_filter(
                $this->mountedActions,
                static fn ($action): bool => is_array($action) && filled($action['name'] ?? null),
            ));
        }

        if (property_exists($this, 'mountedAction') && blank($this->mountedAction)) {
            $this->mountedAction = null;
        }
    }

    protected function safeFillEmpresaForm(): void
    {
        $this->purgeInvalidFilamentMountedActions();

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function defaultEmpresaFormData(): array
    {
        return [
            'codigo' => (string) Empresa::nextCodigo(),
            'fantasia' => '',
            'razao_social' => '',
            'pessoa_tipo' => Empresa::PESSOA_JURIDICA,
            'cidade' => '',
            'cnpj' => '',
            'ie' => '',
            'im' => '',
            'cnae' => '',
            'regime_tributario' => 'normal',
            'cep' => '',
            'endereco' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'cidade_codigo' => '',
            'uf' => 'SC',
            'pais_codigo' => '1058',
            'pais' => 'BRASIL',
            'email' => '',
            'site' => '',
            'telefone' => '',
            'responsavel' => '',
            'cnpj_representante' => '',
            'tipo_atividade' => 'informatica',
            'obs_fisco' => '',
            'obs_carne' => '',
            'obs_nfce' => '',
            'obs_contribuinte' => '',
            'msg_cobranca_whatsapp' => '',
            'nome' => '',
            'logo_path' => '',
            'ativo' => true,
            ...EmpresaParametros::defaultFormValues(),
        ];
    }
}
