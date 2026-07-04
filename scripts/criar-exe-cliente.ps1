#Requires -Version 5.1
<#
.SYNOPSIS
    Gera dist\output\Instalar Unitec ERP.exe via Inno Setup (MariaDB + PHP embutidos).

.NOTES
    Encaminha para build-setup.ps1. Nao usa Laragon.
    Parametro legado -SkipLaragonDownload equivale a -SkipRuntimeDownload.
#>

param(
    [switch]$SkipComposer,
    [switch]$SkipNpm,
    [switch]$SkipLaragonDownload,
    [switch]$SkipRuntimeDownload,
    [string]$LaragonUrl = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

if ($SkipLaragonDownload -and -not $SkipRuntimeDownload) {
    $SkipRuntimeDownload = $true
}

if (-not [string]::IsNullOrWhiteSpace($LaragonUrl)) {
    Write-Host '[!] LaragonUrl foi ignorado — o instalador usa MariaDB embutido (mariadb-win.zip).' -ForegroundColor Yellow
}

$buildArgs = @{
    SkipComposer        = $SkipComposer.IsPresent
    SkipNpm             = $SkipNpm.IsPresent
    SkipRuntimeDownload = $SkipRuntimeDownload.IsPresent
}

& (Join-Path $PSScriptRoot 'build-setup.ps1') @buildArgs
exit $LASTEXITCODE
