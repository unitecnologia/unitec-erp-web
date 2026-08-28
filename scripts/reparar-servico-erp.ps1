#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$AppPath = 'C:\UNITECNOLOGIA_WEB'
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$serviceName = 'UnitecErpServer'
$payloadBin = Join-Path $PSScriptRoot 'bin'
if (-not (Test-Path (Join-Path $payloadBin 'UnitecErpServer.exe'))) {
    $payloadBin = Join-Path (Split-Path -Parent $PSScriptRoot) 'bin'
}
$targetBin = Join-Path $AppPath 'bin'
$serverExe = Join-Path $targetBin 'UnitecErpServer.exe'
$launcherExe = Join-Path $targetBin 'Unitec ERP.exe'

if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
    throw "Instalação do ERP não encontrada em $AppPath."
}

if (-not (Test-Path (Join-Path $payloadBin 'UnitecErpServer.exe'))) {
    throw "Payload do serviço ausente em $payloadBin."
}

Write-Host ''
Write-Host '========================================' -ForegroundColor Cyan
Write-Host '  Reparar serviço do Unitec ERP' -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan
Write-Host "Pasta: $AppPath"

$service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($service -and $service.Status -ne 'Stopped') {
    Write-Host '>> Parando UnitecErpServer...'
    Stop-Service -Name $serviceName -Force
    $service.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
}

# O launcher pode estar aguardando o serviço e manter DLLs do bin bloqueadas.
Get-Process -Name 'Unitec ERP' -ErrorAction SilentlyContinue |
    Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 500

$backupDir = Join-Path $AppPath ('storage\backups\desktop-service-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if (Test-Path $targetBin) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Copy-Item (Join-Path $targetBin '*') $backupDir -Force -Recurse -ErrorAction SilentlyContinue
}

New-Item -ItemType Directory -Path $targetBin -Force | Out-Null
Copy-Item (Join-Path $payloadBin '*') $targetBin -Force -Recurse

if (-not (Test-Path $serverExe)) {
    throw "Falha ao instalar $serverExe."
}

$binPath = "`"$serverExe`""
if ($service) {
    sc.exe config $serviceName binPath= $binPath start= auto | Out-Null
} else {
    sc.exe create $serviceName binPath= $binPath start= auto DisplayName= 'Unitec ERP Server' | Out-Null
    sc.exe description $serviceName 'Mantém MariaDB embutido e Laravel (porta 8765) do Unitec ERP.' | Out-Null
}

$reg = "HKLM:\SYSTEM\CurrentControlSet\Services\$serviceName"
New-ItemProperty -Path $reg -Name 'Environment' -PropertyType MultiString `
    -Value @("UNITEC_APP_PATH=$AppPath") -Force | Out-Null

Write-Host '>> Iniciando UnitecErpServer...'
Start-Service -Name $serviceName

$healthy = $false
for ($i = 0; $i -lt 180; $i++) {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8765/api/health' `
            -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200) {
            $healthy = $true
            break
        }
    } catch {
        # serviço ainda inicializando
    }
    Start-Sleep -Milliseconds 500
}

if (-not $healthy) {
    Write-Host ''
    Write-Host 'O serviço iniciou, mas o health não respondeu.' -ForegroundColor Red
    Write-Host "Consulte: $AppPath\storage\logs\unitec-erp-desktop.log" -ForegroundColor Yellow
    throw 'ERP não ficou saudável na porta 8765.'
}

Write-Host '[OK] Serviço saudável na porta 8765.' -ForegroundColor Green

if (Test-Path $launcherExe) {
    Start-Process -FilePath $launcherExe -ArgumentList @('--app', $AppPath) -WorkingDirectory $AppPath
}

Write-Host '[OK] Reparo concluído. Reiniciar o serviço não deve mais criar dois PHP.' -ForegroundColor Green
Start-Sleep -Seconds 3
