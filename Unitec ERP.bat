@echo off
chcp 65001 >nul
cd /d "%~dp0"
powershell.exe -WindowStyle Hidden -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\open-unitec-app.ps1" -LeigoMode -RelativePath "/admin"
if errorlevel 1 exit /b 1
exit /b 0
