#Requires -Version 5.1
<#
.SYNOPSIS
    Liga OPcache e sobe limites do PHP embutido (performance / evita 500 por timeout).
#>

param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ScriptRoot = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = Split-Path -Parent $ScriptRoot
    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        $AppPath = $ScriptRoot
    }
    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        $AppPath = Split-Path -Parent $ScriptRoot
    }
}

$ini = Join-Path $AppPath 'tools\php\php.ini'
if (-not (Test-Path $ini)) {
    throw ("php.ini nao encontrado: {0}" -f $ini)
}

Write-Host ''
Write-Host 'Ajuste rapido de performance no PHP embutido...' -ForegroundColor Cyan
Write-Host ("Arquivo: {0}" -f $ini) -ForegroundColor Gray
Write-Host ''

$content = Get-Content -Path $ini -Raw -ErrorAction Stop

# Garante zend_extension=opcache ativo
$content = [regex]::Replace($content, '(?m)^\s*;\s*zend_extension\s*=\s*opcache\s*$', 'zend_extension=opcache')
if ($content -notmatch '(?m)^\s*zend_extension\s*=\s*opcache\s*$') {
    $content = $content.TrimEnd() + [Environment]::NewLine + 'zend_extension=opcache' + [Environment]::NewLine
}

$map = [ordered]@{
    'opcache.enable'                 = '1'
    'opcache.enable_cli'             = '0'
    'opcache.memory_consumption'     = '128'
    'opcache.interned_strings_buffer'= '16'
    'opcache.max_accelerated_files'  = '10000'
    'opcache.validate_timestamps'    = '1'
    'opcache.revalidate_freq'        = '2'
    'memory_limit'                   = '256M'
    'max_execution_time'             = '300'
    'realpath_cache_size'            = '4096k'
    'realpath_cache_ttl'             = '600'
}

foreach ($key in $map.Keys) {
    $line = '{0}={1}' -f $key, $map[$key]
    $pattern = '(?m)^\s*{0}\s*=.*$' -f [regex]::Escape($key)
    if ($content -match $pattern) {
        $content = [regex]::Replace($content, $pattern, $line)
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }
}

# UTF-8 sem BOM (PHP no Windows costuma aceitar ASCII/UTF-8)
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($ini, $content, $utf8NoBom)

Write-Host 'OK: php.ini atualizado.' -ForegroundColor Green
Write-Host '  - opcache.enable=1' -ForegroundColor Gray
Write-Host '  - max_execution_time=300' -ForegroundColor Gray
Write-Host '  - memory_limit=256M' -ForegroundColor Gray
Write-Host ''
Write-Host 'Agora rode: Reiniciar Servicos.bat' -ForegroundColor Cyan
Write-Host ''
