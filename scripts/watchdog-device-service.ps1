#Requires -Version 5.1
<#
.SYNOPSIS
  Garante que o Unitecnologia Device Service esteja sempre rodando (watchdog).
#>
$ErrorActionPreference = 'SilentlyContinue'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'services\unitec-device-service\dist'
$exe = Join-Path $dist 'Unitec.DeviceService.exe'
$log = Join-Path $dist 'watchdog.log'
$serviceName = 'UnitecDeviceService'

function Write-Log([string]$msg) {
    $line = '{0:yyyy-MM-dd HH:mm:ss} {1}' -f (Get-Date), $msg
    Add-Content -Path $log -Value $line -ErrorAction SilentlyContinue
}

function Test-Online {
    try {
        $r = Invoke-RestMethod 'http://127.0.0.1:9330/api/status' -TimeoutSec 2
        return [bool]$r.online
    } catch {
        return $false
    }
}

if (-not (Test-Path $exe)) {
    Write-Log "EXE ausente: $exe"
    exit 1
}

if (Test-Online) {
    exit 0
}

if (-not (Get-Service -Name $serviceName -ErrorAction SilentlyContinue)) {
    Write-Log "Serviço Windows ausente: $serviceName"
    exit 1
}

Write-Log 'Iniciando serviço Unitec Device Service (API offline).'
Start-Service -Name $serviceName -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3

if (Test-Online) {
    Write-Log 'OK online.'
    exit 0
}

Write-Log 'FALHA: nao respondeu em 127.0.0.1:9330'
exit 1
