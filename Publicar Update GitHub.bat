@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Publicar Unitec-ERP-Update.zip no GitHub
echo ========================================
echo.
echo Requisitos:
echo   1) dist\Unitec-ERP-Update.zip gerado
echo   2) gh auth login (uma vez)
echo.

powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\publicar-update-github.ps1"

if errorlevel 1 (
    echo.
    echo ERRO ao publicar.
    pause
    exit /b 1
)

echo.
pause
exit /b 0
