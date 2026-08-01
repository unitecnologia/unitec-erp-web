@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Reiniciar servicos Unitec ERP
echo ========================================
echo.
echo Isso vai:
echo   1. Parar servidor web e MySQL
echo   2. Reparar pastas storage
echo   3. Limpar caches
echo   4. Iniciar tudo de novo
echo.
echo Aguarde...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\reiniciar-servicos.ps1" -AppPath "%~dp0"
if errorlevel 1 (
    echo.
    echo FALHOU. Veja a mensagem acima ou instalacao.log
    echo.
    pause
    exit /b 1
)

echo.
pause
exit /b 0
