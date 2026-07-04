@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ========================================
echo   Finalizar atualizacao manual
echo ========================================
echo.
echo Use DEPOIS de copiar dist\pacote-update\unitec-erp-web
echo Preservando .env, storage\ e tools\
echo.
echo Feche o Unitec ERP.bat antes de continuar.
echo.
pause

set "PHP=%~dp0tools\php\php.exe"

if not exist "%PHP%" (
    echo ERRO: PHP nao encontrado em tools\php\php.exe
    pause
    exit /b 1
)

if not exist "%~dp0artisan" (
    echo ERRO: artisan nao encontrado. Rode na pasta do ERP.
    pause
    exit /b 1
)

echo.
echo ^>^> Configurando PHP (SSL + extension zip)
powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -Command "& { . '%~dp0scripts\unitec-install-lib.ps1'; $phpDir = Join-Path (Get-Location).Path 'tools\php'; if (Test-Path $phpDir) { Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot (Get-Location).Path } }"

echo.
echo ^>^> migrate --force
"%PHP%" artisan migrate --force
if errorlevel 1 (
    echo.
    echo ERRO no migrate.
    pause
    exit /b 1
)

echo.
echo ^>^> view:clear
"%PHP%" artisan view:clear
if errorlevel 1 (
    echo ERRO no view:clear.
    pause
    exit /b 1
)

echo.
echo ^>^> config:cache
"%PHP%" artisan config:cache
if errorlevel 1 (
    echo.
    echo Tentando config:clear + config:cache...
    "%PHP%" artisan config:clear
    "%PHP%" artisan config:cache
    if errorlevel 1 (
        echo ERRO no config:cache.
        pause
        exit /b 1
    )
)

echo.
echo ========================================
echo   Concluido!
echo ========================================
echo.
echo Agora inicie: Unitec ERP.bat
echo Confira a versao na tela de login.
echo.
pause
exit /b 0
