# Gerar Instalador Sistema Facil.exe

## Passo único (desenvolvedor)

Duplo clique em **`Gerar Instalador Inno.bat`**

Arquivo gerado:

**`dist\output\Instalar Unitec ERP.exe`**

Envie **somente este EXE** ao cliente.

Alternativa (ZIP em vez de EXE):

```powershell
.\scripts\criar-pacote-cliente.ps1 -SkipComposer -SkipNpm
```

Gera **`dist\Unitec-ERP-Instalador.zip`** (extrair + `Instalar Tudo.bat`).

---

## Requisitos na sua máquina (uma vez)

| Programa | Para quê |
|----------|----------|
| PHP + Composer | Gerar `vendor/` |
| Node.js | Gerar `public/build/` |
| **Inno Setup 6** | Compilar o `.exe` |

- Inno Setup: https://jrsoftware.org/isdl.php  

Na primeira execução o script baixa automaticamente para `installer\assets\`:

- **MariaDB 11.4** (~80 MB) — `mariadb-win.zip`
- **PHP 8.4** (~30 MB) — `php-8.4-win.zip`
- **Visual C++ Redistributable** — `vc_redist.x64.exe`
- **CA SSL** — `cacert.pem`

**Não usa Laragon.** O runtime fica em `C:\UNITECNOLOGIA_WEB\tools\` após a instalação.

---

## O que o cliente faz

1. Duplo clique em `Instalar Unitec ERP.exe` (ou extrair o ZIP e rodar `Instalar Tudo.bat`)
2. Avançar → Instalar
3. Usar atalho **Unitec ERP** na Área de Trabalho

---

Documentação completa: **`docs\GERAR-SETUP-CLIENTE.md`**
