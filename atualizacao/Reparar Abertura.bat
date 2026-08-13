@echo off
chcp 65001 >nul
setlocal EnableExtensions

REM ============================================================
REM  REPARAR ABERTURA Unitec ERP
REM  Cliente com "redirecionamento em excesso" / nao abre login
REM  Envie este arquivo + a pasta scripts\ OU rode de C:\UNITECNOLOGIA_WEB
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0artisan" set "APP=%~dp0"
if exist "%~dp0scripts\reparar-abertura.ps1" if exist "%~dp0artisan" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "SCRIPT=%APP%\scripts\reparar-abertura.ps1"
if not exist "%SCRIPT%" set "SCRIPT=%~dp0scripts\reparar-abertura.ps1"
if not exist "%SCRIPT%" set "SCRIPT=%~dp0reparar-abertura.ps1"

if not exist "%SCRIPT%" (
    echo [ERRO] Nao encontrei scripts\reparar-abertura.ps1
    echo Coloque este .bat dentro de C:\UNITECNOLOGIA_WEB e tente de novo.
    pause
    exit /b 1
)

echo.
echo Reparando abertura do Unitec ERP...
echo Nao feche esta janela.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" -AppPath "%APP%"
set "RC=%ERRORLEVEL%"

if not "%RC%"=="0" (
    echo.
    echo [ERRO] Falha ao reparar. Envie o print desta tela ao suporte.
    pause
    exit /b %RC%
)

exit /b 0
