@echo off
chcp 65001 >nul
setlocal EnableExtensions EnableDelayedExpansion

REM ============================================================
REM  REPARAR SISTEMA Unitec ERP - HTTP 500 (Pail) / views code:5
REM  Independente para instalacao 6.4.1.267 (e posteriores).
REM
REM  Leve junto para o cliente (mesma pasta):
REM    - Reparar Sistema.bat
REM    - Reparar-Sistema-HTTP500.ps1
REM
REM  Seguro:
REM   - so encerra processos DESTA pasta Unitec (nao outros PHP)
REM   - nao altera banco / nao roda migrate
REM   - nao apaga caches indiscriminadamente
REM   - artisan com cwd correto (cd na pasta do ERP)
REM ============================================================

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0artisan" if exist "%~dp0tools\php\php.exe" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "PHPDIR=%APP%\tools\php"
set "PHPEXE=%PHPDIR%\php.exe"
set "ERR=%USERPROFILE%\Desktop\unitec-erro.txt"
set "LARALOG=%APP%\storage\logs\laravel.log"
set "PS1=%~dp0Reparar-Sistema-HTTP500.ps1"

echo.
echo ========================================
echo   REPARAR SISTEMA - HTTP 500 / views
echo ========================================
echo Pasta: %APP%
echo.
echo Este reparo NAO altera banco de dados.
echo Aguarde - nao feche esta janela.
echo.

if not exist "%PS1%" (
  echo [ERRO] Falta o arquivo ao lado deste .bat:
  echo   Reparar-Sistema-HTTP500.ps1
  echo.
  echo Copie os DOIS arquivos para a pasta do ERP ou para um pendrive
  echo e execute novamente.
  pause
  exit /b 1
)

if not exist "%APP%\artisan" (
  echo [ERRO] artisan nao encontrado em: %APP%
  echo Coloque este .bat dentro de C:\UNITECNOLOGIA_WEB ou informe a pasta correta.
  pause
  exit /b 1
)

if not exist "%PHPEXE%" (
  echo [ERRO] PHP nao encontrado: %PHPEXE%
  echo Reinstale o Unitec ERP.
  pause
  exit /b 1
)

echo [1/6] Encerrando somente processos desta instalacao Unitec...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -AppPath "%APP%" -Phase stop
if errorlevel 1 (
  echo [AVISO] Falha parcial ao encerrar processos - continuando.
)
timeout /t 2 /nobreak >nul

echo [2/6] Recriando pastas e rotacionando views (code:5)...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -AppPath "%APP%" -Phase views
if errorlevel 1 (
  echo [ERRO] Falha ao preparar storage/views.
  pause
  exit /b 1
)

echo [3/6] Removendo residuo Pail e manifests contaminados...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -AppPath "%APP%" -Phase pail
if errorlevel 1 (
  echo [ERRO] Falha ao limpar Pail/manifests.
  pause
  exit /b 1
)

echo [4/6] Regenerando descoberta de pacotes (cwd = pasta do ERP)...
cd /d "%APP%"
if errorlevel 1 (
  echo [ERRO] Nao foi possivel entrar em: %APP%
  pause
  exit /b 1
)
echo cwd=%CD%
"%PHPEXE%" artisan package:discover
if errorlevel 1 (
  echo [ERRO] package:discover falhou.
  if exist "%LARALOG%" echo Veja: %LARALOG%
  pause
  exit /b 1
)

REM So packages/services. Nao roda optimize:clear / cache:clear em massa.
findstr /I /C:"PailServiceProvider" /C:"laravel/pail" "%APP%\bootstrap\cache\packages.php" >nul 2>&1
if not errorlevel 1 (
  echo [AVISO] packages.php ainda cita Pail - nova limpeza...
  if exist "%APP%\vendor\laravel\pail" rmdir /S /Q "%APP%\vendor\laravel\pail" >nul 2>&1
  del /F /Q "%APP%\bootstrap\cache\packages.php" "%APP%\bootstrap\cache\services.php" >nul 2>&1
  cd /d "%APP%"
  "%PHPEXE%" artisan package:discover
)
findstr /I /C:"PailServiceProvider" /C:"laravel/pail" "%APP%\bootstrap\cache\packages.php" >nul 2>&1
if not errorlevel 1 (
  echo [ERRO] packages.php continua com Pail apos rediscover.
  echo Envie ao suporte: %APP%\bootstrap\cache\packages.php
  pause
  exit /b 1
)
if exist "%APP%\bootstrap\cache\services.php" (
  findstr /I /C:"PailServiceProvider" "%APP%\bootstrap\cache\services.php" >nul 2>&1
  if not errorlevel 1 (
    echo [AVISO] services.php ainda cita Pail - removendo para rebuild no boot...
    del /F /Q "%APP%\bootstrap\cache\services.php" >nul 2>&1
  )
)
echo [OK] Manifesto sem Pail.

echo [5/6] Reiniciando servicos Unitec (MySQL + HTTP)...
if exist "%APP%\scripts\unitec-install-lib.ps1" (
  powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "Set-Location -LiteralPath '%APP%';" ^
    " . '%APP%\scripts\unitec-install-lib.ps1';" ^
    " Ensure-UnitecStorageStructure -AppPath '%APP%';" ^
    " Start-UnitecStack -AppPath '%APP%' -WaitSeconds 30"
  if errorlevel 1 (
    echo [ERRO] Falha ao iniciar servicos via unitec-install-lib.
    echo Tente abrir pelo atalho Unitec ERP.bat e rode este reparo de novo.
    pause
    exit /b 1
  )
) else if exist "%APP%\Unitec ERP.bat" (
  echo Biblioteca de instalacao ausente - abrindo Unitec ERP.bat...
  start "" "%APP%\Unitec ERP.bat"
  timeout /t 15 /nobreak >nul
) else (
  echo [AVISO] Nao achei scripts\unitec-install-lib.ps1 nem Unitec ERP.bat.
  echo Inicie o ERP manualmente e pressione uma tecla para testar.
  pause
)

echo [6/6] Testando saude HTTP...
timeout /t 2 /nobreak >nul
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -AppPath "%APP%" -Phase health
set "HC=%ERRORLEVEL%"

if "%HC%"=="0" (
  echo.
  echo ========================================
  echo   SISTEMA OK
  echo ========================================
  for /f "usebackq delims=" %%P in (`powershell.exe -NoProfile -Command "$p=8765; $f=Join-Path '%APP%' '.env'; if(Test-Path -LiteralPath $f){ $t=[IO.File]::ReadAllText($f); if($t -match '(?m)^APP_URL\s*=\s*.*:(\d+)'){ $p=[int]$Matches[1] } }; Write-Output $p"`) do set "PORT=%%P"
  if not defined PORT set "PORT=8765"
  start "" "http://127.0.0.1:!PORT!/admin/login"
  echo.
  echo Se a tela ficar em branco, pressione Ctrl+F5.
  echo.
  pause
  exit /b 0
)

echo.
echo ========================================
echo   AINDA COM ERRO
echo ========================================
if exist "%LARALOG%" (
  powershell.exe -NoProfile -Command "Get-Content -LiteralPath '%LARALOG%' -Tail 80 | Out-File -FilePath '%ERR%' -Encoding utf8"
  echo Log salvo em: %ERR%
  start "" notepad "%ERR%"
) else (
  echo Nao achei laravel.log. Teste manual: http://127.0.0.1:8765/admin/login
)
echo.
pause
exit /b 1
