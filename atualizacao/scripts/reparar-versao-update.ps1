#Requires -Version 5.1
<#
.SYNOPSIS
    Reparo quando o update "conclui" mas a versao continua antiga (OPcache/config).

.DESCRIPTION
    1) Para o artisan serve
    2) Apaga bootstrap/cache/config.php e tools/php/opcache
    3) Recria config:cache com OPcache DESLIGADO
    4) Mostra a versao lida de config/unitec.php
    5) Sobe o ERP de novo
#>
param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = $ProjectRoot
}
$AppPath = [System.IO.Path]::GetFullPath($AppPath)

. (Join-Path $AppPath 'scripts\unitec-install-lib.ps1')

Write-Host ''
Write-Host '========================================' -ForegroundColor Cyan
Write-Host '  Reparar versao apos update' -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan
Write-Host ("App: {0}" -f $AppPath) -ForegroundColor White

$unitecPhp = Join-Path $AppPath 'config\unitec.php'
if (-not (Test-Path $unitecPhp)) {
    throw "config\unitec.php nao encontrado em $AppPath"
}

$raw = Get-Content $unitecPhp -Raw
$diskVersion = if ($raw -match "'versao'\s*=>\s*'([^']+)'") { $Matches[1] } else { '?' }
Write-Host ("Versao no disco (config/unitec.php): {0}" -f $diskVersion) -ForegroundColor Yellow

Write-Host '>> Parando servidor PHP...' -ForegroundColor White
Stop-UnitecApplicationServer -AppPath $AppPath

$configCache = Join-Path $AppPath 'bootstrap\cache\config.php'
if (Test-Path $configCache) {
    Remove-Item $configCache -Force
    Write-Host '>> Removido bootstrap\cache\config.php' -ForegroundColor Green
}

$opcacheDir = Join-Path $AppPath 'tools\php\opcache'
if (Test-Path $opcacheDir) {
    Get-ChildItem $opcacheDir -File -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
    Write-Host '>> OPcache em disco limpo' -ForegroundColor Green
}

$phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
Write-Host (">> PHP: {0}" -f $phpExe) -ForegroundColor White

Push-Location $AppPath
try {
    & $phpExe -d opcache.enable=0 -d opcache.enable_cli=0 artisan config:clear 2>&1 | Out-Null
    & $phpExe -d opcache.enable=0 -d opcache.enable_cli=0 artisan config:cache 2>&1 | Out-Null
    Write-Host '>> config:cache regenerado SEM OPcache' -ForegroundColor Green
} finally {
    Pop-Location
}

if (Test-Path $configCache) {
    $cachedRaw = Get-Content $configCache -Raw
    $cachedVersion = if ($cachedRaw -match "'versao'\s*=>\s*'([^']+)'") { $Matches[1] } else { '?' }
    Write-Host ("Versao no cache (bootstrap/cache/config.php): {0}" -f $cachedVersion) -ForegroundColor Yellow
}

Write-Host '>> Reiniciando ERP...' -ForegroundColor White
Start-UnitecApplicationServer -AppPath $AppPath -Restart | Out-Null

Write-Host ''
if ($diskVersion -match '^\d+\.\d+\.\d+\.\d+$') {
    Write-Host ("Pronto. Versao no disco: {0}" -f $diskVersion) -ForegroundColor Green
    Write-Host 'Abra o sistema e confira a versao no login (Ctrl+F5).' -ForegroundColor White
    if ($diskVersion -eq '6.4.1.66') {
        Write-Host ''
        Write-Host 'ATENCAO: o arquivo no disco ainda e 6.4.1.66.' -ForegroundColor Red
        Write-Host 'Os arquivos do pacote NAO foram aplicados. Baixe de novo e Instale,' -ForegroundColor Red
        Write-Host 'ou rode o update com o ERP totalmente parado.' -ForegroundColor Red
    }
} else {
    Write-Host 'Nao foi possivel ler a versao.' -ForegroundColor Red
}
Write-Host ''
