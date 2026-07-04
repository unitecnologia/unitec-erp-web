@echo off
chcp 65001 >nul
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\open-unitec-app.ps1" -RelativePath "/admin"
if errorlevel 1 pause
