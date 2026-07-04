#Requires -Version 5.1
<#
.SYNOPSIS
    Sobe MySQL e servidor do Unitec ERP ao iniciar o Windows (sem abrir navegador).
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if (-not (Test-Path (Join-Path $AppPath '.env'))) {
    exit 0
}

try {
    Sync-UnitecEnvAppUrl -AppPath $AppPath | Out-Null
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 25
    exit 0
} catch {
    Write-InstallLog -AppPath $AppPath -Message "Auto-inicio: $($_.Exception.Message)"
    exit 1
}
