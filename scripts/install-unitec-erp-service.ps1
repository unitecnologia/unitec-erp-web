#Requires -Version 5.1
<#
.SYNOPSIS
    Instala/atualiza o servico Windows "UnitecErpServer".
#>

param(
    [string]$AppPath = 'C:\UNITECNOLOGIA_WEB',
    [switch]$Uninstall
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath
$serviceName = 'UnitecErpServer'
$displayName = 'Unitec ERP Server'
$binExe = Join-Path $AppPath 'bin\UnitecErpServer.exe'

if ($Uninstall) {
    $existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ($existing) {
        if ($existing.Status -ne 'Stopped') {
            Stop-Service -Name $serviceName -Force -ErrorAction SilentlyContinue
        }
        sc.exe delete $serviceName | Out-Null
        Write-Ok "Servico $serviceName removido."
    }
    exit 0
}

if (-not (Test-Path $binExe)) {
    throw "Binario do servico ausente: $binExe. Rode scripts\build-erp-desktop.ps1 e copie para bin\."
}

$existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($existing) {
    if ($existing.Status -ne 'Stopped') {
        Stop-Service -Name $serviceName -Force
    }
    sc.exe delete $serviceName | Out-Null
    Start-Sleep -Seconds 2
}

# Conta LocalSystem com auto-start — sobe sem login do usuario.
$binPath = "`"$binExe`""
sc.exe create $serviceName binPath= $binPath start= auto DisplayName= $displayName | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao criar servico $serviceName (rode como Administrador)."
}

sc.exe description $serviceName "Mantem MariaDB embutido e Laravel (porta 8765) do Unitec ERP." | Out-Null

# Variavel de ambiente do servico via registro
$reg = "HKLM:\SYSTEM\CurrentControlSet\Services\$serviceName"
New-ItemProperty -Path $reg -Name 'Environment' -PropertyType MultiString -Value @("UNITEC_APP_PATH=$AppPath") -Force | Out-Null

# Remove auto-start legado (tarefa de logon PowerShell).
Unregister-UnitecLogonStartup -AppPath $AppPath

Start-Service -Name $serviceName
Write-Ok "Servico $serviceName instalado e iniciado."
Write-Ok "Health: http://127.0.0.1:8765/api/health"
