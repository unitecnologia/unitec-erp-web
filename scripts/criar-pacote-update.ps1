#Requires -Version 5.1
<#
.SYNOPSIS
    Gera o pacote unico de atualizacao FULL para clientes.

.DESCRIPTION
    Gera apenas:
      - dist/Unitec-ERP-Update.zip
      - dist/Unitec-ERP-Update.zip.sha256  (hash + tamanho para validacao no cliente)

    Delta e Unitec-ERP-Update-full.zip foram removidos.
    A validacao SHA256 no cliente usa o sidecar .sha256 (fora do ZIP).

.EXAMPLE
    .\scripts\criar-pacote-update.ps1 -SkipComposer -SkipNpm
#>

param(
    [switch]$SkipComposer,
    [switch]$SkipNpm,
    # Compatibilidade com bats antigos — ignorado (sempre FULL unico).
    [switch]$Full,
    # Inclui services/unitec-device-service/dist (self-contained). So quando o EXE mudar.
    [switch]$IncludeDeviceService
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $ProjectRoot 'scripts\unitec-install-lib.ps1')

$StagingDir = Join-Path $ProjectRoot 'dist\pacote-update\unitec-erp-web'
$ZipPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update.zip'
$ShaPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update.zip.sha256'
$ReadmePath = Join-Path $ProjectRoot 'dist\pacote-update\LEIA-ME.txt'
$LegacyFullZip = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update-full.zip'
$LegacyDeltaZip = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update-delta.zip'
$DeltaStagingDir = Join-Path $ProjectRoot 'dist\pacote-update-delta'
$BaselineDir = Join-Path $ProjectRoot 'dist\update-baseline'

function Write-Title($text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

function Get-UnitecPackageVersion {
    $configPath = Join-Path $ProjectRoot 'config\unitec.php'
    if (-not (Test-Path $configPath)) {
        return 'desconhecida'
    }

    $content = Get-Content $configPath -Raw
    if ($content -match "'versao'\s*=>\s*'([^']+)'") {
        return $Matches[1]
    }

    return 'desconhecida'
}

function Write-UnitecUpdateManifest {
    param(
        [string]$Path,
        [string]$ToVersion
    )

    $payload = [ordered]@{
        format          = 2
        to_version      = $ToVersion
        includes_vendor = $true
        generated_at    = (Get-Date -Format 'yyyy-MM-ddTHH:mm:ssK')
    }

    $json = $payload | ConvertTo-Json -Depth 6 -Compress:$false
    [System.IO.File]::WriteAllText($Path, $json, (New-Object System.Text.UTF8Encoding $false))
}

function New-ZipFromPaths([string[]]$Paths, [string]$Destination) {
    if (Test-Path $Destination) {
        Remove-Item $Destination -Force
    }

    $destDir = Split-Path $Destination
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }

    Compress-Archive -Path $Paths -DestinationPath $Destination -CompressionLevel Optimal
}

function Write-UnitecSha256File {
    param(
        [string]$ZipFile,
        [string]$ShaFile
    )

    $hash = (Get-FileHash -Path $ZipFile -Algorithm SHA256).Hash.ToLowerInvariant()
    $size = [long](Get-Item $ZipFile).Length
    $name = Split-Path $ZipFile -Leaf
    $content = @(
        "$hash  $name"
        "size=$size"
    ) -join "`n"

    [System.IO.File]::WriteAllText($ShaFile, $content + "`n", (New-Object System.Text.UTF8Encoding $false))

    return @{
        Hash = $hash
        Size = $size
    }
}

Set-Location $ProjectRoot
Write-Title 'Gerar pacote de atualizacao (ZIP unico FULL)'

if ($Full) {
    Write-Host '>> -Full ignorado: sempre gera o ZIP unico Unitec-ERP-Update.zip' -ForegroundColor Yellow
}

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

Write-Host '>> Verificando cloudflared.exe (tunel Cloudflare)' -ForegroundColor White
$null = Ensure-UnitecCloudflaredAsset -SourceRoot $ProjectRoot

if ($IncludeDeviceService) {
    Write-Host '>> Device Service dist SERA incluido neste pacote (-IncludeDeviceService)' -ForegroundColor Yellow
} else {
    Write-Host '>> Device Service dist excluido (pacote slim; use -IncludeDeviceService se o EXE mudou)' -ForegroundColor White
}

Write-Host '>> Montando pasta do pacote (sem .env, storage/, tools/)' -ForegroundColor White

if (Test-Path $StagingDir) {
    Remove-Item $StagingDir -Recurse -Force
}

New-Item -ItemType Directory -Path $StagingDir -Force | Out-Null
$copyArgs = @{
    SourceRoot = $ProjectRoot
    TargetRoot = $StagingDir
    UpdateMode = $true
    Quiet      = $true
}
if ($IncludeDeviceService) {
    $copyArgs['IncludeDeviceService'] = $true
}
Copy-UnitecProjectTree @copyArgs

if (Test-Path (Join-Path $StagingDir '.env')) {
    Remove-Item (Join-Path $StagingDir '.env') -Force
}

# Nunca distribuir caches gerados no PC de desenvolvimento.
$cacheDir = Join-Path $StagingDir 'bootstrap\cache'
if (Test-Path $cacheDir) {
    Get-ChildItem -Path $cacheDir -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne '.gitignore' } |
        Remove-Item -Force -ErrorAction SilentlyContinue
}

$dirtyCache = @()
if (Test-Path $cacheDir) {
    $dirtyCache = Get-ChildItem $cacheDir -Filter *.php -ErrorAction SilentlyContinue |
        Where-Object {
            $text = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
            $text -and (
                $text.Contains('C:\Projetos\unitec-erp-web') -or
                $text.Contains('C:/Projetos/unitec-erp-web') -or
                $text.Contains('C:\\Projetos\\unitec-erp-web')
            )
        }
}
if ($dirtyCache) {
    throw 'Pacote contaminado: bootstrap/cache ainda referencia C:\Projetos\unitec-erp-web (sujeira de DEV)'
}

# Guardas extras: sujeira de DEV nao pode ir ao cliente.
$forbiddenRelative = @(
    '.env',
    '.cursor',
    '.git',
    'Desenvolver.bat',
    'scripts\dev-windows.ps1',
    '_tmp_parse_ps1.ps1',
    '.env.appurl.local.bak',
    'importar'
)
foreach ($rel in $forbiddenRelative) {
    $hit = Join-Path $StagingDir $rel
    if (Test-Path $hit) {
        throw ("Pacote contaminado: $rel nao pode ir no ZIP do cliente")
    }
}
$balancaTeste = Get-ChildItem $StagingDir -File -Filter 'Balanca Teste*.exe' -ErrorAction SilentlyContinue
if ($balancaTeste) {
    throw 'Pacote contaminado: emulador de balanca (DEV) encontrado no staging'
}

Remove-PublicStorageLink -Root $StagingDir

if (-not (Test-Path (Join-Path $StagingDir 'artisan'))) {
    throw 'Staging invalido: artisan ausente.'
}

if (-not (Test-Path (Join-Path $StagingDir 'vendor\autoload.php'))) {
    throw 'Staging invalido: vendor/autoload.php ausente.'
}

# Versão do staging (fonte da verdade após a cópia) e manifest coerente.
$versao = Get-UnitecPackageVersion
$stagingConfig = Join-Path $StagingDir 'config\unitec.php'
if (Test-Path $stagingConfig) {
    $stagingContent = Get-Content $stagingConfig -Raw
    if ($stagingContent -match "'versao'\s*=>\s*'([^']+)'") {
        $versao = $Matches[1]
    }
}

$manifestPath = Join-Path $StagingDir 'unitec-update.json'
Write-UnitecUpdateManifest -Path $manifestPath -ToVersion $versao

$manifestJson = Get-Content $manifestPath -Raw | ConvertFrom-Json
$manifestVersion = [string] $manifestJson.to_version
if ($manifestVersion -ne $versao) {
    throw ("Pacote inconsistente: unitec-update.json to_version=$manifestVersion mas config versao=$versao")
}
Write-Host (">> Manifest OK: to_version=$manifestVersion (= config)") -ForegroundColor Green

$fileCount = @(Get-ChildItem $StagingDir -Recurse -File).Count
$sizeMb = [math]::Round((Get-ChildItem $StagingDir -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host ">> Staging: $StagingDir ($fileCount arquivos, ~${sizeMb} MB)" -ForegroundColor Green

Write-Host '>> Gerando Unitec-ERP-Update.zip ...' -ForegroundColor White
New-ZipFromPaths -Paths @($StagingDir) -Destination $ZipPath
if (-not (Test-Path $ZipPath)) {
    throw "Falha ao criar ZIP: $ZipPath"
}

$shaInfo = Write-UnitecSha256File -ZipFile $ZipPath -ShaFile $ShaPath
$zipMb = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
Write-Host (">> ZIP OK: {0} (~{1} MB)" -f $ZipPath, $zipMb) -ForegroundColor Green
Write-Host (">> SHA256: {0} size={1}" -f $shaInfo.Hash, $shaInfo.Size) -ForegroundColor Green

foreach ($legacyZip in @($LegacyFullZip, $LegacyDeltaZip)) {
    if (Test-Path $legacyZip) {
        Write-Host (">> Removendo ZIP legado: {0}" -f $legacyZip) -ForegroundColor Yellow
        Remove-Item $legacyZip -Force -ErrorAction SilentlyContinue
    }
}

foreach ($legacyDir in @($DeltaStagingDir, $BaselineDir)) {
    if (Test-Path $legacyDir) {
        Write-Host (">> Removendo legado: {0}" -f $legacyDir) -ForegroundColor Yellow
        Remove-Item $legacyDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$readme = @"
Unitec ERP - Pacote de atualizacao (ZIP unico)
Versao: $versao
Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

Arquivos:
 - dist\Unitec-ERP-Update.zip
 - dist\Unitec-ERP-Update.zip.sha256

Publicacao:
  scripts\publicar-update-github.ps1
  URL: https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip
"@

Set-Content -Path $ReadmePath -Value $readme -Encoding UTF8

Write-Title 'Pacote ZIP pronto'
Write-Host ''
Write-Host "Versao:  $versao" -ForegroundColor Green
Write-Host "ZIP:     $ZipPath (~$zipMb MB)" -ForegroundColor Green
Write-Host "SHA256:  $ShaPath" -ForegroundColor Green
Write-Host "Staging: $StagingDir ($fileCount arquivos)" -ForegroundColor Green
Write-Host ''
Write-Host 'Proximo passo: scripts\publicar-update-github.ps1' -ForegroundColor White
Write-Host ''
