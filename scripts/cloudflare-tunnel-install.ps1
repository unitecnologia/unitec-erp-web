<#
.SYNOPSIS
  Instala o cloudflared (Cloudflare Tunnel) no Windows.

.DESCRIPTION
  Baixa o cloudflared oficial se não estiver no PATH e coloca em tools\cloudflared\.
#>

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$DestDir = Join-Path $Root 'tools\cloudflared'
$DestExe = Join-Path $DestDir 'cloudflared.exe'

function Test-Cloudflared {
    param([string]$Path)
    if (-not (Test-Path $Path)) { return $false }
    try {
        & $Path --version 2>&1 | Out-Null
        return ($LASTEXITCODE -eq 0 -or $LASTEXITCODE -eq $null)
    } catch {
        return $false
    }
}

$existing = Get-Command cloudflared -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "cloudflared já no PATH: $($existing.Source)" -ForegroundColor Green
    & cloudflared --version
    exit 0
}

if (Test-Cloudflared $DestExe) {
    Write-Host "cloudflared já instalado em: $DestExe" -ForegroundColor Green
    & $DestExe --version
    Write-Host ""
    Write-Host "Dica: adicione ao PATH ou use os scripts cloudflare-tunnel-*.ps1" -ForegroundColor Cyan
    exit 0
}

New-Item -ItemType Directory -Force -Path $DestDir | Out-Null

$arch = if ([Environment]::Is64BitOperatingSystem) { 'amd64' } else { '386' }
$url = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-$arch.exe"
$tmp = Join-Path $env:TEMP "cloudflared-windows-$arch.exe"

Write-Host "Baixando cloudflared ($arch)..." -ForegroundColor Cyan
Write-Host $url
Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing
Copy-Item -Force $tmp $DestExe
Remove-Item -Force $tmp -ErrorAction SilentlyContinue

if (-not (Test-Cloudflared $DestExe)) {
    throw "Falha ao instalar cloudflared em $DestExe"
}

Write-Host ""
Write-Host "OK: $DestExe" -ForegroundColor Green
& $DestExe --version
Write-Host ""
Write-Host "Próximos passos:" -ForegroundColor Yellow
Write-Host "  1. Leia docs\GESTOR-CLOUDFLARE-TUNNEL.md"
Write-Host "  2. cloudflared tunnel login"
Write-Host "  3. .\scripts\cloudflare-tunnel-start.ps1   (túnel nomeado)"
Write-Host "  ou .\scripts\cloudflare-tunnel-quick.ps1  (teste rápido sem domínio)"
