#Requires -Version 5.1
<#
.SYNOPSIS
    Ajusta o .env para desenvolvimento nativo no Windows (PHP + MariaDB em tools\).
#>

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path (Join-Path $PSScriptRoot '..')

Sync-UnitecEnvDatabaseCredentials -AppPath $AppPath `
    -DbHost '127.0.0.1' `
    -DbPort '3306' `
    -DbName 'unitec_erp' `
    -DbUser 'root' `
    -DbPassword (Get-UnitecDefaultDbPassword)

Sync-UnitecEnvPerformanceSettings -AppPath $AppPath | Out-Null
Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl 'http://127.0.0.1:8000' | Out-Null

$envFile = Join-Path $AppPath '.env'
$lines = @(Get-Content $envFile -Encoding UTF8)
$foundRuntime = $false

for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match '^\s*DEV_RUNTIME\s*=') {
        $lines[$i] = 'DEV_RUNTIME=windows'
        $foundRuntime = $true
        break
    }
}

if (-not $foundRuntime) {
    $lines += 'DEV_RUNTIME=windows'
}

Set-UnitecUtf8NoBomFile -Path $envFile -Content ($lines -join [Environment]::NewLine)

Write-Host 'Ambiente configurado: Windows nativo (DEV_RUNTIME=windows).' -ForegroundColor Green
Write-Host 'MySQL: 127.0.0.1:3306 (MariaDB em tools\mysql).' -ForegroundColor DarkGray
