@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Gerar staging para Inno Setup
echo ========================================
echo.
echo Monta dist\staging\unitec-erp-web (sem compilar o .exe)
echo Use depois: installer\unitec-erp.iss no Inno Setup 6
echo.

powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\build-setup.ps1" -SkipCompile -SkipComposer -SkipNpm

if errorlevel 1 (
    echo.
    echo ERRO ao gerar staging.
    pause
    exit /b 1
)

echo.
echo Staging: dist\staging\unitec-erp-web
echo Inno:    installer\unitec-erp.iss
echo Dica:    dist\staging\LEIA-ME-INNO.txt
echo.
echo Para compilar o instalador .exe, use "Gerar Instalador Inno.bat"
echo.
pause
exit /b 0
