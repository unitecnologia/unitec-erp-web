@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Gerar Instalador Sistema Facil.exe
echo ========================================
echo.
echo Staging + compilacao Inno Setup (pode demorar)
echo.

powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\build-setup.ps1" -SkipComposer -SkipNpm

if errorlevel 1 (
    echo.
    echo ERRO ao gerar instalador.
    pause
    exit /b 1
)

echo.
echo Staging: dist\staging\unitec-erp-web
echo EXE:     dist\output\Instalar Unitec ERP.exe
echo.
pause
exit /b 0
