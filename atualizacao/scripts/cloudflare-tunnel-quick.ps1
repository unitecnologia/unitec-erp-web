<#
.SYNOPSIS
  Túnel rápido de teste (URL aleatória *.trycloudflare.com).

.DESCRIPTION
  Não precisa de domínio. Ideal para validar o Gestor no 4G.
  A URL muda a cada execução — atualize APP_URL no .env a cada teste
  ou aceite limitações de cookie/URL gerada.

  Pré-requisito: ERP rodando em http://127.0.0.1:8000
#>

param(
    [string]$LocalUrl = 'http://127.0.0.1:8000'
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot

function Resolve-Cloudflared {
    $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $local = Join-Path $Root 'tools\cloudflared\cloudflared.exe'
    if (Test-Path $local) { return $local }
    throw "cloudflared não encontrado. Rode: .\scripts\cloudflare-tunnel-install.ps1"
}

try {
    $probe = Invoke-WebRequest -Uri "$LocalUrl/up" -UseBasicParsing -TimeoutSec 3
} catch {
    Write-Host "AVISO: ERP não respondeu em $LocalUrl/up" -ForegroundColor Yellow
    Write-Host "Suba o ERP antes (Desenvolver.bat / artisan serve)." -ForegroundColor Yellow
}

$cf = Resolve-Cloudflared
Write-Host "Túnel rápido → $LocalUrl" -ForegroundColor Cyan
Write-Host "Abra a URL https://….trycloudflare.com/gestor/ que aparecer abaixo." -ForegroundColor Green
Write-Host "Lembre de alinhar APP_URL no .env com essa URL (teste)." -ForegroundColor Yellow
Write-Host "Ctrl+C para encerrar." -ForegroundColor DarkGray
Write-Host ""

& $cf tunnel --url $LocalUrl
