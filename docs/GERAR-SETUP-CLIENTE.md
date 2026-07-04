# Gerar Instalador Sistema Facil.exe (desenvolvedor)

Este guia explica como montar o **instalador completo** que o cliente recebe (MariaDB + PHP embutidos + sistema + atalhos).

## Requisitos na maquina de build

| Programa | Uso |
|----------|-----|
| PHP + Composer | `composer install --no-dev` |
| Node.js + npm | `npm run build` |
| **Inno Setup 6** | Compilar o `.exe` — https://jrsoftware.org/isdl.php |

## Passo a passo

1. Abra o terminal na pasta do projeto.
2. Duplo clique em um dos atalhos na raiz:

   | Arquivo | O que faz |
   |---------|-----------|
   | **`Gerar Staging Inno.bat`** | Só monta `dist\staging\` (rápido; compila o `.iss` manualmente no Inno) |
   | **`Gerar Instalador Inno.bat`** | Staging + compila **`Instalar Unitec ERP.exe`** automaticamente |
   | **`Gerar Pacote Atualizacao.bat`** | Gera **`Unitec-ERP-Update.zip`** para a nuvem |

   **ou** rode:

   ```powershell
   .\scripts\build-setup.ps1
   ```

3. Aguarde (primeira vez baixa MariaDB, PHP, VC++, cacert.pem).
4. O arquivo final fica em:

   **`dist\output\Instalar Unitec ERP.exe`**

Envie **somente esse `.exe`** ao cliente.

## O que o script faz

1. `composer install --no-dev` — gera `vendor/`
2. `npm run build` — gera `public/build/`
3. Copia o projeto para `dist/staging/` (sem `node_modules`, `.env`, etc.)
4. Baixa assets em `installer/assets/` se ainda nao existirem (MariaDB, PHP, VC++, cacert.pem)
5. Compila `Instalar Unitec ERP.exe` com Inno Setup

## Parametros uteis

```powershell
# So montar staging (sem compilar) — ou use Gerar Staging Inno.bat
.\scripts\build-setup.ps1 -SkipCompile

# Assets ja baixados manualmente
.\scripts\build-setup.ps1 -SkipRuntimeDownload

# vendor/build ja prontos
.\scripts\build-setup.ps1 -SkipComposer -SkipNpm
```

## Assets manual

Se algum download falhar, coloque os arquivos em `installer\assets\`.

Veja `installer\assets\README.md`.

## Tamanho estimado

| Componente | Tamanho aprox. |
|------------|----------------|
| MariaDB + PHP + assets | ~120 MB |
| vendor + app + build | ~80–150 MB |
| **Setup.exe final** | **~250–350 MB** |

## Instalacao no cliente

Documentacao para o cliente: **`docs\INSTALACAO-CLIENTE.md`**
