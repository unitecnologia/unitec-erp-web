@echo off
chcp 65001 >nul
cd /d "%~dp0\.."
powershell.exe -Sta -NoProfile -ExecutionPolicy Bypass -File "%~dp0\..\scripts\host-update-agent.ps1" -AppPath "%~dp0\.."
pause
