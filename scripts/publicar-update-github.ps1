#Requires -Version 5.1
<#
.SYNOPSIS
    Publica Unitec-ERP-Update.zip no GitHub Releases (canal update).

.DESCRIPTION
    - Release estavel "update": sobe APENAS
        Unitec-ERP-Update.zip
        Unitec-ERP-Update.zip.sha256
    - Release versionado v{versao}: so notes/historico (sem ZIP).

    URL fixa do cliente:
      https://github.com/<owner>/<repo>/releases/download/update/Unitec-ERP-Update.zip

.EXAMPLE
    .\scripts\publicar-update-github.ps1
#>

param(
    [string]$Repo = '',
    [string]$ZipPath = '',
    [string]$ShaPath = '',
    [switch]$SkipVersionedRelease,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

function Write-Title([string]$text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host ("  " + $text) -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

function Get-UnitecVersion {
    $configPath = Join-Path $ProjectRoot 'config\unitec.php'
    if (-not (Test-Path $configPath)) {
        throw 'config/unitec.php nao encontrado.'
    }

    $content = Get-Content $configPath -Raw
    if ($content -match "'versao'\s*=>\s*'([^']+)'") {
        return $Matches[1]
    }

    throw 'Nao foi possivel ler unitec.versao em config/unitec.php.'
}

function Assert-GhAuth {
    & gh auth status 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Host ''
        Write-Host 'GitHub CLI nao autenticado.' -ForegroundColor Red
        Write-Host 'Rode uma vez (navegador):' -ForegroundColor Yellow
        Write-Host '  gh auth login -h github.com -p https -w' -ForegroundColor White
        Write-Host ''
        throw 'gh auth necessario para publicar o update.'
    }
}

function Resolve-DefaultRepo {
    $remote = (& git remote get-url origin 2>$null)
    if (-not $remote) {
        return 'unitecnologia/unitec-erp-web'
    }

    if ($remote -match 'github\.com[:/](?<owner>[^/]+)/(?<repo>[^/.]+)') {
        return ($Matches.owner + '/' + $Matches.repo)
    }

    return 'unitecnologia/unitec-erp-web'
}

function Ensure-Release {
    param(
        [string]$Repository,
        [string]$Tag,
        [string]$Title,
        [string]$Notes,
        [string[]]$Assets
    )

    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & gh release view $Tag --repo $Repository 1>$null 2>$null
    $exists = ($LASTEXITCODE -eq 0)
    $ErrorActionPreference = $prevEap

    if ($exists) {
        Write-Host (">> Release '" + $Tag + "' ja existe - atualizando...") -ForegroundColor Yellow

        if ($Assets.Count -gt 0) {
            foreach ($asset in $Assets) {
                & gh release upload $Tag $asset --repo $Repository --clobber
                if ($LASTEXITCODE -ne 0) {
                    throw ("Falha ao enviar asset para release '" + $Tag + "': " + $asset)
                }
            }
        }

        & gh release edit $Tag --repo $Repository --title $Title --notes $Notes | Out-Null
        return
    }

    Write-Host (">> Criando release '" + $Tag + "'...") -ForegroundColor White
    if ($Assets.Count -gt 0) {
        & gh release create $Tag @Assets --repo $Repository --title $Title --notes $Notes --latest=false
    } else {
        & gh release create $Tag --repo $Repository --title $Title --notes $Notes --latest=false
    }

    if ($LASTEXITCODE -ne 0) {
        throw ("Falha ao criar release '" + $Tag + "'.")
    }
}

Write-Title 'Publicar update no GitHub Releases'
Assert-GhAuth

if ([string]::IsNullOrWhiteSpace($Repo)) {
    $Repo = Resolve-DefaultRepo
}

if ([string]::IsNullOrWhiteSpace($ZipPath)) {
    $ZipPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update.zip'
}

if ([string]::IsNullOrWhiteSpace($ShaPath)) {
    $ShaPath = Join-Path $ProjectRoot 'dist\Unitec-ERP-Update.zip.sha256'
}

if (-not (Test-Path $ZipPath)) {
    throw ("ZIP nao encontrado: " + $ZipPath + ". Gere antes com scripts\criar-pacote-update.ps1.")
}

if (-not (Test-Path $ShaPath)) {
    throw ("SHA256 nao encontrado: " + $ShaPath + ". Gere antes com scripts\criar-pacote-update.ps1.")
}

$versao = Get-UnitecVersion
$tagVersion = 'v' + $versao
$zipMb = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
$downloadUrl = 'https://github.com/' + $Repo + '/releases/download/update/Unitec-ERP-Update.zip'
$shaUrl = 'https://github.com/' + $Repo + '/releases/download/update/Unitec-ERP-Update.zip.sha256'
$notes = @(
    ('Unitec ERP ' + $versao),
    '',
    'Pacote unico (canal update):',
    ' - Unitec-ERP-Update.zip',
    ' - Unitec-ERP-Update.zip.sha256',
    ('Gerado em: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')),
    '',
    'URL estavel do cliente:',
    ('UNITEC_UPDATE_DOWNLOAD_URL=' + $downloadUrl),
    '',
    'Validacao:',
    $shaUrl
) -join "`n"

Write-Host ('Repo:    ' + $Repo) -ForegroundColor White
Write-Host ('Versao:  ' + $versao) -ForegroundColor White
Write-Host ('ZIP:     ' + $ZipPath + ' (~' + $zipMb + ' MB)') -ForegroundColor White
Write-Host ('SHA256:  ' + $ShaPath) -ForegroundColor White
Write-Host ('URL:     ' + $downloadUrl) -ForegroundColor White

if ($DryRun) {
    Write-Host ''
    Write-Host 'DryRun: nenhuma release publicada.' -ForegroundColor Yellow
    exit 0
}

$tempDir = Join-Path $env:TEMP ('unitec-update-gh-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
$uploadZip = Join-Path $tempDir 'Unitec-ERP-Update.zip'
$uploadSha = Join-Path $tempDir 'Unitec-ERP-Update.zip.sha256'

try {
    Copy-Item -Path $ZipPath -Destination $uploadZip -Force
    Copy-Item -Path $ShaPath -Destination $uploadSha -Force

    if (-not $SkipVersionedRelease) {
        Ensure-Release `
            -Repository $Repo `
            -Tag $tagVersion `
            -Title ('Unitec ERP ' + $versao) `
            -Notes $notes `
            -Assets @()
    }

    Ensure-Release `
        -Repository $Repo `
        -Tag 'update' `
        -Title ('Unitec ERP (canal estavel) - ' + $versao) `
        -Notes $notes `
        -Assets @($uploadZip, $uploadSha)
}
finally {
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Write-Title 'Update publicado'
Write-Host ''
Write-Host ('Release versionado (notes): https://github.com/' + $Repo + '/releases/tag/' + $tagVersion) -ForegroundColor Green
Write-Host ('Canal estavel (1 ZIP):      https://github.com/' + $Repo + '/releases/tag/update') -ForegroundColor Green
Write-Host ''
Write-Host 'URL do cliente:' -ForegroundColor Cyan
Write-Host ('UNITEC_UPDATE_DOWNLOAD_URL=' + $downloadUrl) -ForegroundColor White
Write-Host ''
Write-Host 'Um unico ZIP no canal update + sidecar .sha256. Tag vX sem pacote.' -ForegroundColor Yellow
Write-Host ''
