#Requires -Version 5.1
<#
.SYNOPSIS
    Checklist de requisitos do PC para instalar o Unitec ERP (sem instalar o sistema).

.EXAMPLE
    .\scripts\verificar-pc.ps1
    .\scripts\verificar-pc.ps1 -Fix
#>

param(
    [string]$AppPath = '',
    [switch]$Fix,
    [switch]$Quiet
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if (-not (Test-UnitecIsAdministrator)) {
    Write-Warn 'Execute como administrador para um diagnostico completo (hosts, VC++, portas).'
}

$results = Invoke-UnitecSystemRequirementsCheck -SourceRoot $AppPath -FixVcRuntime:$Fix -Quiet:$Quiet
$failed = @($results | Where-Object { -not $_.Ok })

if ($failed.Count -gt 0) {
    Write-Host ''
    Write-Err ('{0} item(ns) impedem a instalacao.' -f $failed.Count)
    exit 1
}

Write-Host ''
Write-Ok 'Este PC atende aos requisitos para instalar o Unitec ERP.'
exit 0
