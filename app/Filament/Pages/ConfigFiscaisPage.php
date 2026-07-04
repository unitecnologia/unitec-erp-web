<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ConfigFiscaisPage extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = '';

    protected static ?string $slug = 'config-fiscais';

    protected static bool $shouldRegisterNavigation = false;

    public string $activeTab = 'webservice';

    /** @var array<string, mixed> */
    public array $form = [];

    public ?TemporaryUploadedFile $certificadoUpload = null;

    /** @var array{titulo: string, emissor: string, validade_inicio: string, validade: string, numero_serie: string}|null */
    public ?array $certificadoInfo = null;

    public function mount(): void
    {
        ErpScreen::set('Config. Fiscais');

        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return;
        }

        $empresa = Empresa::query()->find($empresaId);
        $params = VendasParametro::forEmpresa($empresaId);
        NfeFiscalConfig::ensureDefaults($params, $empresa);
        $this->form = NfeFiscalConfig::toFormArray($params->fresh());
        $this->syncNfeStoragePathsToForm();
        $this->refreshCertificadoInfo();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-form-page', 'erp-config-fiscais-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.config-fiscais.screen'),
            ]);
    }

    public function setActiveTab(string $tab): void
    {
        $allowed = ['webservice', 'certificado', 'nfe', 'email'];

        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'webservice';

        if ($this->activeTab === 'nfe') {
            $this->syncNfeStoragePathsToForm();
        }
    }

    protected function syncNfeStoragePathsToForm(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return;
        }

        $params = NfeFiscalConfig::syncStoragePaths(VendasParametro::forEmpresa($empresaId));

        foreach (NfeFiscalConfig::defaultStoragePaths($empresaId) as $field => $path) {
            $this->form[$field] = $params->{$field} ?? $path;
        }
    }

    public function saveConfig(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        $this->validate([
            'form.uf' => ['required', 'string', 'size:2'],
            'form.ambiente' => ['required', 'integer', 'in:0,1'],
            'form.aguardar' => ['required', 'integer', 'min:0'],
            'form.intervalo' => ['required', 'integer', 'min:0'],
            'form.tentativas' => ['required', 'integer', 'min:1'],
            'form.numero' => ['required', 'integer', 'min:1'],
            'form.serie' => ['required', 'string', 'max:10'],
        ]);

        $params = VendasParametro::forEmpresa($empresaId);

        $payload = [
            'uf' => strtoupper((string) $this->form['uf']),
            'ambiente' => (int) $this->form['ambiente'],
            'aguardar' => (int) $this->form['aguardar'],
            'intervalo' => (int) $this->form['intervalo'],
            'tentativas' => (int) $this->form['tentativas'],
            'ajustar_auto' => ! empty($this->form['ajustar_auto']) ? 'S' : 'N',
            'proxy_host' => $this->form['proxy_host'] ?: null,
            'proxy_porta' => $this->form['proxy_porta'] ?: null,
            'proxy_usuario' => $this->form['proxy_usuario'] ?: null,
            'numero_serie_certificado' => $this->form['numero_serie_certificado'] ?: null,
            ...NfeFiscalConfig::defaultWebStack(),
            'versao_nfe' => (int) ($this->form['versao_nfe'] ?? 4),
            'tipo_emissao' => (int) ($this->form['tipo_emissao'] ?? 1),
            'id_token' => $this->form['id_token'] ?: null,
            'token' => $this->form['token'] ?: null,
            'logomarca' => $this->form['logomarca'] ?: null,
            'numero' => (int) $this->form['numero'],
            'serie' => (string) $this->form['serie'],
            'serie_nfe' => (int) ($this->form['serie_nfe'] ?? 1),
            'email_host' => $this->form['email_host'] ?: null,
            'email_porta' => $this->form['email_porta'] ?: null,
            'email_user' => $this->form['email_user'] ?: null,
            'email_assunto' => $this->form['email_assunto'] ?: null,
            'email_ssl' => ! empty($this->form['email_ssl']) ? 'S' : 'N',
            'email_tls' => ! empty($this->form['email_tls']) ? 'S' : 'N',
        ];

        if (filled($this->form['proxy_senha'] ?? '')) {
            $payload['proxy_senha'] = $this->form['proxy_senha'];
        }

        if (filled($this->form['senha_certificado'] ?? '')) {
            $payload['senha_certificado'] = $this->form['senha_certificado'];
        }

        if (filled($this->form['email_senha'] ?? '')) {
            $payload['email_senha'] = $this->form['email_senha'];
        }

        $payload = [
            ...$payload,
            ...NfeFiscalConfig::defaultStoragePaths($empresaId),
            'caminho_certificado' => $this->form['caminho_certificado'] ?: null,
        ];

        $params->update($payload);
        $params = NfeFiscalConfig::syncStoragePaths($params->fresh());
        $params = NfeFiscalConfig::syncWebStack($params);

        $this->form = NfeFiscalConfig::toFormArray($params);
        $this->form['email_senha'] = '';
        $this->form['proxy_senha'] = '';

        Notification::make()->title('Configurações fiscais gravadas.')->success()->send();
    }

    public function importarCertificado(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        if (! $this->certificadoUpload) {
            Notification::make()->title('Selecione o arquivo .pfx.')->warning()->send();

            return;
        }

        $senha = trim((string) ($this->form['senha_certificado'] ?? ''));

        if ($senha === '') {
            Notification::make()->title('Informe a senha do certificado .pfx.')->warning()->send();

            return;
        }

        $content = file_get_contents($this->certificadoUpload->getRealPath());
        $result = NfeFiscalConfig::readPkcs12($content, $senha);

        if (! $result['ok']) {
            Notification::make()->title($result['message'])->danger()->send();

            return;
        }

        $relative = 'certificados/'.$empresaId.'/certificado.pfx';

        $this->certificadoUpload->storeAs(
            'certificados/'.$empresaId,
            'certificado.pfx',
            'local',
        );

        $params = VendasParametro::forEmpresa($empresaId);
        $params->update([
            'caminho_certificado' => $relative,
            'senha_certificado' => $senha,
            'numero_serie_certificado' => $result['numero_serie'] ?? null,
            ...NfeFiscalConfig::defaultWebStack(),
        ]);

        NfeFiscalConfig::ensureDirectories($params->fresh());
        NfeFiscalConfig::syncWebStack($params->fresh());

        $this->certificadoUpload = null;
        $this->form['caminho_certificado'] = $relative;
        $this->form['numero_serie_certificado'] = (string) ($result['numero_serie'] ?? '');
        $this->form['senha_certificado'] = $senha;
        $this->refreshCertificadoInfo();

        $titulo = (string) ($result['titulo'] ?? 'Certificado digital');
        $validade = (string) ($result['validade'] ?? '');

        Notification::make()
            ->title("Certificado importado: {$titulo}")
            ->body($validade !== '' ? "Válido até {$validade}." : null)
            ->success()
            ->send();
    }

    public function testCertificado(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);

        if ($this->certificadoUpload) {
            $senha = trim((string) ($this->form['senha_certificado'] ?? ''));

            if ($senha === '') {
                Notification::make()->title('Informe a senha do certificado .pfx.')->warning()->send();

                return;
            }

            $result = NfeFiscalConfig::readPkcs12(
                file_get_contents($this->certificadoUpload->getRealPath()),
                $senha,
            );

            if (! $result['ok']) {
                Notification::make()->title($result['message'])->danger()->send();

                return;
            }

            Notification::make()
                ->title("Certificado válido até {$result['validade']}.")
                ->body('Clique em Importar certificado para gravar no servidor.')
                ->success()
                ->send();

            return;
        }

        $result = NfeFiscalConfig::testCertificado(
            $params,
            filled($this->form['senha_certificado'] ?? '') ? $this->form['senha_certificado'] : null,
        );

        if ($result['ok']) {
            $this->refreshCertificadoInfo();
        }

        $notification = Notification::make()->title($result['message']);

        if ($result['ok']) {
            $notification->success()->send();
        } else {
            $notification->danger()->send();
        }
    }

    protected function refreshCertificadoInfo(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            $this->certificadoInfo = null;

            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);
        $path = NfeFiscalConfig::certificadoAbsolutePath($params);
        $senha = $params->safeSenhaCertificado();

        if ($path === null || $senha === null) {
            $this->certificadoInfo = null;

            return;
        }

        $result = NfeFiscalConfig::readPkcs12(file_get_contents($path), $senha);

        if (! $result['ok']) {
            $this->certificadoInfo = null;

            return;
        }

        $this->certificadoInfo = [
            'titulo' => (string) ($result['titulo'] ?? 'Certificado digital'),
            'emissor' => (string) ($result['emissor'] ?? '—'),
            'validade_inicio' => (string) ($result['validade_inicio'] ?? '—'),
            'validade' => (string) ($result['validade'] ?? '—'),
            'numero_serie' => (string) ($result['numero_serie'] ?? ''),
        ];
    }

    public function resetPaths(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);
        $params->update(NfeFiscalConfig::defaultStoragePaths($empresaId));
        NfeFiscalConfig::syncStoragePaths($params->fresh());
        $this->syncNfeStoragePathsToForm();

        Notification::make()->title('Pastas NF-e recriadas no servidor.')->success()->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    protected function resolveEmpresaId(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId) {
            return (int) $empresaId;
        }

        return Empresa::query()->where('ativo', true)->orderBy('id')->value('id');
    }
}
