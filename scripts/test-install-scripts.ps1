#Requires -Version 5.1
<#
.SYNOPSIS
    Valida sintaxe e integridade dos scripts do instalador Windows.
#>

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$ScriptsDir = Join-Path $ProjectRoot 'scripts'
$StagingDir = Join-Path $ProjectRoot 'dist\staging\unitec-erp-web'

$failures = @()

function Add-Failure {
    param([string]$Message)
    $script:failures += $Message
    Write-Host "[FALHA] $Message" -ForegroundColor Red
}

function Add-Pass {
    param([string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor Green
}

Write-Host ''
Write-Host '=== Teste scripts instalador Unitec ERP ===' -ForegroundColor Cyan
Write-Host ''

$scriptFiles = @(
    'unitec-install-lib.ps1',
    'instalar-tudo.ps1',
    'setup-prerequisites.ps1',
    'install-windows.ps1',
    'build-setup.ps1',
    'open-unitec-app.ps1',
    'verificar-pc.ps1'
)

foreach ($name in $scriptFiles) {
    $path = Join-Path $ScriptsDir $name
    if (-not (Test-Path $path)) {
        Add-Failure "Arquivo ausente: $name"
        continue
    }

    $errors = $null
    $tokens = $null
    $ast = [System.Management.Automation.Language.Parser]::ParseFile($path, [ref]$tokens, [ref]$errors)
    if ($errors -and $errors.Count -gt 0) {
        Add-Failure "$name parse: $($errors[0].Message)"
        continue
    }

    Add-Pass "Parse OK: $name"
}

$instalarPath = Join-Path $ScriptsDir 'instalar-tudo.ps1'
$instalarText = Get-Content $instalarPath -Raw
if ($instalarText -match '`\s*\r?\n\s*\r?\n') {
    Add-Failure 'instalar-tudo.ps1 contem linha em branco apos continuacao com backtick'
} else {
    Add-Pass 'instalar-tudo.ps1 sem backtick quebrado por linha em branco'
}

if ($instalarText -notmatch 'Assert-UnitecSystemRequirements') {
    Add-Failure 'instalar-tudo.ps1 nao executa Assert-UnitecSystemRequirements'
} else {
    Add-Pass 'instalar-tudo.ps1 executa checklist de requisitos'
}

if ($instalarText -notmatch '-LaragonInstaller\s+\$laragonInstaller') {
    Add-Failure 'instalar-tudo.ps1 nao passa -LaragonInstaller para setup-prerequisites.ps1'
} else {
    Add-Pass 'instalar-tudo.ps1 repassa -LaragonInstaller'
}

. (Join-Path $ScriptsDir 'unitec-install-lib.ps1')

$normalized = Resolve-UnitecAppPath -Path 'C:\UNITECNOLOGIA_WEB"'
if ($normalized -ne 'C:\UNITECNOLOGIA_WEB') {
    Add-Failure "Resolve-UnitecAppPath nao removeu aspas invalidas: $normalized"
} else {
    Add-Pass 'Resolve-UnitecAppPath corrige caminho com aspas do .bat'
}

try {
    Start-UnitecHiddenProcess -FilePath $env:ComSpec -ArgumentList @('/c', 'exit', '0')
    Add-Pass 'Start-UnitecHiddenProcess aceita argumentos'
    Start-UnitecHiddenProcess -FilePath $env:ComSpec
    Add-Pass 'Start-UnitecHiddenProcess aceita execucao sem argumentos'
} catch {
    Add-Failure "Start-UnitecHiddenProcess: $($_.Exception.Message)"
}

$fakeRoot = Join-Path $env:TEMP ("unitec-staging-test-{0}" -f [guid]::NewGuid().ToString('N'))
try {
    New-Item -ItemType Directory -Path $fakeRoot -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $fakeRoot 'vendor') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $fakeRoot 'public\build') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $fakeRoot 'installer\assets') -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $fakeRoot 'artisan') -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $fakeRoot 'vendor\autoload.php') -Force | Out-Null
    foreach ($rel in @(
        'scripts\instalar-tudo.ps1',
        'scripts\setup-prerequisites.ps1',
        'scripts\unitec-install-lib.ps1',
        'scripts\verificar-pc.ps1'
    )) {
        $target = Join-Path $fakeRoot $rel
        Ensure-Directory (Split-Path $target)
        Copy-Item (Join-Path $ScriptsDir (Split-Path $rel -Leaf)) $target -Force
    }
    'x' | Set-Content (Join-Path $fakeRoot 'public\build\manifest.json') -Encoding ASCII

    try {
        $null = Test-OfflineBundleReady -ProjectRoot $fakeRoot
        Add-Pass 'Test-OfflineBundleReady sem erro de parametro and'
    } catch {
        Add-Failure "Test-OfflineBundleReady: $($_.Exception.Message)"
    }

    $quotedPassword = Format-EnvValue 'rua@2050bc'
    if ($quotedPassword -notmatch '^".+"$') {
        Add-Failure "Format-EnvValue deveria citar senha com @: $quotedPassword"
    } else {
        Add-Pass 'Format-EnvValue cita senha com @ no .env'
    }

    $iniFile = Join-Path $env:TEMP ("unitec-mysql-ini-{0}.ini" -f [guid]::NewGuid().ToString('N'))
    try {
        @(
            '[mysqld]',
            'datadir='
        ) | Set-Content -Path $iniFile -Encoding ASCII

        $dataDir = Get-MysqlDataDirectoryFromIni -IniPath $iniFile -MysqlHome 'C:\laragon\bin\mysql\mysql-test'
        if ($dataDir -ne 'C:\laragon\bin\mysql\mysql-test\data') {
            Add-Failure "Get-MysqlDataDirectoryFromIni deveria usar fallback data: $dataDir"
        } else {
            Add-Pass 'Get-MysqlDataDirectoryFromIni ignora datadir vazio no my.ini'
        }

        try {
            Test-MysqlDataInitialized -DataDir ''
            Add-Pass 'Test-MysqlDataInitialized aceita caminho vazio sem erro de Path'
        } catch {
            Add-Failure "Test-MysqlDataInitialized com caminho vazio: $($_.Exception.Message)"
        }
    } finally {
        Remove-Item $iniFile -Force -ErrorAction SilentlyContinue
    }

    'x' | Set-Content (Join-Path $fakeRoot 'installer\assets\laragon-wamp.exe') -Encoding ASCII
    'x' | Set-Content (Join-Path $fakeRoot 'installer\assets\php-8.4-win.zip') -Encoding ASCII
    'x' | Set-Content (Join-Path $fakeRoot 'installer\assets\vc_redist.x64.exe') -Encoding ASCII

    for ($i = 0; $i -lt 1001; $i++) {
        'x' | Set-Content (Join-Path $fakeRoot "pad-$i.txt") -Encoding ASCII
    }

    if (Test-UnitecStagingReady -Root $fakeRoot) {
        Add-Pass 'Test-UnitecStagingReady aceita staging completo simulado'
    } else {
        Add-Failure 'Test-UnitecStagingReady rejeitou staging simulado valido'
    }

    $badRoot = Join-Path $env:TEMP ("unitec-staging-bad-{0}" -f [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path (Join-Path $badRoot 'installer\assets') -Force | Out-Null
    'x' | Set-Content (Join-Path $badRoot 'installer\assets\laragon-wamp.exe') -Encoding ASCII
    try {
        Assert-UnitecStagingReady -Root $badRoot
        Add-Failure 'Assert-UnitecStagingReady deveria falhar com staging incompleto'
    } catch {
        Add-Pass 'Assert-UnitecStagingReady bloqueia staging incompleto'
    } finally {
        Remove-Item $badRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
} finally {
    Remove-Item $fakeRoot -Recurse -Force -ErrorAction SilentlyContinue
}

if (Test-Path $StagingDir) {
    if (Test-UnitecStagingReady -Root $StagingDir) {
        $count = (Get-ChildItem $StagingDir -Recurse -File).Count
        Add-Pass "Staging atual valido ($count arquivos): $StagingDir"
    } else {
        Add-Failure "Staging atual incompleto: $StagingDir (rode build-setup.ps1 -SkipCompile)"
    }
} else {
    Write-Host '[INFO] Staging ainda nao existe (normal antes do build-setup)' -ForegroundColor Yellow
}

Write-Host ''
if ($failures.Count -eq 0) {
    Write-Host 'Todos os testes passaram.' -ForegroundColor Green
    exit 0
}

Write-Host "$($failures.Count) teste(s) falharam." -ForegroundColor Red
exit 1
