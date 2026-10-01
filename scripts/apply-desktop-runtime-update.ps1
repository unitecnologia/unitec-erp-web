#Requires -Version 5.1
<#
.SYNOPSIS
    Aplica binarios pendentes do UnitecErpServer e FrankenPHP apos o apply do ZIP.

.DESCRIPTION
    Chamado em background pelo apply da atualizacao quando o pacote traz
    runtime-update. Para o servico, aplica tools\frankenphp e bin\ a partir de
    storage\app\unitec-desktop-pending, e sobe de novo — necessario para sair do
    legado php -S 127.0.0.1 e usar FrankenPHP em 0.0.0.0. FrankenPHP/DLLs so
    sao trocados com o processo parado (evita falha em brotlicommon.dll etc.).
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$AppPath
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$AppPath = [System.IO.Path]::GetFullPath($AppPath).TrimEnd('\')
$serviceName = 'UnitecErpServer'
$pendingRoot = Join-Path $AppPath 'storage\app\unitec-desktop-pending'
$pendingBin = Join-Path $pendingRoot 'bin'
$pendingFranken = Join-Path $pendingRoot 'frankenphp'
$targetBin = Join-Path $AppPath 'bin'
$targetFranken = Join-Path $AppPath 'tools\frankenphp'
$serverExe = Join-Path $targetBin 'UnitecErpServer.exe'
$logPath = Join-Path $AppPath 'storage\logs\desktop-runtime-update.log'

function Write-Log([string]$Message) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    try {
        $dir = Split-Path $logPath
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        Add-Content -Path $logPath -Value $line -Encoding UTF8
    } catch { }
    Write-Host $line
}

Write-Log "inicio AppPath=$AppPath"

if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
    throw "Instalacao do ERP nao encontrada em $AppPath."
}

if (-not (Test-Path (Join-Path $pendingBin 'UnitecErpServer.exe'))) {
    Write-Log "nada a fazer: pending sem UnitecErpServer.exe ($pendingBin)"
    exit 0
}

# Deixa o apply do Laravel encerrar (copia/migrate/caches) antes de derrubar o servico.
Start-Sleep -Seconds 8

$service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($service -and $service.Status -ne 'Stopped') {
    Write-Log 'parando UnitecErpServer...'
    try {
        Stop-Service -Name $serviceName -Force -ErrorAction Stop
        $service.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(45))
    } catch {
        Write-Log ("Stop-Service falhou: {0} — tentando sc stop" -f $_.Exception.Message)
        & sc.exe stop $serviceName | Out-Null
        Start-Sleep -Seconds 5
    }
}

Get-Process -Name 'Unitec ERP','UnitecErpServer','frankenphp' -ErrorAction SilentlyContinue |
    Stop-Process -Force -ErrorAction SilentlyContinue

# Legado php -S na 8765.
Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and ($_.CommandLine -match '127\.0\.0\.1:8765|-S\s+0\.0\.0\.0:8765|-S\s+8765') } |
    ForEach-Object {
        Write-Log ("matando php legado PID={0}" -f $_.ProcessId)
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Start-Sleep -Seconds 2

$backupDir = Join-Path $AppPath ('storage\backups\desktop-service-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if (Test-Path $targetBin) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Copy-Item (Join-Path $targetBin '*') (Join-Path $backupDir 'bin') -Force -Recurse -ErrorAction SilentlyContinue
    Write-Log "backup bin -> $backupDir\bin"
}
if (Test-Path $targetFranken) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Copy-Item (Join-Path $targetFranken '*') (Join-Path $backupDir 'frankenphp') -Force -Recurse -ErrorAction SilentlyContinue
    Write-Log "backup frankenphp -> $backupDir\frankenphp"
}

# FrankenPHP (e DLLs como brotlicommon.dll) so pode ser trocado com o processo parado.
if (Test-Path (Join-Path $pendingFranken 'frankenphp.exe')) {
    New-Item -ItemType Directory -Path $targetFranken -Force | Out-Null
    Copy-Item (Join-Path $pendingFranken '*') $targetFranken -Force -Recurse
    Write-Log "frankenphp aplicado de $pendingFranken"
} else {
    Write-Log "frankenphp pendente ausente — mantendo tools\frankenphp atual"
}

New-Item -ItemType Directory -Path $targetBin -Force | Out-Null
Copy-Item (Join-Path $pendingBin '*') $targetBin -Force -Recurse
Write-Log "binarios aplicados de $pendingBin"

if (-not (Test-Path $serverExe)) {
    throw "Falha ao instalar $serverExe."
}

$binPath = "`"$serverExe`""
if ($service) {
    & sc.exe config $serviceName binPath= $binPath start= auto | Out-Null
} else {
    & sc.exe create $serviceName binPath= $binPath start= auto DisplayName= 'Unitec ERP Server' | Out-Null
    & sc.exe description $serviceName 'Mantem MariaDB embutido e Laravel (porta 8765) do Unitec ERP.' | Out-Null
}

$reg = "HKLM:\SYSTEM\CurrentControlSet\Services\$serviceName"
if (Test-Path $reg) {
    New-ItemProperty -Path $reg -Name 'Environment' -PropertyType MultiString `
        -Value @("UNITEC_APP_PATH=$AppPath") -Force | Out-Null
}

Write-Log 'iniciando UnitecErpServer...'
Start-Service -Name $serviceName -ErrorAction SilentlyContinue
& sc.exe start $serviceName | Out-Null

$healthy = $false
for ($i = 0; $i -lt 90; $i++) {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8765/api/health' `
            -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200) {
            $healthy = $true
            Write-Log ("health OK: {0}" -f $response.Content)
            break
        }
    } catch {
        Start-Sleep -Seconds 2
    }
}

$listen = netstat -ano | Select-String ':8765' | Select-String 'LISTENING'
Write-Log ("listener: {0}" -f (($listen | ForEach-Object { $_.Line.Trim() }) -join ' | '))

if (-not $healthy) {
    Write-Log 'AVISO: health nao respondeu a tempo apos troca do desktop.'
    exit 1
}

try {
    Remove-Item -LiteralPath (Join-Path $AppPath 'storage\app\unitec-desktop-pending') -Recurse -Force -ErrorAction SilentlyContinue
} catch { }

Write-Log 'concluido'
exit 0
