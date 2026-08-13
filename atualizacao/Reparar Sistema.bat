@echo off
chcp 65001 >nul
setlocal EnableExtensions

REM ============================================================
REM  REPARAR SISTEMA Unitec ERP (500 / nao abre / pdo_mysql)
REM  Use no PC do cliente: C:\UNITECNOLOGIA_WEB
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0artisan" if exist "%~dp0tools\php\php.exe" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "PHPDIR=%APP%\tools\php"
set "PHPEXE=%PHPDIR%\php.exe"
set "INI=%PHPDIR%\php.ini"
set "EXTDIR=%PHPDIR%\ext"
set "LOG=%APP%\instalacao.log"
set "ERR=%USERPROFILE%\Desktop\unitec-erro.txt"
set "LARALOG=%APP%\storage\logs\laravel.log"

echo.
echo ========================================
echo   REPARAR SISTEMA Unitec ERP
echo ========================================
echo Pasta: %APP%
echo.
echo Aguarde - nao feche esta janela.
echo.

if not exist "%PHPEXE%" (
    echo [ERRO] PHP nao encontrado: %PHPEXE%
    echo Reinstale o Unitec ERP.
    pause
    exit /b 1
)

echo [1/8] Encerrando PHP e MySQL antigos...
taskkill /F /IM php.exe >nul 2>&1
REM so mata mysqld do Unitec (nao outros)
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "Get-Process mysqld -EA SilentlyContinue | Where-Object { $_.Path -like '*UNITECNOLOGIA_WEB*' -or $_.Path -like '*unitec-erp-web*' } | Stop-Process -Force -EA SilentlyContinue"
timeout /t 2 /nobreak >nul

echo [2/8] Recriando pastas storage...
mkdir "%APP%\storage\framework\sessions" 2>nul
mkdir "%APP%\storage\framework\views" 2>nul
mkdir "%APP%\storage\framework\cache\data" 2>nul
mkdir "%APP%\storage\logs" 2>nul
mkdir "%APP%\storage\app\private\updates" 2>nul
mkdir "%APP%\bootstrap\cache" 2>nul

echo [3/8] Corrigindo php.ini (pdo_mysql)...
if not exist "%INI%" (
  if exist "%PHPDIR%\php.ini-development" copy /Y "%PHPDIR%\php.ini-development" "%INI%" >nul
)
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ini='%INI%'; $extDir=('%EXTDIR%' -replace '\\','/');" ^
  "if(-not(Test-Path $ini)){throw 'php.ini ausente'};" ^
  "$c=[IO.File]::ReadAllText($ini);" ^
  "@('curl','fileinfo','gd','intl','mbstring','mysqli','openssl','pdo_mysql','zip') | ForEach-Object {" ^
  "  $e=$_; $c=[regex]::Replace($c,'(?m)^;\s*extension\s*=\s*'+$e+'\s*$','extension='+$e);" ^
  "  if($c -notmatch ('(?m)^\s*extension\s*=\s*'+$e+'\s*$')){ $c += \"`r`nextension=$e\" }" ^
  "};" ^
  "$c=[regex]::Replace($c,'(?m)^\s*;?\s*extension_dir\s*=.*\r?\n?','');" ^
  "$c=$c.TrimEnd()+\"`r`nextension_dir=`\"$extDir`\"`r`n\";" ^
  "[IO.File]::WriteAllText($ini,$c,(New-Object Text.UTF8Encoding $false))"

pushd "%PHPDIR%"
"%PHPEXE%" -m > "%TEMP%\unitec-php-m.txt" 2> "%TEMP%\unitec-php-m.err"
popd
findstr /I /X "pdo_mysql" "%TEMP%\unitec-php-m.txt" >nul
if errorlevel 1 (
  echo [AVISO] pdo_mysql nao carregou.
  echo.
  echo --- Erro do PHP ---
  type "%TEMP%\unitec-php-m.err"
  echo -------------------
  echo.
  echo [3b] Instalando Visual C++ x64...
  set "VC="
  if exist "%APP%\installer\assets\vc_redist.x64.exe" set "VC=%APP%\installer\assets\vc_redist.x64.exe"
  if not defined VC if exist "%~dp0vc_redist.x64.exe" set "VC=%~dp0vc_redist.x64.exe"
  if not defined VC if exist "%TEMP%\vc_redist.x64.exe" set "VC=%TEMP%\vc_redist.x64.exe"
  if not defined VC (
    echo Baixando VC++ da Microsoft (~25 MB)...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
      "[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12;" ^
      "Invoke-WebRequest -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile '%TEMP%\vc_redist.x64.exe' -UseBasicParsing"
    if exist "%TEMP%\vc_redist.x64.exe" set "VC=%TEMP%\vc_redist.x64.exe"
  )
  if defined VC (
    echo Instalando: %VC%
    "%VC%" /install /quiet /norestart
    echo Aguardando 8s...
    timeout /t 8 /nobreak >nul
    pushd "%PHPDIR%"
    "%PHPEXE%" -m > "%TEMP%\unitec-php-m.txt" 2> "%TEMP%\unitec-php-m.err"
    popd
  ) else (
    echo [ERRO] Nao baixou o VC++. Sem internet?
  )
)
findstr /I /X "pdo_mysql" "%TEMP%\unitec-php-m.txt" >nul
if errorlevel 1 (
  echo.
  echo ========================================
  echo   PRECISA REINICIAR O WINDOWS
  echo ========================================
  echo.
  echo O Visual C++ foi instalado ^(ou precisa instalar^),
  echo mas o PHP so carrega pdo_mysql DEPOIS do reinicio.
  echo.
  echo 1. Reinicie o computador
  echo 2. Rode de novo: Reparar Sistema.bat
  echo.
  echo Ou rode agora: Instalar Visual C++.bat
  echo.
  echo --- Aviso PHP ---
  type "%TEMP%\unitec-php-m.err"
  echo.
  set /p R=Reiniciar Windows agora? (S/N): 
  if /I "%R%"=="S" shutdown /r /t 5 /c "Unitec ERP: reinicio para ativar Visual C++"
  pause
  exit /b 1
)
echo [OK] pdo_mysql

echo [4/8] Limpando caches quebrados do update...
del /F /Q "%APP%\bootstrap\cache\*.php" >nul 2>&1
cd /d "%APP%"
"%PHPEXE%" artisan optimize:clear >nul 2>&1
"%PHPEXE%" artisan view:clear >nul 2>&1
"%PHPEXE%" artisan config:clear >nul 2>&1
"%PHPEXE%" artisan route:clear >nul 2>&1
"%PHPEXE%" artisan cache:clear >nul 2>&1

echo [5/8] Ajustando .env (debug para capturar erro)...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$f='%APP%\.env'; if(Test-Path $f){" ^
  " $c=[IO.File]::ReadAllText($f);" ^
  " $c=[regex]::Replace($c,'(?m)^APP_DEBUG=.*$','APP_DEBUG=true');" ^
  " $c=[regex]::Replace($c,'(?m)^LOG_LEVEL=.*$','LOG_LEVEL=debug');" ^
  " $c=[regex]::Replace($c,'(?m)^SESSION_SECURE_COOKIE=.*$','SESSION_SECURE_COOKIE=false');" ^
  " if($c -notmatch '(?m)^SESSION_SECURE_COOKIE='){ $c += \"`r`nSESSION_SECURE_COOKIE=false\" };" ^
  " [IO.File]::WriteAllText($f,$c,(New-Object Text.UTF8Encoding $false)) }"

echo [6/8] Migrate + config:cache...
"%PHPEXE%" artisan migrate --force
"%PHPEXE%" artisan config:cache >nul 2>&1

echo [7/8] Iniciando MySQL + servidor web...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "Set-Location '%APP%'; . '%APP%\scripts\unitec-install-lib.ps1';" ^
  "Ensure-UnitecStorageStructure -AppPath '%APP%';" ^
  "Start-UnitecStack -AppPath '%APP%' -WaitSeconds 30"

if errorlevel 1 (
  echo [ERRO] Falha ao iniciar servicos.
  echo Veja %LOG%
  pause
  exit /b 1
)

echo [8/8] Testando login...
timeout /t 2 /nobreak >nul
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "try {" ^
  "  $r=Invoke-WebRequest 'http://127.0.0.1:8765/admin/login' -UseBasicParsing -TimeoutSec 20;" ^
  "  Write-Host ('HTTP ' + [int]$r.StatusCode);" ^
  "  if($r.Content -match 'Server Error|Whoops|SQLSTATE|Exception'){" ^
  "    $r.Content | Out-File '%ERR%' -Encoding UTF8;" ^
  "    Write-Host 'AINDA COM ERRO - salvo em Desktop\unitec-erro.txt';" ^
  "    exit 2" ^
  "  }; exit 0" ^
  "} catch {" ^
  "  $code=0; if($_.Exception.Response){ $code=[int]$_.Exception.Response.StatusCode };" ^
  "  Write-Host ('HTTP ' + $code + ' - ' + $_.Exception.Message);" ^
  "  if(Test-Path '%LARALOG%'){ Get-Content '%LARALOG%' -Tail 80 | Out-File '%ERR%' -Encoding UTF8 }" ^
  "  else { $_.Exception.Message | Out-File '%ERR%' -Encoding UTF8 };" ^
  "  exit 2" ^
  "}"

if errorlevel 2 (
  echo.
  echo ========================================
  echo   AINDA COM ERRO 500
  echo ========================================
  echo Envie ao suporte o arquivo:
  echo   %ERR%
  if exist "%LARALOG%" echo E tambem: %LARALOG%
  echo.
  start "" notepad "%ERR%"
  pause
  exit /b 1
)

REM Volta debug off
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$f='%APP%\.env'; if(Test-Path $f){" ^
  " $c=[IO.File]::ReadAllText($f);" ^
  " $c=[regex]::Replace($c,'(?m)^APP_DEBUG=.*$','APP_DEBUG=false');" ^
  " $c=[regex]::Replace($c,'(?m)^LOG_LEVEL=.*$','LOG_LEVEL=warning');" ^
  " [IO.File]::WriteAllText($f,$c,(New-Object Text.UTF8Encoding $false)) }"
"%PHPEXE%" artisan config:cache >nul 2>&1

echo.
echo ========================================
echo   SISTEMA OK
echo ========================================
echo Abrindo o ERP...
start "" "http://127.0.0.1:8765/admin/login"
echo.
echo Se a tela ficar em branco, pressione Ctrl+F5.
echo.
pause
exit /b 0
