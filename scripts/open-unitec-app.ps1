#Requires -Version 5.1
<#
.SYNOPSIS
    Inicia o Unitec ERP e abre o navegador (Chrome/Edge) com o zoom configurado.
#>

param(
    [string]$AppPath = '',
    [string]$AppUrl = '',
    [string]$RelativePath = '/admin',
    [switch]$SkipBrowser,
    [switch]$LeigoMode,
    [switch]$Kiosk
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

function Get-UnitecBrowserExe {
    $candidates = @(
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'),
        (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe')
    )

    foreach ($path in $candidates) {
        if (-not [string]::IsNullOrWhiteSpace($path) -and (Test-Path $path)) {
            return $path
        }
    }

    return $null
}

function Open-UnitecBrowser {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$AppPath,
        [switch]$Kiosk
    )

    # Zoom fica na página (param_ui_zoom) — funciona igual abrindo a URL direto no navegador.
    $browser = Get-UnitecBrowserExe

    if ([string]::IsNullOrWhiteSpace($browser)) {
        Start-Process $Url
        return
    }

    $arguments = @()
    if ($Kiosk) {
        $arguments += '--kiosk'
    }
    $arguments += $Url

    Start-Process -FilePath $browser -ArgumentList $arguments
}

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    $AppUrl = Get-UnitecDefaultAppUrl
}

$relative = if ($RelativePath.StartsWith('/')) { $RelativePath } else { "/$RelativePath" }
$targetUrl = $AppUrl.TrimEnd('/') + $relative

try {
    if (-not (Test-Path (Join-Path $AppPath 'public\index.php'))) {
        throw 'O sistema nao foi encontrado. Reinstale o Unitec ERP.'
    }

    if (-not $LeigoMode) {
        Write-Title 'Abrindo Unitec ERP'
    }

    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null

    # Se um update anterior parou no PHP/VC++, termina agora (apos reboot).
    try {
        if (Test-UnitecUpdatePendingFinish -AppPath $AppPath) {
            if (-not $LeigoMode) {
                Write-Host 'Detectada atualizacao pendente - finalizando...' -ForegroundColor Cyan
            }
            Complete-UnitecPendingUpdate -AppPath $AppPath | Out-Null
        }
    } catch {
        Write-InstallLog -AppPath $AppPath -Message ('Falha ao finalizar update pendente: {0}' -f $_.Exception.Message)
        throw ('Atualizacao pendente nao concluida: {0}' -f $_.Exception.Message)
    }

    Start-UnitecStack -AppPath $AppPath -WaitSeconds 15

    if (-not $SkipBrowser) {
        Open-UnitecBrowser -Url $targetUrl -AppPath $AppPath -Kiosk:$Kiosk
    }

    exit 0
} catch {
    Write-InstallLog -AppPath $AppPath -Message $_.Exception.Message

    Show-UnitecLeigoMessage -Title 'Unitec ERP' -Message @"
Nao foi possivel abrir o sistema.

$($_.Exception.Message)

Abra o arquivo COMO USAR na Area de Trabalho
ou ligue para o suporte da Unitecnologia.
"@ -Icon Error

    exit 1
}
