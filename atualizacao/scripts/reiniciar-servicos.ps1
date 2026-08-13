#Requires -Version 5.1
<#
.SYNOPSIS
    Para e reinicia MySQL + servidor web do Unitec ERP (corrige Server Error / porta morta).
#>

param(
    [string]$AppPath = '',
    [switch]$SkipBrowser
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

Write-Title 'Reiniciar servicos Unitec ERP'
Write-Host ("Pasta: {0}" -f $AppPath) -ForegroundColor Gray
Write-Host ''

try {
    Write-Host '>> Reparando pastas storage...' -ForegroundColor White
    Ensure-UnitecStorageStructure -AppPath $AppPath

    Write-Host '>> Corrigindo PHP (pdo_mysql / extension_dir)...' -ForegroundColor White
    try {
        $phpDir = Get-UnitecPhpDirectory -AppPath $AppPath
        if ($phpDir) {
            Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath
            $null = Repair-PhpExecutableRuntime -SourceRoot $AppPath -PhpExe (Join-Path $phpDir 'php.exe') -AllowFix
        }
    } catch {
        Write-Warn ("PHP: {0}" -f $_.Exception.Message)
    }

    Write-Host '>> Encerrando servidor web...' -ForegroundColor White
    Stop-UnitecApplicationServer -AppPath $AppPath

    Write-Host '>> Encerrando MySQL embutido...' -ForegroundColor White
    Stop-UnitecEmbeddedMysql -AppPath $AppPath

    Start-Sleep -Seconds 2

    Write-Host '>> Limpando caches...' -ForegroundColor White
    try {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('view:clear') -AllowFailure | Out-Null
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:clear') -AllowFailure | Out-Null
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('route:clear') -AllowFailure | Out-Null
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('route:cache') -AllowFailure | Out-Null
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('event:cache') -AllowFailure | Out-Null
    } catch {
        Write-Warn ("Cache: {0}" -f $_.Exception.Message)
    }

    Write-Host '>> Iniciando MySQL + servidor web...' -ForegroundColor White
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 25

    $appUrl = Get-UnitecDefaultAppUrl
    Write-Ok ("Sistema ativo em {0}" -f $appUrl)
    Write-InstallLog -AppPath $AppPath -Message 'Servicos reiniciados com sucesso.'

    if (-not $SkipBrowser) {
        Start-Process ($appUrl.TrimEnd('/') + '/admin')
    }

    Write-Host ''
    Write-Host 'Pronto. Se ainda der Server Error, pressione Ctrl+F5 no navegador.' -ForegroundColor Green
    Write-Host ''
    exit 0
} catch {
    Write-Err $_.Exception.Message
    Write-InstallLog -AppPath $AppPath -Message ('ERRO reiniciar servicos: {0}' -f $_.Exception.Message)
    Write-Host ''
    Write-Host 'Consulte instalacao.log nesta pasta.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}
