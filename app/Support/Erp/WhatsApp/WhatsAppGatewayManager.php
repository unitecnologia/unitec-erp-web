<?php

namespace App\Support\Erp\WhatsApp;

use App\Models\Empresa;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class WhatsAppGatewayManager
{
    public function gatewayRoot(): string
    {
        return base_path('services/erp-whatsapp-gateway');
    }

    public function configPath(): string
    {
        return storage_path('app/whatsapp/gateway-config.json');
    }

    public function pidPath(): string
    {
        return storage_path('app/whatsapp/gateway.pid');
    }

    public function sessionsPath(): string
    {
        return storage_path('app/whatsapp/sessions');
    }

    public function logPath(): string
    {
        return storage_path('logs/whatsapp-gateway.log');
    }

    public function resolveOrCreateKey(Empresa $empresa): string
    {
        $existing = trim((string) ($empresa->param_whatsapp_interno_chave ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        $shared = Empresa::query()
            ->whereNotNull('param_whatsapp_interno_chave')
            ->where('param_whatsapp_interno_chave', '!=', '')
            ->value('param_whatsapp_interno_chave');

        if (filled($shared)) {
            $empresa->forceFill(['param_whatsapp_interno_chave' => $shared])->save();

            return (string) $shared;
        }

        $key = Str::random(64);
        $empresa->forceFill(['param_whatsapp_interno_chave' => $key])->save();

        return $key;
    }

    public function writeRuntimeConfig(Empresa $empresa): void
    {
        $key = $this->resolveOrCreateKey($empresa);
        $config = WhatsAppConfig::fromEmpresa($empresa->fresh());

        File::ensureDirectoryExists(dirname($this->configPath()));
        File::ensureDirectoryExists($this->sessionsPath());

        File::put($this->configPath(), json_encode([
            'port' => $config->gatewayPort,
            'key' => $key,
            'sessionsPath' => $this->sessionsPath(),
            'host' => '127.0.0.1',
            'nodeExecutable' => $this->resolveNodeExecutable(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function resolveRuntimeKey(WhatsAppConfig $config): string
    {
        if (is_file($this->configPath())) {
            $raw = json_decode((string) File::get($this->configPath()), true);

            if (is_array($raw)) {
                $fileKey = trim((string) ($raw['key'] ?? ''));

                if ($fileKey !== '') {
                    return $fileKey;
                }
            }
        }

        return $config->internoChave;
    }

    public function clearLocalSession(Empresa $empresa): void
    {
        $sessionDir = $this->sessionsPath().DIRECTORY_SEPARATOR.$empresa->getKey();

        if (is_dir($sessionDir)) {
            File::deleteDirectory($sessionDir);
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function restartGateway(Empresa $empresa): array
    {
        $preflight = $this->preflightChecks();

        if (! $preflight['ok']) {
            return $preflight;
        }

        $this->writeRuntimeConfig($empresa);
        $config = WhatsAppConfig::fromEmpresa($empresa->fresh() ?? $empresa);
        $this->stopGatewayProcess($config);

        $started = $this->startGatewayProcess();

        if (! $started) {
            return [
                'ok' => false,
                'message' => 'Não foi possível iniciar o serviço Node.'.$this->logHint(),
            ];
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            usleep(400_000);

            if ($this->isHealthy($config)) {
                return [
                    'ok' => true,
                    'message' => 'Serviço Node iniciado e respondendo.',
                ];
            }
        }

        return [
            'ok' => false,
            'message' => 'Serviço iniciado, mas ainda não respondeu.'.$this->logHint(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function ensureRunning(Empresa $empresa): array
    {
        $this->writeRuntimeConfig($empresa);

        $empresa = $empresa->fresh() ?? $empresa;
        $config = WhatsAppConfig::fromEmpresa($empresa);
        $empresaId = (int) $empresa->getKey();
        $key = $this->resolveOrCreateKey($empresa);

        if ($this->isHealthy($config)) {
            if (! $this->isAuthenticated($config, $empresaId, $key)) {
                $this->writeRuntimeConfig($empresa->fresh());
                $key = $this->resolveOrCreateKey($empresa->fresh());
            }

            if ($this->isAuthenticated($config, $empresaId, $key)) {
                return [
                    'ok' => true,
                    'message' => 'Serviço WhatsApp interno ativo.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'Gateway ativo, mas a chave interna não confere. Salve a empresa e rode scripts\\restart-whatsapp-gateway.ps1',
            ];
        }

        $preflight = $this->preflightChecks();

        if (! $preflight['ok']) {
            return $preflight;
        }

        $started = $this->startGatewayProcess();

        if (! $started) {
            return [
                'ok' => false,
                'message' => 'Não foi possível iniciar o serviço WhatsApp interno.'.$this->logHint(),
            ];
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            usleep(400_000);

            if ($this->isHealthy($config)) {
                return [
                    'ok' => true,
                    'message' => 'Serviço WhatsApp interno iniciado.',
                ];
            }
        }

        return [
            'ok' => false,
            'message' => 'Serviço iniciado, mas não respondeu a tempo.'.$this->logHint(),
        ];
    }

    public function isHealthy(WhatsAppConfig $config): bool
    {
        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->get($config->gatewayBaseUrl().'/health');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isAuthenticated(WhatsAppConfig $config, int $empresaId, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->withHeaders([
                    'X-Erp-Gateway-Key' => $key,
                ])
                ->get($config->gatewayBaseUrl().'/sessions/'.$empresaId.'/status');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function stopGatewayProcess(WhatsAppConfig $config): void
    {
        $port = $config->gatewayPort;

        if (is_file($this->pidPath())) {
            $pid = trim((string) File::get($this->pidPath()));

            if ($pid !== '' && $pid !== 'windows-background' && ctype_digit($pid)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    $process = Process::fromShellCommandline('taskkill /F /PID '.$pid.' /T');
                    $process->run();
                } else {
                    $process = new Process(['kill', $pid]);
                    $process->run();
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf(
                'powershell -NoProfile -Command "$p = Get-NetTCPConnection -LocalPort %d -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty OwningProcess; if ($p) { Stop-Process -Id $p -Force -ErrorAction SilentlyContinue }"',
                $port,
            );
            $process = Process::fromShellCommandline($command);
            $process->run();
        }

        if (is_file($this->pidPath())) {
            @unlink($this->pidPath());
        }

        usleep(500_000);
    }

    public function nodeExecutable(): ?string
    {
        return $this->resolveNodeExecutable();
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function preflightChecks(): array
    {
        if (! $this->nodeAvailable()) {
            return [
                'ok' => false,
                'message' => 'Node.js não encontrado. Instale Node 20+ ou use o runtime em tools\\node.',
            ];
        }

        $index = $this->gatewayRoot().DIRECTORY_SEPARATOR.'index.js';

        if (! is_file($index)) {
            return [
                'ok' => false,
                'message' => 'Gateway não encontrado em services/erp-whatsapp-gateway/index.js',
            ];
        }

        $baileys = $this->gatewayRoot()
            .DIRECTORY_SEPARATOR.'node_modules'
            .DIRECTORY_SEPARATOR.'@whiskeysockets'
            .DIRECTORY_SEPARATOR.'baileys'
            .DIRECTORY_SEPARATOR.'package.json';

        if (! is_file($baileys)) {
            return [
                'ok' => false,
                'message' => 'Dependências do gateway não instaladas. Rode: cd services/erp-whatsapp-gateway && npm install',
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    protected function nodeAvailable(): bool
    {
        return $this->resolveNodeExecutable() !== null;
    }

    protected function resolveNodeExecutable(): ?string
    {
        if (is_file($this->configPath())) {
            $raw = json_decode((string) File::get($this->configPath()), true);

            if (is_array($raw)) {
                $configured = trim((string) ($raw['nodeExecutable'] ?? ''));

                if ($configured !== '' && is_file($configured)) {
                    return $configured;
                }
            }
        }

        $embedded = base_path('tools'.DIRECTORY_SEPARATOR.'node'.DIRECTORY_SEPARATOR.'node.exe');

        if (is_file($embedded)) {
            return $embedded;
        }

        $nodeRoot = base_path('tools'.DIRECTORY_SEPARATOR.'node');

        if (is_dir($nodeRoot)) {
            $matches = glob($nodeRoot.DIRECTORY_SEPARATOR.'node-v*-win-x64'.DIRECTORY_SEPARATOR.'node.exe');

            if (is_array($matches) && $matches !== []) {
                rsort($matches);

                if (is_file($matches[0])) {
                    return $matches[0];
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([
                getenv('ProgramFiles').DIRECTORY_SEPARATOR.'nodejs'.DIRECTORY_SEPARATOR.'node.exe',
                getenv('ProgramFiles(x86)').DIRECTORY_SEPARATOR.'nodejs'.DIRECTORY_SEPARATOR.'node.exe',
                getenv('LOCALAPPDATA').DIRECTORY_SEPARATOR.'Programs'.DIRECTORY_SEPARATOR.'node'.DIRECTORY_SEPARATOR.'node.exe',
            ] as $candidate) {
                if (is_string($candidate) && is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        $process = new Process(['node', '-v']);
        $process->run();

        if ($process->isSuccessful()) {
            return 'node';
        }

        return null;
    }

    protected function startGatewayProcess(): bool
    {
        $nodeExecutable = $this->resolveNodeExecutable();

        if ($nodeExecutable === null) {
            return false;
        }

        if (is_file($this->pidPath())) {
            $pid = trim((string) File::get($this->pidPath()));

            if ($pid !== '' && $this->isProcessRunning($pid)) {
                return true;
            }
        }

        $index = $this->gatewayRoot().DIRECTORY_SEPARATOR.'index.js';

        if (! is_file($index)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($this->pidPath()));
        File::ensureDirectoryExists(dirname($this->logPath()));

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->startGatewayProcessWindows($nodeExecutable);
        }

        $process = new Process([$nodeExecutable, 'index.js'], $this->gatewayRoot());
        $process->setTimeout(null);
        $process->start();

        if ($process->getPid() !== null) {
            File::put($this->pidPath(), (string) $process->getPid());
        }

        return true;
    }

    protected function startGatewayProcessWindows(string $nodeExecutable): bool
    {
        $nodeEscaped = str_replace("'", "''", $nodeExecutable);
        $workEscaped = str_replace("'", "''", $this->gatewayRoot());
        $logEscaped = str_replace("'", "''", $this->logPath());

        // Mesmo padrão do Start-UnitecWhatsAppGateway (install-lib): Start-Process Hidden + PID real.
        $script = <<<POWERSHELL
\$ErrorActionPreference = 'Stop'
\$log = '{$logEscaped}'
\$dir = Split-Path -Parent \$log
if (-not (Test-Path \$dir)) { New-Item -ItemType Directory -Path \$dir -Force | Out-Null }
Add-Content -Path \$log -Value ("{0} --- start {1} index.js" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), '{$nodeEscaped}')
\$p = Start-Process -FilePath '{$nodeEscaped}' -ArgumentList 'index.js' -WorkingDirectory '{$workEscaped}' -WindowStyle Hidden -PassThru
if (-not \$p) { exit 1 }
Start-Sleep -Milliseconds 800
if (\$p.HasExited) {
  Add-Content -Path \$log -Value ("{0} processo encerrou imediatamente exit={1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), \$p.ExitCode)
  exit 2
}
Write-Output \$p.Id
POWERSHELL;

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-NonInteractive',
            '-Command',
            $script,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            File::append(
                $this->logPath(),
                date('Y-m-d H:i:s').' start falhou: '.$process->getErrorOutput().' '.$process->getOutput().PHP_EOL
            );

            return false;
        }

        $pid = trim($process->getOutput());

        if ($pid === '' || ! ctype_digit($pid)) {
            File::append(
                $this->logPath(),
                date('Y-m-d H:i:s').' start sem PID. stdout='.$process->getOutput().' stderr='.$process->getErrorOutput().PHP_EOL
            );

            return false;
        }

        File::put($this->pidPath(), $pid);

        return true;
    }

    protected function isProcessRunning(string $pid): bool
    {
        if ($pid === 'windows-background') {
            return $this->isHealthy(WhatsAppConfig::fromFormData([
                'param_whatsapp_gateway_port' => 8091,
            ]));
        }

        if (! ctype_digit($pid)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['tasklist', '/FI', 'PID eq '.$pid]);
            $process->run();

            return str_contains($process->getOutput(), $pid);
        }

        return file_exists('/proc/'.$pid);
    }

    protected function logHint(): string
    {
        $tail = $this->tailLog(12);

        if ($tail === '') {
            return ' Veja storage/logs/whatsapp-gateway.log';
        }

        return ' Detalhe: '.$tail;
    }

    protected function tailLog(int $lines = 12): string
    {
        if (! is_file($this->logPath())) {
            return '';
        }

        try {
            $content = (string) File::get($this->logPath());
            $parts = preg_split("/\r\n|\n|\r/", trim($content)) ?: [];
            $parts = array_values(array_filter($parts, static fn (string $line): bool => trim($line) !== ''));
            $slice = array_slice($parts, -$lines);
            $text = trim(implode(' | ', $slice));

            if (strlen($text) > 400) {
                return substr($text, -400);
            }

            return $text;
        } catch (\Throwable) {
            return '';
        }
    }
}
