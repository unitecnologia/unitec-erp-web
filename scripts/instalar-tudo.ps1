#Requires -Version 5.1

<#

.SYNOPSIS

    Instalador Unitec ERP — um clique, sem perguntas.

#>



param(

    [string]$AppPath = '',

    [string]$AppUrl = '',

    [string]$DbHost = '127.0.0.1',

    [switch]$NoPause,

    [switch]$FromSetup

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

$progress = $null



if ($LeigoMode) {

    $progress = Start-UnitecLeigoProgress

}



Write-InstallLog -AppPath $AppPath -Message 'Inicio da instalacao automatica (runtime MariaDB mariadb-install-db)'



try {

    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Verificando o computador...' -Percent 8

    Ensure-Directory (Split-Path $AppPath)



    $sourceFull = [System.IO.Path]::GetFullPath($SourceRoot).TrimEnd('\')

    $targetFull = [System.IO.Path]::GetFullPath($AppPath).TrimEnd('\')



    if ($sourceFull -ne $targetFull) {

        Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Copiando arquivos do sistema...' -Percent 15

        Copy-UnitecProjectTree -SourceRoot $sourceFull -TargetRoot $targetFull -Quiet

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
        Write-InstallLog -AppPath $AppPath -Message ('Modo terminal — banco remoto em {0}' -f $DbHost)
        & $setupScript -AppPath $AppPath -SourceRoot $AppPath -ServiceWaitSeconds 25 -SkipMysql
    } else {
        Write-InstallLog -AppPath $AppPath -Message 'Modo servidor — banco local com acesso na rede (3306)'
        & $setupScript -AppPath $AppPath -SourceRoot $AppPath -ServiceWaitSeconds 25
    }



    $offlineReady = Test-OfflineBundleReady -ProjectRoot $AppPath

    $installArgs = @{

        Unattended = $true

        AppPath    = $AppPath

        AppUrl     = $AppUrl

        DbHost     = $DbHost

        FromSetup  = $FromSetup

    }



    if ($offlineReady) {

        $installArgs.Offline = $true

    }



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Configurando banco de dados e usuario...' -Percent 55

    & (Join-Path $PSScriptRoot 'install-windows.ps1') @installArgs

    if ($LASTEXITCODE -ne 0) {

        throw 'A configuracao do sistema nao foi concluida.'

    }



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Criando atalho na Area de Trabalho...' -Percent 72

    Register-UnitecFirewallRule

    New-UnitecDesktopShortcuts -AppPath $AppPath

    Install-UnitecHeidiSqlSupport -AppPath $AppPath

    Register-UnitecLogonStartup -AppPath $AppPath



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Iniciando o Unitec ERP...' -Percent 85

    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null

    Start-UnitecApplicationServer -AppPath $AppPath



    Write-InstallStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath -Message 'Quase pronto...' -Percent 95

    if (-not (Wait-UnitecApplicationReady -AppUrl $AppUrl -MaxAttempts 15 -DelaySeconds 2)) {

        throw 'O sistema nao respondeu apos a instalacao.'

    }



    Write-InstallLog -AppPath $AppPath -Message 'Instalacao concluida com sucesso'



    if ($LeigoMode) {

        Update-UnitecLeigoProgress -Context $progress -Message 'Abrindo o sistema...' -Percent 100

    }



    $openScript = Join-Path $PSScriptRoot 'open-unitec-app.ps1'

    & $openScript -AppPath $AppPath -AppUrl $AppUrl -RelativePath '/admin' -LeigoMode
    if ($LASTEXITCODE -ne 0) {
        throw 'O sistema foi instalado, mas nao abriu no navegador. Use o atalho Unitec ERP.'
    }

    if ($LeigoMode) {

        Stop-UnitecLeigoProgress -Context $progress

        $progress = $null



        Show-UnitecLeigoMessage -Title 'Instalacao concluida!' -Icon Information -Message @"

Pronto! O Unitec ERP foi instalado.



Para abrir amanha:

  clique no atalho "Unitec ERP" na Area de Trabalho.



Login:

  E-mail: usuario@unitecnologia.local

  Senha:  01



Leia tambem o arquivo "COMO USAR - Unitec ERP" na Area de Trabalho.

"@

    } else {

        Write-Title 'Pronto!'

        Write-Host 'Login: usuario@unitecnologia.local / Senha: 01'

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

