@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Corrigir PHP (pdo_mysql)
echo ========================================
echo.
echo Use quando aparecer:
echo   Extensao PHP pdo_mysql nao esta ativa
echo.
echo Aguarde...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\corrigir-php.ps1" -AppPath "%~dp0"
if errorlevel 1 (
    echo.
    echo FALHOU. Veja a mensagem acima.
    echo.
    pause
    exit /b 1
)

echo.
echo OK. Agora abra: Reiniciar Servicos.bat
echo.
pause
exit /b 0
