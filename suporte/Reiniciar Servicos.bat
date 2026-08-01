@echo off
chcp 65001 >nul
cd /d "%~dp0\.."

echo.
echo ========================================
echo   Reiniciar servicos Unitec ERP
echo ========================================
echo.
echo Pasta: %CD%
echo.
echo Isso vai parar e iniciar MySQL + servidor web.
echo Aguarde...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0\..\scripts\reiniciar-servicos.ps1" -AppPath "%~dp0\.."
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
