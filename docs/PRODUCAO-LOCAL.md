# Unitec ERP — desenvolvimento local (Windows)

## Subir o ambiente

Duplo clique em **`Desenvolver.bat`** ou:

```powershell
cd C:\Projetos\unitec-erp-web
.\scripts\dev-windows.ps1
```

A janela fica aberta com o servidor rodando. **Ctrl+C** para parar.

Ou: `.\scripts\subir.ps1` (mesma coisa)

### O que acontece

1. Ajusta o `.env` para `127.0.0.1:3306` e `http://127.0.0.1:8000`
2. Na **primeira vez**, instala PHP 8.4 + MariaDB em `tools\` (~150 MB)
3. Sobe MySQL local e `php artisan serve` na porta **8000**
4. Abre http://127.0.0.1:8000/admin

**Login demo:** USUARIO / **01**

---

## Acesso

| Onde | URL |
|------|-----|
| Este PC | http://127.0.0.1:8000/admin |
| Rede local | http://SEU_IP:8000/admin |

---

## Banco de dados

| Item | Valor |
|------|-------|
| Host | 127.0.0.1 |
| Porta | 3306 |
| Banco | unitec_erp |
| Usuario | root |
| Senha | rua@2050bc |

Dados em: `tools\mysql\data\`

---

## Comandos uteis

```powershell
# Artisan com o PHP embutido do projeto
tools\php\php.exe artisan migrate
tools\php\php.exe artisan optimize:clear
tools\php\php.exe scripts\erp-route-smoke.php

# Ou, se PHP estiver no PATH:
php artisan migrate
```

## Primeira vez no browser

1. F12 → Application → **Clear site data** (remove Service Worker antigo)
2. Abra http://127.0.0.1:8000/admin
3. Faca login

## Firewall (outros PCs na loja)

PowerShell **como administrador**:

```powershell
netsh advfirewall firewall add rule name="Unitec ERP HTTP" dir=in action=allow protocol=TCP localport=8000 profile=private
```

## Linux (futuro)

Se precisar testar em um PC com Linux, instale PHP 8.4, Composer, Node, MySQL/MariaDB e siga o mesmo fluxo (`composer install`, `npm run build`, `php artisan migrate`, `php artisan serve`). Nao ha container Docker neste repositorio.
