@echo off
chcp 65001 >nul
setlocal EnableExtensions

set "APP=C:\UNITECNOLOGIA_WEB"
if exist "%~dp0artisan" if exist "%~dp0tools\php\php.exe" set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"

set "PHPEXE=%APP%\tools\php\php.exe"
set "SCRIPTDIR=%APP%\scripts"
set "SCRIPT=%SCRIPTDIR%\aplicar-migrations-pendentes.php"
set "LARALOG=%APP%\storage\logs\laravel.log"
set "STATUS=%TEMP%\unitec-migrate-status.txt"
set "B64FILE=%TEMP%\unitec-aplicar-mig.b64"

echo.
echo ========================================
echo   APLICAR MIGRATIONS
echo ========================================
echo Pasta: %APP%
echo.

if not exist "%PHPEXE%" (
  echo [ERRO] PHP nao encontrado: %PHPEXE%
  pause
  exit /b 1
)
if not exist "%APP%\artisan" (
  echo [ERRO] artisan nao encontrado em: %APP%
  pause
  exit /b 1
)

cd /d "%APP%"
if errorlevel 1 (
  echo [ERRO] Nao foi possivel entrar na pasta do ERP.
  pause
  exit /b 1
)

set "USE_ARTISAN="
"%PHPEXE%" artisan list --raw 2>nul | findstr /I /C:"unitec:migrate-tolerante" >nul
if not errorlevel 1 set "USE_ARTISAN=1"

if defined USE_ARTISAN (
  echo [1/3] migrate tolerante ^(artisan unitec:migrate-tolerante^)...
  echo Aguarde - pode demorar se houver muitas...
  echo.
  "%PHPEXE%" artisan unitec:migrate-tolerante
  if errorlevel 1 goto MIG_FAIL
) else (  if not exist "%SCRIPT%" (
    echo [INFO] Script ausente - recriando...
    if not exist "%SCRIPTDIR%" mkdir "%SCRIPTDIR%"
    del /F /Q "%B64FILE%" >nul 2>&1
    set "UNITEC_APP=%APP%"
    (
  echo PD9waHAKCi8qKgogKiBXcmFwcGVyIENMSSBkbyBtaWdyYXRlIHRvbGVyYW50ZSAoQXBsaWNhciBN
  echo aWdyYXRpb25zLmJhdCkuCiAqIFByZWZlcml2ZWwgYXBvcyB1cGRhdGU6IHBocCBhcnRpc2FuIHVu
  echo aXRlYzptaWdyYXRlLXRvbGVyYW50ZQogKi8KCmRlY2xhcmUoc3RyaWN0X3R5cGVzPTEpOwoKdXNl
  echo IEFwcFxTdXBwb3J0XEVycFxEYXRhYmFzZVxUb2xlcmFudE1pZ3JhdGVSdW5uZXI7Cgokcm9vdCA9
  echo IGRpcm5hbWUoX19ESVJfXyk7CnJlcXVpcmUgJHJvb3QuJy92ZW5kb3IvYXV0b2xvYWQucGhwJzsK
  echo JGFwcCA9IHJlcXVpcmUgJHJvb3QuJy9ib290c3RyYXAvYXBwLnBocCc7CiRhcHAtPm1ha2UoSWxs
  echo dW1pbmF0ZVxDb250cmFjdHNcQ29uc29sZVxLZXJuZWw6OmNsYXNzKS0+Ym9vdHN0cmFwKCk7Cgok
  echo cmVzdWx0ID0gJGFwcC0+bWFrZShUb2xlcmFudE1pZ3JhdGVSdW5uZXI6OmNsYXNzKS0+cnVuKCRy
  echo b290KTsKCmV4aXQoJHJlc3VsdFsnb2snXSA/IDAgOiAxKTsK
    ) > "%B64FILE%"
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "try { $raw = Get-Content -Raw -LiteralPath $env:TEMP\unitec-aplicar-mig.b64; $bytes = [Convert]::FromBase64String(($raw -replace '\s','')); $dest = Join-Path -Path $env:UNITEC_APP -ChildPath 'scripts\aplicar-migrations-pendentes.php'; New-Item -ItemType Directory -Force -Path (Split-Path -Parent $dest) | Out-Null; [IO.File]::WriteAllBytes($dest, $bytes); if (-not (Test-Path -LiteralPath $dest)) { throw 'arquivo nao criado' }; Write-Host ('bytes=' + $bytes.Length); exit 0 } catch { Write-Host $_; exit 1 }"
    if errorlevel 1 (
      echo [ERRO] Falha ao recriar o script.
      pause
      exit /b 1
    )
    del /F /Q "%B64FILE%" >nul 2>&1
    echo [OK] Script criado: %SCRIPT%
    echo.
  )
  echo [1/3] migrate tolerante ^(script^)...
  echo Aguarde - pode demorar se houver muitas...
  echo.
  "%PHPEXE%" "%SCRIPT%"
  if errorlevel 1 goto MIG_FAIL
)
goto MIG_OK

:MIG_FAIL
echo.
echo [ERRO] Migrate falhou. Veja a mensagem acima.
if exist "%LARALOG%" echo Log: %LARALOG%
pause
exit /b 1

:MIG_OK
echo.
echo [2/3] migrate:status  (conferencia)
"%PHPEXE%" artisan migrate:status > "%STATUS%" 2>&1
if errorlevel 1 (
  echo [AVISO] Nao listou o status.
  type "%STATUS%"
  pause
  exit /b 1
)
findstr /I /C:"Pending" "%STATUS%" >nul
if not errorlevel 1 (
  echo [ERRO] Ainda ha migration^(s^) Pending:
  findstr /I /C:"Pending" "%STATUS%"
  pause
  exit /b 1
)
echo [OK] Nenhuma migration Pending.
echo.
echo [3/3] Limpando cache do indicador BD...
"%PHPEXE%" artisan optimize:clear >nul 2>&1
"%PHPEXE%" artisan config:cache >nul 2>&1
echo.
echo ========================================
echo   MIGRATIONS OK
echo ========================================
echo Abra o ERP de novo - deve mostrar BD: Atualizado.
echo.
pause
exit /b 0