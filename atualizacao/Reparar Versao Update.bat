@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo ========================================
echo   Reparar versao apos update
echo ========================================
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\reparar-versao-update.ps1" -AppPath "%~dp0"
echo.
pause
exit /b %ERRORLEVEL%
