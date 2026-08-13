# Unitecnologia Device Service

Serviço local Windows (porta **9330**) para o ERP Unitec falar com dispositivos do caixa:
impressoras RAW/ESC-POS, gaveta, e no futuro etiquetas, PDF, balança, pinpad, etc.

## Requisitos

- Windows 10/11
- [.NET 8 SDK](https://dotnet.microsoft.com/download/dotnet/8.0) (para build)
- Runtime .NET 8 Desktop (ao publicar self-contained, o EXE já embute)

## Rodar em desenvolvimento

```powershell
cd services\unitec-device-service
dotnet restore
dotnet run --project src\Unitec.DeviceService
```

API em `http://127.0.0.1:9330`.

### Endpoints (MVP)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/status` | Status do serviço |
| GET | `/api/printers` | Impressoras Windows instaladas |
| POST | `/api/print/raw` | `{ "printer": "POS-80C", "data": "<base64>", "copies": 1 }` |
| POST | `/api/open-drawer` | `{ "printer": "POS-80C" }` |
| POST | `/api/print/pdf` | Stub (501) — fase 2 |

Bind **somente localhost**. Header opcional `X-Unitec-Key` (ative em `appsettings.json`:
`DeviceService:RequireApiKey` + `DeviceService:ApiKey`).

## Publicar EXE

```powershell
dotnet publish src\Unitec.DeviceService -c Release -r win-x64 --self-contained true -o dist
```

## Serviço Windows (opcional)

```powershell
sc create "UnitecDeviceService" binPath= "C:\caminho\Unitec.DeviceService.exe" start= auto
sc start UnitecDeviceService
```

Quando rodando como serviço sem sessão interativa, o ícone da bandeja pode não aparecer —
a API continua ativa.

## Integração com o ERP

O navegador do caixa (JS `erp-device-service.js`) chama `127.0.0.1:9330`.
Laravel gera o ESC/POS (mike42) e o JS envia Base64 ao Device Service.
Se o serviço estiver offline, o cupom NFC-e cai no fallback `window.print`.
