#Requires -Version 5.1
<#
.SYNOPSIS
    Pos-update: NAO reinicia mais o PHP.

.DESCRIPTION
    Mantido so por compatibilidade com updates antigos que ainda agendam este script.
    Matar o artisan serve apos o update deixava a porta 8765 zumbi (TCP ok, HTTP morto).
    OPcache com validate_timestamps=1 carrega os arquivos novos sem kill.
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$AppPath,

    [int]$DelaySeconds = 0
)

$ErrorActionPreference = 'SilentlyContinue'
$AppPath = [System.IO.Path]::GetFullPath($AppPath)
$logFile = Join-Path $AppPath 'storage\logs\erp-update-spawn.log'
$line = '[{0}] restart-apos-update: ignorado (update sem reiniciar PHP).' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
try { Add-Content -Path $logFile -Value $line -Encoding UTF8 } catch {}
exit 0
