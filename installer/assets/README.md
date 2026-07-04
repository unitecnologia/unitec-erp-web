# Assets do instalador (nao versionados no Git)

Coloque aqui os arquivos usados pelo **Instalador Sistema Facil.exe** (modo offline na loja).

## MariaDB 11.4 (MySQL embutido)

Arquivo: **`mariadb-win.zip`**

O instalador extrai para `C:\UNITECNOLOGIA_WEB\tools\mysql\` (sem Laragon).

Download oficial:

https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip

Renomeie/copie para: `installer\assets\mariadb-win.zip`

## PHP 8.4 (obrigatorio para o Laravel)

Arquivo: **`php-8.4-win.zip`**

O instalador extrai para `C:\UNITECNOLOGIA_WEB\tools\php\`.

Download (Thread Safe x64):

https://windows.php.net/downloads/releases/archives/php-8.4.12-Win32-vs17-x64.zip

Renomeie/copie para: `installer\assets\php-8.4-win.zip`

## Visual C++ Redistributable (obrigatorio para PHP 8.4)

Arquivo: **`vc_redist.x64.exe`**

O instalador executa automaticamente se o PHP nao rodar por falta do VC++.

Download oficial:

https://aka.ms/vs/17/release/vc_redist.x64.exe

Salve como: `installer\assets\vc_redist.x64.exe`

## HeidiSQL (suporte — gerenciar banco MySQL)

Arquivo: **`HeidiSQL_12.18.0.7304_Setup.exe`** (ou outro `HeidiSQL_*_Setup.exe`)

Usado pelo instalador para instalar silenciosamente em `C:\UNITECNOLOGIA_WEB\tools\heidisql\` e criar atalho em `suporte\`.

Download oficial:

https://www.heidisql.com/download.php

Salve o instalador Setup (64 bits) em `installer\assets\HeidiSQL_12.18.0.7304_Setup.exe`

## CA bundle SSL (HTTPS — Atualizar Sistema e curl)

Arquivo: **`cacert.pem`**

Usado pelo instalador e pela atualizacao automatica para confiar em HTTPS (Dropbox, site Unitec, etc.) no PHP embarcado do Windows.

Download oficial:

https://curl.se/ca/cacert.pem

Salve como: `installer\assets\cacert.pem`

O instalador copia para `tools\php\extras\ssl\cacert.pem` e configura `curl.cainfo` / `openssl.cafile` no `php.ini`.

## Geracao automatica

Ao rodar **`.\scripts\build-setup.ps1`** ou **`.\scripts\criar-pacote-update.ps1`**, MariaDB, PHP, VC++ e **cacert.pem** sao baixados automaticamente se ainda nao existirem em `installer\assets\`. O HeidiSQL Setup deve ser baixado manualmente.
