#Requires -Version 5.1
<#
.SYNOPSIS
    Abre o HeidiSQL conectado ao banco unitec_erp (suporte Unitec).
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if (-not (Test-Path (Join-Path $AppPath '.env'))) {
    Write-Host 'Sistema ainda nao configurado (.env ausente).' -ForegroundColor Red
    exit 1
}

$heidiExe = Resolve-HeidiSqlExecutable -AppPath $AppPath -AllowInstall
if (-not $heidiExe) {
    Write-Host 'HeidiSQL nao encontrado. Reinstale o Unitec ERP ou coloque HeidiSQL_*_Setup.exe em installer\assets\.' -ForegroundColor Red
    exit 1
}

$db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
$hostName = if ([string]::IsNullOrWhiteSpace($db.DbHost)) { '127.0.0.1' } else { $db.DbHost }
$port = if ([string]::IsNullOrWhiteSpace($db.DbPort)) { '3306' } else { $db.DbPort }
$user = if ([string]::IsNullOrWhiteSpace($db.DbUser)) { $script:UnitecDefaultDbUser } else { $db.DbUser }
$password = if ($null -eq $db.DbPassword) { Get-UnitecDefaultDbPassword } else { $db.DbPassword }
$database = if ([string]::IsNullOrWhiteSpace($db.DbName)) { $script:UnitecDefaultDbName } else { $db.DbName }

$launchArgs = @(
    "-h=$hostName",
    "-u=$user",
    "-p=$password",
    "-P=$port",
    "-d=$database"
)

Write-Host "Abrindo HeidiSQL ($database @ ${hostName}:$port)..." -ForegroundColor White
Start-Process -FilePath $heidiExe -ArgumentList $launchArgs -WorkingDirectory (Split-Path $heidiExe -Parent)
exit 0
