#Requires -Version 5.1
<#
.SYNOPSIS
    Publica dist/Unitec-ERP-Update.zip no GitHub Releases (substitui o Dropbox).

.DESCRIPTION
    Cria/atualiza:
     - Release versionado: v{versao}  (historico)
     - Release estavel:    update     (URL fixa para o .env do cliente)

    URL estavel (recomendada no cliente):
      https://github.com/<owner>/<repo>/releases/download/update/Unitec-ERP-Update.zip

    Se o repositorio de codigo for PRIVADO, use -Repo com um repositorio
    PUBLICO so de updates (ex.: unitecnologia/unitec-erp-updates), senao o
    cliente nao consegue baixar sem autenticacao.

.EXAMPLE
    .\scripts\publicar-update-github.ps1

.EXAMPLE
    .\scripts\publicar-update-github.ps1 -Repo unitecnologia/unitec-erp-updates
#>

param(
    [string]$Repo = '',
    [string]$ZipPath = '',
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
        [string]$Asset
    )

    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & gh release view $Tag --repo $Repository 1>$null 2>$null
    $exists = ($LASTEXITCODE -eq 0)
    $ErrorActionPreference = $prevEap

    if ($exists) {
        Write-Host (">> Release '" + $Tag + "' ja existe - atualizando asset...") -ForegroundColor Yellow
        & gh release upload $Tag $Asset --repo $Repository --clobber
        if ($LASTEXITCODE -ne 0) {
            throw ("Falha ao enviar asset para release '" + $Tag + "'.")
        }

        & gh release edit $Tag --repo $Repository --title $Title --notes $Notes | Out-Null
        return
    }

    Write-Host (">> Criando release '" + $Tag + "'...") -ForegroundColor White
    & gh release create $Tag $Asset --repo $Repository --title $Title --notes $Notes --latest=false
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

if (-not (Test-Path $ZipPath)) {
    throw ("ZIP nao encontrado: " + $ZipPath + ". Gere antes com Gerar Pacote Atualizacao.bat.")
}

$versao = Get-UnitecVersion
$tagVersion = 'v' + $versao
$zipMb = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
$downloadUrl = 'https://github.com/' + $Repo + '/releases/download/update/Unitec-ERP-Update.zip'
$notes = @(
    ('Unitec ERP ' + $versao),
    '',
    'Pacote: Unitec-ERP-Update.zip',
    ('Gerado em: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')),
    '',
    'No cliente (URL estavel):',
    ('UNITEC_UPDATE_DOWNLOAD_URL=' + $downloadUrl)
) -join "`n"

Write-Host ('Repo:    ' + $Repo) -ForegroundColor White
Write-Host ('Versao:  ' + $versao) -ForegroundColor White
Write-Host ('ZIP:     ' + $ZipPath + ' (~' + $zipMb + ' MB)') -ForegroundColor White
Write-Host ('URL:     ' + $downloadUrl) -ForegroundColor White

if ($DryRun) {
    Write-Host ''
    Write-Host 'DryRun: nenhuma release publicada.' -ForegroundColor Yellow
    exit 0
}

$uploadName = 'Unitec-ERP-Update.zip'
$tempDir = Join-Path $env:TEMP ('unitec-update-gh-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
$uploadPath = Join-Path $tempDir $uploadName

try {
    Copy-Item -Path $ZipPath -Destination $uploadPath -Force

    if (-not $SkipVersionedRelease) {
        Ensure-Release -Repository $Repo -Tag $tagVersion -Title ('Unitec ERP ' + $versao) -Notes $notes -Asset $uploadPath
    }

    Ensure-Release -Repository $Repo -Tag 'update' -Title ('Unitec ERP (canal estavel) - ' + $versao) -Notes $notes -Asset $uploadPath
}
finally {
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Write-Title 'Update publicado'
Write-Host ''
Write-Host ('Release versionado: https://github.com/' + $Repo + '/releases/tag/' + $tagVersion) -ForegroundColor Green
Write-Host ('Canal estavel:      https://github.com/' + $Repo + '/releases/tag/update') -ForegroundColor Green
Write-Host ''
Write-Host 'Configure no .env do cliente (uma vez):' -ForegroundColor Cyan
Write-Host ('UNITEC_UPDATE_DOWNLOAD_URL=' + $downloadUrl) -ForegroundColor White
Write-Host ''
Write-Host 'Proximas atualizacoes: so rodar este script de novo - a URL do cliente nao muda.' -ForegroundColor Yellow
Write-Host ''
