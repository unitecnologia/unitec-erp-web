#Requires -Version 5.1
<#
.SYNOPSIS
    [OBSOLETO] Antigo pipeline PowerShell de atualizacao.
.DESCRIPTION
    Pipeline oficial: bin\Unitec Atualizador.exe --zip <Unitec-ERP-Update.zip>
    ou atualizacao pela tela do ERP (que dispara o Atualizador).
    Este script redireciona para o Atualizador quando possivel.
#>

param(
    [string]$AppPath = '',
    [string]$MegaFolderUrl = '',
    [string]$ZipName = '',
    [string]$LocalZip = '',
    [switch]$LeigoMode,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot
$updater = Join-Path $AppPath 'bin\Unitec Atualizador.exe'

Write-Host ''
Write-Host '========================================' -ForegroundColor Yellow
Write-Host '  atualizar-sistema.ps1 esta OBSOLETO' -ForegroundColor Yellow
Write-Host '  Use: bin\Unitec Atualizador.exe' -ForegroundColor Yellow
Write-Host '========================================' -ForegroundColor Yellow
Write-Host ''

if (-not (Test-Path -LiteralPath $updater)) {
    throw 'bin\Unitec Atualizador.exe ausente. Reinstale o ERP ou rode scripts\build-erp-desktop.ps1.'
}

$argsList = @('--app', $AppPath, '--quiet')
if (-not [string]::IsNullOrWhiteSpace($LocalZip)) {
    if (-not (Test-Path -LiteralPath $LocalZip)) {
        throw "ZIP nao encontrado: $LocalZip"
    }
    $argsList += @('--zip', $LocalZip)
} else {
    Write-Warn 'Sem -LocalZip: o Atualizador so limpa/regenera cache (nao baixa da nuvem). Preferir tela do ERP ou --zip.'
}

$proc = Start-Process -FilePath $updater -ArgumentList $argsList -WorkingDirectory $AppPath -Wait -PassThru
exit ([int]$proc.ExitCode)
