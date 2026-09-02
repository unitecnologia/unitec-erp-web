#Requires -Version 5.1
<#
.SYNOPSIS
    Sobe o HTTP de desenvolvimento do ERP via FrankenPHP (porta 8000).
    Usado por composer dev / scripts oficiais. Fail-closed sem servidor embutido do PHP.
#>
param(
    [int]$Port = 8000,
    [string]$BindHost = '0.0.0.0'
)

$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$AppPath = (Get-Location).Path

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

Write-Host "DEV HTTP obrigatorio: FrankenPHP :$Port" -ForegroundColor Cyan
$ok = Start-UnitecFrankenPhpServer -AppPath $AppPath -Port $Port -BindHost $BindHost -Foreground
if (-not $ok) {
    throw "FrankenPHP nao iniciou na porta $Port."
}
