<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Rules\DocumentoBrasileiroValido;
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
    use ManagesEmpresaBloquearEstoqueNegativo;
    use ManagesEmpresaFormUi;
    use ManagesEmpresaLogo;
    use ManagesEmpresaLookup;
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

        try {
            $this->validate(
                [
                    'data.cnpj' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido(cnpjOnly: true)],
                    'data.cnpj_representante' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido(cnpjOnly: true)],
                ],
                [],
                [
                    'data.cnpj' => 'CNPJ',
                    'data.cnpj_representante' => 'CNPJ do representante',
                ],
            );

            if ($this instanceof EditRecord) {
                $this->save();
            } else {
                /** @var CreateRecord $this */
                $this->create();
            }

            $empresa = $this->resolveEmpresaRecordForWhatsApp();

            if ($empresa) {
                app(\App\Support\Erp\WhatsApp\WhatsAppGatewayManager::class)->writeRuntimeConfig($empresa->fresh());
            }
        } catch (\Throwable $exception) {
            report($exception);

            \Filament\Notifications\Notification::make()
                ->title('Não foi possível gravar')
                ->body($exception->getMessage())
                ->danger()
                ->send();
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

        $fantasia = trim((string) ($merged['fantasia'] ?? ''));
        $merged['nome'] = $fantasia !== '' ? $fantasia : ($merged['nome'] ?? '');

        if (isset($merged['codigo']) && $merged['codigo'] !== '') {
            $merged['codigo'] = (int) $merged['codigo'];
        }

        $merged = $this->normalizeEmpresaParametrosFormData($merged);
        $merged = $this->normalizeEmpresaDocumentFormData($merged);

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
            }
        }

        if (array_key_exists('param_api_servicos_timeout', $data) && $data['param_api_servicos_timeout'] !== '') {
            $data['param_api_servicos_timeout'] = (int) $data['param_api_servicos_timeout'];
        }

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

        $this->form->fill($this->data);
    }

    protected function prepareEmpresaParametrosForForm(): void
    {
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

        $this->form->fill($this->data);
    }

    protected function getEmpresaListRedirectUrl(): string
    {
        ErpScreen::set('Empresa');

        return EmpresaResource::getUrl('index');
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
            'msg_cobranca_whatsapp' => '',
            'nome' => '',
            'logo_path' => '',
            'ativo' => true,
            ...EmpresaParametros::defaultFormValues(),
        ];
    }
}
