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

# composer do PATH; senao tools\composer.phar (gitignored, fora do ZIP: tools/ e excluido no UpdateMode)
# rodando no tools\php\php.exe, baixado da fonte oficial na primeira vez.
function Resolve-UnitecComposer {
    $global = Get-Command composer -ErrorAction SilentlyContinue
    if ($global) {
        return @($global.Source)
    }

    $phpExe = Join-Path $ProjectRoot 'tools\php\php.exe'
    if (-not (Test-Path $phpExe)) {
        throw 'composer nao esta no PATH e tools\php\php.exe nao existe.'
    }

    $phar = Join-Path $ProjectRoot 'tools\composer.phar'
    if (-not (Test-Path $phar)) {
        Write-Host '>> composer.phar ausente: baixando de getcomposer.org para tools\' -ForegroundColor Yellow
        [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
        $tmp = $phar + '.download'
        $url = 'https://getcomposer.org/download/latest-stable/composer.phar'
        Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing
        $esperado = ((Invoke-WebRequest -Uri ($url + '.sha256') -UseBasicParsing).Content.Trim() -split '\s+')[0].ToLowerInvariant()
        $obtido = (Get-FileHash -Path $tmp -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($esperado -ne $obtido) {
            Remove-Item $tmp -Force -ErrorAction SilentlyContinue
            throw 'composer.phar baixado com SHA256 diferente do oficial.'
        }
        Move-Item $tmp $phar -Force
    }

    return @($phpExe, $phar)
}

function Invoke-UnitecComposer([string[]]$ComposerArgs) {
    $cmd = Resolve-UnitecComposer
    $exe = $cmd[0]
    $prefix = @($cmd | Select-Object -Skip 1)
    & $exe @prefix @ComposerArgs | Out-Host
    return $LASTEXITCODE
}

$script:RestaurarComposerDev = $false

function Restore-UnitecComposerDev {
    if (-not $script:RestaurarComposerDev) {
        return
    }
    $script:RestaurarComposerDev = $false
    Write-Host '>> Restaurando dependencias de desenvolvimento (composer install)' -ForegroundColor White
    $code = Invoke-UnitecComposer @('install', '--no-interaction')
    if ($code -ne 0) {
        Write-Host '>> AVISO: falha ao restaurar dependencias DEV. Rode composer install manualmente.' -ForegroundColor Yellow
    }
}

trap {
    Restore-UnitecComposerDev
    break
}

Set-Location $ProjectRoot
Write-Title 'Gerar pacote de atualizacao (ZIP unico FULL)'

if ($Full) {
    Write-Host '>> -Full ignorado: sempre gera o ZIP unico Unitec-ERP-Update.zip' -ForegroundColor Yellow
}

if (-not $SkipComposer) {
    Write-Host '>> composer install --no-dev' -ForegroundColor White
    $script:RestaurarComposerDev = $true
    $code = Invoke-UnitecComposer @('install', '--no-dev', '--optimize-autoloader', '--no-interaction')
    if ($code -ne 0) { throw 'composer install falhou.' }
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
    Publish-UnitecDeviceServiceDist -SourceRoot $ProjectRoot | Out-Null
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

# Pacote de update normalmente exclui bin/ e tools/. Embutimos runtime-update/
# (FrankenPHP + UnitecErpServer) para clientes ainda no legado php -S 127.0.0.1.
Write-Host '>> Embutindo runtime-update (FrankenPHP + desktop bin)...' -ForegroundColor White
Publish-UnitecRuntimeUpdateToStaging -SourceRoot $ProjectRoot -StagingDir $StagingDir

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

# Pacote --no-dev: laravel/pail (e afins) nao pode ir ao cliente.
$forbiddenDevDirs = @(
    'vendor\laravel\pail',
    'vendor\laravel\pao',
    'vendor\laravel\sail',
    'vendor\nunomaduro\collision',
    'vendor\filp\whoops',
    'vendor\phpunit\phpunit'
)
$devVendorHits = $forbiddenDevDirs |
    ForEach-Object { Join-Path $StagingDir $_ } |
    Where-Object { Test-Path $_ }
if ($devVendorHits) {
    throw ("Pacote contaminado com require-dev: " + ($devVendorHits -join ', '))
}

# So a lista "packages" de producao (nao dev-package-names).
# Usa PHP json_decode: ConvertFrom-Json do PowerShell falha com chaves duplicadas (PDF/Pdf).
$installedJson = Join-Path $StagingDir 'vendor\composer\installed.json'
$forbiddenPkgNames = @('laravel/pail', 'laravel/pao', 'laravel/sail', 'nunomaduro/collision', 'filp/whoops', 'phpunit/phpunit')
if (Test-Path $installedJson) {
    $phpExe = Join-Path $ProjectRoot 'tools\php\php.exe'
    if (-not (Test-Path $phpExe)) { $phpExe = 'php' }
    $forbiddenCsv = ($forbiddenPkgNames -join ',')
    $phpCheck = @'
$j = json_decode(file_get_contents($argv[1]), true);
if (!is_array($j)) { fwrite(STDERR, "installed.json invalido\n"); exit(2); }
$pkgs = $j['packages'] ?? (array_is_list($j) ? $j : []);
$forbid = array_filter(array_map('trim', explode(',', $argv[2])));
foreach ($pkgs as $p) {
    $name = (string)($p['name'] ?? '');
    if ($name !== '' && in_array($name, $forbid, true)) {
        fwrite(STDERR, "packages[] contem $name\n");
        exit(3);
    }
}
echo "ok\n";
exit(0);
'@
    $tmpPhp = Join-Path $env:TEMP ('unitec-check-installed-' + [guid]::NewGuid().ToString('N') + '.php')
    Set-Content -Path $tmpPhp -Value $phpCheck -Encoding UTF8
    try {
        $out = & $phpExe $tmpPhp $installedJson $forbiddenCsv 2>&1
        $code = $LASTEXITCODE
    } finally {
        Remove-Item $tmpPhp -Force -ErrorAction SilentlyContinue
    }
    if ($code -eq 3) {
        throw ("Pacote contaminado: vendor/composer/installed.json packages[] contem dependencia DEV ($out). Rode composer install --no-dev")
    }
    if ($code -ne 0) {
        throw "Falha ao validar vendor/composer/installed.json (exit=$code): $out"
    }
    Write-Host '>> installed.json packages[] sem dependencias DEV conhecidas' -ForegroundColor Green
}

# Nenhum *.php de cache pode ir no ZIP (packages.php/services.php inclusive).
$cachePhpLeft = @()
if (Test-Path $cacheDir) {
    $cachePhpLeft = @(Get-ChildItem $cacheDir -Filter '*.php' -File -ErrorAction SilentlyContinue)
}
if ($cachePhpLeft.Count -gt 0) {
    throw ("Pacote contaminado: bootstrap/cache ainda tem PHP: " + (($cachePhpLeft | ForEach-Object Name) -join ', '))
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

Restore-UnitecComposerDev

Write-Title 'Pacote ZIP pronto'
Write-Host ''
Write-Host "Versao:  $versao" -ForegroundColor Green
Write-Host "ZIP:     $ZipPath (~$zipMb MB)" -ForegroundColor Green
Write-Host "SHA256:  $ShaPath" -ForegroundColor Green
Write-Host "Staging: $StagingDir ($fileCount arquivos)" -ForegroundColor Green
Write-Host ''
Write-Host 'Proximo passo: scripts\publicar-update-github.ps1' -ForegroundColor White
Write-Host ''
