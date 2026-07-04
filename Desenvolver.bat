@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo Iniciando Unitec ERP (Windows nativo)...
echo Deixe esta janela aberta. Ctrl+C para parar o servidor.
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\dev-windows.ps1"
echo.
echo Servidor encerrado.
pause
