@echo off
chcp 65001 >nul
cd /d "%~dp0\.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0\..\scripts\open-unitec-heidisql.ps1" -AppPath "%~dp0\.."
if errorlevel 1 pause
