#Requires -Version 5.1
<#
.SYNOPSIS
    Monta o pacote offline e compila Instalador Sistema Facil.exe (Inno Setup).

.EXAMPLE
    .\scripts\build-setup.ps1
#>

param(
    [switch]$SkipComposer,
    [switch]$SkipNpm,
    [switch]$SkipRuntimeDownload,
    [switch]$SkipCompile,
    # Embute .env de desenvolvimento + dump do banco unitec_erp no instalador.
    [switch]$IncludeDevData,
    [string]$MariaDbUrl = '',
    [string]$DevDbHost = '127.0.0.1',
    [string]$DevDbPort = '3306',
    [string]$DevDbName = 'unitec_erp',
    [string]$DevDbUser = 'root',
    [string]$DevDbPassword = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $ProjectRoot 'scripts\unitec-install-lib.ps1')

if ([string]::IsNullOrWhiteSpace($MariaDbUrl)) {
    $MariaDbUrl = $script:UnitecMariaDbDownloadUrl
}
$StagingDir = Join-Path $ProjectRoot 'dist\staging\unitec-erp-web'
$OutputDir = Join-Path $ProjectRoot 'dist\output'
$MariaDbAsset = Join-Path $ProjectRoot 'installer\assets\mariadb-win.zip'
$Php84Asset = Join-Path $ProjectRoot 'installer\assets\php-8.4-win.zip'
$NodeAsset = Join-Path $ProjectRoot 'installer\assets\node-win.zip'
$WhatsAppGatewayDepsAsset = Join-Path $ProjectRoot 'installer\assets\whatsapp-gateway-node-modules.zip'
$VcRedistAsset = Join-Path $ProjectRoot 'installer\assets\vc_redist.x64.exe'
$CaCertAsset = Join-Path $ProjectRoot 'installer\assets\cacert.pem'
$HeidiSqlAsset = Join-Path $ProjectRoot 'installer\assets\HeidiSQL_12.18.0.7304_Setup.exe'
$IssFile = Join-Path $ProjectRoot 'installer\unitec-erp.iss'

function Write-Title($text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

function Find-InnoSetupCompiler {
    if ($env:ISCC -and (Test-Path $env:ISCC)) {
        return $env:ISCC
    }

    $candidates = @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
        "${env:LocalAppData}\Programs\Inno Setup 6\ISCC.exe"
    )

    foreach ($path in $candidates) {
        if (Test-Path $path) {
            return $path
        }
    }

    $cmd = Get-Command ISCC.exe -ErrorAction SilentlyContinue
    if ($cmd -and (Test-Path $cmd.Source)) {
        return $cmd.Source
    }

    return $null
}

function Install-InnoSetupCompiler {
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget) {
        return $null
    }

    Write-Host '>> Inno Setup 6 nao encontrado - instalando via winget...' -ForegroundColor Yellow
    & winget install --id JRSoftware.InnoSetup --accept-package-agreements --accept-source-agreements --silent
    if ($LASTEXITCODE -ne 0) {
        return $null
    }

    return Find-InnoSetupCompiler
}

function Ensure-Directory($path) {
    if (-not (Test-Path $path)) {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

function Ensure-StorageStructure($root) {
    $storageRoot = Join-Path $root 'storage'
    if (Test-Path $storageRoot) {
        # Staging nao pode levar dumps/homolog/logs do PC de desenvolvimento (quebra o Inno).
        Remove-Item $storageRoot -Recurse -Force -ErrorAction SilentlyContinue
    }

    $dirs = @(
        'storage\app\public',
        'storage\app\private',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\logs',
        'bootstrap\cache'
    )

    foreach ($dir in $dirs) {
        $full = Join-Path $root $dir
        Ensure-Directory $full
    }

    foreach ($keep in @(
        'storage\app\.gitignore',
        'storage\framework\.gitignore',
        'storage\logs\.gitignore',
        'storage\app\public\.gitignore',
        'storage\framework\cache\.gitignore',
        'storage\framework\sessions\.gitignore',
        'storage\framework\views\.gitignore'
    )) {
        $src = Join-Path $ProjectRoot $keep
        $dst = Join-Path $root $keep
        if ((Test-Path $src)) {
            Ensure-Directory (Split-Path $dst)
            Copy-Item $src $dst -Force
        }
    }
}

function Clear-LaravelRuntimeCaches($root) {
    $cacheDir = Join-Path $root 'bootstrap\cache'
    if (Test-Path $cacheDir) {
        Get-ChildItem -Path $cacheDir -File -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -ne '.gitignore' } |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }

    foreach ($relative in @(
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'tools\php\opcache'
    )) {
        $path = Join-Path $root $relative
        if (Test-Path $path) {
            Get-ChildItem -Path $path -Force -ErrorAction SilentlyContinue |
                Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    # So caches compilados — scripts podem citar paths de exemplo sem ir para runtime.
    if (Test-Path $cacheDir) {
        $hits = Get-ChildItem $cacheDir -Filter *.php -ErrorAction SilentlyContinue |
            Where-Object {
                $text = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
                $text -and (
                    $text.Contains('C:\Projetos\unitec-erp-web') -or
                    $text.Contains('C:/Projetos/unitec-erp-web')
                )
            }
        if ($hits) {
            throw 'bootstrap/cache ainda contem referencia a C:\Projetos\unitec-erp-web'
        }
    }
}

Write-Title 'Gerar Instalador Sistema Facil.exe'
Set-Location $ProjectRoot

Ensure-Directory $StagingDir
Ensure-Directory $OutputDir
Ensure-Directory (Split-Path $MariaDbAsset)

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
    throw 'vendor/ ausente. Rode composer install antes de gerar o setup.'
}

if (-not (Test-Path 'public\build') -or ((Get-ChildItem 'public\build' -ErrorAction SilentlyContinue | Measure-Object).Count -eq 0)) {
    throw 'public/build/ ausente. Rode npm run build antes de gerar o setup.'
}

Write-Host '>> Copiando arquivos para staging' -ForegroundColor White

if (Test-Path $StagingDir) {
    Remove-Item $StagingDir -Recurse -Force
}

Copy-UnitecProjectTree -SourceRoot $ProjectRoot -TargetRoot $StagingDir -ExcludeTools

Ensure-StorageStructure $StagingDir
Clear-LaravelRuntimeCaches $StagingDir
Write-Host '>> storage/ do staging limpo (sem dados locais).' -ForegroundColor Gray
Write-Host '>> caches Laravel/OPcache removidos do staging.' -ForegroundColor Gray

# Lixo de desenvolvimento que nao deve ir ao cliente (salvo IncludeDevData p/ .env)
foreach ($junk in @(
    '.env.appurl.local.bak',
    '.env.backup',
    '.env.production',
    'instalacao.log',
    '.phpunit.result.cache',
    'composer-setup.php',
    'composer.phar',
    'vc_redist.x64.exe',
    'tmp-print-props.txt',
    '_tmp_parse_ps1.ps1',
    'unitec_erp',
    '.unitec-serve.pid'
)) {
    $junkPath = Join-Path $StagingDir $junk
    if (Test-Path $junkPath) {
        Remove-Item $junkPath -Recurse -Force -ErrorAction SilentlyContinue
    }
}

if (-not $IncludeDevData) {
    if (Test-Path (Join-Path $StagingDir '.env')) {
        Remove-Item (Join-Path $StagingDir '.env') -Force
    }

    $seedDirCleanup = Join-Path $StagingDir 'installer\seed'
    if (Test-Path $seedDirCleanup) {
        Remove-Item $seedDirCleanup -Recurse -Force -ErrorAction SilentlyContinue
    }
} else {
    Write-Host '>> IncludeDevData: mantendo/preparando .env e dump do banco no staging' -ForegroundColor Cyan

    $seedDir = Join-Path $StagingDir 'installer\seed'
    Ensure-Directory $seedDir

    $envSource = Join-Path $ProjectRoot '.env'
    if (-not (Test-Path $envSource)) {
        throw 'IncludeDevData requer .env na raiz do projeto.'
    }

    $envTarget = Join-Path $StagingDir '.env'
    $envLines = Get-Content $envSource -Encoding UTF8
    $rewritten = foreach ($line in $envLines) {
        if ($line -match '^\s*APP_URL\s*=') {
            'APP_URL=http://127.0.0.1:8765'
        } elseif ($line -match '^\s*APP_ENV\s*=') {
            'APP_ENV=production'
        } else {
            $line
        }
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($envTarget, $rewritten, $utf8NoBom)
    Write-Host '>> .env embutido (APP_URL=8765, APP_ENV=production)' -ForegroundColor Green

    if ([string]::IsNullOrWhiteSpace($DevDbPassword)) {
        $DevDbPassword = Get-UnitecDefaultDbPassword
    }

    $sqlPath = Join-Path $seedDir 'unitec_erp.sql'
    Write-Host (">> Gerando dump {0}@{1}:{2}/{3} ..." -f $DevDbUser, $DevDbHost, $DevDbPort, $DevDbName) -ForegroundColor White
    Export-UnitecDatabaseDump `
        -OutputPath $sqlPath `
        -DbHost $DevDbHost `
        -DbPort $DevDbPort `
        -DbUser $DevDbUser `
        -DbPassword $DevDbPassword `
        -DbName $DevDbName `
        -AppPath $ProjectRoot | Out-Null

    $sqlMb = [math]::Round((Get-Item $sqlPath).Length / 1MB, 1)
    Write-Host (">> Dump gerado: {0} (~{1} MB)" -f $sqlPath, $sqlMb) -ForegroundColor Green

    $flagPath = Join-Path $seedDir 'INCLUDE_DEV_DATA.flag'
    $flagText = @(
        'Unitec ERP - pacote com dados de desenvolvimento',
        ("Gerado em: {0}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')),
        ("Origem DB: {0}:{1}/{2}" -f $DevDbHost, $DevDbPort, $DevDbName),
        'Na instalacao: sobrescreve .env e restaura este dump.'
    )
    [System.IO.File]::WriteAllLines($flagPath, $flagText, $utf8NoBom)
}

if (Test-Path (Join-Path $StagingDir 'tools')) {
    Remove-Item (Join-Path $StagingDir 'tools') -Recurse -Force
}

$nodeRuntime = Ensure-UnitecNodeRuntime -AppPath $ProjectRoot -SourceRoot $ProjectRoot
if (-not $nodeRuntime) {
    throw 'Node.js nao esta disponivel para montar o pacote offline do WhatsApp.'
}

$nodeRuntimeDir = Split-Path $nodeRuntime -Parent
if (-not (Test-Path $NodeAsset)) {
    Compress-Archive -Path (Join-Path $nodeRuntimeDir '*') -DestinationPath $NodeAsset -CompressionLevel Optimal -Force
}

$gatewaySource = Join-Path $ProjectRoot 'services\erp-whatsapp-gateway'
$gatewayNodeModules = Join-Path $gatewaySource 'node_modules'
$npmRuntime = Get-UnitecNpmExecutable -AppPath $ProjectRoot
if (-not $npmRuntime) {
    throw 'npm nao esta disponivel para montar o pacote offline do WhatsApp.'
}

if (-not (Test-Path $gatewayNodeModules)) {
    Push-Location $gatewaySource
    try {
        & $npmRuntime ci --omit=dev --no-fund --no-audit
        if ($LASTEXITCODE -ne 0) {
            throw 'npm ci do gateway WhatsApp falhou ao montar o pacote offline.'
        }
    } finally {
        Pop-Location
    }
}

Compress-Archive -Path $gatewayNodeModules -DestinationPath $WhatsAppGatewayDepsAsset -CompressionLevel Optimal -Force

if (-not (Test-Path $MariaDbAsset)) {
    if ($SkipRuntimeDownload) {
        throw "Coloque mariadb-win.zip em installer\assets\ ou remova -SkipRuntimeDownload."
    }

    Write-Host ">> Baixando MariaDB 11.4 (~80 MB): $MariaDbUrl" -ForegroundColor White
    try {
        Invoke-WebRequest -Uri $MariaDbUrl -OutFile $MariaDbAsset -UseBasicParsing
    } catch {
        throw "Falha ao baixar MariaDB. Baixe manualmente de $MariaDbUrl e salve em installer\assets\mariadb-win.zip"
    }
}

Assert-UnitecMariaDbZipAsset -ZipPath $MariaDbAsset

if (-not (Test-Path $Php84Asset)) {
    if ($SkipRuntimeDownload) {
        throw "Coloque php-8.4-win.zip em installer\assets\ ou remova -SkipRuntimeDownload."
    }

    Write-Host ">> Baixando PHP 8.4 (~30 MB): $($script:UnitecPhp84DownloadUrl)" -ForegroundColor White
    try {
        Invoke-WebRequest -Uri $script:UnitecPhp84DownloadUrl -OutFile $Php84Asset -UseBasicParsing
    } catch {
        throw "Falha ao baixar PHP 8.4. Baixe manualmente de $($script:UnitecPhp84DownloadUrl) e salve em installer\assets\php-8.4-win.zip"
    }
}

if (-not (Test-Path $VcRedistAsset)) {
    if ($SkipRuntimeDownload) {
        throw "Coloque vc_redist.x64.exe em installer\assets\ ou remova -SkipRuntimeDownload."
    }

    Write-Host ">> Baixando Visual C++ Redistributable (~25 MB): $($script:UnitecVcRedistDownloadUrl)" -ForegroundColor White
    try {
        Invoke-WebRequest -Uri $script:UnitecVcRedistDownloadUrl -OutFile $VcRedistAsset -UseBasicParsing
    } catch {
        throw "Falha ao baixar VC++ Redist. Baixe manualmente de $($script:UnitecVcRedistDownloadUrl) e salve em installer\assets\vc_redist.x64.exe"
    }
}

if (-not (Test-Path $HeidiSqlAsset)) {
    if ($SkipRuntimeDownload) {
        Write-Host '>> AVISO: HeidiSQL Setup ausente - coloque HeidiSQL_*_Setup.exe em installer\assets\ (opcional no ERP).' -ForegroundColor Yellow
    } else {
        Write-Host '>> AVISO: HeidiSQL Setup ausente - baixe em https://www.heidisql.com/download.php' -ForegroundColor Yellow
        Write-Host '>>         Salve em installer\assets\HeidiSQL_12.18.0.7304_Setup.exe (opcional).' -ForegroundColor Yellow
    }
} else {
    Write-Host ">> HeidiSQL: $HeidiSqlAsset" -ForegroundColor Green
}

$IconAsset = Join-Path $ProjectRoot 'installer\assets\unitec-erp.ico'
Ensure-UnitecAppIconAsset -TargetPath $IconAsset

if (-not (Test-Path $CaCertAsset)) {
    if ($SkipRuntimeDownload) {
        throw "Coloque cacert.pem em installer\assets\ ou remova -SkipRuntimeDownload."
    }

    $null = Ensure-UnitecCaCertAsset -SourceRoot $ProjectRoot
}

Write-Host ">> MariaDB: $MariaDbAsset" -ForegroundColor Green
Write-Host ">> PHP 8.4: $Php84Asset" -ForegroundColor Green
Write-Host ">> VC++ Redist: $VcRedistAsset" -ForegroundColor Green
Write-Host ">> CA SSL: $CaCertAsset" -ForegroundColor Green
Write-Host ">> Icone: $IconAsset" -ForegroundColor Green

Sync-InstallerAssetsToStaging -ProjectRoot $ProjectRoot -StagingDir $StagingDir
Remove-PublicStorageLink -Root $StagingDir

# Binarios desktop (servico + launcher + atualizador), se ja compilados.
$desktopBin = Join-Path $ProjectRoot 'bin'
$stagingBin = Join-Path $StagingDir 'bin'
if ((Test-Path (Join-Path $desktopBin 'Unitec ERP.exe')) -and (Test-Path (Join-Path $desktopBin 'UnitecErpServer.exe'))) {
    Ensure-Directory $stagingBin
    Copy-Item (Join-Path $desktopBin '*') $stagingBin -Force -Recurse
    Write-Host '>> Binarios Unitec ERP Desktop copiados para staging\bin.' -ForegroundColor Green
} else {
    Write-Host '>> AVISO: bin\Unitec ERP.exe / UnitecErpServer.exe ausentes. Rode scripts\build-erp-desktop.ps1 antes do instalador definitivo.' -ForegroundColor Yellow
}

# Cliente: um unico atalho (Unitec ERP.exe). Sem .bat na raiz do pacote.
Get-ChildItem -Path $StagingDir -Filter '*.bat' -File -ErrorAction SilentlyContinue |
    Remove-Item -Force
Write-Host '>> Removidos .bat da raiz do staging (cliente usa atalho Unitec ERP.exe).' -ForegroundColor Gray

foreach ($optional in (Get-UnitecStagingOptionalPaths)) {
    $full = Join-Path $StagingDir $optional
    if (-not (Test-Path $full)) {
        Write-Host ">> AVISO: opcional ausente no staging: $optional" -ForegroundColor Yellow
    }
}

Assert-UnitecStagingReady -Root $StagingDir

$fileCount = (Get-ChildItem $StagingDir -Recurse -File).Count
$sizeMb = [math]::Round((Get-ChildItem $StagingDir -Recurse -File | Measure-Object Length -Sum).Sum / 1MB)
Write-Host ">> Staging: $StagingDir ($fileCount arquivos, ~${sizeMb} MB)" -ForegroundColor Green

if ($SkipCompile) {
    $stagingParent = Split-Path $StagingDir
    $innoReadme = Join-Path $stagingParent 'LEIA-ME-INNO.txt'
    $readme = @"
Unitec ERP - staging pronto para Inno Setup
Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

Pasta do staging (Source no .iss):
  $StagingDir

Script Inno Setup:
  $IssFile

Como compilar:
  1. Abra o Inno Setup 6
  2. Arquivo → Abrir → installer\unitec-erp.iss
  3. Build → Compile (Ctrl+F9)

Ou na raiz do projeto:
  Gerar Instalador Inno.bat

Saida esperada:
  dist\output\Instalar Unitec ERP.exe

Requisitos em installer\assets\:
  mariadb-win.zip, php-8.4-win.zip, vc_redist.x64.exe, cacert.pem
Opcionais: HeidiSQL_*_Setup.exe, unitec-erp.ico (icone da marca)

O staging NAO inclui tools\ - MariaDB e PHP sao extraidos na instalacao do cliente.
"@
    Set-Content -Path $innoReadme -Value $readme -Encoding UTF8
    Write-Host ">> Instrucoes Inno: $innoReadme" -ForegroundColor Green
    Write-Host '>> Compilacao ignorada (-SkipCompile). Staging pronto.' -ForegroundColor Yellow
    exit 0
}

$iscc = Find-InnoSetupCompiler
if (-not $iscc) {
    $iscc = Install-InnoSetupCompiler
}
if (-not $iscc) {
    throw @"
Inno Setup 6 nao encontrado.
Instale em https://jrsoftware.org/isdl.php
Ou: winget install --id JRSoftware.InnoSetup
Depois rode novamente: .\scripts\build-setup.ps1
"@
}

Write-Host ">> Compilando com: $iscc" -ForegroundColor White
& $iscc $IssFile
if ($LASTEXITCODE -ne 0) { throw 'Compilacao Inno Setup falhou.' }

$setupExe = Join-Path $OutputDir 'Instalar Unitec ERP.exe'
Write-Title 'Setup gerado com sucesso'
Write-Host $setupExe -ForegroundColor Green
Write-Host ''
Write-Host 'Envie este arquivo ao cliente. Instalacao offline (sem internet na loja).' -ForegroundColor White
if ($IncludeDevData) {
    Write-Host 'ATENCAO: este setup inclui .env e dump do banco de desenvolvimento.' -ForegroundColor Yellow
    Write-Host 'Ao instalar em C:\UNITECNOLOGIA_WEB, substitui .env e o banco unitec_erp.' -ForegroundColor Yellow
}
Write-Host ''
