@echo off
chcp 65001 >nul
title Unitec ERP - Liberar Timeout / OPcache
cd /d "%~dp0"

echo.
echo  Ajuste rapido de performance no PHP embutido...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference='Stop'; ^
   $root=Split-Path -Parent $MyInvocation.MyCommand.Path; ^
   if (-not (Test-Path (Join-Path $root 'artisan'))) { $root=Split-Path -Parent $root }; ^
   $ini=Join-Path $root 'tools\php\php.ini'; ^
   if (-not (Test-Path $ini)) { throw ('php.ini nao encontrado: '+$ini) }; ^
   $c=Get-Content $ini -Raw; ^
   $c=$c -replace '(?m)^\s*;\s*zend_extension\s*=\s*opcache\s*$','zend_extension=opcache'; ^
   if ($c -notmatch '(?m)^\s*zend_extension\s*=\s*opcache\s*$') { $c=$c.TrimEnd()+\"`r`nzend_extension=opcache`r`n\" }; ^
   $map=@{ 'opcache.enable'='1'; 'opcache.enable_cli'='0'; 'opcache.memory_consumption'='128'; 'opcache.interned_strings_buffer'='16'; 'opcache.max_accelerated_files'='10000'; 'opcache.validate_timestamps'='1'; 'opcache.revalidate_freq'='2'; 'memory_limit'='256M'; 'max_execution_time'='300'; 'realpath_cache_size'='4096k'; 'realpath_cache_ttl'='600' }; ^
   foreach ($k in $map.Keys) { $line=\"$k=$($map[$k])\"; if ($c -match ('(?m)^\s*'+[regex]::Escape($k)+'\s*=')) { $c=[regex]::Replace($c,('(?m)^\s*'+[regex]::Escape($k)+'\s*=.*$'),$line) } else { $c=$c.TrimEnd()+\"`r`n$line`r`n\" } }; ^
   Set-Content -Path $ini -Value $c -Encoding ASCII; ^
   Write-Host ('OK: '+$ini) -ForegroundColor Green"

if errorlevel 1 (
  echo.
  echo  Falhou. Abra tools\php\php.ini e ajuste manualmente:
  echo    max_execution_time=300
  echo    memory_limit=256M
  echo    opcache.enable=1
  echo    zend_extension=opcache
  echo.
  pause
  exit /b 1
)

echo.
echo  Agora rode: Reiniciar Servicos.bat
echo.
pause
