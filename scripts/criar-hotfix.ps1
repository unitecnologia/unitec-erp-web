<#
.SYNOPSIS
  Gera o pacote HOTFIX (Unitec-ERP-Hotfix.zip + .sha256) para UMA versao publicada.

.DESCRIPTION
  O pacote contem apenas os arquivos corrigidos + hotfix.json. O ERP do cliente (modo_atualizacao=hotfix)
  baixa do release "hotfix-{VersaoBase}", valida SHA256, faz backup, aplica e reverte se falhar.
  NAO altera a versao do ERP e NAO toca no release "update".

  Base (sha256_original) - informe UM:
    -BaseDir  pasta com a arvore publicada da versao (ex.: Unitec-ERP-Update.zip extraido). Preferido:
              o pacote oficial e montado da arvore de trabalho, nao de um commit.
    -BaseRef  commit/tag que corresponde exatamente ao que foi publicado.

  Correcao (sha256_novo): -FixRef (commit) ou, se omitido, a arvore de trabalho atual.

  Revisoes sao CUMULATIVAS: a revisao N deve conter todos os arquivos das revisoes anteriores.
  Informe -HotfixAnterior (zip da revisao anterior) para aceitar instalacoes que ja tenham a revisao anterior.

.EXAMPLE
  .\scripts\criar-hotfix.ps1 -VersaoBase 6.4.1.240 -BaseDir C:\pacotes\6.4.1.240\unitec-erp-web `
    -Arquivos app/Support/Erp/Os/OrdemServicoReportData.php -Revisao 1 -Descricao "Impressao OS"
#>
param(
    [Parameter(Mandatory = $true)][string]$VersaoBase,
    [Parameter(Mandatory = $true)][string[]]$Arquivos,
    [Parameter(Mandatory = $true)][int]$Revisao,
    [string]$Descricao = '',
    [string]$BaseDir = '',
    [string]$BaseRef = '',
    [string]$FixRef = '',
    [string]$HotfixAnterior = '',
    [string]$Saida = '',
    [string]$ChavePrivada = ''
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$PrefixosPermitidos = @('app/', 'resources/views/', 'lang/')
$CaminhosBloqueados = @('app/Support/Erp/Hotfix/', 'app/Console/Commands/HotfixCommand.php')
$ExtensoesPermitidas = @('.php', '.json')

if ([string]::IsNullOrWhiteSpace($ChavePrivada)) {
    $ChavePrivada = if ($env:UNITEC_HOTFIX_CHAVE) { $env:UNITEC_HOTFIX_CHAVE } else { 'C:\Projetos\chaves-unitec\hotfix-privada.pem' }
}
if (-not (Test-Path -LiteralPath $ChavePrivada -PathType Leaf)) {
    throw "Chave privada de assinatura nao encontrada: $ChavePrivada (php scripts\hotfix-chave.php gerar <arquivo>)"
}
if ((Resolve-Path -LiteralPath $ChavePrivada).Path.StartsWith($ProjectRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'A chave privada nao pode ficar dentro do projeto.'
}

if ($VersaoBase -notmatch '^\d+(\.\d+){1,3}$') { throw "VersaoBase invalida: $VersaoBase" }
if ($Revisao -lt 1) { throw 'Revisao deve ser >= 1.' }
if ([string]::IsNullOrWhiteSpace($BaseDir) -eq [string]::IsNullOrWhiteSpace($BaseRef)) {
    throw 'Informe exatamente um: -BaseDir (arvore publicada) ou -BaseRef (commit publicado).'
}
if ($BaseDir -and -not (Test-Path (Join-Path $BaseDir 'artisan'))) { throw "BaseDir sem artisan: $BaseDir" }
if ([string]::IsNullOrWhiteSpace($Saida)) { $Saida = Join-Path $ProjectRoot 'dist\hotfix' }

function Get-Sha256Hex([byte[]]$Bytes) {
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try { return (-join ($sha.ComputeHash($Bytes) | ForEach-Object { $_.ToString('x2') })) }
    finally { $sha.Dispose() }
}

function Get-GitBlob([string]$Ref, [string]$Caminho) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = 'git'
    $psi.Arguments = "-c core.autocrlf=false show `"${Ref}:$Caminho`""
    $psi.WorkingDirectory = $ProjectRoot
    $psi.UseShellExecute = $false
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $p = [System.Diagnostics.Process]::Start($psi)
    $ms = New-Object System.IO.MemoryStream
    $p.StandardOutput.BaseStream.CopyTo($ms)
    $null = $p.StandardError.ReadToEnd()
    $p.WaitForExit()
    if ($p.ExitCode -ne 0) { return $null }
    return $ms.ToArray()
}

function Get-ArquivoBytes([string]$Raiz, [string]$Caminho) {
    $full = Join-Path $Raiz ($Caminho -replace '/', '\')
    if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { return $null }
    return [System.IO.File]::ReadAllBytes($full)
}

# Revisao anterior (opcional): hashes "novos" dela viram sha256_anteriores.
$anteriores = @{}
if ($HotfixAnterior) {
    $zipAnt = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path $HotfixAnterior))
    try {
        $entrada = $zipAnt.Entries | Where-Object { $_.FullName -eq 'hotfix.json' } | Select-Object -First 1
        if (-not $entrada) { throw 'HotfixAnterior sem hotfix.json.' }
        $reader = New-Object System.IO.StreamReader($entrada.Open())
        $manAnt = $reader.ReadToEnd() | ConvertFrom-Json
        $reader.Dispose()
    } finally { $zipAnt.Dispose() }
    if ($manAnt.versao_base -ne $VersaoBase) { throw "HotfixAnterior e da versao $($manAnt.versao_base)." }
    if ([int]$manAnt.revisao -ge $Revisao) { throw "Revisao deve ser maior que a anterior ($($manAnt.revisao))." }
    foreach ($a in $manAnt.arquivos) {
        $lista = @($a.sha256_novo) + @($a.sha256_anteriores | Where-Object { $_ })
        $anteriores[$a.caminho] = $lista
    }
    foreach ($c in $anteriores.Keys) {
        if (-not (@($Arquivos | ForEach-Object { ($_ -replace '\\', '/').Trim('/') }) -contains $c)) {
            throw "Revisoes sao cumulativas: inclua tambem $c (estava na revisao $($manAnt.revisao))."
        }
    }
}

$itens = @()
$conteudos = @{}
foreach ($bruto in $Arquivos) {
    $caminho = ($bruto -replace '\\', '/').Trim().Trim('/')
    if ($caminho -match '(^|/)\.\.?(/|$)' -or $caminho -match ':') { throw "Caminho inseguro: $caminho" }
    if (-not ($PrefixosPermitidos | Where-Object { $caminho.StartsWith($_) })) { throw "Fora dos prefixos permitidos ($($PrefixosPermitidos -join ', ')): $caminho" }
    if ($CaminhosBloqueados | Where-Object { $caminho.StartsWith($_) }) { throw "O mecanismo de hotfix so muda por atualizacao oficial: $caminho" }
    if ($ExtensoesPermitidas -notcontains [System.IO.Path]::GetExtension($caminho).ToLowerInvariant()) { throw "Extensao nao permitida: $caminho" }
    if ($conteudos.ContainsKey($caminho)) { throw "Arquivo duplicado: $caminho" }

    $novo = if ($FixRef) { Get-GitBlob $FixRef $caminho } else { Get-ArquivoBytes $ProjectRoot $caminho }
    if ($null -eq $novo) { throw "Arquivo corrigido nao encontrado: $caminho" }
    $original = if ($BaseDir) { Get-ArquivoBytes $BaseDir $caminho } else { Get-GitBlob $BaseRef $caminho }

    $shaNovo = Get-Sha256Hex $novo
    $shaOriginal = if ($null -ne $original) { Get-Sha256Hex $original } else { $null }
    if ($shaOriginal -eq $shaNovo -and -not $anteriores.ContainsKey($caminho)) {
        throw "Sem alteracao em relacao a base: $caminho"
    }

    $conteudos[$caminho] = $novo
    $itens += [ordered]@{
        caminho           = $caminho
        sha256_original   = $shaOriginal
        sha256_anteriores = @($anteriores[$caminho] | Where-Object { $_ -and $_ -ne $shaNovo })
        sha256_novo       = $shaNovo
    }
    $tag = if ($null -eq $original) { 'NOVO' } else { 'ALTERADO' }
    Write-Host ("  [{0}] {1}" -f $tag, $caminho)
}

# Sintaxe PHP antes de empacotar (Blade e validado no cliente antes de aplicar).
$php = Join-Path $ProjectRoot 'tools\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }
$tmpLint = Join-Path ([System.IO.Path]::GetTempPath()) ('hotfix-lint-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tmpLint -Force | Out-Null
try {
    foreach ($c in $conteudos.Keys) {
        if (-not $c.EndsWith('.php') -or $c.EndsWith('.blade.php')) { continue }
        $f = Join-Path $tmpLint ([guid]::NewGuid().ToString('N') + '.php')
        [System.IO.File]::WriteAllBytes($f, $conteudos[$c])
        & $php -l $f 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Erro de sintaxe PHP em $c" }
    }
} finally { Remove-Item $tmpLint -Recurse -Force -ErrorAction SilentlyContinue }

$manifesto = [ordered]@{
    tipo        = 'unitec-hotfix'
    id          = "$VersaoBase-hf$Revisao"
    versao_base = $VersaoBase
    revisao     = $Revisao
    descricao   = $Descricao
    gerado_em   = (Get-Date).ToString('yyyy-MM-ddTHH:mm:ssK')
    arquivos    = $itens
}
$json = $manifesto | ConvertTo-Json -Depth 6
$utf8 = New-Object System.Text.UTF8Encoding($false)

New-Item -ItemType Directory -Path $Saida -Force | Out-Null
$zipPath = Join-Path $Saida 'Unitec-ERP-Hotfix.zip'
$shaPath = "$zipPath.sha256"
Remove-Item $zipPath, $shaPath -Force -ErrorAction SilentlyContinue

$fs = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $e = $zip.CreateEntry('hotfix.json', [System.IO.Compression.CompressionLevel]::Optimal)
    $s = $e.Open(); $b = $utf8.GetBytes($json); $s.Write($b, 0, $b.Length); $s.Dispose()
    foreach ($c in $conteudos.Keys) {
        $e = $zip.CreateEntry('arquivos/' + $c, [System.IO.Compression.CompressionLevel]::Optimal)
        $s = $e.Open(); $b = $conteudos[$c]; $s.Write($b, 0, $b.Length); $s.Dispose()
    }
} finally { $zip.Dispose(); $fs.Dispose() }

$zipBytes = [System.IO.File]::ReadAllBytes($zipPath)
if ($zipBytes.Length -gt 20MB) { throw 'Hotfix acima de 20 MB (limite do ERP).' }
$shaZip = Get-Sha256Hex $zipBytes
$assinatura = (& $php (Join-Path $ProjectRoot 'scripts\hotfix-chave.php') assinar $ChavePrivada $VersaoBase $shaZip $zipBytes.Length | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $assinatura -notmatch '^[A-Za-z0-9+/=]+$') { throw "Falha ao assinar o hotfix: $assinatura" }
[System.IO.File]::WriteAllText($shaPath, "$shaZip  Unitec-ERP-Hotfix.zip`nsize=$($zipBytes.Length)`nsig=$assinatura`n", $utf8)

Write-Host ''
Write-Host "Hotfix $($manifesto.id) gerado:" -ForegroundColor Green
Write-Host "  $zipPath ($($zipBytes.Length) bytes)"
Write-Host "  $shaPath"
Write-Host "  SHA256 $shaZip"
Write-Host "Publicar: .\scripts\publicar-hotfix-github.ps1 -VersaoBase $VersaoBase" -ForegroundColor Yellow
