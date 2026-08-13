#Requires -Version 5.1

<#

.SYNOPSIS

    Instalador Unitec ERP - um clique, sem perguntas.

#>



param(

    [string]$AppPath = '',

    [string]$AppUrl = '',

    [string]$DbHost = '127.0.0.1',

    [switch]$NoPause,

    [switch]$FromSetup,

    [switch]$Recovery,

    [switch]$ApplyBundledSeed

)



$ErrorActionPreference = 'Stop'

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8



. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')



function Test-IsAdministrator {

    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()

    $principal = New-Object Security.Principal.WindowsPrincipal($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

}



function Request-Administrator {

    if (Test-IsAdministrator) {

        return

    }



    $scriptPath = $MyInvocation.MyCommand.Path

    $argList = @(

        '-Sta',

        '-NoProfile',

        '-ExecutionPolicy', 'Bypass',

        '-File', """$scriptPath""",

        '-AppPath', """$AppPath""",

        '-AppUrl', """$AppUrl""",

        '-DbHost', """$DbHost"""

    )



    if ($FromSetup) { $argList += '-FromSetup' }

    if ($Recovery) { $argList += '-Recovery' }

    if ($ApplyBundledSeed) { $argList += '-ApplyBundledSeed' }

    if ($NoPause) { $argList += '-NoPause' }



    Start-Process powershell.exe -Verb RunAs -ArgumentList $argList -Wait

    exit 0

}



function Write-InstallStep {

    param(

        $Progress,

        [string]$Message,

        [int]$Percent,

        [string]$AppPath,

        [switch]$LeigoMode

    )



    Write-InstallLog -AppPath $AppPath -Message $Message



    if ($LeigoMode) {

        Update-UnitecLeigoProgress -Context $Progress -Message $Message -Percent $Percent

    } else {

        Write-Host ">> $Message" -ForegroundColor White

    }

}



if ([string]::IsNullOrWhiteSpace($AppPath)) {

    $AppPath = Get-UnitecDefaultAppPath

}



$AppPath = Resolve-UnitecAppPath -Path $AppPath

if (-not $ApplyBundledSeed -and (Test-UnitecBundledSeedPresent -AppPath $AppPath)) {
    $ApplyBundledSeed = $true
}

if ($ApplyBundledSeed) {
    $Recovery = $false
} elseif (-not $Recovery -and (Test-UnitecExistingInstall -AppPath $AppPath)) {
    $Recovery = $true
}

if ([string]::IsNullOrWhiteSpace($AppUrl)) {

    $AppUrl = Get-UnitecDefaultAppUrl

}



$SourceRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))



Request-Administrator

$LeigoMode = [bool]$FromSetup

if ($FromSetup) {

    $NoPause = $true

}



$logFile = Start-InstallLog -AppPath $AppPath

# TEMP seguro antes de qualquer Expand-Archive / checklist (PCs com caminho 8.3 quebrado).
$safeTemp = Initialize-UnitecSafeTempEnvironment -AppPath $AppPath
Write-InstallLog -AppPath $AppPath -Message ("TEMP seguro: {0}" -f $safeTemp)

$progress = $null



if ($LeigoMode) {

    $progress = Start-UnitecLeigoProgress

}



Write-InstallLog -AppPath $AppPath -Message 'Inicio da instalacao automatica (runtime MariaDB mariadb-install-db)'

if ($Recovery) {
    Write-InstallLog -AppPath $AppPath -Message 'Modo recupera: pasta/instalacao existente — banco sera preservado.'
    Ensure-UnitecEnvFile -AppPath $AppPath -AppUrl $AppUrl | Out-Null
}



try {

    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Verificando o computador...' -Percent 8

    Ensure-Directory (Split-Path $AppPath)



    $sourceFull = [System.IO.Path]::GetFullPath($SourceRoot).TrimEnd('\')

    $targetFull = [System.IO.Path]::GetFullPath($AppPath).TrimEnd('\')



    if ($sourceFull -ne $targetFull) {

        Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Copiando arquivos do sistema...' -Percent 15

        if ($Recovery) {
            Copy-UnitecProjectTree -SourceRoot $sourceFull -TargetRoot $targetFull -Quiet -ExcludeTools
        } else {
            Copy-UnitecProjectTree -SourceRoot $sourceFull -TargetRoot $targetFull -Quiet
        }

    }



    Set-Location $AppPath



    if ([string]::IsNullOrWhiteSpace($DbHost)) {
        $DbHost = '127.0.0.1'
    }

    $remoteDb = Test-UnitecRemoteDatabaseHost -HostName $DbHost

    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Verificando requisitos...' -Percent 22

    Assert-UnitecSystemRequirements -SourceRoot $AppPath -FixVcRuntime



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Instalando PHP e MySQL...' -Percent 35

    $setupScript = Join-Path $PSScriptRoot 'setup-prerequisites.ps1'

    if ($remoteDb) {
        Write-InstallLog -AppPath $AppPath -Message ('Modo terminal - banco remoto em {0}' -f $DbHost)
        & $setupScript -AppPath $AppPath -SourceRoot $AppPath -ServiceWaitSeconds 25 -SkipMysql
    } else {
        Write-InstallLog -AppPath $AppPath -Message 'Modo servidor - banco local com acesso na rede (3306)'
        & $setupScript -AppPath $AppPath -SourceRoot $AppPath -ServiceWaitSeconds 25
    }



    $offlineReady = Test-OfflineBundleReady -ProjectRoot $AppPath

    $installArgs = @{

        Unattended = $true

        AppPath    = $AppPath

        AppUrl     = $AppUrl

        DbHost     = $DbHost

        FromSetup  = $FromSetup

        Recovery   = $Recovery

        ApplyBundledSeed = $ApplyBundledSeed

    }



    if ($offlineReady) {

        $installArgs.Offline = $true

    }



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Configurando banco de dados e usuario...' -Percent 55

    & (Join-Path $PSScriptRoot 'install-windows.ps1') @installArgs

    if ($LASTEXITCODE -ne 0) {

        throw 'A configuracao do sistema nao foi concluida.'

    }



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Liberando portas no firewall (estacoes da rede)...' -Percent 70

    Register-UnitecNetworkFirewallRules -IncludeMariaDb

    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Criando atalho na Area de Trabalho...' -Percent 72

    New-UnitecDesktopShortcuts -AppPath $AppPath

    Install-UnitecHeidiSqlSupport -AppPath $AppPath

    # Remove tarefa de logon legada; auto-start oficial = servico UnitecErpServer.
    Unregister-UnitecLogonStartup -AppPath $AppPath

    $serviceScript = Join-Path $PSScriptRoot 'install-unitec-erp-service.ps1'
    $serverExe = Join-Path $AppPath 'bin\UnitecErpServer.exe'
    $serviceInstalled = $false
    if ((Test-Path $serviceScript) -and (Test-Path $serverExe)) {
        Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Registrando servico Windows Unitec ERP Server...' -Percent 80
        try {
            & $serviceScript -AppPath $AppPath
            $serviceInstalled = $true
        } catch {
            Write-Warn ("Servico Windows nao instalado agora: {0}" -f $_.Exception.Message)
        }
    }

    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Iniciando o Unitec ERP...' -Percent 85

    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null

    if (-not $serviceInstalled) {
        Start-UnitecApplicationServer -AppPath $AppPath
    }



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Quase pronto...' -Percent 95

    if (-not (Wait-UnitecApplicationReady -AppUrl $AppUrl -MaxAttempts 15 -DelaySeconds 2)) {

        throw 'O sistema nao respondeu apos a instalacao.'

    }



    Write-InstallLog -AppPath $AppPath -Message 'Instalacao concluida com sucesso'

    # Sessões PHP antigas + marcador para o navegador limpar cookies sozinho na abertura.
    $sessionsDir = Join-Path $AppPath 'storage\framework\sessions'
    if (Test-Path -LiteralPath $sessionsDir) {
        Get-ChildItem -LiteralPath $sessionsDir -File -Force -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -ne '.gitignore' } |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }
    Set-Content -LiteralPath (Join-Path $AppPath '.unitec-browser-reset') -Value ((Get-Date).ToString('o')) -Encoding ASCII

    if ($LeigoMode) {

        Update-UnitecLeigoProgress -Context $progress -Message 'Abrindo o sistema...' -Percent 100

    }



    $launcherExe = Join-Path $AppPath 'bin\Unitec ERP.exe'
    if (Test-Path -LiteralPath $launcherExe) {
        $openProc = Start-Process -FilePath $launcherExe -ArgumentList @(
            '--app', $AppPath
        ) -PassThru -WindowStyle Normal

        if ($null -eq $openProc) {
            Write-InstallLog -AppPath $AppPath -Message 'Sistema instalado; abertura automatica falhou (use o atalho Unitec ERP).'
        }
    } else {
        Write-InstallLog -AppPath $AppPath -Message 'Sistema instalado; bin\Unitec ERP.exe ausente — use o atalho apos build-erp-desktop.'
    }

    if ($LeigoMode) {

        Stop-UnitecLeigoProgress -Context $progress

        $progress = $null



        Show-UnitecLeigoMessage -Title 'Instalacao concluida!' -Icon Information -Message @"

Pronto! O Unitec ERP foi instalado.



Para abrir amanha:

  clique no atalho "Unitec ERP" na Area de Trabalho.



Login:

  Usuario: USUARIO

  Senha:  01



Leia tambem o arquivo "COMO USAR - Unitec ERP" na Area de Trabalho.

"@

    } else {

        Write-Title 'Pronto!'

        Write-Host 'Login: USUARIO / Senha: 01'

    }

} catch {

    if ($null -ne $progress) {

        Stop-UnitecLeigoProgress -Context $progress

        $progress = $null

    }



    Write-InstallLog -AppPath $AppPath -Message "ERRO: $($_.Exception.Message)"



    if ($LeigoMode) {

        Show-UnitecLeigoMessage -Title 'Instalacao nao concluida' -Icon Error -Message @"

Nao foi possivel concluir a instalacao.



$($_.Exception.Message)



Entre em contato com o suporte da Unitecnologia

e informe que a instalacao falhou.

"@

    } else {

        Write-Err $_.Exception.Message

        Write-Host "Detalhes: $logFile" -ForegroundColor Yellow

        if (-not $NoPause) { Read-Host 'Pressione Enter para fechar' }

    }



    exit 1

}



if (-not $NoPause -and -not $LeigoMode) {

    Read-Host 'Pressione Enter para fechar'

}



exit 0

