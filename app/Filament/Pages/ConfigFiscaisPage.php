<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\NfseDpsSequencia;
use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\VendasParametro;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Erp\Nfse\NfseDpsNumeracaoRecusada;
use App\Support\Erp\Nfse\NfseRegimeTributario;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Fiscal\NfceTerminalSequencia;
use Unitec\FiscalEngine\Nfe\DfeDistribuidor;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
     * Série NFC-e por caixa (aba PDVs Offline).
     *
     * @var array<int, array{id: int, nome: string, terminal: string, serie: string, proximo_numero: int, ultimo_nfce: int|null}>
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
        $this->loadNfseRegime($empresa);
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
        $allowed = ['webservice', 'certificado', 'nfce', 'nfe', 'nfse', 'pdv_offline', 'resp_tecnico'];

        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'webservice';

        if ($this->activeTab === 'nfe') {
            $this->syncNfeStoragePathsToForm();
        }

        if ($this->activeTab === 'pdv_offline') {
            $this->loadTerminais();
        }
    }

    /**
     * Carrega os caixas da empresa para edição da série NFC-e por caixa.
     */
    protected function loadTerminais(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            $this->terminais = [];

            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);

        $this->terminais = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('numero_logico_terminal')
            ->orderBy('id')
            ->get()
            ->map(function (Terminal $t) use ($params): array {
                $serieGravada = trim((string) ($t->serie ?: ''));
                $serieConsulta = $serieGravada !== ''
                    ? $serieGravada
                    : NfceTerminalSequencia::serieEfetiva($t, $params);
                $ultimo = NfceTerminalSequencia::ultimoNumero((int) $t->empresa_id, $serieConsulta);

                return [
                    'id' => (int) $t->id,
                    'nome' => (string) ($t->nome ?: 'Caixa'),
                    'terminal' => (string) ($t->numero_logico_terminal ?: $t->id),
                    'serie' => $serieGravada,
                    'proximo_numero' => NfceTerminalSequencia::proximoPiso($t, $params),
                    'ultimo_nfce' => $ultimo ?? 0,
                ];
            })
            ->all();
    }

    /**
     * Grava a série NFC-e e o próximo número de cada caixa.
     * Bloqueia séries duplicadas — cada caixa precisa de série exclusiva.
     */
    public function saveTerminaisSeries(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        if ($this->persistTerminaisSeries($empresaId)) {
            Notification::make()->title('Séries dos caixas gravadas.')->success()->send();
        }
    }

    /**
     * Persiste série / próximo nº dos caixas. Retorna false se bloqueou (duplicata ou piso).
     */
    protected function persistTerminaisSeries(int $empresaId): bool
    {
        if ($this->terminais === []) {
            return true;
        }

        $params = VendasParametro::forEmpresa($empresaId);
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
                    ->body("A série {$serie} está repetida. Cada caixa precisa de série exclusiva.")
                    ->danger()
                    ->send();

                return false;
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
            $terminal->serie = $serie !== '' ? $serie : null;

            $piso = NfceTerminalSequencia::proximoPiso($terminal, $params);
            $proximo = max(1, (int) ($linha['proximo_numero'] ?? $piso));

            if ($proximo < $piso) {
                $nome = (string) ($linha['nome'] ?? 'Caixa');
                Notification::make()
                    ->title('Próx. nº inválido')
                    ->body("O caixa {$nome} não pode usar próximo {$proximo}. O mínimo é {$piso} (último da série + 1).")
                    ->danger()
                    ->send();

                return false;
            }

            $terminal->update([
                'serie' => $serie !== '' ? $serie : null,
                'numeracao_inicial' => $proximo,
                'usar_numero_inicial' => true,
            ]);
        }

        $this->loadTerminais();

        return true;
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

        $this->form['nfse_provedor'] = $this->normalizarNfseProvedor($this->form['nfse_provedor'] ?? null) ?? 'nacional';
        $this->form['nfse_ambiente'] = $this->normalizarNfseAmbiente($this->form['nfse_ambiente'] ?? null);
        $this->form['nfse_reg_esp_trib'] = $this->codigoNfseOuNulo($this->form['nfse_reg_esp_trib'] ?? null);
        $this->form['nfse_reg_ap_trib_sn'] = $this->codigoNfseOuNulo($this->form['nfse_reg_ap_trib_sn'] ?? null);
        $this->form['nfse_serie_dps'] = trim((string) ($this->form['nfse_serie_dps'] ?? ''));
        $this->form['nfse_serie_rps'] = trim((string) ($this->form['nfse_serie_rps'] ?? ''));
        $this->form['nfse_tipo_rps'] = trim((string) ($this->form['nfse_tipo_rps'] ?? ''));
        $this->form['nfse_ws_usuario'] = trim((string) ($this->form['nfse_ws_usuario'] ?? ''));
        $this->form['nfse_url_producao'] = trim((string) ($this->form['nfse_url_producao'] ?? ''));
        $this->form['nfse_url_homologacao'] = trim((string) ($this->form['nfse_url_homologacao'] ?? ''));
        $this->aplicarPadroesIpm();
        $nacional = $this->form['nfse_provedor'] === 'nacional';
        $ipm = $this->form['nfse_provedor'] === 'ipm';
        $nsuDigits = preg_replace('/\D/', '', (string) ($this->form['dfe_ultimo_nsu'] ?? '')) ?? '';

        try {
            if (strlen($nsuDigits) > 15) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'form.dfe_ultimo_nsu' => 'Último NSU inválido. Informe até 15 dígitos.',
                ]);
            }

            $this->form['dfe_ultimo_nsu'] = DfeDistribuidor::normalizarNsu($nsuDigits);

            $this->validate([
                'form.uf' => ['required', 'string', 'size:2'],
                'form.ambiente' => ['required', 'integer', 'in:0,1'],
                'form.aguardar' => ['required', 'integer', 'min:0'],
                'form.intervalo' => ['required', 'integer', 'min:0'],
                'form.tentativas' => ['required', 'integer', 'min:1'],
                'form.numero_nfe' => ['required', 'integer', 'min:1'],
                'form.serie_nfe' => ['required', 'integer', 'min:1', 'max:999'],
                'form.dfe_ultimo_nsu' => ['required', 'regex:/^\d{15}$/'],
                'form.id_token' => ['nullable', 'string', 'max:40'],
                'form.token' => ['nullable', 'string', 'max:120'],
                'form.versao_qrcode' => ['nullable', 'integer', 'in:2,3'],
                'form.resp_tecnico_cnpj' => ['nullable', 'string', 'max:18'],
                'form.resp_tecnico_contato' => ['nullable', 'string', 'max:60'],
                'form.resp_tecnico_email' => ['nullable', 'string', 'max:60'],
                'form.resp_tecnico_fone' => ['nullable', 'string', 'max:20'],
                'form.resp_tecnico_id_csrt' => ['nullable', 'string', 'max:6'],
                'form.resp_tecnico_csrt' => ['nullable', 'string', 'max:100'],
                'form.nfse_provedor' => ['required', 'in:nacional,ipm'],
                'form.nfse_ambiente' => ['nullable', 'in:'.implode(',', array_keys(NfseSefinAmbiente::opcoes()))],
                'form.nfse_reg_esp_trib' => ['nullable', 'in:'.implode(',', array_keys(NfseRegimeTributario::regimesEspeciais()))],
                'form.nfse_reg_ap_trib_sn' => ['nullable', 'in:'.implode(',', array_keys(NfseRegimeTributario::regimesApuracaoSimples()))],
                'form.nfse_serie_dps' => [$nacional ? 'required' : 'nullable', 'regex:'.NfseDpsSequencia::SERIE_PATTERN],
                'form.nfse_proximo_dps' => [$nacional ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.NfseDpsSequencia::NUMERO_MAX],
                'form.nfse_serie_rps' => [$ipm ? 'required' : 'nullable', 'regex:/^[A-Za-z0-9]{1,5}$/'],
                'form.nfse_proximo_rps' => [$ipm ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.NfseDpsSequencia::NUMERO_MAX],
                'form.nfse_tipo_rps' => [$ipm ? 'required' : 'nullable', 'regex:/^[0-9]$/'],
                'form.nfse_ws_usuario' => [$ipm ? 'required' : 'nullable', 'string', 'max:20'],
                'form.nfse_ws_senha' => ['nullable', 'string', 'max:120'],
                'form.nfse_url_producao' => ['nullable', 'string', 'max:255', 'url'],
                'form.nfse_url_homologacao' => ['nullable', 'string', 'max:255', 'url'],
            ], [
                'form.nfse_provedor.required' => 'Selecione o provedor da NFS-e.',
                'form.nfse_provedor.in' => 'Provedor da NFS-e inválido.',
                'form.nfse_ambiente.in' => 'Ambiente da NFS-e inválido.',
                'form.nfse_reg_esp_trib.in' => 'Regime especial de tributação da NFS-e inválido.',
                'form.nfse_reg_ap_trib_sn.in' => 'Regime de apuração do Simples da NFS-e inválido.',
                'form.nfse_serie_dps.required' => 'Informe a série DPS.',
                'form.nfse_serie_dps.regex' => 'Série DPS inválida. Use até 5 dígitos, no máximo 89999.',
                'form.nfse_proximo_dps.required' => 'Informe o próximo Nº DPS.',
                'form.nfse_proximo_dps.integer' => 'Próximo Nº DPS inválido.',
                'form.nfse_proximo_dps.min' => 'O próximo Nº DPS deve ser maior que zero.',
                'form.nfse_proximo_dps.max' => 'Próximo Nº DPS inválido.',
                'form.nfse_serie_rps.required' => 'Informe a série RPS.',
                'form.nfse_serie_rps.regex' => 'Série RPS inválida. Use até 5 letras ou números.',
                'form.nfse_proximo_rps.required' => 'Informe o próximo Nº RPS.',
                'form.nfse_proximo_rps.integer' => 'Próximo Nº RPS inválido.',
                'form.nfse_proximo_rps.min' => 'O próximo Nº RPS deve ser maior que zero.',
                'form.nfse_proximo_rps.max' => 'Próximo Nº RPS inválido.',
                'form.nfse_tipo_rps.required' => 'Informe o tipo do RPS.',
                'form.nfse_tipo_rps.regex' => 'Tipo do RPS inválido. Use um dígito.',
                'form.nfse_ws_usuario.required' => 'Informe o usuário do WebService.',
                'form.nfse_ws_usuario.max' => 'Usuário do WebService inválido.',
                'form.nfse_ws_senha.max' => 'Senha do WebService inválida.',
                'form.nfse_url_producao.url' => 'URL de produção da NFS-e inválida.',
                'form.nfse_url_producao.max' => 'URL de produção da NFS-e inválida.',
                'form.nfse_url_homologacao.url' => 'URL de homologação da NFS-e inválida.',
                'form.nfse_url_homologacao.max' => 'URL de homologação da NFS-e inválida.',
                'form.dfe_ultimo_nsu.required' => 'Informe o último NSU.',
                'form.dfe_ultimo_nsu.regex' => 'Último NSU inválido. Informe até 15 dígitos.',
            ]);

            if ($nacional) {
                $recusa = NfseDpsSequencia::recusaProximo(
                    $empresaId,
                    (string) $this->form['nfse_serie_dps'],
                    (int) $this->form['nfse_proximo_dps'],
                );

                if ($recusa !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'form.nfse_proximo_dps' => $recusa,
                    ]);
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $primeiro = collect($e->validator->errors()->all())->first();

            Notification::make()
                ->title('Não foi possível gravar')
                ->body($primeiro ?: 'Verifique os campos das abas e tente novamente.')
                ->danger()
                ->send();

            throw $e;
        }

        try {
            $this->persistConfigFiscais($empresaId);
        } catch (NfseDpsNumeracaoRecusada $e) {
            Notification::make()
                ->title('Não foi possível gravar')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Não foi possível gravar')
                ->body('Ocorreu um erro ao salvar as configurações fiscais. Tente novamente.')
                ->danger()
                ->send();
        }
    }

    protected function persistConfigFiscais(int $empresaId): void
    {
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
            'id_token' => trim((string) ($this->form['id_token'] ?? '')) ?: null,
            'token' => trim((string) ($this->form['token'] ?? '')) ?: null,
            'versao_qrcode' => (int) ($this->form['versao_qrcode'] ?? 2),
            'logomarca' => $this->form['logomarca'] ?: null,
            'serie_nfe' => (int) ($this->form['serie_nfe'] ?? 1),
            'numero_nfe' => (int) ($this->form['numero_nfe'] ?? 1),
            'dfe_ultimo_nsu' => DfeDistribuidor::normalizarNsu((string) ($this->form['dfe_ultimo_nsu'] ?? '')),
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

        $this->persistNfseRegime($empresaId);

        if (($this->form['nfse_provedor'] ?? 'nacional') === 'nacional') {
            $this->persistNfseNumeracao($empresaId);
        }

        $this->form = NfeFiscalConfig::toFormArray($params);
        $this->form['proxy_senha'] = '';
        $this->loadNfseRegime(Empresa::query()->find($empresaId));

        if (! $this->persistTerminaisSeries($empresaId)) {
            Notification::make()
                ->title('Configurações gravadas, mas as séries dos caixas não foram salvas.')
                ->warning()
                ->send();

            return;
        }

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

        try {
            $realPath = $this->certificadoUpload->getRealPath();

            if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
                Notification::make()
                    ->title('Arquivo .pfx inválido ou expirado.')
                    ->body('Selecione o arquivo novamente e clique em Importar certificado.')
                    ->warning()
                    ->send();

                return;
            }

            $content = file_get_contents($realPath);

            if ($content === false || $content === '') {
                Notification::make()->title('Não foi possível ler o arquivo .pfx.')->danger()->send();

                return;
            }

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
            // Evita DecryptException de PFX antigo corrompido ao sobrescrever.
            $params->setAttribute('certificado_pfx', null);
            $params->syncOriginalAttribute('certificado_pfx');

            $params->forceFill([
                'caminho_certificado' => $relative,
                'certificado_pfx' => $content,
                'senha_certificado' => $senha,
                'numero_serie_certificado' => $result['numero_serie'] ?? null,
                ...NfeFiscalConfig::defaultWebStack(),
            ])->save();

            try {
                NfeFiscalConfig::ensureDirectories($params->fresh());
                NfeFiscalConfig::syncWebStack($params->fresh());
            } catch (\Illuminate\Contracts\Encryption\DecryptException $decryptException) {
                report($decryptException);
            }

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
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível importar o certificado.')
                ->body('Falha ao gravar o certificado no banco (chave de criptografia). Tente novamente; se persistir, avise o suporte.')
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            $msg = $exception->getMessage();
            $lower = mb_strtolower($msg);
            if (str_contains($lower, 'mac is invalid') || str_contains($lower, 'mac verify')) {
                $msg = 'Senha do .pfx incorreta ou arquivo inválido. Digite a senha novamente (diferencia maiúsculas/minúsculas) e selecione o arquivo de novo.';
            }

            Notification::make()
                ->title('Não foi possível importar o certificado.')
                ->body($msg)
                ->danger()
                ->send();
        }
    }

    public function excluirCertificado(): void
    {
        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        $params = VendasParametro::forEmpresa($empresaId);
        $relative = trim((string) ($params->caminho_certificado ?? ''));
        $defaultRelative = 'certificados/'.$empresaId.'/certificado.pfx';

        $candidates = array_values(array_unique(array_filter([
            $relative,
            $relative !== '' ? Storage::disk('local')->path($relative) : null,
            $defaultRelative,
            Storage::disk('local')->path($defaultRelative),
        ])));

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }

            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $params->setAttribute('certificado_pfx', null);
        $params->syncOriginalAttribute('certificado_pfx');

        $params->forceFill([
            'caminho_certificado' => null,
            'certificado_pfx' => null,
            'senha_certificado' => null,
            'numero_serie_certificado' => null,
        ])->save();

        $this->certificadoUpload = null;
        $this->certificadoInfo = null;
        $this->form['caminho_certificado'] = '';
        $this->form['numero_serie_certificado'] = '';
        $this->form['senha_certificado'] = '';

        Notification::make()->title('Certificado digital removido.')->success()->send();
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

    public function updatedForm(mixed $value, ?string $key = null): void
    {
        if ($key !== 'nfse_provedor') {
            return;
        }

        $this->form['nfse_provedor'] = $this->normalizarNfseProvedor($value) ?? 'nacional';
        $this->aplicarPadroesIpm();
    }

    protected function loadNfseRegime(?Empresa $empresa): void
    {
        $this->form['nfse_provedor'] = $this->normalizarNfseProvedor($empresa?->nfse_provedor) ?? 'nacional';
        $this->form['nfse_ambiente'] = $this->codigoNfse($empresa?->nfse_ambiente);
        $this->form['nfse_reg_esp_trib'] = $this->codigoNfse($empresa?->nfse_reg_esp_trib);
        $this->form['nfse_reg_ap_trib_sn'] = $this->codigoNfse($empresa?->nfse_reg_ap_trib_sn);

        $serie = trim((string) ($empresa?->nfse_serie_dps ?? ''));
        $this->form['nfse_serie_dps'] = $serie !== '' ? $serie : Nfse::SERIE_DPS;

        $empresaId = (int) ($empresa?->id ?? 0);
        $this->form['nfse_proximo_dps'] = $empresaId > 0
            ? NfseDpsSequencia::proximoConfigurado($empresaId, $this->form['nfse_serie_dps'])
            : 1;

        $this->form['nfse_serie_rps'] = trim((string) ($empresa?->nfse_serie_rps ?? ''));
        $proximoRps = (int) ($empresa?->nfse_proximo_rps ?? 0);
        $this->form['nfse_proximo_rps'] = $proximoRps > 0 ? $proximoRps : 1;
        $this->form['nfse_tipo_rps'] = trim((string) ($empresa?->nfse_tipo_rps ?? '')) ?: '1';
        $this->form['nfse_ws_usuario'] = trim((string) ($empresa?->nfse_ws_usuario ?? ''));
        $this->form['nfse_ws_senha'] = (string) ($empresa?->nfse_ws_senha ?? '');
        $this->form['nfse_url_producao'] = trim((string) ($empresa?->nfse_url_producao ?? ''));
        $this->form['nfse_url_homologacao'] = trim((string) ($empresa?->nfse_url_homologacao ?? ''));
        $this->aplicarPadroesIpm();
    }

    protected function persistNfseRegime(int $empresaId): void
    {
        $proximoRps = (int) ($this->form['nfse_proximo_rps'] ?? 0);

        Empresa::query()->whereKey($empresaId)->update([
            'nfse_provedor' => $this->normalizarNfseProvedor($this->form['nfse_provedor'] ?? null) ?? 'nacional',
            'nfse_ambiente' => $this->normalizarNfseAmbiente($this->form['nfse_ambiente'] ?? null),
            'nfse_reg_esp_trib' => $this->codigoNfseOuNulo($this->form['nfse_reg_esp_trib'] ?? null),
            'nfse_reg_ap_trib_sn' => $this->codigoNfseOuNulo($this->form['nfse_reg_ap_trib_sn'] ?? null),
            'nfse_serie_rps' => $this->codigoNfseOuNulo($this->form['nfse_serie_rps'] ?? null),
            'nfse_proximo_rps' => $proximoRps > 0 ? $proximoRps : null,
            'nfse_tipo_rps' => $this->codigoNfseOuNulo($this->form['nfse_tipo_rps'] ?? null) ?? '1',
            'nfse_ws_usuario' => $this->codigoNfseOuNulo($this->form['nfse_ws_usuario'] ?? null),
            'nfse_ws_senha' => $this->codigoNfseOuNulo($this->form['nfse_ws_senha'] ?? null),
            'nfse_url_producao' => $this->codigoNfseOuNulo($this->form['nfse_url_producao'] ?? null),
            'nfse_url_homologacao' => $this->codigoNfseOuNulo($this->form['nfse_url_homologacao'] ?? null),
        ]);
    }

    protected function persistNfseNumeracao(int $empresaId): void
    {
        NfseDpsSequencia::configurar(
            $empresaId,
            (string) $this->form['nfse_serie_dps'],
            (int) $this->form['nfse_proximo_dps'],
        );
    }

    protected function aplicarPadroesIpm(): void
    {
        if (($this->form['nfse_provedor'] ?? 'nacional') !== 'ipm') {
            return;
        }

        if (trim((string) ($this->form['nfse_tipo_rps'] ?? '')) === '') {
            $this->form['nfse_tipo_rps'] = '1';
        }

        if ((int) ($this->form['nfse_proximo_rps'] ?? 0) < 1) {
            $this->form['nfse_proximo_rps'] = 1;
        }

        if (trim((string) ($this->form['nfse_ws_usuario'] ?? '')) !== '') {
            return;
        }

        $empresaId = $this->resolveEmpresaId();
        $cnpj = preg_replace('/\D/', '', (string) (Empresa::query()->whereKey($empresaId)->value('cnpj') ?? '')) ?? '';

        if ($cnpj !== '') {
            $this->form['nfse_ws_usuario'] = $cnpj;
        }
    }

    /**
     * @return 'nacional'|'ipm'|null
     */
    protected function normalizarNfseProvedor(mixed $valor): ?string
    {
        $texto = strtolower(trim((string) $valor));

        return in_array($texto, ['nacional', 'ipm'], true) ? $texto : null;
    }

    protected function normalizarNfseAmbiente(mixed $valor): ?string
    {
        $texto = strtolower(trim((string) $valor));

        return array_key_exists($texto, NfseSefinAmbiente::opcoes()) ? $texto : null;
    }

    protected function codigoNfse(mixed $valor): string
    {
        return trim((string) $valor);
    }

    protected function codigoNfseOuNulo(mixed $valor): ?string
    {
        $texto = $this->codigoNfse($valor);

        return $texto === '' ? null : $texto;
    }

    protected function resolveEmpresaId(): ?int
    {
        return \App\Support\Erp\ErpContext::currentEmpresaId();
    }
}
