# Gestor (Unitec Executivo) — Cloudflare Tunnel

Acesso ao **Gestor** (`/gestor`) de qualquer lugar (4G/casa), com o ERP
permanecendo no **servidor local** da loja. Sem abrir porta no roteador.

```
Celular (4G)
    → https://gestor.seudominio.com.br/gestor/
    → Cloudflare
    → Túnel (cloudflared no PC da loja)
    → http://127.0.0.1:8000  (ERP local)
```

---

## Pré-requisitos

1. ERP rodando no PC/servidor da loja (`http://127.0.0.1:8000`)
2. Conta [Cloudflare](https://dash.cloudflare.com/) (gratuita)
3. Um domínio na Cloudflare (ex.: `seuloja.com.br`) — **recomendado** para uso diário
4. Windows com PowerShell

---

## Passo a passo (domínio fixo — produção)

### 1. Instalar o cloudflared

```powershell
cd C:\Projetos\unitec-erp-web
.\scripts\cloudflare-tunnel-install.ps1
```

### 2. Login na Cloudflare

```powershell
.\tools\cloudflared\cloudflared.exe tunnel login
```

Abre o navegador → escolha o domínio → autorize.

### 3. Criar o túnel

```powershell
.\tools\cloudflared\cloudflared.exe tunnel create unitec-gestor
```

Anote o **TUNNEL_ID** (UUID) e o caminho do JSON em `%USERPROFILE%\.cloudflared\`.

### 4. DNS do Gestor

```powershell
.\tools\cloudflared\cloudflared.exe tunnel route dns unitec-gestor gestor.seudominio.com.br
```

Troque `gestor.seudominio.com.br` pelo hostname desejado (subdomínio do domínio na Cloudflare).

### 5. Arquivo de configuração

```powershell
copy config\cloudflared\config.example.yml $env:USERPROFILE\.cloudflared\config.yml
notepad $env:USERPROFILE\.cloudflared\config.yml
```

Ajuste:

- `tunnel:` → TUNNEL_ID
- `credentials-file:` → caminho do `.json` gerado
- `hostname:` → o mesmo do DNS (ex. `gestor.seuloja.com.br`)
- `service:` → `http://127.0.0.1:8000` (porta do ERP)

### 6. `.env` do ERP

```env
APP_URL=https://gestor.seudominio.com.br
SESSION_SECURE_COOKIE=true
```

Depois:

```powershell
.\tools\php\php.exe artisan config:clear
```

> Em dias de trabalho só na loja (sem túnel), volte `APP_URL=http://127.0.0.1:8000` e
> `SESSION_SECURE_COOKIE=false` — ou use um `.env` separado se preferir.

### 7. Subir ERP + túnel

Terminal 1 — ERP:

```powershell
.\scripts\dev-windows.ps1
```

Terminal 2 — túnel:

```powershell
.\scripts\cloudflare-tunnel-start.ps1
```

Ou dê duplo clique em **`Gestor Cloudflare Tunnel.bat`**.

### 8. No celular

Abra:

```
https://gestor.seudominio.com.br/gestor/
```

Login com usuário liberado para o Executivo (admin/supervisor ou permissões do Gestor).

---

## Teste rápido (sem domínio)

URL aleatória (`*.trycloudflare.com`), muda a cada execução:

```powershell
.\scripts\cloudflare-tunnel-install.ps1
.\scripts\cloudflare-tunnel-quick.ps1
```

1. Copie a URL `https://….trycloudflare.com` que o cloudflared mostrar  
2. Coloque no `.env`: `APP_URL=https://….trycloudflare.com` + `SESSION_SECURE_COOKIE=true`  
3. `php artisan config:clear`  
4. Abra `https://….trycloudflare.com/gestor/` no celular  

Serve para validar. Para uso diário, use o **túnel nomeado** com domínio.

---

## Push (notificações)

O Web Push do Gestor exige **HTTPS** (o túnel já entrega). Confira VAPID no `.env`:

```env
VAPID_SUBJECT=mailto:seu@email.com
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

No app: **Mais** → ativar notificações. O agendador `gestor:push-alertas` precisa estar rodando no PC da loja.

---

## Segurança

- O túnel expõe o **ERP inteiro** nesse hostname (não só `/gestor`). Use senhas fortes.
- Não publique MySQL na internet.
- Ideal: usuário dedicado do Gestor (sem acesso desnecessário ao admin).
- O PC da loja precisa ficar ligado e com internet para o celular funcionar.

---

## Problemas comuns

| Sintoma | O que checar |
|---------|----------------|
| Login não grava / volta pro login | `APP_URL` diferente da URL do navegador; `SESSION_SECURE_COOKIE=true` com HTTPS |
| Página “não segura” / push falha | Sem HTTPS ou APP_URL em `http://` |
| Túnel sobe mas dá 502 | ERP não está em `127.0.0.1:8000` |
| DNS não resolve | Domínio precisa estar na Cloudflare (nameservers ativos); espere propagação |

---

## Arquivos do projeto

| Arquivo | Função |
|---------|--------|
| `scripts/cloudflare-tunnel-install.ps1` | Baixa o `cloudflared` |
| `scripts/cloudflare-tunnel-quick.ps1` | Teste sem domínio |
| `scripts/cloudflare-tunnel-start.ps1` | Túnel nomeado (produção) |
| `config/cloudflared/config.example.yml` | Modelo do `config.yml` |
| `Gestor Cloudflare Tunnel.bat` | Atalho para iniciar o túnel |
| `bootstrap/app.php` | `trustProxies` (HTTPS atrás do Cloudflare) |
