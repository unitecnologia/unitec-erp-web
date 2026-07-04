#Requires -Version 5.1
<#
.SYNOPSIS
    Baixa Unitec-ERP-Update.zip do Mega e aplica sobre a instalacao local.
.DESCRIPTION
    Preserva .env, storage/ e tools/. Executa migrate e reinicia o stack embutido.
#>

param(
    [string]$AppPath = '',
    [string]$MegaFolderUrl = '',
    [string]$ZipName = '',
    [string]$LocalZip = '',
    [switch]$LeigoMode,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if (-not $PSBoundParameters.ContainsKey('LeigoMode')) {
    $LeigoMode = $true
}

function Read-UnitecUpdateRequest {
    param([string]$Root)

    $requestFile = Join-Path $Root 'storage\app\private\erp-update-request.json'
    if (-not (Test-Path $requestFile)) {
        return $null
    }

    try {
        return Get-Content $requestFile -Raw -Encoding UTF8 | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Write-UpdateStep {
    param(
        $Progress,
        [string]$Message,
        [int]$Percent,
        [string]$AppPath,
        [switch]$LeigoMode
    )

    Write-InstallLog -AppPath $AppPath -Message $Message

    if ($LeigoMode) {
        Update-UnitecLeigoProgress -Context $Progress -Message $Message -Percent $Percent
    } else {
        Write-Host ">> $Message" -ForegroundColor White
    }
}

function Resolve-UnitecMegadlPath {
    param([string]$Root)

    $assetsMegadl = Join-Path $Root 'installer\assets\megatools\megadl.exe'
    $toolsMegadl = Join-Path $Root 'tools\megatools\megadl.exe'

    if (Test-Path $assetsMegadl) {
        Ensure-Directory (Split-Path $toolsMegadl -Parent)
        Copy-Item $assetsMegadl $toolsMegadl -Force -ErrorAction SilentlyContinue
    }

    foreach ($candidate in @($toolsMegadl, $assetsMegadl)) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    $command = Get-Command megadl -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    throw @"
megadl.exe nao encontrado.

Copie megadl.exe (megatools) para:
  $assetsMegadl

Consulte installer\assets\megatools\README.txt
"@
}

function Resolve-UnitecUpdateSourceRoot {
    param([string]$ExtractRoot)

    $nested = Join-Path $ExtractRoot 'unitec-erp-web'
    if (Test-Path (Join-Path $nested 'artisan')) {
        return $nested
    }

    if (Test-Path (Join-Path $ExtractRoot 'artisan')) {
        return $ExtractRoot
    }

    throw 'Pacote invalido: artisan nao encontrado no ZIP de atualizacao.'
}

function Get-UnitecDownloadedUpdateZip {
    param(
        [string]$DownloadRoot,
        [string]$ExpectedName
    )

    $matches = Get-ChildItem -Path $DownloadRoot -Recurse -File -Filter $ExpectedName -ErrorAction SilentlyContinue
    $zip = $matches | Select-Object -First 1

    if ($null -eq $zip) {
        throw "Arquivo $ExpectedName nao encontrado apos o download."
    }

    return $zip.FullName
}

function Hide-PowerShellConsoleWindow {
    try {
        if (-not ([Environment]::UserInteractive)) {
            return
        }

        Add-Type @"
using System;
using System.Runtime.InteropServices;
public class UnitecConsoleWindow {
    [DllImport("kernel32.dll")]
    public static extern IntPtr GetConsoleWindow();
    [DllImport("user32.dll")]
    public static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
}
"@ -ErrorAction Stop

        $handle = [UnitecConsoleWindow]::GetConsoleWindow()
        if ($handle -ne [IntPtr]::Zero) {
            [UnitecConsoleWindow]::ShowWindow($handle, 0) | Out-Null
        }
    } catch {
        # ignore
    }
}

function Open-UnitecErpAfterUpdate {
    param(
        [string]$AppPath,
        [string]$RelativePath = '/admin'
    )

    $appUrl = Get-UnitecDefaultAppUrl
    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $appUrl | Out-Null

    $relative = if ($RelativePath.StartsWith('/')) { $RelativePath } else { "/$RelativePath" }
    $targetUrl = $appUrl.TrimEnd('/') + $relative

    Start-Process $targetUrl
}

$progress = $null

try {
    if (-not $LeigoMode) {
        Write-Title 'Atualizar Unitec ERP'
    }

    Start-InstallLog -AppPath $AppPath | Out-Null
    Write-InstallLog -AppPath $AppPath -Message 'Iniciando atualizacao remota.'

    if ($LeigoMode) {
        Hide-PowerShellConsoleWindow
        $progress = Start-UnitecLeigoProgress `
            -FormTitle 'Atualizando Unitec ERP' `
            -Heading 'Aguarde, estamos atualizando o sistema...' `
            -InitialStatus 'Preparando atualizacao...' `
            -Hint 'Pode demorar alguns minutos. Nao feche esta janela.'
    }

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Preparando atualizacao...' -Percent 5

    $request = Read-UnitecUpdateRequest -Root $AppPath
    if ([string]::IsNullOrWhiteSpace($MegaFolderUrl) -and $null -ne $request) {
        $MegaFolderUrl = [string]$request.mega_folder_url
    }
    if ([string]::IsNullOrWhiteSpace($ZipName) -and $null -ne $request) {
        $ZipName = [string]$request.zip_name
    }
    if ([string]::IsNullOrWhiteSpace($ZipName)) {
        $ZipName = 'Unitec-ERP-Update.zip'
    }

    $zipPath = $LocalZip
    if ([string]::IsNullOrWhiteSpace($zipPath)) {
        if ([string]::IsNullOrWhiteSpace($MegaFolderUrl)) {
            throw 'URL da pasta Mega nao configurada.'
        }

        $megadl = Resolve-UnitecMegadlPath -Root $AppPath
        $downloadRoot = Join-Path $env:TEMP ("unitec-update-download-{0:yyyyMMddHHmmss}" -f (Get-Date))
        Ensure-Directory $downloadRoot

        Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
            -Message ("Baixando pacote do Mega ($ZipName)...") -Percent 18
        Write-InstallLog -AppPath $AppPath -Message ("Download Mega: {0}" -f $MegaFolderUrl)

        & $megadl --no-progress --path $downloadRoot $MegaFolderUrl
        if ($LASTEXITCODE -ne 0) {
            throw "megadl falhou (codigo $LASTEXITCODE)."
        }

        $zipPath = Get-UnitecDownloadedUpdateZip -DownloadRoot $downloadRoot -ExpectedName $ZipName
    } elseif (-not (Test-Path $zipPath)) {
        throw "ZIP local nao encontrado: $zipPath"
    }

    Write-InstallLog -AppPath $AppPath -Message ("Pacote: {0}" -f $zipPath)

    $extractRoot = Join-Path $env:TEMP ("unitec-update-extract-{0:yyyyMMddHHmmss}" -f (Get-Date))
    Ensure-Directory $extractRoot

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Extraindo pacote de atualizacao...' -Percent 34
    Expand-Archive -Path $zipPath -DestinationPath $extractRoot -Force

    $sourceRoot = Resolve-UnitecUpdateSourceRoot -ExtractRoot $extractRoot
    Write-InstallLog -AppPath $AppPath -Message ("Origem do pacote: {0}" -f $sourceRoot)

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Encerrando o sistema para aplicar arquivos...' -Percent 46
    Stop-UnitecApplicationServer -AppPath $AppPath
    Write-InstallLog -AppPath $AppPath -Message 'Servidor web encerrado.'

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Aplicando arquivos (preservando .env, storage/ e tools/)...' -Percent 58
    Copy-UnitecProjectTree -SourceRoot $sourceRoot -TargetRoot $AppPath -UpdateMode -Quiet:$LeigoMode
    Write-InstallLog -AppPath $AppPath -Message 'Arquivos aplicados.'

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Atualizando banco de dados...' -Percent 76
    Invoke-UnitecDatabaseMigrate -AppPath $AppPath
    Write-InstallLog -AppPath $AppPath -Message 'Migrate concluido.'

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Finalizando configuracao...' -Percent 86
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('view:clear') -AllowFailure | Out-Null
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null

    Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
        -Message 'Reiniciando Unitec ERP...' -Percent 93
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 20
    Write-InstallLog -AppPath $AppPath -Message 'Atualizacao concluida com sucesso.'

    if (-not $NoBrowser) {
        Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
            -Message 'Abrindo o sistema...' -Percent 100
        Open-UnitecErpAfterUpdate -AppPath $AppPath -RelativePath '/admin'
    } else {
        Write-UpdateStep -Progress $progress -LeigoMode:$LeigoMode -AppPath $AppPath `
            -Message 'Atualizacao concluida.' -Percent 100
    }

    if ($null -ne $progress) {
        Start-Sleep -Milliseconds 800
        Stop-UnitecLeigoProgress -Context $progress
        $progress = $null
    }

    if (-not $LeigoMode) {
        Write-Ok 'Atualizacao concluida.'
    }

    exit 0
} catch {
    if ($null -ne $progress) {
        Stop-UnitecLeigoProgress -Context $progress
        $progress = $null
    }

    Write-Err $_.Exception.Message
    Write-InstallLog -AppPath $AppPath -Message ('ERRO atualizacao: {0}' -f $_.Exception.Message)

    Show-UnitecLeigoMessage -Title 'Unitec ERP' -Message @"
Nao foi possivel concluir a atualizacao.

$($_.Exception.Message)

Consulte instalacao.log ou ligue para o suporte da Unitecnologia.
"@ -Icon Error

    exit 1
}
