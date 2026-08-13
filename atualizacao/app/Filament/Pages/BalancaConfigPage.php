<?php

namespace App\Filament\Pages;

use App\Support\Erp\Balanca\BalancaEtiquetaLayout;
use App\Support\Erp\Balanca\BalancaExportService;
use App\Support\Erp\Balanca\BalancaModel;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpSystemConfig;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BalancaConfigPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $title = '';

    protected static ?string $slug = 'balanca';

    protected static bool $shouldRegisterNavigation = false;

    public string $modelo = BalancaModel::DEFAULT;

    public string $modeloPadrao = BalancaModel::DEFAULT;

    public string $diretorio = BalancaModel::DEFAULT_DIRECTORY;

    public bool $usarComoPadrao = true;

    public string $status = '';

    public int $progressPercent = 0;

    public string $progressLabel = '';

    public string $feedbackMsg = '';

    public string $feedbackTipo = 'ok';

    public bool $running = false;

    public int $produtosGerados = 0;

    public bool $wroteToDisk = false;

    public ?string $downloadPath = null;

    public ?string $downloadName = null;

    /** @var list<array{name: string, bytes: int}> */
    public array $arquivos = [];

    public bool $showEtiquetas = false;

    public int $etiquetaModelo = BalancaEtiquetaLayout::DEFAULT_MODELO;

    public string $etiquetaPrefixo = BalancaEtiquetaLayout::DEFAULT_PREFIXO;

    public int $etiquetaDigitos = BalancaEtiquetaLayout::DEFAULT_DIGITOS;

    public string $etiquetaFeedbackMsg = '';

    public string $etiquetaFeedbackTipo = 'ok';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('balanca.access');
    }

    public function mount(): void
    {
        ErpScreen::set('Balança');
        $this->loadFromEmpresa();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
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

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            'erp-balanca-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.balanca.screen'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function modeloOptions(): array
    {
        $options = BalancaModel::options();
        $padrao = BalancaModel::normalize($this->modeloPadrao);

        if (isset($options[$padrao])) {
            $options[$padrao] = $options[$padrao].' · padrão';
        }

        return $options;
    }

    public function isModeloPadrao(): bool
    {
        return BalancaModel::normalize($this->modelo) === BalancaModel::normalize($this->modeloPadrao);
    }

    public function updatedModelo(mixed $value): void
    {
        $this->modelo = BalancaModel::normalize((string) $value);
        $this->usarComoPadrao = $this->isModeloPadrao();
    }

    public function updatedUsarComoPadrao(mixed $value): void
    {
        if ((bool) $value) {
            $this->definirModeloPadrao();

            return;
        }

        // Sempre há um modelo padrão; não permite desmarcar o atual.
        $this->usarComoPadrao = $this->isModeloPadrao();
    }

    public function definirModeloPadrao(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.update')) {
            $this->usarComoPadrao = $this->isModeloPadrao();

            return;
        }

        $this->modelo = BalancaModel::normalize($this->modelo);

        if ($this->isModeloPadrao()) {
            $this->usarComoPadrao = true;

            return;
        }

        $this->salvarModeloPadraoSilencioso();
        $this->modeloPadrao = $this->modelo;
        $this->usarComoPadrao = true;
        $this->setFeedback('ok', 'Modelo padrão: '.BalancaModel::options()[$this->modelo]);
    }

    public function loadFromEmpresa(): void
    {
        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        $modelo = trim((string) ($empresa?->param_balanca_modelo ?? ''));
        $this->modelo = BalancaModel::normalize($modelo !== '' ? $modelo : BalancaModel::DEFAULT);
        $this->modeloPadrao = $this->modelo;

        $dir = trim((string) ($empresa?->param_balanca_diretorio ?? ''));
        $this->diretorio = $dir !== ''
            ? app(BalancaExportService::class)->normalizeDirectory($dir)
            : BalancaModel::DEFAULT_DIRECTORY;

        $this->usarComoPadrao = true;
        $this->loadEtiquetaFromEmpresa($empresa);
    }

    protected function loadEtiquetaFromEmpresa(mixed $empresa): void
    {
        $modelo = $empresa?->param_balanca_etiqueta_modelo
            ?? $empresa?->param_pdv_modelo_balanca
            ?? BalancaEtiquetaLayout::DEFAULT_MODELO;

        $this->etiquetaModelo = BalancaEtiquetaLayout::normalizeModelo($modelo);
        $this->etiquetaPrefixo = BalancaEtiquetaLayout::normalizePrefixo(
            $empresa?->param_balanca_prefixo_barra ?? BalancaEtiquetaLayout::DEFAULT_PREFIXO
        );

        $digitos = $empresa?->param_balanca_digitos ?? null;
        $this->etiquetaDigitos = ($digitos !== null && $digitos !== '')
            ? BalancaEtiquetaLayout::normalizeDigitos($digitos)
            : BalancaEtiquetaLayout::digitosForModelo($this->etiquetaModelo);
    }

    /**
     * @return array<int, string>
     */
    public function etiquetaModeloOptions(): array
    {
        return BalancaEtiquetaLayout::options();
    }

    /**
     * @return list<array{modelo: int, title: string, digitos: int, valor: string, parts: list<array{v: string, role: string, cap: string}>}>
     */
    public function etiquetaDiagrams(): array
    {
        return BalancaEtiquetaLayout::diagrams();
    }

    public function openEtiquetas(): void
    {
        if ($this->running) {
            return;
        }

        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);
        $this->loadEtiquetaFromEmpresa($empresa);
        $this->clearEtiquetaFeedback();
        $this->showEtiquetas = true;
    }

    public function closeEtiquetas(): void
    {
        $this->showEtiquetas = false;
        $this->clearEtiquetaFeedback();
    }

    public function updatedEtiquetaModelo(mixed $value): void
    {
        $this->etiquetaModelo = BalancaEtiquetaLayout::normalizeModelo($value);
        $this->etiquetaDigitos = BalancaEtiquetaLayout::digitosForModelo($this->etiquetaModelo);
    }

    public function updatedEtiquetaPrefixo(mixed $value): void
    {
        $this->etiquetaPrefixo = BalancaEtiquetaLayout::normalizePrefixo($value);
    }

    public function updatedEtiquetaDigitos(mixed $value): void
    {
        $this->etiquetaDigitos = BalancaEtiquetaLayout::normalizeDigitos($value);
    }

    public function salvarEtiquetas(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.update')) {
            return;
        }

        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        if (! $empresa) {
            $this->setEtiquetaFeedback('erro', 'Empresa não encontrada.');

            return;
        }

        $this->etiquetaModelo = BalancaEtiquetaLayout::normalizeModelo($this->etiquetaModelo);
        $this->etiquetaPrefixo = BalancaEtiquetaLayout::normalizePrefixo($this->etiquetaPrefixo);
        $this->etiquetaDigitos = BalancaEtiquetaLayout::normalizeDigitos($this->etiquetaDigitos);

        $empresa->forceFill([
            'param_balanca_etiqueta_modelo' => $this->etiquetaModelo,
            'param_balanca_prefixo_barra' => $this->etiquetaPrefixo,
            'param_balanca_digitos' => $this->etiquetaDigitos,
            // Mantém PDV alinhado (mesmo campo legado Delphi).
            'param_pdv_modelo_balanca' => $this->etiquetaModelo,
        ])->save();

        $this->setEtiquetaFeedback('ok', 'Configuração de etiquetas gravada.');
    }

    public function selectEtiquetaDiagram(int $modelo): void
    {
        $this->etiquetaModelo = BalancaEtiquetaLayout::normalizeModelo($modelo);
        $this->etiquetaDigitos = BalancaEtiquetaLayout::digitosForModelo($this->etiquetaModelo);
    }

    public function gerarArquivo(): void
    {
        if ($this->running) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.generate')) {
            return;
        }

        $this->running = true;
        $this->clearFeedback();
        $this->status = 'Gerando arquivo…';
        $this->progressPercent = 8;
        $this->progressLabel = 'Preparando…';
        $this->downloadPath = null;
        $this->downloadName = null;
        $this->arquivos = [];
        $this->produtosGerados = 0;
        $this->wroteToDisk = false;

        try {
            $this->modelo = BalancaModel::normalize($this->modelo);
            $this->diretorio = app(BalancaExportService::class)->normalizeDirectory($this->diretorio);
            $this->salvarDiretorioSilencioso();

            if ($this->usarComoPadrao || $this->isModeloPadrao()) {
                $this->salvarModeloPadraoSilencioso();
                $this->modeloPadrao = $this->modelo;
            }

            $this->progressPercent = 35;
            $this->progressLabel = 'Gerando arquivos…';

            $result = app(BalancaExportService::class)->generate(
                $this->modelo,
                $this->diretorio,
                Auth::user()?->empresa_id
            );

            $this->produtosGerados = (int) ($result['produtos'] ?? 0);
            $this->wroteToDisk = (bool) ($result['wrote_to_disk'] ?? false);
            $this->arquivos = $result['files'] ?? [];
            $this->downloadPath = $result['download_path'] ?? null;
            $this->downloadName = $result['download_name'] ?? null;
            $this->status = (string) ($result['message'] ?? '');

            if ($result['ok'] ?? false) {
                $this->progressPercent = 100;
                $this->progressLabel = (string) $result['message'];
                $this->setFeedback(
                    $this->wroteToDisk ? 'ok' : 'info',
                    (string) $result['message']
                );
            } else {
                $this->progressPercent = 0;
                $this->progressLabel = (string) ($result['message'] ?? 'Falha ao gerar arquivo.');
                $this->setFeedback('erro', (string) ($result['message'] ?? 'Falha ao gerar arquivo.'));
            }
        } catch (Throwable $e) {
            report($e);
            $this->status = '';
            $this->progressPercent = 0;
            $this->progressLabel = 'Erro ao gerar arquivo.';
            $this->setFeedback('erro', 'Erro ao gerar arquivo: '.$e->getMessage());
        } finally {
            $this->running = false;
        }
    }

    public function downloadArquivo(): ?StreamedResponse
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.generate')) {
            return null;
        }

        $path = (string) ($this->downloadPath ?? '');
        $name = (string) ($this->downloadName ?? 'balanca.txt');

        if ($path === '' || ! is_file($path)) {
            $this->setFeedback('erro', 'Arquivo de download não encontrado. Gere novamente.');

            return null;
        }

        return response()->streamDownload(function () use ($path): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            fpassthru($handle);
            fclose($handle);
        }, $name, [
            'Content-Type' => str_ends_with(strtolower($name), '.zip')
                ? 'application/zip'
                : 'text/plain; charset=windows-1252',
        ]);
    }

    public function salvarDiretorio(): void
    {
        if ($this->running) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.update')) {
            return;
        }

        $this->diretorio = app(BalancaExportService::class)->normalizeDirectory($this->diretorio);
        $this->salvarDiretorioSilencioso();
    }

    public function selecionarPasta(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'balanca.update')) {
            return;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->setFeedback('info', 'Neste sistema, informe o caminho manualmente no campo.');

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $initial = trim($this->diretorio);
        if ($initial === '' || ! is_dir($initial)) {
            $initial = BalancaModel::DEFAULT_DIRECTORY;
        }
        $initial = str_replace('/', '\\', $initial);

        try {
            $selected = $this->pickWindowsFolder($initial);
        } catch (Throwable $e) {
            report($e);
            $this->setFeedback('erro', 'Não foi possível abrir o seletor de pasta: '.$e->getMessage());

            return;
        }

        if ($selected === null) {
            return;
        }

        $this->diretorio = $selected;
        $this->salvarDiretorioSilencioso();
        $this->setFeedback('ok', 'Pasta selecionada: '.$selected);
    }

    public function dismissFeedback(): void
    {
        $this->clearFeedback();
    }

    public function handleEscape(): void
    {
        if ($this->running) {
            return;
        }

        if ($this->showEtiquetas) {
            $this->closeEtiquetas();

            return;
        }

        $this->redirect(filament()->getUrl());
    }

    public function closeScreen(): void
    {
        $this->handleEscape();
    }

    protected function setEtiquetaFeedback(string $tipo, string $message): void
    {
        $this->etiquetaFeedbackTipo = in_array($tipo, ['ok', 'erro', 'info'], true) ? $tipo : 'info';
        $this->etiquetaFeedbackMsg = $message;
    }

    protected function clearEtiquetaFeedback(): void
    {
        $this->etiquetaFeedbackMsg = '';
        $this->etiquetaFeedbackTipo = 'ok';
    }

    public function dismissEtiquetaFeedback(): void
    {
        $this->clearEtiquetaFeedback();
    }

    protected function salvarModeloPadraoSilencioso(): void
    {
        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        if (! $empresa) {
            return;
        }

        $empresa->forceFill([
            'param_balanca_modelo' => BalancaModel::normalize($this->modelo),
        ])->save();
    }

    protected function salvarDiretorioSilencioso(): void
    {
        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        if (! $empresa) {
            return;
        }

        $empresa->forceFill([
            'param_balanca_diretorio' => app(BalancaExportService::class)->normalizeDirectory($this->diretorio),
        ])->save();
    }

    protected function setFeedback(string $tipo, string $message): void
    {
        $this->feedbackTipo = in_array($tipo, ['ok', 'erro', 'info'], true) ? $tipo : 'info';
        $this->feedbackMsg = $message;
    }

    protected function clearFeedback(): void
    {
        $this->feedbackMsg = '';
        $this->feedbackTipo = 'ok';
    }

    protected function pickWindowsFolder(string $initialPath): ?string
    {
        $initialPath = str_replace(['"', "'"], '', $initialPath);
        $script = <<<'PS1'
Add-Type -AssemblyName System.Windows.Forms
$dialog = New-Object System.Windows.Forms.FolderBrowserDialog
$dialog.Description = 'Selecione a pasta para gerar os arquivos da balança'
$dialog.ShowNewFolderButton = $true
$initial = $env:UNITEC_BALANCA_INITIAL
if ($initial -and (Test-Path -LiteralPath $initial)) {
    $dialog.SelectedPath = $initial
}
[void][System.Windows.Forms.Application]::EnableVisualStyles()
$result = $dialog.ShowDialog()
if ($result -eq [System.Windows.Forms.DialogResult]::OK -and $dialog.SelectedPath) {
    [Console]::Out.Write($dialog.SelectedPath)
}
PS1;

        $process = new \Symfony\Component\Process\Process([
            'powershell.exe',
            '-NoProfile',
            '-STA',
            '-ExecutionPolicy', 'Bypass',
            '-Command', $script,
        ]);
        $process->setTimeout(300);
        $process->setEnv([
            'UNITEC_BALANCA_INITIAL' => $initialPath,
            'SystemRoot' => (string) (getenv('SystemRoot') ?: 'C:\\Windows'),
            'WINDIR' => (string) (getenv('WINDIR') ?: 'C:\\Windows'),
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput().' '.$process->getOutput());

            throw new \RuntimeException($error !== '' ? $error : 'Falha ao abrir o diálogo de pasta.');
        }

        $path = trim($process->getOutput());

        if ($path === '') {
            return null;
        }

        return str_replace('/', '\\', $path);
    }
}
