#Requires -Version 5.1
<#
.SYNOPSIS
    Compila Unitec ERP Server, Unitec ERP.exe e Unitec Atualizador.exe (self-contained).

.DESCRIPTION
    Publica win-x64 com runtime .NET embutido (Windows Desktop incluido no launcher/atualizador).
    O cliente nao precisa instalar .NET Desktop Runtime.
    PublishSingleFile=false: melhor para PCs fracos (sem extracao em temp) e updates parciais.
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

function Assert-SelfContainedRuntimeConfig {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RuntimeConfigPath,
        [Parameter(Mandatory = $true)]
        [string]$Label
    )

    if (-not (Test-Path -LiteralPath $RuntimeConfigPath)) {
        throw ('{0}: runtimeconfig ausente ({1}).' -f $Label, $RuntimeConfigPath)
    }

    $rt = Get-Content -LiteralPath $RuntimeConfigPath -Raw -ErrorAction SilentlyContinue
    if ([string]::IsNullOrWhiteSpace($rt) -or ($rt -notmatch '"includedFrameworks"')) {
        throw ('{0}: publish nao ficou self-contained (runtimeconfig sem includedFrameworks).' -f $Label)
    }
}

function Copy-PublishOutputToBin {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceDir,
        [Parameter(Mandatory = $true)]
        [string]$DestinationDir
    )

    if (-not (Test-Path -LiteralPath $SourceDir)) {
        throw ('Pasta de publish ausente: {0}' -f $SourceDir)
    }

    Get-ChildItem -LiteralPath $SourceDir -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Extension -ne '.pdb' } |
        ForEach-Object {
            $dest = Join-Path $DestinationDir $_.Name
            $ok = $false
            for ($i = 0; $i -lt 8; $i++) {
                try {
                    Copy-Item -LiteralPath $_.FullName -Destination $dest -Force -ErrorAction Stop
                    $ok = $true
                    break
                } catch {
                    Start-Sleep -Milliseconds 400
                }
            }
            if (-not $ok) {
                throw ('Falha ao copiar {0} para {1} (arquivo em uso). Pare o UnitecErpServer e tente de novo.' -f $_.Name, $DestinationDir)
            }
        }
}

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw 'dotnet SDK nao encontrado. Instale .NET 8 SDK.'
}

if (-not (Test-Path $Solution)) {
    throw ('Solution ausente: {0}' -f $Solution)
}

# Evita lock de DLLs/EXE no DEV (servico Windows + launcher/atualizador).
$svc = Get-Service -Name 'UnitecErpServer' -ErrorAction SilentlyContinue
if ($svc -and $svc.Status -ne 'Stopped') {
    Write-Host '>> Parando servico UnitecErpServer para liberar bin\...' -ForegroundColor Yellow
    try { Stop-Service -Name 'UnitecErpServer' -Force -ErrorAction Stop } catch {
        & sc.exe stop UnitecErpServer | Out-Null
    }
    Start-Sleep -Seconds 2
}
foreach ($procName in @('Unitec ERP', 'Unitec Atualizador', 'UnitecErpServer')) {
    Get-Process -Name $procName -ErrorAction SilentlyContinue |
        Stop-Process -Force -ErrorAction SilentlyContinue
}
Start-Sleep -Seconds 1

Write-Host '>> Compilando Unitec ERP Desktop (self-contained win-x64)...' -ForegroundColor Cyan
& dotnet restore $Solution
if ($LASTEXITCODE -ne 0) { throw 'dotnet restore falhou.' }

$publishProjects = @(
    @{
        Label = 'UnitecErpServer'
        Project = Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpServer\Unitec.ErpServer.csproj'
        Out = Join-Path $OutputDir 'server'
        RuntimeConfig = 'UnitecErpServer.runtimeconfig.json'
        Exe = 'UnitecErpServer.exe'
    },
    @{
        Label = 'Unitec ERP'
        Project = Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpLauncher\Unitec.ErpLauncher.csproj'
        Out = Join-Path $OutputDir 'launcher'
        RuntimeConfig = 'Unitec ERP.runtimeconfig.json'
        Exe = 'Unitec ERP.exe'
    },
    @{
        Label = 'Unitec Atualizador'
        Project = Join-Path $ProjectRoot 'services\unitec-erp-desktop\src\Unitec.ErpUpdater\Unitec.ErpUpdater.csproj'
        Out = Join-Path $OutputDir 'updater'
        RuntimeConfig = 'Unitec Atualizador.runtimeconfig.json'
        Exe = 'Unitec Atualizador.exe'
    }
)

foreach ($item in $publishProjects) {
    if (Test-Path -LiteralPath $item.Out) {
        Remove-Item -LiteralPath $item.Out -Recurse -Force -ErrorAction SilentlyContinue
    }
    New-Item -ItemType Directory -Path $item.Out -Force | Out-Null

    Write-Host ('>> Publish {0} (self-contained)...' -f $item.Label) -ForegroundColor White
    & dotnet publish $item.Project `
        -c $Configuration `
        -r win-x64 `
        --self-contained true `
        -o $item.Out `
        /p:PublishSingleFile=false
    if ($LASTEXITCODE -ne 0) {
        throw ('publish {0} falhou.' -f $item.Label)
    }

    $exePath = Join-Path $item.Out $item.Exe
    if (-not (Test-Path -LiteralPath $exePath)) {
        throw ('{0}: EXE ausente apos publish ({1}).' -f $item.Label, $exePath)
    }

    Assert-SelfContainedRuntimeConfig `
        -RuntimeConfigPath (Join-Path $item.Out $item.RuntimeConfig) `
        -Label $item.Label

    $coreClr = Join-Path $item.Out 'coreclr.dll'
    if (-not (Test-Path -LiteralPath $coreClr)) {
        throw ('{0}: coreclr.dll ausente - publish nao embutiu o runtime.' -f $item.Label)
    }
}

# Copia para bin/ do projeto (dev). O instalador prefere dist\erp-desktop.
$binDir = Join-Path $ProjectRoot 'bin'
$binCopyOk = $false
try {
    if (-not (Test-Path -LiteralPath $binDir)) {
        New-Item -ItemType Directory -Path $binDir -Force | Out-Null
    } else {
        # Limpa o que der; arquivos locked pelo servico sao sobrescritos na copia com retry.
        Get-ChildItem -LiteralPath $binDir -File -ErrorAction SilentlyContinue |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }

    # Server primeiro (runtime base); launcher/updater acrescentam Windows Desktop.
    Copy-PublishOutputToBin -SourceDir (Join-Path $OutputDir 'server') -DestinationDir $binDir
    Copy-PublishOutputToBin -SourceDir (Join-Path $OutputDir 'launcher') -DestinationDir $binDir
    Copy-PublishOutputToBin -SourceDir (Join-Path $OutputDir 'updater') -DestinationDir $binDir

    Assert-SelfContainedRuntimeConfig `
        -RuntimeConfigPath (Join-Path $binDir 'Unitec ERP.runtimeconfig.json') `
        -Label 'bin\Unitec ERP'
    Assert-SelfContainedRuntimeConfig `
        -RuntimeConfigPath (Join-Path $binDir 'UnitecErpServer.runtimeconfig.json') `
        -Label 'bin\UnitecErpServer'
    Assert-SelfContainedRuntimeConfig `
        -RuntimeConfigPath (Join-Path $binDir 'Unitec Atualizador.runtimeconfig.json') `
        -Label 'bin\Unitec Atualizador'

    if (-not (Test-Path -LiteralPath (Join-Path $binDir 'coreclr.dll'))) {
        throw 'bin\coreclr.dll ausente apos copia self-contained.'
    }
    if (-not (Test-Path -LiteralPath (Join-Path $binDir 'hostfxr.dll'))) {
        throw 'bin\hostfxr.dll ausente apos copia self-contained.'
    }

    $binCopyOk = $true
    $fileCount = @(Get-ChildItem -LiteralPath $binDir -File).Count
    $sizeMb = [math]::Round(((Get-ChildItem -LiteralPath $binDir -File | Measure-Object Length -Sum).Sum / 1MB), 1)
    Write-Host ('>> Binarios self-contained em: {0} ({1} arquivos, ~{2} MB)' -f $binDir, $fileCount, $sizeMb) -ForegroundColor Green
    Get-ChildItem $binDir -File |
        Where-Object { $_.Name -match '^(Unitec ERP|Unitec Atualizador|UnitecErpServer|coreclr|hostfxr)\.(exe|dll)$' } |
        Select-Object Name, Length |
        Format-Table -AutoSize
} catch {
    Write-Host ('>> AVISO: bin\ nao atualizado ({0}).' -f $_.Exception.Message) -ForegroundColor Yellow
    Write-Host '>> Publish OK em dist\erp-desktop (instalador usa esta pasta). Rode como admin para aplicar no DEV.' -ForegroundColor Yellow
}

# Restaura o servico no DEV se ele existia e a copia do bin concluiu.
if ($svc -and $binCopyOk) {
    try {
        Start-Service -Name 'UnitecErpServer' -ErrorAction Stop
        Write-Host '>> Servico UnitecErpServer reiniciado.' -ForegroundColor Green
    } catch {
        Write-Host ('>> AVISO: nao reiniciou UnitecErpServer: {0}' -f $_.Exception.Message) -ForegroundColor Yellow
    }
}

Write-Host ('>> Publish self-contained pronto: {0}' -f $OutputDir) -ForegroundColor Green

