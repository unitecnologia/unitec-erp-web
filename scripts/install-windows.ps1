#Requires -Version 5.1
<#
.SYNOPSIS
    Instalador Unitec ERP Web — Windows + MySQL (runtime embutido).

.PARAMETER Unattended
    Modo silencioso (Setup.exe). Usa valores padrao sem perguntas.

.PARAMETER Offline
    Nao roda composer/npm; exige vendor/ e public/build/ no pacote.
#>

param(
    [switch]$Unattended,
    [switch]$Offline,
    [switch]$FromSetup,
    [string]$AppUrl = '',
    [string]$DbHost = '127.0.0.1',
    [string]$DbPort = '3306',
    [string]$DbName = 'unitec_erp',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = $ProjectRoot
}
$AppPath = Resolve-UnitecAppPath -Path $AppPath
Set-Location $AppPath

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')
Initialize-UnitecRuntimePath -AppPath $AppPath

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    $DbPassword = Get-UnitecDefaultDbPassword
}

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    $AppUrl = Get-DefaultAppUrl -ProjectRoot $AppPath
}

$isFreshInstall = -not (Test-Path '.env')
$isServerInstall = -not (Test-UnitecRemoteDatabaseHost -HostName $DbHost)
$useOfflineBundle = $Offline -or (Test-OfflineBundleReady -ProjectRoot $AppPath)

Write-Title 'Instalador Unitec ERP Web'

if ($Unattended) {
    Write-Host 'Modo automatico (Setup.exe).'
} else {
    Write-Host 'Este assistente configura o sistema no Windows com MySQL.'
    Write-Host 'Recomendado: PHP 8.4 + MariaDB (instalados automaticamente pelo Setup)'
}

Write-Host ''

Invoke-Step 'Verificando programas necessarios' {
    $missing = @()
    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath

    if (-not ((Test-Path $phpExe) -or (Test-Tool 'php'))) {
        $missing += 'PHP (reinstale o Unitec ERP ou habilite pdo_mysql)'
    }

    if (-not $useOfflineBundle) {
        if (-not (Test-Tool 'composer')) { $missing += 'Composer (https://getcomposer.org)' }
        if (-not (Test-Tool 'node')) { $missing += 'Node.js (https://nodejs.org)' }
        if (-not (Test-Tool 'npm')) { $missing += 'npm (vem com Node.js)' }
    }

    if ($missing.Count -gt 0) {
        Write-Err 'Faltam programas obrigatorios:'
        $missing | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
        Write-Host ''
        Write-Host 'Use o Instalador Sistema Facil.exe ou reinstale o Unitec ERP.'
        throw ('Faltam programas obrigatorios: {0}' -f ($missing -join '; '))
    }

    $phpVersion = Get-PhpVersionFromExe -SourceRoot $AppPath -AllowFix:$Unattended
    Write-Ok "PHP $phpVersion"

    if (-not (Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe)) {
        if ($Unattended) {
            throw 'Extensao PHP pdo_mysql nao detectada. Reinstale o Unitec ERP como administrador.'
        }

        Write-Warn 'Extensao PHP pdo_mysql nao detectada. Reinstale o Unitec ERP como administrador.'
    } else {
        Write-Ok 'Extensao pdo_mysql ativa.'
    }

    if (-not (Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe)) {
        if ($Unattended) {
            throw 'Extensao PHP intl nao detectada. Reinstale o Unitec ERP como administrador.'
        }

        Write-Warn 'Extensao PHP intl nao detectada. Reinstale o Unitec ERP como administrador.'
    } else {
        Write-Ok 'Extensao intl ativa.'
    }

    if ($useOfflineBundle) {
        Write-Ok 'Pacote offline detectado (vendor + assets compilados).'
    } else {
        Write-Ok 'Composer, Node.js e npm encontrados.'
    }
}

if (-not $Unattended) {
    Write-Title 'Configuracao do banco MySQL'

    $DbHost = Read-Default 'Servidor MySQL' $DbHost
    $DbPort = Read-Default 'Porta MySQL' $DbPort
    $DbName = Read-Default 'Nome do banco' $DbName
    $DbUser = Read-Default 'Usuario MySQL' $DbUser
    $DbPassword = Read-SecretDefault 'Senha MySQL' $DbPassword
    $AppUrl = Read-Default 'Endereco do sistema (APP_URL)' $AppUrl
}

if (Test-Path '.env') {
    if ($Unattended -and -not ($FromSetup -and $isServerInstall)) {
        Write-Ok 'Arquivo .env existente — mantido (atualizacao).'
        $isFreshInstall = $false
        Sync-UnitecEnvDatabasePassword -AppPath $AppPath -Password $DbPassword | Out-Null
    } elseif ($Unattended -and $FromSetup -and $isServerInstall) {
        Write-Ok 'Instalacao assistida no servidor — reconfigurando ambiente e banco.'
        $isFreshInstall = $true
    } else {
        Write-Warn 'Ja existe um arquivo .env nesta pasta.'
        $overwrite = Read-Default 'Substituir .env? (s/n)' 'n'
        if ($overwrite -notmatch '^[sS]') {
            Write-Warn 'Mantendo .env atual.'
            $isFreshInstall = $false
        } else {
            $isFreshInstall = $true
        }
    }
}

if ($isFreshInstall) {
    Invoke-Step 'Gerando arquivo .env' {
        Write-EnvFile -path '.env' -templatePath '.env.mysql.example' -replacements @{
            '__APP_URL__'     = $AppUrl
            '__DB_HOST__'     = $DbHost
            '__DB_PORT__'     = $DbPort
            '__DB_DATABASE__' = $DbName
            '__DB_USERNAME__' = $DbUser
            '__DB_PASSWORD__' = (Format-EnvValue $DbPassword)
        }

        Write-Ok 'Arquivo .env criado.'
    }
} elseif ($Unattended) {
    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
    Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword | Out-Null
}

Invoke-Step 'Preparando banco de dados' {
    Ensure-UnitecDatabaseSetup -AppPath $AppPath -MysqlHost $DbHost -Port $DbPort -User $DbUser -Password $DbPassword -Database $DbName -ThrowOnFailure

    if ($Unattended) {
        Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword | Out-Null
    }
}

if (-not $useOfflineBundle) {
    Invoke-Step 'Instalando dependencias PHP (Composer)' {
        & composer install --no-dev --optimize-autoloader --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'composer install falhou.' }
        Write-Ok 'Dependencias PHP instaladas.'
    }
} else {
    Invoke-Step 'Dependencias PHP (offline)' {
        if (-not (Test-Path 'vendor\autoload.php')) {
            throw 'Pacote offline incompleto: pasta vendor/ ausente.'
        }
        Write-Ok 'vendor/ ja incluido no pacote.'
    }
}

if ($isFreshInstall -or (Test-UnitecEnvMissingAppKey -AppPath $AppPath)) {
    Invoke-Step 'Gerando chave da aplicacao' {
        $configCache = Join-Path $AppPath 'bootstrap\cache\config.php'
        if (Test-Path $configCache) {
            Remove-Item $configCache -Force
        }

        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('key:generate', '--force') | Out-Null
        Write-Ok 'APP_KEY gerada.'
    }
}

Invoke-Step 'Criando/atualizando tabelas no banco (migrate)' {
    $freshMigrate = $false

    if ($isServerInstall) {
        if ($isFreshInstall -or $FromSetup) {
            $freshMigrate = $true
        } elseif (Test-UnitecDatabaseConnectionFromEnv -AppPath $AppPath) {
            $freshMigrate = Test-UnitecNeedsInitialSeed -AppPath $AppPath
        }
    }

    if ($freshMigrate -and -not $isFreshInstall) {
        Write-Warn 'Instalacao incompleta detectada — recriando tabelas do zero (migrate:fresh).'
    }

    if ($FromSetup -and $isServerInstall -and $freshMigrate) {
        Write-Ok 'Servidor: banco sera recriado do zero (migrate:fresh).'
    }

    Invoke-UnitecDatabaseMigrate -AppPath $AppPath -LogToInstallFile:$Unattended -FreshInstall:$freshMigrate
    Write-Ok 'Migracoes aplicadas.'
}

$runSeed = $isFreshInstall
if (-not $runSeed) {
    $runSeed = Test-UnitecNeedsInitialSeed -AppPath $AppPath
}

if ($runSeed) {
    if (-not $isFreshInstall) {
        Write-Warn 'Instalacao incompleta detectada — executando dados iniciais (seed).'
    }

    Invoke-Step 'Dados iniciais (empresa e usuario)' {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('db:seed', '--force') | Out-Null
        Write-Ok 'Seed concluido.'
    }
} else {
    Write-Ok 'Instalacao existente — seed ignorado.'
}

if (-not $useOfflineBundle) {
    Invoke-Step 'Compilando interface (npm run build)' {
        & npm install --ignore-scripts
        if ($LASTEXITCODE -ne 0) { throw 'npm install falhou.' }

        & npm run build
        if ($LASTEXITCODE -ne 0) { throw 'npm run build falhou.' }
        Write-Ok 'Assets compilados.'
    }
} else {
    Invoke-Step 'Interface (offline)' {
        if (-not (Test-OfflineBundleReady -ProjectRoot $AppPath)) {
            throw 'Pacote offline incompleto: public/build/ ausente.'
        }
        Write-Ok 'Assets ja compilados no pacote.'
    }
}

Invoke-Step 'Pasta de arquivos (fotos, uploads)' {
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('storage:link') -AllowFailure | Out-Null
    Write-Ok 'storage:link executado.'
}

Invoke-Step 'Otimizando configuracao' {
    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
    Sync-UnitecEnvPerformanceSettings -AppPath $AppPath | Out-Null

    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:clear') -AllowFailure | Out-Null
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') | Out-Null
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('view:cache') | Out-Null

    Write-Ok 'Cache de producao gerado.'
}

Write-Title 'Instalacao concluida'

Write-Host ''
Write-Host 'Acesse o sistema em:' -ForegroundColor Green
Write-Host "  $AppUrl" -ForegroundColor White
Write-Host ''
Write-Host 'Login inicial:' -ForegroundColor Green
Write-Host '  E-mail: usuario@unitecnologia.local'
Write-Host '  Senha:  01'
Write-Host ''
Write-Warn 'Troque a senha apos o primeiro acesso.'
Write-Host ''

Write-Host ''
Write-Host 'Use os atalhos INFORSYSTEM na Area de Trabalho:' -ForegroundColor Green
Write-Host '  Retaguarda, PDV e Pre-venda'
Write-Host ''
Write-Host "Pasta do sistema: $(Get-UnitecDefaultAppPath)"
Write-Host ''
Write-Host 'Documentacao: docs\INSTALACAO-CLIENTE.md'
Write-Host ''

if ($Unattended) {
    exit 0
}
