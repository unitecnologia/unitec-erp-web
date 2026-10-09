<#
.SYNOPSIS
  Publica dist\hotfix\Unitec-ERP-Hotfix.zip (+ .sha256) no release "hotfix-{VersaoBase}".

.DESCRIPTION
  Nunca toca no release "update" (atualizacao oficial). Release marcado como nao-latest.
  O ERP so aplica em clientes com modo_atualizacao=hotfix no portal e versao instalada = VersaoBase.
  Para retirar um hotfix: gh release delete-asset hotfix-{VersaoBase} Unitec-ERP-Hotfix.zip.sha256
#>
param(
    [Parameter(Mandatory = $true)][string]$VersaoBase,
    [string]$Repo = '',
    [string]$Pasta = ''
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

if ($VersaoBase -notmatch '^\d+(\.\d+){1,3}$') { throw "VersaoBase invalida: $VersaoBase" }
if ([string]::IsNullOrWhiteSpace($Pasta)) { $Pasta = Join-Path $ProjectRoot 'dist\hotfix' }

$zip = Join-Path $Pasta 'Unitec-ERP-Hotfix.zip'
$sha = "$zip.sha256"
if (-not (Test-Path $zip) -or -not (Test-Path $sha)) { throw "Pacote nao encontrado em $Pasta (rode criar-hotfix.ps1)." }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$arquivo = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $entrada = $arquivo.Entries | Where-Object { $_.FullName -eq 'hotfix.json' } | Select-Object -First 1
    if (-not $entrada) { throw 'ZIP sem hotfix.json.' }
    $reader = New-Object System.IO.StreamReader($entrada.Open())
    $manifesto = $reader.ReadToEnd() | ConvertFrom-Json
    $reader.Dispose()
} finally { $arquivo.Dispose() }

if ($manifesto.tipo -ne 'unitec-hotfix') { throw 'hotfix.json com tipo invalido.' }
if ($manifesto.versao_base -ne $VersaoBase) { throw "Pacote e da versao $($manifesto.versao_base), nao $VersaoBase." }

$linhas = Get-Content $sha
$hashEsperado = ($linhas | Where-Object { $_ -match '^[a-f0-9]{64}' } | Select-Object -First 1).Substring(0, 64)
$sizeEsperado = [long](($linhas | Where-Object { $_ -match '^size=' } | Select-Object -First 1) -replace '^size=', '')
$hashReal = (Get-FileHash -Path $zip -Algorithm SHA256).Hash.ToLowerInvariant()
if ($hashReal -ne $hashEsperado -or (Get-Item $zip).Length -ne $sizeEsperado) { throw '.sha256 nao confere com o ZIP.' }
if (-not ($linhas | Where-Object { $_ -match '^sig=[A-Za-z0-9+/=]+$' })) { throw '.sha256 sem assinatura (sig=). Gere com criar-hotfix.ps1.' }

& gh auth status 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'gh auth necessario (gh auth login -h github.com -p https -w).' }

if ([string]::IsNullOrWhiteSpace($Repo)) {
    $remote = (& git remote get-url origin 2>$null)
    if ($remote -match 'github\.com[:/](?<owner>[^/]+)/(?<repo>[^/.]+)') { $Repo = $Matches.owner + '/' + $Matches.repo }
    else { $Repo = 'unitecnologia/unitec-erp-web' }
}

$tag = "hotfix-$VersaoBase"
if ($tag -eq 'update') { throw 'Tag reservada.' }
$titulo = "Hotfix $VersaoBase (revisao $($manifesto.revisao))"
$notas = "Hotfix $($manifesto.id) para a versao $VersaoBase.`n`n$($manifesto.descricao)`n`nArquivos:`n" +
    (($manifesto.arquivos | ForEach-Object { "- $($_.caminho)" }) -join "`n")

& gh release view $tag --repo $Repo 1>$null 2>$null
if ($LASTEXITCODE -eq 0) {
    # Ordem: ZIP antes do .sha256, para o cliente nunca ver hash novo apontando para ZIP antigo.
    & gh release upload $tag $zip --repo $Repo --clobber
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao enviar o ZIP.' }
    & gh release upload $tag $sha --repo $Repo --clobber
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao enviar o .sha256.' }
    & gh release edit $tag --repo $Repo --title $titulo --notes $notas | Out-Null
} else {
    & gh release create $tag $zip $sha --repo $Repo --title $titulo --notes $notas --latest=false
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao criar o release.' }
}

Write-Host "Publicado: https://github.com/$Repo/releases/download/$tag/Unitec-ERP-Hotfix.zip" -ForegroundColor Green
