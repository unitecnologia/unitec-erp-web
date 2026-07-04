@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Gerar pacote Unitec-ERP-Update.zip
echo ========================================
echo.

powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\criar-pacote-update.ps1" -SkipComposer

if errorlevel 1 (
    echo.
    echo ERRO ao gerar o pacote.
    pause
    exit /b 1
)

echo.
echo Pasta: dist\pacote-update\unitec-erp-web
echo ZIP:   dist\Unitec-ERP-Update.zip
echo.
pause
exit /b 0
