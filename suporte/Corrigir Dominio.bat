@echo off
chcp 65001 >nul
cd /d "%~dp0\.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0\..\scripts\open-unitec-app.ps1" -RelativePath "/admin" -SkipBrowser
if errorlevel 1 pause
