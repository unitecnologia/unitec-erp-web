megatools (LEGADO — nao obrigatorio)
====================================

A atualizacao automatica usa HTTPS (Dropbox ou URL direta no .env).
Nao e necessario incluir megadl.exe no instalador.

Este diretorio permanece apenas para referencia ou scripts antigos de suporte.

Atualizacao recomendada
-----------------------
  Ajuda > Atualizar Sistema (menu do ERP)

Configure no .env do cliente:
  UNITEC_UPDATE_DOWNLOAD_URL=https://...link-direto.../Unitec-ERP-Update.zip

Atualizacao manual (suporte)
----------------------------
  scripts\atualizar-sistema.ps1 -AppPath "C:\UNITECNOLOGIA_WEB" -LocalZip "C:\Temp\Unitec-ERP-Update.zip"

Ou copie dist\pacote-update\unitec-erp-web para C:\UNITECNOLOGIA_WEB
(preservando .env, storage\ e tools\).
