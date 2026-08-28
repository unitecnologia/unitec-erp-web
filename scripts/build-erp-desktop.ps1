#Requires -Version 5.1
<#
.SYNOPSIS
    Compila Unitec ERP Server, Unitec ERP.exe e Unitec Atualizador.exe.
#>

param(
    [string]$Configuration = 'Release',
    [string]$OutputDir = ''
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$Solution = Join-Path $ProjectRoot 'services\unitec-erp-desktop\Unitec.ErpDesktop.sln'

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $ProjectRoot 'dist\erp-desktop'
}

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw 'dotnet SDK nao encontrado. Instale .NET 8 SDK.'
}

if (-not (Test-Path $Solution)) {
    throw "Solution ausente: $Solution"
}

Write-Host '>> Compilando Unitec ERP Desktop...' -ForegroundColor Cyan
& dotnet restore $Solution
if ($LASTEXITCODE -ne 0) { throw 'dotnet restore falhou.' }

& dotnet publish (Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpServer\Unitec.ErpServer.csproj') `
    -c $Configuration -r win-x64 --self-contained false -o (Join-Path $OutputDir 'server') /p:PublishSingleFile=false
if ($LASTEXITCODE -ne 0) { throw 'publish UnitecErpServer falhou.' }

& dotnet publish (Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpLauncher\Unitec.ErpLauncher.csproj') `
    -c $Configuration -r win-x64 --self-contained false -o (Join-Path $OutputDir 'launcher') /p:PublishSingleFile=false
if ($LASTEXITCODE -ne 0) { throw 'publish Unitec ERP falhou.' }

& dotnet publish (Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpUpdater\Unitec.ErpUpdater.csproj') `
    -c $Configuration -r win-x64 --self-contained false -o (Join-Path $OutputDir 'updater') /p:PublishSingleFile=false
if ($LASTEXITCODE -ne 0) { throw 'publish Unitec Atualizador falhou.' }

# Copia para bin/ do projeto (dev) e para staging de instalacao.
$binDir = Join-Path $ProjectRoot 'bin'
New-Item -ItemType Directory -Path $binDir -Force | Out-Null
Copy-Item (Join-Path $OutputDir 'server\*') $binDir -Force -Recurse
Copy-Item (Join-Path $OutputDir 'launcher\Unitec ERP.exe') $binDir -Force
Copy-Item (Join-Path $OutputDir 'launcher\Unitec ERP.dll') $binDir -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $OutputDir 'launcher\Unitec.ErpCommon.dll') $binDir -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $OutputDir 'updater\Unitec Atualizador.exe') $binDir -Force
Copy-Item (Join-Path $OutputDir 'updater\Unitec Atualizador.dll') $binDir -Force -ErrorAction SilentlyContinue

# Dependencias compartilhadas do launcher/updater
Get-ChildItem (Join-Path $OutputDir 'launcher') -File |
    Where-Object { $_.Extension -in '.dll', '.json' } |
    ForEach-Object { Copy-Item $_.FullName $binDir -Force }
Get-ChildItem (Join-Path $OutputDir 'updater') -File |
    Where-Object { $_.Extension -in '.dll', '.json' } |
    ForEach-Object { Copy-Item $_.FullName $binDir -Force }
Get-ChildItem (Join-Path $OutputDir 'server') -File |
    Where-Object { $_.Extension -in '.dll', '.json', '.exe' } |
    ForEach-Object { Copy-Item $_.FullName $binDir -Force }

Write-Host ">> Binarios em: $binDir" -ForegroundColor Green
Get-ChildItem $binDir -File | Select-Object Name, Length | Format-Table -AutoSize
