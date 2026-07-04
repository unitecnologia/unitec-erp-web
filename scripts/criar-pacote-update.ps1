#Requires -Version 5.1
<#
.SYNOPSIS
    Gera dist/pacote-update/unitec-erp-web e dist/Unitec-ERP-Update.zip para publicar na nuvem.

.EXAMPLE
    .\scripts\criar-pacote-update.ps1

.EXAMPLE
    .\scripts\criar-pacote-update.ps1 -SkipComposer -SkipNpm
#>

param(
    [switch]$SkipComposer,
    [switch]$SkipNpm
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $ProjectRoot 'scripts\unitec-install-lib.ps1')

$StagingDir = Join-Path $ProjectRoot 'dist\pacote-update\unitec-erp-web'
$ZipPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update.zip'
$ReadmePath = Join-Path $ProjectRoot 'dist\pacote-update\LEIA-ME.txt'

function Write-Title($text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

Set-Location $ProjectRoot
Write-Title 'Gerar pacote de atualizacao (nuvem)'

if (-not $SkipComposer) {
    Write-Host '>> composer install --no-dev' -ForegroundColor White
    & composer install --no-dev --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'composer install falhou.' }
} else {
    Write-Host '>> composer ignorado (-SkipComposer)' -ForegroundColor Yellow
}

if (-not $SkipNpm) {
    Write-Host '>> npm install + npm run build' -ForegroundColor White
    & npm install --ignore-scripts
    if ($LASTEXITCODE -ne 0) { throw 'npm install falhou.' }
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build falhou.' }
} else {
    Write-Host '>> npm ignorado (-SkipNpm)' -ForegroundColor Yellow
}

if (-not (Test-Path 'vendor\autoload.php')) {
    throw 'vendor/ ausente. Rode composer install antes de gerar o pacote.'
}

if (-not (Test-Path 'public\build') -or ((Get-ChildItem 'public\build' -ErrorAction SilentlyContinue | Measure-Object).Count -eq 0)) {
    throw 'public/build/ ausente. Rode npm run build antes de gerar o pacote.'
}

Write-Host '>> Verificando cacert.pem (SSL HTTPS)' -ForegroundColor White
$null = Ensure-UnitecCaCertAsset -SourceRoot $ProjectRoot

Write-Host '>> Montando pasta do pacote (sem .env, storage/, tools/)' -ForegroundColor White

if (Test-Path $StagingDir) {
    Remove-Item $StagingDir -Recurse -Force
}

New-Item -ItemType Directory -Path $StagingDir -Force | Out-Null

Copy-UnitecProjectTree -SourceRoot $ProjectRoot -TargetRoot $StagingDir -UpdateMode -Quiet

if (Test-Path (Join-Path $StagingDir '.env')) {
    Remove-Item (Join-Path $StagingDir '.env') -Force
}

Remove-PublicStorageLink -Root $StagingDir

if (-not (Test-Path (Join-Path $StagingDir 'artisan'))) {
    throw 'Staging invalido: artisan ausente.'
}

if (-not (Test-Path (Join-Path $StagingDir 'vendor\autoload.php'))) {
    throw 'Staging invalido: vendor/autoload.php ausente.'
}

$fileCount = (Get-ChildItem $StagingDir -Recurse -File).Count
$sizeMb = [math]::Round((Get-ChildItem $StagingDir -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host ">> Staging: $StagingDir ($fileCount arquivos, ~${sizeMb} MB)" -ForegroundColor Green

Write-Host '>> Criando ZIP' -ForegroundColor White

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
$versao = 'desconhecida'

$configPath = Join-Path $ProjectRoot 'config\unitec.php'
if (Test-Path $configPath) {
    if ($configPath -match "'versao'\s*=>\s*'([^']+)'") {
        $versao = $Matches[1]
    } else {
        $content = Get-Content $configPath -Raw
        if ($content -match "'versao'\s*=>\s*'([^']+)'") {
            $versao = $Matches[1]
        }
    }
}

$readme = @"
Unitec ERP - Pacote de atualizacao
Versao: $versao
Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

Arquivos:
  - dist\pacote-update\unitec-erp-web\   (pasta para conferencia)
  - dist\Unitec-ERP-Update.zip           (enviar para a nuvem)

Publicacao:
  1. Envie Unitec-ERP-Update.zip para o Dropbox (ou site Unitec) com link direto dl=1.
  2. Mantenha o nome do arquivo: Unitec-ERP-Update.zip
  3. Configure no .env do cliente:
       UNITEC_UPDATE_DOWNLOAD_URL=https://...link-direto.../Unitec-ERP-Update.zip

O pacote NAO inclui .env, storage/ nem tools/ (preservados na instalacao do cliente).
"@

Set-Content -Path $ReadmePath -Value $readme -Encoding UTF8

Write-Title 'Pacote de atualizacao pronto'
Write-Host ''
Write-Host "Pasta:  $StagingDir" -ForegroundColor Green
Write-Host "ZIP:    $ZipPath (~${zipMb} MB)" -ForegroundColor Green
Write-Host "Versao: $versao" -ForegroundColor Green
Write-Host ''
Write-Host 'Envie Unitec-ERP-Update.zip para a nuvem (link HTTPS direto no .env do cliente).' -ForegroundColor White
Write-Host ''
