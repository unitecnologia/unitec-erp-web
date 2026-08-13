<#
.SYNOPSIS
  Inicia o Cloudflare Tunnel nomeado (domínio fixo).

.DESCRIPTION
  Usa %USERPROFILE%\.cloudflared\config.yml (veja config\cloudflared\config.example.yml).

  Pré-requisitos:
  - .\scripts\cloudflare-tunnel-install.ps1
  - cloudflared tunnel login
  - tunnel criado + DNS roteado (docs\GESTOR-CLOUDFLARE-TUNNEL.md)
  - ERP em http://127.0.0.1:8000
  - .env com APP_URL=https://seu-dominio e SESSION_SECURE_COOKIE=true
#>

param(
    [string]$ConfigPath = ''
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

if (-not $ConfigPath) {
    $ConfigPath = Join-Path $env:USERPROFILE '.cloudflared\config.yml'
}

if (-not (Test-Path $ConfigPath)) {
    $example = Join-Path $Root 'config\cloudflared\config.example.yml'
    Write-Host "Arquivo não encontrado: $ConfigPath" -ForegroundColor Red
    Write-Host "Copie o exemplo e edite:" -ForegroundColor Yellow
    Write-Host "  copy `"$example`" `"$ConfigPath`""
    Write-Host "Documentação: docs\GESTOR-CLOUDFLARE-TUNNEL.md"
    exit 1
}

$cf = Resolve-Cloudflared

Write-Host "Iniciando túnel com: $ConfigPath" -ForegroundColor Cyan
Write-Host "ERP local deve estar em http://127.0.0.1:8000" -ForegroundColor DarkGray
Write-Host "No celular: https://SEU_DOMINIO/gestor/" -ForegroundColor Green
Write-Host "Ctrl+C para encerrar." -ForegroundColor DarkGray
Write-Host ""

& $cf tunnel --config $ConfigPath run
