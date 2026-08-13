@echo off
chcp 65001 >nul
setlocal EnableExtensions

REM ============================================================
REM  FINALIZAR apos update com erro pdo_mysql
REM  NAO use atualizar-sistema.ps1 de novo — use este BAT.
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0tools\php\php.exe" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

echo.
echo ========================================
echo   Finalizar update (corrigir PHP)
echo ========================================
echo Pasta: %APP%
echo.
echo NAO rode atualizar-sistema de novo.
echo Este BAT so corrige o PHP e termina a instalacao.
echo.

set "PHPDIR=%APP%\tools\php"
set "PHPEXE=%PHPDIR%\php.exe"
set "INI=%PHPDIR%\php.ini"
set "EXTDIR=%PHPDIR%\ext"
set "DLL=%EXTDIR%\php_pdo_mysql.dll"
set "LOG=%APP%\instalacao.log"

if not exist "%PHPEXE%" (
    echo [ERRO] Falta: %PHPEXE%
    echo Reinstale o Unitec ERP.
    pause
    exit /b 1
)

if not exist "%DLL%" (
    echo [ERRO] Falta: %DLL%
    echo A pasta tools\php\ext esta incompleta.
    pause
    exit /b 1
)

if not exist "%INI%" (
    if exist "%PHPDIR%\php.ini-development" copy /Y "%PHPDIR%\php.ini-development" "%INI%" >nul
    if exist "%PHPDIR%\php.ini-production" if not exist "%INI%" copy /Y "%PHPDIR%\php.ini-production" "%INI%" >nul
)

echo [1/5] Gravando php.ini (extension_dir absoluto)...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ini='%INI%'; $extDir=('%EXTDIR%' -replace '\\','/');" ^
  "$c=[IO.File]::ReadAllText($ini);" ^
  "@('curl','fileinfo','gd','intl','mbstring','mysqli','openssl','pdo_mysql','zip') | ForEach-Object {" ^
  "  $e=$_; $c=[regex]::Replace($c,'(?m)^;\s*extension\s*=\s*'+$e+'\s*$','extension='+$e);" ^
  "  if($c -notmatch ('(?m)^\s*extension\s*=\s*'+$e+'\s*$')){ $c += \"`r`nextension=$e\" }" ^
  "};" ^
  "$c=[regex]::Replace($c,'(?m)^\s*;?\s*extension_dir\s*=.*\r?\n?','');" ^
  "$c=$c.TrimEnd()+\"`r`nextension_dir=`\"$extDir`\"`r`n\";" ^
  "[IO.File]::WriteAllText($ini,$c,(New-Object Text.UTF8Encoding $false));" ^
  "Write-Host 'php.ini gravado'"

echo [2/5] Testando pdo_mysql (cwd = tools\php)...
pushd "%PHPDIR%"
"%PHPEXE%" -m > "%TEMP%\unitec-php-m.txt" 2> "%TEMP%\unitec-php-m.err"
popd
findstr /I /X "pdo_mysql" "%TEMP%\unitec-php-m.txt" >nul
if errorlevel 1 (
    echo.
    echo pdo_mysql nao carregou. Tentando Visual C++...
    set "VC=%APP%\installer\assets\vc_redist.x64.exe"
    if not exist "%VC%" set "VC=%APP%\tools\vc_redist.x64.exe"
    if exist "%VC%" (
        "%VC%" /install /quiet /norestart
        timeout /t 4 /nobreak >nul
        pushd "%PHPDIR%"
        "%PHPEXE%" -m > "%TEMP%\unitec-php-m.txt" 2> "%TEMP%\unitec-php-m.err"
        popd
    )
)

findstr /I /X "pdo_mysql" "%TEMP%\unitec-php-m.txt" >nul
if errorlevel 1 (
    echo.
    echo [ERRO] pdo_mysql continua inativo.
    echo.
    echo php --ini:
    "%PHPEXE%" --ini
    echo.
    echo Avisos:
    type "%TEMP%\unitec-php-m.err"
    echo.
    echo Instale e reinicie o Windows:
    echo   https://aka.ms/vs/17/release/vc_redist.x64.exe
    echo.
    echo Depois rode este BAT de novo.
    echo %date% %time% ERRO pdo_mysql apos update >> "%LOG%"
    pause
    exit /b 1
)

echo [OK] pdo_mysql ativo.
echo %date% %time% pdo_mysql OK - finalizando update >> "%LOG%"

echo [3/5] Migrate...
cd /d "%APP%"
"%PHPEXE%" artisan migrate --force
if errorlevel 1 (
    echo [AVISO] migrate retornou erro - veja acima.
)

echo [4/5] Cache...
"%PHPEXE%" artisan view:clear
"%PHPEXE%" artisan config:clear
"%PHPEXE%" artisan config:cache

echo [5/5] Subindo MySQL + servidor...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "Set-Location '%APP%';" ^
  ". '%APP%\scripts\unitec-install-lib.ps1';" ^
  "Ensure-UnitecStorageStructure -AppPath '%APP%';" ^
  "try { Start-UnitecStack -AppPath '%APP%' -WaitSeconds 25; Write-Host '[OK] Servicos ativos' } catch { Write-Host $_.Exception.Message; exit 1 }"

if errorlevel 1 (
    echo.
    echo Tentando abrir pelo Unitec ERP.exe...
    if exist "%APP%\bin\Unitec ERP.exe" (
        start "" "%APP%\bin\Unitec ERP.exe" --app "%APP%"
    ) else (
        powershell.exe -WindowStyle Hidden -NoProfile -ExecutionPolicy Bypass -File "%APP%\scripts\open-unitec-app.ps1" -LeigoMode -AppMode -RelativePath "/admin/login" -AppPath "%APP%"
    )
)

echo.
echo ========================================
echo   PRONTO - update finalizado
echo ========================================
echo Abra o ERP e confira a versao no login.
echo %date% %time% Update finalizado via Corrigir PHP Cliente >> "%LOG%"
echo.
pause
exit /b 0
