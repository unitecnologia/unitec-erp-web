#Requires -Version 5.1
<#
.SYNOPSIS
    Instalador Unitec ERP Web - Windows + MySQL (runtime embutido).

.PARAMETER Unattended
    Modo silencioso (Setup.exe). Usa valores padrao sem perguntas.

.PARAMETER Offline
    Nao roda composer/npm; exige vendor/ e public/build/ no pacote.
#>

param(
    [switch]$Unattended,
    [switch]$Offline,
    [switch]$FromSetup,
    # Pasta/instalacao ja existia: reinstala arquivos, preserva banco e .env.
    [switch]$Recovery,
    # SOMENTE suporte: apaga e recria o banco. Nunca usar em cliente com dados.
    [switch]$ForceDatabaseReset,
    # Restaura dump embutido (installer\seed) e sobrescreve .env/banco mesmo em pasta existente.
    [switch]$ApplyBundledSeed,
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

if (-not $ApplyBundledSeed -and (Test-UnitecBundledSeedPresent -AppPath $AppPath)) {
    $ApplyBundledSeed = $true
}

if ($ApplyBundledSeed) {
    # Pacote com dados de desenvolvimento: sobrescreve .env e banco.
    $Recovery = $false
    Write-Warn 'Pacote com seed de desenvolvimento detectado — .env e banco serao substituidos.'
}

if (-not $Recovery -and -not $ApplyBundledSeed -and (Test-UnitecExistingInstall -AppPath $AppPath)) {
    $Recovery = $true
}

if ($Recovery -and -not $ForceDatabaseReset -and -not $ApplyBundledSeed) {
    Ensure-UnitecEnvFile -AppPath $AppPath -AppUrl $AppUrl | Out-Null
}

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    $DbPassword = Get-UnitecDefaultDbPassword
}

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    $AppUrl = Get-DefaultAppUrl -ProjectRoot $AppPath
}

# Fresh = pasta nova sem instalacao previa. Recovery nunca e fresh.
# Seed embutido: trata como fresh (sobrescreve .env se veio no pacote).
$isFreshInstall = (-not $Recovery -and -not (Test-Path '.env')) -or $ApplyBundledSeed
$isServerInstall = -not (Test-UnitecRemoteDatabaseHost -HostName $DbHost)
$useOfflineBundle = $Offline -or (Test-OfflineBundleReady -ProjectRoot $AppPath)

Write-Title 'Instalador Unitec ERP Web'

if ($ApplyBundledSeed) {
    Write-Host 'Modo seed: instalacao com banco e .env de desenvolvimento embutidos.' -ForegroundColor Cyan
} elseif ($Recovery -and -not $ForceDatabaseReset) {
    Write-Host 'Modo recupera: instalacao existente detectada — banco e .env serao preservados.' -ForegroundColor Cyan
} elseif ($Unattended) {
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
        $missing | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
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

if ($ApplyBundledSeed) {
    Write-Ok 'Seed embutido: .env do pacote sera usado (sobrescrita permitida).'
    $isFreshInstall = $true
} elseif (Test-Path '.env') {
    if ($Recovery -and -not $ForceDatabaseReset) {
        Write-Ok 'Modo recupera — mantendo .env e banco existentes.'
        $isFreshInstall = $false
    } elseif ($Unattended -and -not ($FromSetup -and $isServerInstall)) {
        Write-Ok 'Arquivo .env existente - mantido (atualizacao).'
        $isFreshInstall = $false
        Sync-UnitecEnvDatabasePassword -AppPath $AppPath -Password $DbPassword | Out-Null
    } elseif ($Unattended -and $FromSetup -and $isServerInstall) {
        # Reinstalar Setup.exe NÃO pode zerar .env/banco de cliente com dados.
        $dbAlreadyHasData = $false
        if (Test-UnitecDatabaseConnectionFromEnv -AppPath $AppPath) {
            $dbAlreadyHasData = -not (Test-UnitecDatabaseLooksEmpty -AppPath $AppPath)
        }

        if (($dbAlreadyHasData -or $Recovery) -and -not $ForceDatabaseReset) {
            Write-Ok 'Setup em instalacao existente - mantendo .env e banco (sem reset).'
            $isFreshInstall = $false
            if (-not $Recovery) {
                Sync-UnitecEnvDatabasePassword -AppPath $AppPath -Password $DbPassword | Out-Null
            }
        } else {
            Write-Ok 'Instalacao assistida no servidor - ambiente novo (banco vazio).'
            $isFreshInstall = $true
        }
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

if ($Recovery -and -not $ForceDatabaseReset -and -not $ApplyBundledSeed) {
    $isFreshInstall = $false
}

if ($ApplyBundledSeed -and (Test-Path '.env')) {
    Invoke-Step 'Usando .env embutido no pacote' {
        Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
        Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword | Out-Null
        Write-Ok 'Arquivo .env do pacote mantido (APP_URL/DB ajustados).'
    }
} elseif ($isFreshInstall) {
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
} elseif ($Unattended -and -not $Recovery) {
    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
    Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword | Out-Null
} elseif ($Unattended -and $Recovery) {
    # Recupera: so ajusta URL; nao sobrescreve senha/host do .env do cliente.
    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
}

Invoke-Step 'Preparando banco de dados' {
    Ensure-UnitecDatabaseSetup -AppPath $AppPath -MysqlHost $DbHost -Port $DbPort -User $DbUser -Password $DbPassword -Database $DbName -ThrowOnFailure

    if ($Unattended -and -not $Recovery) {
        Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword | Out-Null
    }

    if ($ApplyBundledSeed) {
        Import-UnitecBundledSeedDatabase -AppPath $AppPath -DbHost $DbHost -DbPort $DbPort -DbUser $DbUser -DbPassword $DbPassword -DbName $DbName
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

if ($ApplyBundledSeed -and -not (Test-UnitecEnvMissingAppKey -AppPath $AppPath)) {
    Write-Ok 'APP_KEY do .env embutido mantida.'
} elseif ($isFreshInstall -or (Test-UnitecEnvMissingAppKey -AppPath $AppPath)) {
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
    $dbLooksEmpty = $true

    if ($isServerInstall -and (Test-UnitecDatabaseConnectionFromEnv -AppPath $AppPath)) {
        $dbLooksEmpty = Test-UnitecDatabaseLooksEmpty -AppPath $AppPath
    }

    if ($ApplyBundledSeed) {
        Write-Ok 'Seed embutido restaurado — migrate incremental (sem migrate:fresh).'
        $freshMigrate = $false
    } elseif ($ForceDatabaseReset) {
        Write-Warn 'ForceDatabaseReset ativo — migrate:fresh sera tentado (suporte apenas).'
        $freshMigrate = $true
    } elseif ($Recovery) {
        Write-Ok 'Modo recupera — migrate incremental (banco preservado).'
        $freshMigrate = $false
    } elseif ($isServerInstall -and $dbLooksEmpty -and ($isFreshInstall -or $FromSetup)) {
        # So zera banco quando realmente esta vazio (1a instalacao).
        $freshMigrate = $true
    } elseif ($isServerInstall -and -not $dbLooksEmpty -and ($isFreshInstall -or $FromSetup)) {
        Write-Warn 'Banco ja possui dados — migrate:fresh BLOQUEADO. Aplicando migrate incremental.'
        $freshMigrate = $false
    }

    if ($freshMigrate) {
        Write-Ok 'Servidor: banco vazio — criando tabelas do zero (migrate:fresh).'
    }

    Invoke-UnitecDatabaseMigrate -AppPath $AppPath -LogToInstallFile:$Unattended -FreshInstall:$freshMigrate
    Write-Ok 'Migracoes aplicadas.'
}

# Seed so em banco vazio / sem usuarios. Nunca em cliente com dados.
$runSeed = $false
if ($ApplyBundledSeed) {
    Write-Ok 'Seed artisan ignorado (dados vindos do dump embutido).'
    $runSeed = $false
} elseif ($ForceDatabaseReset) {
    $runSeed = $true
} elseif ($Recovery) {
    # Recupera: so seed se realmente nao houver usuarios (banco intacto = ignora).
    $runSeed = Test-UnitecNeedsInitialSeed -AppPath $AppPath
    if (-not $runSeed) {
        Write-Ok 'Modo recupera — seed ignorado (dados preservados).'
    }
} elseif (Test-UnitecNeedsInitialSeed -AppPath $AppPath) {
    $runSeed = $true
}

if ($runSeed) {
    if (-not $isFreshInstall) {
        Write-Warn 'Banco sem usuarios - executando dados iniciais (seed).'
    }

    Invoke-Step 'Dados iniciais (empresa e usuario)' {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('db:seed', '--force') | Out-Null
        Write-Ok 'Seed concluido.'
    }
} else {
    Write-Ok 'Instalacao existente - seed ignorado (dados preservados).'
}

Invoke-Step 'Preparando WhatsApp' {
    $nodeExe = Ensure-UnitecNodeRuntime -AppPath $AppPath -SourceRoot $AppPath
    if (-not $nodeExe) {
        throw 'Node.js nao ficou disponivel para o gateway WhatsApp.'
    }

    $npmExe = Get-UnitecNpmExecutable -AppPath $AppPath
    $gatewayPath = Join-Path $AppPath 'services\erp-whatsapp-gateway'

    if (-not $npmExe -or -not (Test-Path $gatewayPath)) {
        throw 'Gateway WhatsApp ou npm nao encontrado no pacote.'
    }

    $gatewayDepsBundle = Join-Path $AppPath 'installer\assets\whatsapp-gateway-node-modules.zip'
    $gatewayDepsTarget = Join-Path $gatewayPath 'node_modules'

    if (-not (Test-Path (Join-Path $gatewayDepsTarget '@whiskeysockets\baileys\package.json'))) {
        if (Test-Path $gatewayDepsBundle) {
            Expand-Archive -Path $gatewayDepsBundle -DestinationPath $gatewayPath -Force
        } else {
            Push-Location $gatewayPath
            try {
                & $npmExe ci --omit=dev --no-fund --no-audit
                if ($LASTEXITCODE -ne 0) {
                    throw 'npm ci do gateway WhatsApp falhou.'
                }
            } finally {
                Pop-Location
            }
        }
    }

    if (-not (Test-Path (Join-Path $gatewayDepsTarget '@whiskeysockets\baileys\package.json'))) {
        throw 'Dependencias offline do gateway WhatsApp nao foram encontradas.'
    }

    Write-Ok 'Gateway WhatsApp preparado.'
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

Invoke-Step 'Liberando portas no firewall (estacoes da rede)' {
    Register-UnitecNetworkFirewallRules -IncludeMariaDb
}

Write-Host ''
Write-Host 'Acesse o sistema em:' -ForegroundColor Green
Write-Host "  $AppUrl" -ForegroundColor White
$lanIp = Get-UnitecLanIPv4Address
if (-not [string]::IsNullOrWhiteSpace($lanIp)) {
    Write-Host "  http://${lanIp}:$($script:UnitecServePort)/admin/login  (outras estacoes)" -ForegroundColor Cyan
}
Write-Host ''
Write-Host 'Login inicial:' -ForegroundColor Green
Write-Host '  Usuario: USUARIO'
Write-Host '  Senha:  01'
Write-Host ''
Write-Warn 'Troque a senha apos o primeiro acesso.'
Write-Host ''

Write-Host ''
Write-Host 'Use o atalho "Unitec ERP" na Area de Trabalho (janela de aplicativo Chrome/Edge).' -ForegroundColor Green
Write-Host ''
Write-Host "Pasta do sistema: $(Get-UnitecDefaultAppPath)"
Write-Host ''
Write-Host 'Documentacao: docs\INSTALACAO-CLIENTE.md'
Write-Host ''

if ($Unattended) {
    exit 0
}
