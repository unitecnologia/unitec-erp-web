@echo off
chcp 65001 >nul
title Unitec ERP - Liberar Timeout / OPcache
cd /d "%~dp0"

echo.
echo  Ajuste rapido de performance no PHP embutido...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\corrigir-performance-php.ps1" -AppPath "%~dp0"
if errorlevel 1 (
  echo.
  echo  Falhou. Ajuste manual em tools\php\php.ini:
  echo    max_execution_time=300
  echo    memory_limit=256M
  echo    opcache.enable=1
  echo    zend_extension=opcache
  echo.
  pause
  exit /b 1
)

echo.
pause
