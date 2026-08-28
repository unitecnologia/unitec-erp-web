#Requires -Version 5.1
<#
.SYNOPSIS
    [LEGADO / NAO OFICIAL] Sobe MySQL+PHP sem o servico Windows.
.DESCRIPTION
    Auto-start oficial: servico UnitecErpServer (Automatic).
    Este script permanece so para suporte/reparo manual — nao e registrado no logon.
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

# Se o servico existir, preferir ele.
$svc = Get-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
if ($svc) {
    if ($svc.Status -ne 'Running') {
        Start-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
    }
    exit 0
}

try {
    Ensure-UnitecEnvFile -AppPath $AppPath | Out-Null
    Sync-UnitecEnvAppUrl -AppPath $AppPath | Out-Null
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 25
    exit 0
} catch {
    Write-InstallLog -AppPath $AppPath -Message "Auto-inicio: $($_.Exception.Message)"
    exit 1
}
