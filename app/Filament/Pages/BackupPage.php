<?php

namespace App\Filament\Pages;

use App\Support\Erp\Backup\DatabaseBackupService;
use App\Support\Erp\EmpresaParametros;
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
use Throwable;

class BackupPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $title = '';

    protected static ?string $slug = 'backup';

    protected static bool $shouldRegisterNavigation = false;

    public string $pastaDestino = '';

    public int $intervaloHoras = 24;

    public bool $habilitarAutomatico = false;

    public string $portalBkpToken = '';

    public string $ultimoEm = '';

    public string $ultimoStatus = '';

    public string $mysqldumpPath = '';

    public bool $running = false;

    public bool $progressActive = false;

    public int $progressPct = 0;

    public string $progressLabel = '';

    public string $progressDetail = '';

    public string $progressStep = '';

    public string $feedbackMsg = '';

    public string $feedbackTipo = 'ok';

    /** @var list<array{name: string, path: string, kind?: string, size: int, size_label: string, modified_at: string, modified_ts: int}> */
    public array $arquivos = [];

    public bool $showRestoreModal = false;

    public string $pastaRestore = '';

    public string $arquivoRestorePath = '';

    public string $confirmacaoRestore = '';

    /** @var list<array{name: string, path: string, kind: string, size: int, size_label: string, modified_at: string, modified_ts: int}> */
    public array $arquivosRestore = [];

    public string $progressMode = 'backup';

    public function mount(): void
    {
        ErpScreen::set('Backup');
        $this->loadFromEmpresa();
        $this->refreshArquivos();
        $this->resolveMysqldumpLabel();
        $this->pastaRestore = $this->pastaDestino;
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('backup.access');
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
            'erp-backup-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.backup.screen'),
            ]);
    }

    public function loadFromEmpresa(): void
    {
        $empresaId = Auth::user()?->empresa_id;
        $empresa = ErpSystemConfig::empresa($empresaId);

        $this->pastaDestino = ErpSystemConfig::backupDestinationPath($empresaId);
        $this->intervaloHoras = ErpSystemConfig::backupIntervalHours($empresaId);
        $this->habilitarAutomatico = ErpSystemConfig::backupEnabled($empresaId);
        $this->portalBkpToken = (string) ($empresa?->param_portal_bkp_token ?? '');
        $this->ultimoEm = (string) (ErpSystemConfig::backupLastAt($empresaId) ?? '');
        $this->ultimoStatus = ErpSystemConfig::backupLastStatus($empresaId);

        if ($this->pastaDestino === '') {
            $this->pastaDestino = app(DatabaseBackupService::class)->resolveDestination($empresaId);
        }

        if ($empresa && filled($empresa->param_backup_ultimo_em)) {
            try {
                $this->ultimoEm = \Illuminate\Support\Carbon::parse((string) $empresa->param_backup_ultimo_em)->format('d/m/Y H:i:s');
            } catch (Throwable) {
                $this->ultimoEm = (string) $empresa->param_backup_ultimo_em;
            }
        }
    }

    public function refreshArquivos(): void
    {
        $this->arquivos = app(DatabaseBackupService::class)->listBackups(Auth::user()?->empresa_id);
    }

    public function resolveMysqldumpLabel(): void
    {
        try {
            $this->mysqldumpPath = app(DatabaseBackupService::class)->resolveMysqldumpPath();
        } catch (Throwable $e) {
            $this->mysqldumpPath = '';
        }
    }

    public function gerarBackup(): void
    {
        if ($this->running || $this->progressActive) {
            return;
        }

        // Dispara o fluxo Alpine com progresso em etapas (padrão Migra / fiscal).
        $this->js('(window.__erpBackupRun || (() => {}))()');
    }

    public function atualizarProgresso(int $pct, string $label, string $detail = '', string $step = ''): void
    {
        $this->progressActive = true;
        $this->progressPct = max(0, min(100, $pct));
        $this->progressLabel = $label;
        $this->progressDetail = $detail;
        if ($step !== '') {
            $this->progressStep = $step;
        }
        $this->clearFeedback();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function prepararBackup(): array
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.create')) {
            $this->resetProgress();

            return ['ok' => false, 'message' => 'Sem permissão.'];
        }

        if ($this->running) {
            return ['ok' => false, 'message' => 'Já existe um backup em andamento.'];
        }

        $this->running = true;
        $this->clearFeedback();
        $this->atualizarProgresso(12, 'Preparando backup…', 'Validando pasta e configuração', 'preparar');

        try {
            $this->salvarConfigSilencioso();
            $this->resolveMysqldumpLabel();

            if ($this->mysqldumpPath === '') {
                $this->failBackup('mysqldump não encontrado. Verifique a instalação do MySQL/MariaDB.');

                return ['ok' => false, 'message' => 'mysqldump não encontrado.'];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);
            $this->failBackup($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message?: string, path?: string, size?: int}
     */
    public function executarDump(): array
    {
        if (! $this->running) {
            return ['ok' => false, 'message' => 'Backup não iniciado.'];
        }

        $this->atualizarProgresso(42, 'Exportando banco de dados…', 'Gerando arquivo SQL com mysqldump', 'exportar');

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $result = app(DatabaseBackupService::class)->run(Auth::user()?->empresa_id, scheduled: false);

            if (! ($result['ok'] ?? false)) {
                $message = (string) ($result['message'] ?? 'Falha ao gerar backup.');
                $this->failBackup($message);

                return ['ok' => false, 'message' => $message];
            }

            return [
                'ok' => true,
                'message' => (string) ($result['message'] ?? 'Backup gerado.'),
                'path' => (string) ($result['path'] ?? ''),
                'size' => (int) ($result['size'] ?? 0),
            ];
        } catch (Throwable $e) {
            report($e);
            $this->failBackup($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array{ok?: bool, message?: string}  $dumpResult
     */
    public function finalizarBackup(array $dumpResult = []): void
    {
        $this->atualizarProgresso(92, 'Finalizando…', 'Atualizando status e lista de arquivos', 'finalizar');

        $this->loadFromEmpresa();
        $this->refreshArquivos();
        $this->resolveMysqldumpLabel();

        $message = (string) ($dumpResult['message'] ?? 'Backup concluído com sucesso.');

        $this->progressPct = 100;
        $this->progressLabel = 'Backup concluído';
        $this->progressDetail = $message;
        $this->progressStep = 'finalizar';
        $this->running = false;
        $this->progressActive = false;

        $this->setFeedback('ok', $message);
    }

    public function failBackup(string $message): void
    {
        $this->running = false;
        $this->progressActive = false;
        $this->progressPct = 0;
        $this->progressLabel = '';
        $this->progressDetail = '';
        $this->progressStep = '';
        $this->progressMode = 'backup';
        $this->loadFromEmpresa();
        $this->setFeedback('erro', $message !== '' ? $message : 'Falha ao gerar backup.');
    }

    public function abrirRestoreModal(?string $sqlPath = null): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.restore')) {
            return;
        }

        if ($this->running || $this->progressActive) {
            return;
        }

        $this->clearFeedback();
        $this->confirmacaoRestore = '';
        $this->showRestoreModal = true;

        if (is_string($sqlPath) && trim($sqlPath) !== '') {
            $validated = app(DatabaseBackupService::class)->validateSqlBackupPath($sqlPath);
            if ($validated['ok'] ?? false) {
                $this->arquivoRestorePath = (string) $validated['path'];
                $this->pastaRestore = dirname((string) $validated['path']);
                $this->refreshArquivosRestore();

                return;
            }
        }

        if (trim($this->pastaRestore) === '') {
            $this->pastaRestore = trim($this->pastaDestino) !== ''
                ? $this->pastaDestino
                : app(DatabaseBackupService::class)->resolveDestination(Auth::user()?->empresa_id);
        }

        $this->refreshArquivosRestore();

        if ($this->arquivoRestorePath === '' && $this->arquivosRestore !== []) {
            $this->arquivoRestorePath = (string) ($this->arquivosRestore[0]['path'] ?? '');
        }
    }

    public function fecharRestoreModal(): void
    {
        if ($this->running || $this->progressActive) {
            return;
        }

        $this->showRestoreModal = false;
        $this->confirmacaoRestore = '';
    }

    public function selecionarPastaRestore(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.restore')) {
            return;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->setFeedback('info', 'Neste sistema, informe o caminho manualmente no campo.');

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $initial = trim($this->pastaRestore);
        if ($initial === '' || ! is_dir($initial)) {
            $initial = trim($this->pastaDestino);
        }
        if ($initial === '' || ! is_dir($initial)) {
            $initial = app(DatabaseBackupService::class)->resolveDestination(Auth::user()?->empresa_id);
        }

        $initial = str_replace('/', '\\', $initial);

        try {
            $selected = $this->pickWindowsFolder($initial, 'Selecione a pasta onde está o backup');
        } catch (Throwable $e) {
            report($e);
            $this->setFeedback('erro', 'Não foi possível abrir o seletor de pasta: '.$e->getMessage());

            return;
        }

        if ($selected === null) {
            return;
        }

        $this->pastaRestore = $selected;
        $this->arquivoRestorePath = '';
        $this->refreshArquivosRestore();

        if ($this->arquivosRestore !== []) {
            $this->arquivoRestorePath = (string) ($this->arquivosRestore[0]['path'] ?? '');
        }
    }

    public function atualizarPastaRestore(): void
    {
        $this->arquivoRestorePath = '';
        $this->refreshArquivosRestore();

        if ($this->arquivosRestore !== []) {
            $this->arquivoRestorePath = (string) ($this->arquivosRestore[0]['path'] ?? '');
        }
    }

    public function refreshArquivosRestore(): void
    {
        $pasta = trim($this->pastaRestore);
        $this->arquivosRestore = $pasta !== ''
            ? app(DatabaseBackupService::class)->listSqlBackupsInDirectory($pasta)
            : [];

        if ($this->arquivoRestorePath !== '') {
            $stillThere = collect($this->arquivosRestore)
                ->contains(fn (array $item): bool => ($item['path'] ?? '') === $this->arquivoRestorePath);

            if (! $stillThere) {
                $this->arquivoRestorePath = '';
            }
        }
    }

    public function confirmarRestoreEExecutar(): void
    {
        if ($this->running || $this->progressActive) {
            return;
        }

        $this->js('(window.__erpBackupRestore || (() => {}))()');
    }

    /**
     * @return array{ok: bool, message?: string, path?: string}
     */
    public function prepararRestore(): array
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.restore')) {
            $this->resetProgress();

            return ['ok' => false, 'message' => 'Sem permissão.'];
        }

        if ($this->running) {
            return ['ok' => false, 'message' => 'Já existe uma operação em andamento.'];
        }

        $confirm = mb_strtoupper(trim($this->confirmacaoRestore));
        if ($confirm !== 'RESTAURAR') {
            $this->resetProgress();
            $this->setFeedback('erro', 'Digite RESTAURAR para confirmar a restauração.');

            return ['ok' => false, 'message' => 'Confirmação inválida.'];
        }

        $validated = app(DatabaseBackupService::class)->validateSqlBackupPath($this->arquivoRestorePath);
        if (! ($validated['ok'] ?? false)) {
            $this->resetProgress();
            $message = (string) ($validated['message'] ?? 'Arquivo inválido.');
            $this->setFeedback('erro', $message);

            return ['ok' => false, 'message' => $message];
        }

        $this->running = true;
        $this->progressMode = 'restore';
        $this->showRestoreModal = false;
        $this->clearFeedback();
        $this->atualizarProgresso(10, 'Preparando restauração…', 'Validando arquivo e gerando backup de segurança', 'preparar');

        try {
            app(DatabaseBackupService::class)->resolveMysqlClientPath();

            return [
                'ok' => true,
                'path' => (string) $validated['path'],
            ];
        } catch (Throwable $e) {
            report($e);
            $this->failRestore($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message?: string, safety_path?: string}
     */
    public function executarRestore(string $sqlPath = ''): array
    {
        if (! $this->running) {
            return ['ok' => false, 'message' => 'Restauração não iniciada.'];
        }

        $path = trim($sqlPath) !== '' ? trim($sqlPath) : trim($this->arquivoRestorePath);

        $this->atualizarProgresso(35, 'Gerando backup de segurança…', 'Cópia do banco atual antes de sobrescrever', 'exportar');

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(1200);
            }

            $this->atualizarProgresso(55, 'Importando dump…', 'Restaurando banco a partir do arquivo selecionado', 'exportar');

            $result = app(DatabaseBackupService::class)->restore($path, Auth::user()?->empresa_id);

            if (! ($result['ok'] ?? false)) {
                $message = (string) ($result['message'] ?? 'Falha ao restaurar backup.');
                $this->failRestore($message);

                return ['ok' => false, 'message' => $message];
            }

            return [
                'ok' => true,
                'message' => (string) ($result['message'] ?? 'Restauração concluída.'),
                'safety_path' => (string) ($result['safety_path'] ?? ''),
            ];
        } catch (Throwable $e) {
            report($e);
            $this->failRestore($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array{ok?: bool, message?: string}  $result
     */
    public function finalizarRestore(array $result = []): void
    {
        $this->atualizarProgresso(92, 'Finalizando…', 'Atualizando lista de arquivos', 'finalizar');

        $this->loadFromEmpresa();
        $this->refreshArquivos();
        $this->refreshArquivosRestore();
        $this->confirmacaoRestore = '';

        $message = (string) ($result['message'] ?? 'Restauração concluída com sucesso.');

        $this->progressPct = 100;
        $this->progressLabel = 'Restauração concluída';
        $this->progressDetail = $message;
        $this->progressStep = 'finalizar';
        $this->running = false;
        $this->progressActive = false;
        $this->progressMode = 'backup';

        $this->setFeedback('ok', $message);
    }

    public function failRestore(string $message): void
    {
        $this->running = false;
        $this->progressActive = false;
        $this->progressPct = 0;
        $this->progressLabel = '';
        $this->progressDetail = '';
        $this->progressStep = '';
        $this->progressMode = 'backup';
        $this->loadFromEmpresa();
        $this->refreshArquivos();
        $this->setFeedback('erro', $message !== '' ? $message : 'Falha ao restaurar backup.');
    }

    public function salvarConfig(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.update')) {
            return;
        }

        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        if (! $empresa) {
            $this->setFeedback('erro', 'Empresa não encontrada.');

            return;
        }

        $pasta = trim($this->pastaDestino);
        $intervalo = max(1, (int) $this->intervaloHoras);

        $empresa->forceFill([
            'param_backup_pasta_destino' => $pasta !== '' ? $pasta : null,
            'param_backup_intervalo_horas' => $intervalo,
            'param_backup_habilitar' => (bool) $this->habilitarAutomatico,
            'param_portal_bkp_token' => trim($this->portalBkpToken),
        ])->save();

        $this->intervaloHoras = $intervalo;
        $this->pastaDestino = $pasta !== '' ? $pasta : app(DatabaseBackupService::class)->resolveDestination($empresa->id);

        $this->setFeedback('ok', 'Configuração de backup salva.');
    }

    public function selecionarPasta(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'backup.update')) {
            return;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->setFeedback('info', 'Neste sistema, informe o caminho manualmente no campo.');

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $initial = trim($this->pastaDestino);
        if ($initial === '' || ! is_dir($initial)) {
            $initial = app(DatabaseBackupService::class)->resolveDestination(Auth::user()?->empresa_id);
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

        $this->pastaDestino = $selected;
        $this->setFeedback('ok', 'Pasta selecionada: '.$selected);
    }

    public function abrirPasta(): void
    {
        $path = trim($this->pastaDestino);
        if ($path === '') {
            $path = app(DatabaseBackupService::class)->resolveDestination(Auth::user()?->empresa_id);
        }

        if (! is_dir($path)) {
            try {
                \Illuminate\Support\Facades\File::ensureDirectoryExists($path);
            } catch (Throwable) {
                $this->setFeedback('erro', 'Pasta inválida.');

                return;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = str_replace('/', '\\', $path);
            pclose(popen('start "" explorer "'.$normalized.'"', 'r'));
            $this->setFeedback('ok', 'Pasta aberta no Explorer.');

            return;
        }

        $this->setFeedback('info', 'Pasta de backup: '.$path);
    }

    public function dismissFeedback(): void
    {
        $this->clearFeedback();
    }

    public function handleEscape(): void
    {
        if ($this->running || $this->progressActive) {
            return;
        }

        if ($this->showRestoreModal) {
            $this->fecharRestoreModal();

            return;
        }

        $this->redirect(filament()->getUrl());
    }

    public function statusLabel(): string
    {
        $options = EmpresaParametros::sistemaBackupStatusOptions();

        return $options[$this->ultimoStatus] ?? ($this->ultimoStatus !== '' ? $this->ultimoStatus : 'Nunca executado');
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

    protected function resetProgress(): void
    {
        $this->running = false;
        $this->progressActive = false;
        $this->progressPct = 0;
        $this->progressLabel = '';
        $this->progressDetail = '';
        $this->progressStep = '';
        $this->progressMode = 'backup';
    }

    protected function salvarConfigSilencioso(): void
    {
        $empresa = ErpSystemConfig::empresa(Auth::user()?->empresa_id);

        if (! $empresa) {
            return;
        }

        $pasta = trim($this->pastaDestino);

        $empresa->forceFill([
            'param_backup_pasta_destino' => $pasta !== '' ? $pasta : null,
            'param_backup_intervalo_horas' => max(1, (int) $this->intervaloHoras),
            'param_backup_habilitar' => (bool) $this->habilitarAutomatico,
        ])->save();
    }

    protected function pickWindowsFolder(string $initialPath, string $description = 'Selecione a pasta para salvar os backups'): ?string
    {
        $initialPath = str_replace(['"', "'"], '', $initialPath);
        $description = str_replace(['"', "'"], '', $description);
        $script = <<<'PS1'
Add-Type -AssemblyName System.Windows.Forms
$dialog = New-Object System.Windows.Forms.FolderBrowserDialog
$dialog.Description = $env:UNITEC_BACKUP_DIALOG_DESC
$dialog.ShowNewFolderButton = $true
$initial = $env:UNITEC_BACKUP_INITIAL
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
            'UNITEC_BACKUP_INITIAL' => $initialPath,
            'UNITEC_BACKUP_DIALOG_DESC' => $description,
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
