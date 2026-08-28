#Requires -Version 5.1
<#
.SYNOPSIS
    Valida a arquitetura nova (checklist 1-10) sem remover mecanismos antigos.
#>

param(
    [string]$AppPath = 'C:\Projetos\unitec-erp-web',
    [string]$AppUrl = 'http://127.0.0.1:8765',
    [switch]$SkipServiceInstall,
    [switch]$SkipBrowser
)

$ErrorActionPreference = 'Stop'
$results = [ordered]@{}

function Set-Result([string]$Id, [bool]$Ok, [string]$Detail) {
    $results[$Id] = @{ ok = $Ok; detail = $Detail }
    $color = if ($Ok) { 'Green' } else { 'Red' }
    Write-Host ("[{0}] {1}: {2}" -f ($(if ($Ok) { 'OK' } else { 'FAIL' }), $Id, $Detail)) -ForegroundColor $color
}

Write-Host '== Validacao Unitec ERP Desktop ==' -ForegroundColor Cyan
Write-Host "AppPath=$AppPath"
Write-Host "AppUrl=$AppUrl"

$binLauncher = Join-Path $AppPath 'bin\Unitec ERP.exe'
$binServer = Join-Path $AppPath 'bin\UnitecErpServer.exe'
$binUpdater = Join-Path $AppPath 'bin\Unitec Atualizador.exe'

Set-Result 'binaries' ((Test-Path $binLauncher) -and (Test-Path $binServer) -and (Test-Path $binUpdater)) `
    "launcher=$(Test-Path $binLauncher); server=$(Test-Path $binServer); updater=$(Test-Path $binUpdater)"

if (-not $SkipServiceInstall) {
    try {
        $svc = Get-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
        if (-not $svc) {
            Write-Host '>> Instalando servico (requer admin)...' -ForegroundColor Yellow
            & powershell -ExecutionPolicy Bypass -File (Join-Path $AppPath 'scripts\install-unitec-erp-service.ps1') -AppPath $AppPath
        } elseif ($svc.Status -ne 'Running') {
            Start-Service UnitecErpServer
        }
        Start-Sleep -Seconds 3
        $svc = Get-Service UnitecErpServer
        Set-Result 'service_running' ($svc.Status -eq 'Running') ("Status=$($svc.Status)")
    } catch {
        Set-Result 'service_running' $false $_.Exception.Message
    }
} else {
    Set-Result 'service_running' $false 'SkipServiceInstall'
}

# 2/3 health
try {
    $health = Invoke-RestMethod -Uri ($AppUrl.TrimEnd('/') + '/api/health') -TimeoutSec 5
    $ok = ($health.status -eq 'ok')
    Set-Result 'api_health' $ok ("status=$($health.status); version=$($health.version)")
} catch {
    Set-Result 'api_health' $false $_.Exception.Message
}

# 7 MariaDB
try {
    $client = New-Object System.Net.Sockets.TcpClient
    $iar = $client.BeginConnect('127.0.0.1', 3306, $null, $null)
    $wait = $iar.AsyncWaitHandle.WaitOne(800)
    $maria = $wait -and $client.Connected
    $client.Close()
    Set-Result 'mariadb_port' $maria $(if ($maria) { 'porta 3306 aberta' } else { 'porta 3306 fechada' })
} catch {
    Set-Result 'mariadb_port' $false $_.Exception.Message
}

# 4/5 launcher single instance
if ((Test-Path $binLauncher) -and -not $SkipBrowser) {
    $before = @(Get-Process -Name 'Unitec ERP' -ErrorAction SilentlyContinue).Count
    Start-Process -FilePath $binLauncher -ArgumentList @('--app', $AppPath)
    Start-Sleep -Milliseconds 400
    Start-Process -FilePath $binLauncher -ArgumentList @('--app', $AppPath)
    Start-Sleep -Seconds 2
    $after = @(Get-Process -Name 'Unitec ERP' -ErrorAction SilentlyContinue).Count
    Set-Result 'launcher_single' ($after -le 1) ("processos launcher=$after (antes=$before)")
} else {
    Set-Result 'launcher_single' $false 'SkipBrowser ou launcher ausente'
}

# 6 service stopped then launcher starts
if (-not $SkipServiceInstall) {
    try {
        Stop-Service UnitecErpServer -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        $stopped = (Get-Service UnitecErpServer).Status -eq 'Stopped'
        if ($stopped -and (Test-Path $binLauncher) -and -not $SkipBrowser) {
            Start-Process -FilePath $binLauncher -ArgumentList @('--app', $AppPath)
            Start-Sleep -Seconds 8
            $svc2 = Get-Service UnitecErpServer
            $health2 = $null
            try { $health2 = Invoke-RestMethod -Uri ($AppUrl.TrimEnd('/') + '/api/health') -TimeoutSec 5 } catch {}
            Set-Result 'launcher_starts_service' (($svc2.Status -eq 'Running') -and ($health2.status -eq 'ok')) `
                ("svc=$($svc2.Status); health=$($health2.status)")
        } else {
            Set-Result 'launcher_starts_service' $false 'nao foi possivel parar servico ou SkipBrowser'
        }
    } catch {
        Set-Result 'launcher_starts_service' $false $_.Exception.Message
    }
} else {
    Set-Result 'launcher_starts_service' $false 'SkipServiceInstall'
}

# 9/10 packaging contamination check on bootstrap/cache
$cacheDir = Join-Path $AppPath 'bootstrap\cache'
$dirty = @()
if (Test-Path $cacheDir) {
    $dirty = Get-ChildItem $cacheDir -Filter *.php -ErrorAction SilentlyContinue | Where-Object {
        $t = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
        $t -and ($t.Contains('C:\Projetos\unitec-erp-web') -or $t.Contains('C:/Projetos/unitec-erp-web'))
    }
}
Set-Result 'no_dev_path_in_cache' ($dirty.Count -eq 0) $(if ($dirty.Count -eq 0) { 'cache limpo' } else { ($dirty.Name -join ', ') })

Write-Host ''
Write-Host 'Resumo:' -ForegroundColor Cyan
$fail = 0
foreach ($k in $results.Keys) {
    if (-not $results[$k].ok) { $fail++ }
}
Write-Host ("Passou: {0} | Falhou: {1}" -f ($results.Count - $fail), $fail)
if ($fail -gt 0) { exit 1 }
exit 0
