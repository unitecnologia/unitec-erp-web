@echo off
chcp 65001 >nul
setlocal EnableExtensions

REM ============================================================
REM  COPIAR PARA O CLIENTE e executar COMO ADMINISTRADOR
REM  Nao usa atualizar-sistema. So conserta PHP/VC++ e abre.
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0artisan" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "PHPDIR=%APP%\tools\php"
set "PHPEXE=%PHPDIR%\php.exe"
set "INI=%PHPDIR%\php.ini"
set "EXTDIR=%PHPDIR%\ext"
set "VC=%~dp0vc_redist.x64.exe"
if not exist "%VC%" set "VC=%APP%\tools\vc_redist.x64.exe"
if not exist "%VC%" set "VC=%APP%\installer\assets\vc_redist.x64.exe"
if not exist "%VC%" set "VC=%APP%\vc_redist.x64.exe"
if not exist "%VC%" set "VC=%TEMP%\vc_redist.x64.exe"

echo.
echo ========================================
echo   ABRIR UNITEC (reparo PHP + VC++)
echo ========================================
echo Pasta: %APP%
echo.

if not exist "%PHPEXE%" (
  echo [ERRO] Sem PHP em tools\php
  pause
  exit /b 1
)

if not exist "%EXTDIR%\php_pdo_mysql.dll" (
  echo [ERRO] Falta php_pdo_mysql.dll em tools\php\ext
  pause
  exit /b 1
)

echo [1] Ajustando php.ini...
if not exist "%INI%" if exist "%PHPDIR%\php.ini-development" copy /Y "%PHPDIR%\php.ini-development" "%INI%" >nul
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ini='%INI%'; $ext=('%EXTDIR%' -replace '\\','/'); $c=[IO.File]::ReadAllText($ini);" ^
  "@('curl','fileinfo','gd','intl','mbstring','mysqli','openssl','pdo_mysql','zip')|%%{ $e=$_; $c=[regex]::Replace($c,'(?m)^;\s*extension\s*=\s*'+$e+'\s*$','extension='+$e); if($c -notmatch ('(?m)^\s*extension\s*=\s*'+$e+'\s*$')){$c+=\"`r`nextension=$e\"} };" ^
  "$c=[regex]::Replace($c,'(?m)^\s*;?\s*extension_dir\s*=.*\r?\n?',''); $c=$c.TrimEnd()+\"`r`nextension_dir=`\"$ext`\"`r`n\";" ^
  "[IO.File]::WriteAllText($ini,$c,(New-Object Text.UTF8Encoding $false))"

echo [2] Testando pdo_mysql...
pushd "%PHPDIR%"
"%PHPEXE%" -m > "%TEMP%\u-php-m.txt" 2> "%TEMP%\u-php-m.err"
popd
findstr /I /X "pdo_mysql" "%TEMP%\u-php-m.txt" >nul
if errorlevel 1 (
  echo pdo_mysql FALHOU. Instalando Visual C++...
  if not exist "%VC%" (
    echo Baixando VC++...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12;" ^
      "Invoke-WebRequest 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile '%TEMP%\vc_redist.x64.exe' -UseBasicParsing"
    set "VC=%TEMP%\vc_redist.x64.exe"
  )
  if exist "%VC%" (
    echo Instalando %VC% ...
    "%VC%" /install /quiet /norestart
    timeout /t 8 /nobreak >nul
  )
  pushd "%PHPDIR%"
  "%PHPEXE%" -m > "%TEMP%\u-php-m.txt" 2> "%TEMP%\u-php-m.err"
  popd
)

findstr /I /X "pdo_mysql" "%TEMP%\u-php-m.txt" >nul
if errorlevel 1 (
  echo.
  echo ========================================
  echo  REINICIE O WINDOWS AGORA
  echo ========================================
  echo Depois rode este mesmo BAT de novo.
  echo.
  type "%TEMP%\u-php-m.err"
  echo.
  set /p R=Reiniciar agora? (S/N): 
  if /I "%R%"=="S" shutdown /r /t 3 /c "Unitec: ativar Visual C++"
  pause
  exit /b 1
)

echo [OK] pdo_mysql

echo [3] Limpando cache...
del /F /Q "%APP%\bootstrap\cache\*.php" >nul 2>&1
cd /d "%APP%"
"%PHPEXE%" artisan optimize:clear >nul 2>&1
"%PHPEXE%" artisan migrate --force
"%PHPEXE%" artisan config:cache >nul 2>&1

echo [4] Subindo servicos...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Set-Location '%APP%'; . '%APP%\scripts\unitec-install-lib.ps1';" ^
  "Ensure-UnitecStorageStructure -AppPath '%APP%';" ^
  "try { Start-UnitecStack -AppPath '%APP%' -WaitSeconds 30 } catch { Write-Host $_.Exception.Message; exit 1 }"

if errorlevel 1 (
  echo Falhou Start-UnitecStack - tentando serve direto...
  taskkill /F /IM php.exe >nul 2>&1
  start "" /B "%PHPEXE%" artisan serve --host=0.0.0.0 --port=8765
  timeout /t 3 /nobreak >nul
)

echo.
echo Abrindo navegador...
start "" "http://127.0.0.1:8765/admin/login"
echo.
echo Se ainda der 500: Ctrl+F5. Se der pdo_mysql de novo: reinicie o Windows.
pause
exit /b 0
