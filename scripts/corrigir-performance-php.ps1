#Requires -Version 5.1
<#
.SYNOPSIS
    Liga OPcache (incluindo CLI do PHP) e sobe limites do PHP embutido.
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ScriptRoot = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = Split-Path -Parent $ScriptRoot
    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        $AppPath = $ScriptRoot
    }
    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        $AppPath = Split-Path -Parent $ScriptRoot
    }
}

. (Join-Path $ScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath
$ini = Join-Path $AppPath 'tools\php\php.ini'
if (-not (Test-Path $ini)) {
    throw ("php.ini nao encontrado: {0}" -f $ini)
}

Write-Host ''
Write-Host 'Ajuste rapido de performance no PHP embutido...' -ForegroundColor Cyan
Write-Host ("Arquivo: {0}" -f $ini) -ForegroundColor Gray
Write-Host ''

Ensure-UnitecPhpIniForWindowsDev -AppPath $AppPath | Out-Null
$null = Sync-UnitecEnvPerformanceSettings -AppPath $AppPath

Write-Host 'OK: php.ini / .env atualizados.' -ForegroundColor Green
Write-Host '  - opcache.enable=1' -ForegroundColor Gray
Write-Host '  - opcache.enable_cli=1  (CLI artisan / jobs)' -ForegroundColor Gray
Write-Host '  - opcache.file_cache + fallback (Windows/ASLR)' -ForegroundColor Gray
Write-Host '  - FRANKENPHP_NUM_THREADS no .env (HTTP)' -ForegroundColor Gray
Write-Host ''
Write-Host 'Agora rode: Reiniciar Servicos.bat' -ForegroundColor Cyan
Write-Host ''
