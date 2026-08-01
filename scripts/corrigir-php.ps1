#Requires -Version 5.1
<#
.SYNOPSIS
    Corrige php.ini (pdo_mysql / extension_dir absoluto) no PC do cliente.
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

Write-Title 'Corrigir PHP (pdo_mysql)'
Write-Host ("Pasta: {0}" -f $AppPath) -ForegroundColor Gray
Write-Host ''

$phpDir = Get-UnitecPhpDirectory -AppPath $AppPath
if (-not $phpDir) {
    Write-Err 'PHP embutido nao encontrado em tools\php.'
    Write-Host 'Reinstale o Unitec ERP ou copie tools\php do pacote de instalacao.' -ForegroundColor Yellow
    exit 1
}

$phpExe = Join-Path $phpDir 'php.exe'
Write-Host ("PHP: {0}" -f $phpExe) -ForegroundColor White

Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath -DisableOpcache
Write-Ok 'php.ini atualizado (extensoes + extension_dir absoluto).'

$repair = Repair-PhpExecutableRuntime -SourceRoot $AppPath -PhpExe $phpExe -AllowFix
if (-not $repair.Ok) {
    Write-Warn ('PHP ainda com problema: {0}' -f $repair.Error)
    Write-Host 'Instale o Visual C++ Redistributable x64 e tente de novo.' -ForegroundColor Yellow
}

$modules = & $phpExe -m 2>$null
$needed = @('pdo_mysql', 'mysqli', 'intl', 'mbstring', 'openssl', 'curl', 'gd', 'zip')
$missing = @()
foreach ($ext in $needed) {
    if (-not ($modules | Where-Object { $_.Trim() -eq $ext })) {
        $missing += $ext
    }
}

if ($missing.Count -gt 0) {
    Write-Err ('Extensoes faltando: {0}' -f ($missing -join ', '))
    $dll = Join-Path $phpDir 'ext\php_pdo_mysql.dll'
    Write-Host ("DLL pdo_mysql existe: {0}" -f (Test-Path $dll)) -ForegroundColor Gray
    Write-InstallLog -AppPath $AppPath -Message ('ERRO Corrigir PHP: faltam {0}' -f ($missing -join ', '))
    exit 1
}

Write-Ok 'pdo_mysql e demais extensoes ativas.'
Write-InstallLog -AppPath $AppPath -Message 'PHP corrigido (pdo_mysql OK).'

Write-Host ''
Write-Host 'Agora rode: Reiniciar Servicos.bat' -ForegroundColor Cyan
Write-Host 'Ou: Unitec ERP.bat' -ForegroundColor Cyan
Write-Host ''
exit 0
