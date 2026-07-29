@echo off
chcp 65001 >nul
title Unitec — Cloudflare Tunnel (Gestor)
cd /d "%~dp0"

echo.
echo  ERP local deve estar rodando em http://127.0.0.1:8000
echo  (Desenvolver.bat / scripts\dev-windows.ps1)
echo.
echo  Iniciando Cloudflare Tunnel...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\cloudflare-tunnel-start.ps1"
if errorlevel 1 (
  echo.
  echo  Se ainda nao configurou o tunel, leia:
  echo    docs\GESTOR-CLOUDFLARE-TUNNEL.md
  echo.
  echo  Teste rapido sem dominio:
  echo    powershell -File scripts\cloudflare-tunnel-quick.ps1
  echo.
  pause
)
