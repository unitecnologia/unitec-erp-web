@echo off
chcp 65001 >nul
setlocal EnableExtensions

REM ============================================================
REM  Instala Visual C++ x64 (obrigatorio para PHP pdo_mysql)
REM  Depois REINICIE o Windows e rode Reparar Sistema.bat
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0tools\php\php.exe" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "VC=%APP%\installer\assets\vc_redist.x64.exe"
if not exist "%VC%" set "VC=%~dp0vc_redist.x64.exe"
if not exist "%VC%" set "VC=%TEMP%\vc_redist.x64.exe"

echo.
echo ========================================
echo   Instalar Visual C++ Redistributable
echo ========================================
echo.
echo Isso e necessario para o PHP carregar pdo_mysql.
echo.

if not exist "%APP%\installer\assets\vc_redist.x64.exe" if not exist "%~dp0vc_redist.x64.exe" (
  echo Baixando VC++ da Microsoft...
  powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12;" ^
    "Invoke-WebRequest -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile '%TEMP%\vc_redist.x64.exe' -UseBasicParsing"
  if not exist "%TEMP%\vc_redist.x64.exe" (
    echo [ERRO] Nao foi possivel baixar. Baixe manualmente:
    echo https://aka.ms/vs/17/release/vc_redist.x64.exe
    start "" "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    pause
    exit /b 1
  )
  set "VC=%TEMP%\vc_redist.x64.exe"
)

if exist "%APP%\installer\assets\vc_redist.x64.exe" set "VC=%APP%\installer\assets\vc_redist.x64.exe"
if exist "%~dp0vc_redist.x64.exe" set "VC=%~dp0vc_redist.x64.exe"

echo Instalando: %VC%
echo Aguarde...
"%VC%" /install /quiet /norestart
set "EC=%ERRORLEVEL%"
echo Codigo saida: %EC%
echo.
echo ========================================
echo   REINICIE O WINDOWS AGORA
echo ========================================
echo.
echo Depois que reiniciar, rode:
echo   Reparar Sistema.bat
echo.
set /p R=Reiniciar agora? (S/N): 
if /I "%R%"=="S" shutdown /r /t 5 /c "Unitec ERP: reinicio apos Visual C++"
pause
exit /b 0
