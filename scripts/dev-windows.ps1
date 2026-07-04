#Requires -Version 5.1
<#
.SYNOPSIS
    Desenvolvimento nativo no Windows — PHP e MySQL na maquina (porta 8000).
.PARAMETER Port
    Porta do artisan serve (padrao 8000).
.PARAMETER BindHost
    Host de bind (padrao 0.0.0.0 = acessivel na rede local, p/ o app Forca de Vendas).
    Use 127.0.0.1 para restringir so a esta maquina.
.PARAMETER SkipBrowser
    Nao abre o navegador ao iniciar.
.PARAMETER Background
    Sobe o servidor em processo oculto (padrao: janela aberta com Ctrl+C).
#>

param(
    [int]$Port = 8000,
    [string]$BindHost = '0.0.0.0',
    [switch]$SkipBrowser,
    [switch]$SkipSetup,
    [switch]$Background
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Set-Location (Join-Path $PSScriptRoot '..')
$AppPath = (Get-Location).Path

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

Write-Host ''
Write-Host 'UNI SISTEMAS 3.0 — desenvolvimento Windows' -ForegroundColor Cyan
Write-Host ''

& (Join-Path $PSScriptRoot 'use-env-windows.ps1')

if (-not $SkipSetup -and -not (Test-UnitecEmbeddedRuntimeInstalled -AppPath $AppPath)) {
    Write-Host 'Primeira vez: instalando PHP + MariaDB em tools\ (pode demorar alguns minutos)...' -ForegroundColor Yellow
    & (Join-Path $PSScriptRoot 'setup-prerequisites.ps1') -AppPath $AppPath
}

$laragonPath = 'C:\laragon'
$useLegacyLaragon = (Test-Path (Join-Path $laragonPath 'laragon.exe')) -and
    -not (Test-UnitecEmbeddedRuntimeInstalled -AppPath $AppPath)

if ($useLegacyLaragon) {
    $null = Ensure-LaragonPhp84 -LaragonPath $laragonPath -SourceRoot $AppPath
    Initialize-UnitecRuntimePath -LaragonPath $laragonPath
    $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $laragonPath -MaxWaitSeconds 30 -ThrowOnFailure
} else {
    Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath
    Update-UnitecMysqlIniPerformance -AppPath $AppPath | Out-Null
    $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -MaxWaitSeconds 30 -ThrowOnFailure
}

Ensure-UnitecPhpIniForWindowsDev -AppPath $AppPath | Out-Null

$phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
if (-not (Test-Path $phpExe)) {
    throw "PHP nao encontrado em tools\php. Rode: .\scripts\setup-prerequisites.ps1 -AppPath `"$AppPath`""
}

if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
    Write-Host 'Instalando dependencias Composer...' -ForegroundColor White
    Push-Location $AppPath
    try {
        if (Get-Command composer -ErrorAction SilentlyContinue) {
            composer install --no-interaction
        } else {
            throw 'Composer nao encontrado. Instale em https://getcomposer.org'
        }
    } finally {
        Pop-Location
    }
}

Ensure-UnitecDatabaseFromEnv -AppPath $AppPath -LaragonPath $laragonPath
Ensure-UnitecApplicationSchema -AppPath $AppPath

if (Test-UnitecNeedsInitialSeed -AppPath $AppPath -LaragonPath $laragonPath) {
    Write-Host 'Populando banco demo (seed)...' -ForegroundColor White
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('db:seed', '--force') | Out-Null
}

Sync-UnitecEnvPerformanceSettings -AppPath $AppPath | Out-Null
Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:clear') -AllowFailure | Out-Null

$appUrl = "http://127.0.0.1:$Port"

Write-Host ''
Write-Host "Acesse: $appUrl/admin" -ForegroundColor Green
Write-Host 'Login demo: USUARIO / 01' -ForegroundColor DarkGray

if ($BindHost -eq '0.0.0.0') {
    $lanIp = $null
    try {
        $lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.IPAddress -match '^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)' } |
            Select-Object -First 1 -ExpandProperty IPAddress)
    } catch {}

    if ($lanIp) {
        Write-Host "App Forca de Vendas (rede): http://${lanIp}:$Port  (porta $Port liberada no firewall)" -ForegroundColor Cyan
    } else {
        Write-Host "Servidor publicado na rede (0.0.0.0:$Port) para o app Forca de Vendas." -ForegroundColor Cyan
    }
}

Write-Host ''

if (-not $SkipBrowser) {
    Start-Job -ScriptBlock {
        param($Url)
        Start-Sleep -Seconds 2
        Start-Process $Url
    } -ArgumentList ($appUrl + '/admin') | Out-Null
}

$null = Start-UnitecWhatsAppGateway -AppPath $AppPath
Write-Host 'WhatsApp: apos alterar o gateway, rode .\scripts\restart-whatsapp-gateway.ps1' -ForegroundColor DarkGray

if ($Background -and (Test-UnitecWebServerListening -Port $Port)) {
    Write-Host "Servidor ja ativo em $appUrl" -ForegroundColor Green

    return
}

$foreground = -not $Background
$started = Start-UnitecPhpArtisanServer -AppPath $AppPath -Port $Port -BindHost $BindHost -Foreground:$foreground

if (-not $started -and -not $foreground) {
    throw "Nao foi possivel iniciar o servidor na porta $Port."
}
