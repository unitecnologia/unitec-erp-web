<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\VendasParametro;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Mail\FiscalMailService;
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

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('config_fiscais.access');
    }

    public string $activeTab = 'webservice';

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * Série NFC-e por caixa (PDVs offline), editável na aba "PDVs Offline".
     *
     * @var array<int, array{id: int, nome: string, terminal: string, serie: string, numero_inicial: int, usar_numero_inicial: bool}>
     */
    public array $terminais = [];

    public ?TemporaryUploadedFile $certificadoUpload = null;

    /** @var array{titulo: string, emissor: string, validade_inicio: string, validade: string, numero_serie: string}|null */
    public ?array $certificadoInfo = null;

    public string $emailTestTo = '';

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
        $this->loadTerminais();
        $this->emailTestTo = (string) ($empresa?->email ?? '');
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
        $allowed = ['webservice', 'certificado', 'nfce', 'nfe', 'pdv_offline', 'resp_tecnico'];

        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'webservice';

        if ($this->activeTab === 'nfe') {
            $this->syncNfeStoragePathsToForm();
        }

        if ($this->activeTab === 'pdv_offline') {
            $this->loadTerminais();
        }
    }

    /**
     * Carrega os caixas da empresa para edição da série NFC-e por PDV offline.
     */
    protected function loadTerminais(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            $this->terminais = [];

            return;
        }

        $this->terminais = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('numero_logico_terminal')
            ->orderBy('id')
            ->get()
            ->map(fn (Terminal $t): array => [
                'id' => (int) $t->id,
                'nome' => (string) ($t->nome ?: 'Caixa'),
                'terminal' => (string) ($t->numero_logico_terminal ?: $t->id),
                'serie' => (string) ($t->serie ?: ''),
                'numero_inicial' => (int) ($t->numeracao_inicial ?: 1),
                'usar_numero_inicial' => (bool) $t->usar_numero_inicial,
            ])
            ->all();
    }

    /**
     * Grava a série NFC-e (e número inicial) de cada caixa. Bloqueia séries
     * duplicadas entre caixas — cada PDV offline precisa de série exclusiva.
     */
    public function saveTerminaisSeries(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        $seriesUsadas = [];

        foreach ($this->terminais as $linha) {
            $serie = trim((string) ($linha['serie'] ?? ''));

            if ($serie === '') {
                continue;
            }

            $chave = ltrim($serie, '0') ?: '0';

            if (isset($seriesUsadas[$chave])) {
                Notification::make()
                    ->title('Série duplicada entre caixas')
                    ->body("A série {$serie} está repetida. Cada PDV offline precisa de série exclusiva.")
                    ->danger()
                    ->send();

                return;
            }

            $seriesUsadas[$chave] = true;
        }

        foreach ($this->terminais as $linha) {
            $terminal = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($linha['id'] ?? 0)
                ->first();

            if (! $terminal) {
                continue;
            }

            $serie = trim((string) ($linha['serie'] ?? ''));

            $terminal->update([
                'serie' => $serie !== '' ? $serie : null,
                'numeracao_inicial' => max(1, (int) ($linha['numero_inicial'] ?? 1)),
                'usar_numero_inicial' => (bool) ($linha['usar_numero_inicial'] ?? false),
            ]);
        }

        $this->loadTerminais();

        Notification::make()->title('Séries dos PDVs offline gravadas.')->success()->send();
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
            'form.numero_nfe' => ['required', 'integer', 'min:1'],
            'form.serie_nfe' => ['required', 'integer', 'min:1', 'max:999'],
            'form.id_token' => ['nullable', 'string', 'max:40'],
            'form.token' => ['nullable', 'string', 'max:120'],
            'form.versao_qrcode' => ['nullable', 'integer', 'in:2,3'],
            'form.resp_tecnico_cnpj' => ['nullable', 'string', 'max:18'],
            'form.resp_tecnico_contato' => ['nullable', 'string', 'max:60'],
            'form.resp_tecnico_email' => ['nullable', 'string', 'max:60'],
            'form.resp_tecnico_fone' => ['nullable', 'string', 'max:20'],
            'form.resp_tecnico_id_csrt' => ['nullable', 'string', 'max:6'],
            'form.resp_tecnico_csrt' => ['nullable', 'string', 'max:100'],
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
            'versao_qrcode' => (int) ($this->form['versao_qrcode'] ?? 2),
            'logomarca' => $this->form['logomarca'] ?: null,
            'numero' => (int) $this->form['numero'],
            'serie' => (string) $this->form['serie'],
            'serie_nfe' => (int) ($this->form['serie_nfe'] ?? 1),
            'numero_nfe' => (int) ($this->form['numero_nfe'] ?? 1),
            'email_host' => $this->form['email_host'] ?: null,
            'email_porta' => $this->form['email_porta'] ?: null,
            'email_user' => $this->form['email_user'] ?: null,
            'email_assunto' => $this->form['email_assunto'] ?: null,
            'email_ssl' => ! empty($this->form['email_ssl']) ? 'S' : 'N',
            'email_tls' => ! empty($this->form['email_tls']) ? 'S' : 'N',
            'email_modo' => FiscalMailService::normalizeModo((string) ($this->form['email_modo'] ?? FiscalMailService::MODO_SMTP)),
            'email_api_provedor' => FiscalMailService::normalizeApiProvider((string) ($this->form['email_api_provedor'] ?? FiscalMailService::API_BREVO)),
            'resp_tecnico_cnpj' => NfeFiscalConfig::defaultRespTecnico()['cnpj'],
            'resp_tecnico_contato' => NfeFiscalConfig::defaultRespTecnico()['contato'],
            'resp_tecnico_email' => NfeFiscalConfig::defaultRespTecnico()['email'],
            'resp_tecnico_fone' => NfeFiscalConfig::defaultRespTecnico()['fone'],
            'resp_tecnico_id_csrt' => $this->form['resp_tecnico_id_csrt'] ?: null,
            'resp_tecnico_csrt' => $this->form['resp_tecnico_csrt'] ?: null,
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

        if (filled($this->form['email_api_key'] ?? '')) {
            $payload['email_api_key'] = $this->form['email_api_key'];
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

    public function testEmailSmtp(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);
        $empresa = Empresa::query()->find($empresaId);

        $result = FiscalMailService::testEmail(
            $this->form,
            $params,
            $this->emailTestTo,
            $empresa,
        );

        $notification = Notification::make()->title($result['message']);

        if ($result['ok']) {
            $notification->success()->send();
        } else {
            $notification->danger()->send();
        }
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

    public function ultimaNfceNumeroLabel(): string
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return '—';
        }

        $serie = trim((string) ($this->form['serie'] ?? '1'));
        $serieSemZeros = ltrim($serie, '0') ?: '0';
        $series = array_values(array_unique([
            $serie,
            $serieSemZeros,
            str_pad($serieSemZeros, 3, '0', STR_PAD_LEFT),
        ]));

        $ultimo = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', $series)
            ->max('numero');

        if ($ultimo === null) {
            return '0';
        }

        return (string) (int) $ultimo;
    }

    public function ultimaNfeNumeroLabel(): string
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return '—';
        }

        $serie = trim((string) ($this->form['serie_nfe'] ?? '1'));
        $serieSemZeros = ltrim($serie, '0') ?: '0';
        $series = array_values(array_unique([
            $serie,
            $serieSemZeros,
            str_pad($serieSemZeros, 3, '0', STR_PAD_LEFT),
        ]));

        $ultimo = Nfe::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', $series)
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        if ($ultimo === null) {
            return '0';
        }

        return (string) (int) $ultimo;
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
        return \App\Support\Erp\ErpContext::currentEmpresaId();
    }
}
