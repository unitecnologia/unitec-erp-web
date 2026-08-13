@echo off
chcp 65001 >nul
title Unitec — Túnel rápido Gestor (sem domínio)
cd /d "%~dp0"

echo.
echo  1) Deixe o ERP rodando (Desenvolver.bat)
echo  2) Este script cria uma URL publica temporaria
echo  3) Depois atualize APP_URL no .env com a URL mostrada
echo     e rode: tools\php\php.exe artisan config:clear
echo  4) No celular abra: URL/gestor/
echo.
echo  A URL muda a cada vez. Para uso diario, compre um dominio.
echo  Docs: docs\GESTOR-CLOUDFLARE-TUNNEL.md
echo.
pause

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\cloudflare-tunnel-quick.ps1"
pause
