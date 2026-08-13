@echo off
chcp 65001 >nul
title Unitec — Instalar Cloudflare Tunnel
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\cloudflare-tunnel-install.ps1"
echo.
pause
