@echo off
setlocal EnableExtensions
chcp 65001 >nul 2>&1
title Unitec ERP - Liberar porta 8765 (rede)

:: Eleva para Administrador (obrigatorio para alterar o Firewall)
net session >nul 2>&1
if errorlevel 1 (
    echo Solicitando permissao de Administrador...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

set "RULE_NAME=Unitec ERP (porta 8765)"
set "PORT=8765"

echo.
echo ========================================
echo  Unitec ERP - Liberar porta %PORT%
echo  Rode este arquivo no PC SERVIDOR
echo ========================================
echo.

echo [1/3] Removendo regra antiga (se existir)...
netsh advfirewall firewall delete rule name="%RULE_NAME%" >nul 2>&1

echo [2/3] Criando regra de entrada TCP %PORT%...
netsh advfirewall firewall add rule name="%RULE_NAME%" dir=in action=allow protocol=TCP localport=%PORT% profile=any enable=yes description="Permite acesso do ERP pelas estacoes e pelo app Forca de Vendas na rede local."
if errorlevel 1 (
    echo.
    echo [ERRO] Nao foi possivel liberar a porta no Firewall.
    echo        Confirme que este .bat foi executado como Administrador.
    echo.
    pause
    exit /b 1
)

echo [OK] Firewall: porta %PORT% liberada para a rede local.
echo.

echo [3/3] Verificando se o ERP esta escutando na porta %PORT%...
netstat -ano | findstr ":%PORT% " | findstr /I "LISTENING" >nul 2>&1
if errorlevel 1 (
    echo [AVISO] Nada em LISTENING na porta %PORT%.
    echo         O firewall foi liberado, mas o servico do ERP pode estar parado.
    echo         No servidor, abra http://127.0.0.1:%PORT% e confira o UnitecErpServer.
) else (
    echo [OK] Porta %PORT% em LISTENING neste PC.
    netstat -ano | findstr ":%PORT% " | findstr /I "LISTENING"
)

echo.
echo IP(s) desta maquina (use nas outras estacoes):
powershell -NoProfile -Command ^
  "Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -ExpandProperty IPAddress"

echo.
echo Nas outras maquinas abra:
echo   http://SEU_IP:%PORT%/admin/login
echo Exemplo:
echo   http://192.168.0.120:%PORT%/admin/login
echo.
echo ========================================
pause
endlocal
