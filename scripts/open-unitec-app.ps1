#Requires -Version 5.1
<#
.SYNOPSIS
    Inicia o Unitec ERP e abre o navegador.
#>

param(
    [string]$AppPath = '',
    [string]$AppUrl = '',
    [string]$RelativePath = '/admin',
    [switch]$SkipBrowser,
    [switch]$LeigoMode
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

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
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 15

    if (-not $SkipBrowser) {
        Start-Process $targetUrl
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
