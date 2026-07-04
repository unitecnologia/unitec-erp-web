#Requires -Version 5.1
<#
.SYNOPSIS
    Gera dist/Unitec-ERP-Instalador.zip para o cliente (extrair + Instalar Tudo.bat).

.NOTES
    Usa o mesmo staging do build-setup (MariaDB + PHP embutidos em installer\assets\).
    Nao usa Laragon.
#>

param(
    [switch]$SkipComposer,
    [switch]$SkipNpm,
    [switch]$SkipRuntimeDownload
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$StagingDir = Join-Path $ProjectRoot 'dist\staging\unitec-erp-web'
$ZipPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Instalador.zip'

function Write-Title($text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

$buildArgs = @{
    SkipCompile         = $true
    SkipComposer        = $SkipComposer.IsPresent
    SkipNpm             = $SkipNpm.IsPresent
    SkipRuntimeDownload = $SkipRuntimeDownload.IsPresent
}

& (Join-Path $PSScriptRoot 'build-setup.ps1') @buildArgs
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

if (-not (Test-Path (Join-Path $StagingDir 'artisan'))) {
    throw "Staging ausente: $StagingDir"
}

Write-Title 'Gerar ZIP para cliente'

$distDir = Split-Path $ZipPath
if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir -Force | Out-Null
}

if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

$parent = Split-Path $StagingDir
Compress-Archive -Path (Join-Path $parent 'unitec-erp-web') -DestinationPath $ZipPath -CompressionLevel Optimal

$zipMb = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)

Write-Title 'Pacote pronto'
Write-Host ''
Write-Host $ZipPath -ForegroundColor Green
Write-Host "Tamanho: ~${zipMb} MB" -ForegroundColor Gray
Write-Host ''
Write-Host 'Envie este ZIP ao cliente.' -ForegroundColor White
Write-Host ''
Write-Host 'Cliente:' -ForegroundColor Cyan
Write-Host '  1. Extrair o ZIP (clique direito - Extrair tudo)'
Write-Host '  2. Entrar na pasta unitec-erp-web'
Write-Host '  3. Duplo clique em Instalar Tudo.bat'
Write-Host ''
Write-Host 'Runtime: MariaDB + PHP em installer\assets\ (sem Laragon).' -ForegroundColor DarkGray
Write-Host ''
