#Requires -Version 5.1
<#
.SYNOPSIS
  Publica o Device Service, sobe agora e agenda watchdog (a cada 1 min + no logon).
#>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'services\unitec-device-service\dist'
$exe = Join-Path $dist 'Unitec.DeviceService.exe'
$project = Join-Path $root 'services\unitec-device-service\src\Unitec.DeviceService\Unitec.DeviceService.csproj'
$watchdog = Join-Path $PSScriptRoot 'watchdog-device-service.ps1'
$taskName = 'UnitecDeviceServiceWatchdog'

$env:Path = [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' +
            [System.Environment]::GetEnvironmentVariable('Path', 'User')

Get-Process -Name 'Unitec.DeviceService' -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2

Write-Host 'Publicando Device Service...'
& dotnet publish $project -c Release -r win-x64 --self-contained false -o $dist

Write-Host 'Iniciando agora...'
Start-Process -FilePath $exe -WorkingDirectory $dist

# Startup folder (logon)
$startup = [Environment]::GetFolderPath('Startup')
$lnkPath = Join-Path $startup 'Unitecnologia Device Service.lnk'
$w = New-Object -ComObject WScript.Shell
$lnk = $w.CreateShortcut($lnkPath)
$lnk.TargetPath = $exe
$lnk.WorkingDirectory = $dist
$lnk.WindowStyle = 7
$lnk.Description = 'Unitecnologia Device Service'
$lnk.Save()

# Watchdog a cada 1 minuto (por 10 anos)
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$watchdog`""
$triggerLogon = New-ScheduledTaskTrigger -AtLogOn
$triggerMin = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew

Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($triggerLogon, $triggerMin) -Principal $principal -Settings $settings -Force | Out-Null

Start-Sleep -Seconds 3
try {
    $s = Invoke-RestMethod 'http://127.0.0.1:9330/api/status' -TimeoutSec 3
    Write-Host ("OK online: {0}" -f $s.service)
} catch {
    Write-Warning 'API ainda nao respondeu. Aguarde e abra http://127.0.0.1:9330/api/status'
}

Write-Host "Atalho Startup: $lnkPath"
Write-Host "Watchdog agendado: $taskName (a cada 1 min)"
