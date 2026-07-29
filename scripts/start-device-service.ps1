#Requires -Version 5.1
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'services\unitec-device-service\dist'
$exe = Join-Path $dist 'Unitec.DeviceService.exe'

$env:Path = [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' +
            [System.Environment]::GetEnvironmentVariable('Path', 'User')

function Test-Online {
    try {
        $null = Invoke-RestMethod 'http://127.0.0.1:9330/api/status' -TimeoutSec 2
        return $true
    } catch { return $false }
}

if (Test-Online) {
    Write-Host 'Device Service ja online em http://127.0.0.1:9330'
    exit 0
}

if (-not (Test-Path $exe)) {
    $project = Join-Path $root 'services\unitec-device-service\src\Unitec.DeviceService\Unitec.DeviceService.csproj'
    Write-Host 'Publicando...'
    Get-Process -Name 'Unitec.DeviceService' -ErrorAction SilentlyContinue | Stop-Process -Force
    Start-Sleep 1
    & dotnet publish $project -c Release -r win-x64 --self-contained false -o $dist
}

Write-Host 'Iniciando Unitec.DeviceService.exe...'
Start-Process -FilePath $exe -WorkingDirectory $dist
Start-Sleep 3
if (Test-Online) { Write-Host 'OK online.' } else { Write-Warning 'Nao respondeu ainda.' }
