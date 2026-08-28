#Requires -Version 5.1
<#
.SYNOPSIS
  Registra o Device Service como serviço Windows automático.
#>
param(
    [string]$AppPath = (Split-Path -Parent $PSScriptRoot),
    [switch]$Publish
)

$ErrorActionPreference = 'Stop'
$root = $AppPath
$dist = Join-Path $root 'services\unitec-device-service\dist'
$exe = Join-Path $dist 'Unitec.DeviceService.exe'
$project = Join-Path $root 'services\unitec-device-service\src\Unitec.DeviceService\Unitec.DeviceService.csproj'
$serviceName = 'UnitecDeviceService'
$displayName = 'Unitec Device Service'

$env:Path = [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' +
            [System.Environment]::GetEnvironmentVariable('Path', 'User')

if ($Publish) {
    & sc.exe stop $serviceName | Out-Null
    Start-Sleep -Seconds 2
    Write-Host 'Publicando Device Service...'
    & dotnet publish $project -c Release -r win-x64 --self-contained false -o $dist
    if ($LASTEXITCODE -ne 0) { throw 'Publish do Device Service falhou.' }
}

if (-not (Test-Path $exe)) {
    throw "EXE não publicado: $exe"
}

$service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if (-not $service) {
    & sc.exe create $serviceName binPath= ('"' + $exe + '"') start= auto DisplayName= $displayName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao criar o serviço Windows.' }
} else {
    & sc.exe config $serviceName binPath= ('"' + $exe + '"') start= auto | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao configurar o serviço Windows.' }
}

Start-Service -Name $serviceName

Start-Sleep -Seconds 3
try {
    $s = Invoke-RestMethod 'http://127.0.0.1:9330/api/status' -TimeoutSec 3
    Write-Host ("OK online: {0}" -f $s.service)
} catch {
    Write-Warning 'API ainda nao respondeu. Aguarde e abra http://127.0.0.1:9330/api/status'
}

Write-Host "Serviço Windows: $displayName ($serviceName)"
