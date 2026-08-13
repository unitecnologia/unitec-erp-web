#Requires -Version 5.1
<#
.SYNOPSIS
    Publica a arvore de update (sem ZIP) no branch GitHub update-files.

.DESCRIPTION
    Empurra dist/pacote-update/unitec-erp-web + manifest.json para o branch
    update-files (force). Cliente baixa via raw.githubusercontent.com.

.EXAMPLE
    .\scripts\publicar-update-files-github.ps1
#>

param(
    [string]$Repo = '',
    [string]$SourceDir = '',
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
    $content = Get-Content $configPath -Raw
    if ($content -match "'versao'\s*=>\s*'([^']+)'") {
        return $Matches[1]
    }
    throw 'Nao foi possivel ler unitec.versao.'
}

function Resolve-DefaultRepo {
    $remote = (& git remote get-url origin 2>$null)
    if ($remote -match 'github\.com[:/](?<owner>[^/]+)/(?<repo>[^/.]+)') {
        return ($Matches.owner + '/' + $Matches.repo)
    }
    return 'unitecnologia/unitec-erp-web'
}

Write-Title 'Publicar update-files (sem ZIP)'

if ([string]::IsNullOrWhiteSpace($Repo)) {
    $Repo = Resolve-DefaultRepo
}

if ([string]::IsNullOrWhiteSpace($SourceDir)) {
    $SourceDir = Join-Path $ProjectRoot 'dist\pacote-update\unitec-erp-web'
}

$manifestPath = Join-Path $SourceDir 'manifest.json'
if (-not (Test-Path $SourceDir)) {
    throw "Pasta fonte ausente: $SourceDir - rode criar-pacote-update.ps1 antes."
}
if (-not (Test-Path $manifestPath)) {
    throw "manifest.json ausente em $SourceDir - rode criar-pacote-update.ps1."
}

$version = Get-UnitecVersion
$work = Join-Path $ProjectRoot 'dist\update-files-git'
$remoteUrl = "https://github.com/$Repo.git"
# Pasta no GitHub = atualizacao/ (igual no PC do cliente)
$baseRaw = "https://raw.githubusercontent.com/$($Repo.Replace('\','/'))/update-files/atualizacao"
$browseUrl = "https://github.com/$Repo/tree/update-files/atualizacao"

Write-Host "Repo:    $Repo"
Write-Host "Versao:  $version"
Write-Host "Fonte:   $SourceDir"
Write-Host "Pasta GH: atualizacao/"
Write-Host "Browse:  $browseUrl"
Write-Host "Raw:     $baseRaw/manifest.json"

# Atualiza base_url no manifest (apontando para a pasta atualizacao no GitHub).
$manifestObj = Get-Content $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
$manifestObj.base_url = $baseRaw
$manifestObj.version = $version
$manifestJson = $manifestObj | ConvertTo-Json -Depth 8 -Compress:$false
[System.IO.File]::WriteAllText($manifestPath, $manifestJson, (New-Object System.Text.UTF8Encoding $false))

if ($DryRun) {
    Write-Host 'DryRun: nao publica.' -ForegroundColor Yellow
    return
}

if (Test-Path $work) {
    Remove-Item $work -Recurse -Force
}
New-Item -ItemType Directory -Path $work -Force | Out-Null
$workAtualizacao = Join-Path $work 'atualizacao'
New-Item -ItemType Directory -Path $workAtualizacao -Force | Out-Null

Write-Host '>> Copiando arvore para worktree/atualizacao/ ...' -ForegroundColor White
& robocopy $SourceDir $workAtualizacao /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy falhou ($LASTEXITCODE)"
}

# README na raiz do branch explicando a pasta
$readmeBranch = @"
# Canal de atualizacao Unitec ERP

Os arquivos do update ficam na pasta **atualizacao/** (igual no PC do cliente).

- Pasta: ``atualizacao/``
- Manifest: ``atualizacao/manifest.json``
- URL raw: $baseRaw/manifest.json

O **UnitecErpServer** baixa de ``atualizacao/`` no GitHub para ``C:\UNITECNOLOGIA_WEB\atualizacao\`` no PC.
"@
[System.IO.File]::WriteAllText((Join-Path $work 'README.md'), $readmeBranch, (New-Object System.Text.UTF8Encoding $false))

# Manifest tambem na raiz do branch (compat): base_url continua apontando para /atualizacao
$rootManifest = Join-Path $work 'manifest.json'
Copy-Item -LiteralPath (Join-Path $workAtualizacao 'manifest.json') -Destination $rootManifest -Force

# CRITICO: o .gitignore do projeto ignora /vendor — sem isso o update sobe incompleto.
$gi = Join-Path $work '.gitignore'
[System.IO.File]::WriteAllText(
    $gi,
    "# canal update-files: publicar TODOS os arquivos em atualizacao/`n",
    (New-Object System.Text.UTF8Encoding $false)
)

Push-Location $work
try {
    git init -q
    git checkout -b update-files
    git add -A -f
    $stagedVendor = @(git ls-files 'atualizacao/vendor/autoload.php')
    if ($stagedVendor.Count -lt 1) {
        throw 'atualizacao/vendor/autoload.php NAO entrou no commit — abortando publish incompleto.'
    }
    $stagedCount = @(git ls-files 'atualizacao/').Count
    Write-Host (">> Arquivos em atualizacao/ no commit: {0}" -f $stagedCount) -ForegroundColor Green

    git -c user.email='update@unitecnologia.local' -c user.name='Unitec Update' commit -m "update-files/atualizacao $version" -q
    if ($LASTEXITCODE -ne 0) {
        throw 'git commit falhou'
    }

    Write-Host '>> Push force branch update-files...' -ForegroundColor White
    git remote add origin $remoteUrl
    git config http.postBuffer 524288000
    git push -f origin update-files
    if ($LASTEXITCODE -ne 0) {
        throw 'git push update-files falhou'
    }
}
finally {
    Pop-Location
}

# Notes na tag versionada (sem assets).
$notes = @"
## Unitec ERP $version

Update por arquivos na pasta GitHub ``atualizacao/`` (sem ZIP).

Browse: $browseUrl
Manifest: $baseRaw/manifest.json
"@

$prev = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
& gh release view "v$version" --repo $Repo 1>$null 2>$null
$exists = ($LASTEXITCODE -eq 0)
$ErrorActionPreference = $prev

if ($exists) {
    & gh release edit "v$version" --repo $Repo --notes $notes | Out-Null
} else {
    & gh release create "v$version" --repo $Repo --title "Unitec ERP $version" --notes $notes --latest=false
}

Write-Title 'Update-files /atualizacao publicado'
Write-Host "Browse:   $browseUrl" -ForegroundColor Green
Write-Host "Manifest: $baseRaw/manifest.json" -ForegroundColor Green
Write-Host "Branch:   update-files" -ForegroundColor Green
Write-Host ''
