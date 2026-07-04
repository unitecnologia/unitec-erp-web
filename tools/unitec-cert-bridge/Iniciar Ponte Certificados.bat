@echo off
setlocal
cd /d "%~dp0"
title Unitec Cert Bridge
echo.
echo Unitec Cert Bridge - lista certificados instalados no Windows
echo Mantenha esta janela aberta enquanto usar Config. Fiscais no navegador.
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0serve.ps1"
if errorlevel 1 (
    echo.
    echo Se apareceu erro de permissao, execute como Administrador:
    echo   netsh http add urlacl url=http://127.0.0.1:18765/ user=Everyone
    echo.
)
pause
