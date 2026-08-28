#Requires -Version 5.1
<#
.SYNOPSIS
    Repara abertura do Unitec ERP no PC do cliente (loop de redirecionamento / cookie antigo).
    Uso remoto: enviar este script + bat; cliente dá dois cliques. Sem limpar cookie na mão.
#>

param(
    [string]$AppPath = 'C:\UNITECNOLOGIA_WEB'
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

if (-not (Test-Path -LiteralPath (Join-Path $AppPath 'artisan'))) {
    $near = Split-Path -Parent $PSScriptRoot
    if (Test-Path -LiteralPath (Join-Path $near 'artisan')) {
        $AppPath = $near
    }
}

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath
$AppUrl = Get-UnitecDefaultAppUrl
$launcherExe = Join-Path $AppPath 'bin\Unitec ERP.exe'

try {
    $envUrl = Get-UnitecEnvValue -AppPath $AppPath -Key 'APP_URL'
    if (-not [string]::IsNullOrWhiteSpace($envUrl)) {
        $AppUrl = $envUrl.Trim().TrimEnd('/')
    }
} catch {}

Write-Host ''
Write-Host '========================================' -ForegroundColor Cyan
Write-Host '  Reparar abertura - Unitec ERP' -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan
Write-Host ("Pasta: {0}" -f $AppPath)
Write-Host ''

# 1) Sessões PHP no servidor
$sessionsDir = Join-Path $AppPath 'storage\framework\sessions'
if (Test-Path -LiteralPath $sessionsDir) {
    Get-ChildItem -LiteralPath $sessionsDir -File -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne '.gitignore' } |
        Remove-Item -Force -ErrorAction SilentlyContinue
}

# 3) Reabre pelo launcher oficial (Unitec ERP.exe).
if (-not (Test-Path -LiteralPath $launcherExe)) {
    throw 'bin\Unitec ERP.exe nao encontrado. Reinstale o sistema ou rode build-erp-desktop.'
}

# Preferir servico Windows se existir.
$svc = Get-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
if ($svc) {
    if ($svc.Status -ne 'Running') {
        Start-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 3
    }
} else {
    Ensure-UnitecEnvFile -AppPath $AppPath -AppUrl $AppUrl | Out-Null
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 20 | Out-Null
}

New-UnitecDesktopShortcuts -AppPath $AppPath
Start-Process -FilePath $launcherExe -ArgumentList @('--app', $AppPath) -WorkingDirectory $AppPath

Write-Host ''
Write-Host '[OK] Sistema aberto com Unitec ERP.exe.' -ForegroundColor Green
Write-Host 'Login: USUARIO   Senha: 01' -ForegroundColor White
Write-Host ''
Start-Sleep -Seconds 4
exit 0
