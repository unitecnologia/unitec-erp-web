#Requires -Version 5.1
<#
.SYNOPSIS
    Repara pastas storage do Unitec ERP no PC do cliente (sessions/views/cache).
.DESCRIPTION
    Use quando o ERP falha com:
    file_put_contents(...\storage\framework\sessions\...): Failed to open stream
    (comum apos atualizacao que falhou por disco cheio / timeout na extracao).
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

Write-Host ''
Write-Host 'Unitec ERP - reparo de storage' -ForegroundColor Cyan
Write-Host ("Pasta: {0}" -f $AppPath) -ForegroundColor Gray
Write-Host ''

$free = (Get-PSDrive -Name (Split-Path -Qualifier $AppPath).TrimEnd(':') -ErrorAction SilentlyContinue).Free
if ($null -ne $free) {
    $freeGb = [math]::Round($free / 1GB, 2)
    Write-Host ("Espaco livre no disco: {0} GB" -f $freeGb) -ForegroundColor $(if ($freeGb -lt 2) { 'Yellow' } else { 'Gray' })
    if ($freeGb -lt 1) {
        Write-Warn 'Disco com pouco espaco. Libere arquivos temporarios antes de atualizar de novo.'
    }
}

Ensure-UnitecStorageStructure -AppPath $AppPath
Write-Ok 'Pastas storage/framework recriadas.'

try {
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('optimize:clear') -AllowFailure | Out-Null
    Write-Ok 'Caches limpos.'
} catch {
    Write-Warn ("Nao foi possivel limpar cache: {0}" -f $_.Exception.Message)
}

try {
    Start-UnitecStack -AppPath $AppPath -WaitSeconds 20
    Write-Ok ('Sistema ativo em {0}' -f (Get-UnitecDefaultAppUrl))
} catch {
    Write-Warn ("Nao foi possivel reiniciar automaticamente: {0}" -f $_.Exception.Message)
    Write-Host 'Tente abrir o atalho do Unitec ERP novamente.' -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Pronto. Abra o ERP no navegador (Ctrl+F5).' -ForegroundColor Green
Write-Host ''
