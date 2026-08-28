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
| POST | `/api/scale/read` | Testa leitura serial de balança (porta COM) |

Bind **somente localhost**. Header opcional `X-Unitec-Key` (ative em `appsettings.json`:
`DeviceService:RequireApiKey` + `DeviceService:ApiKey`).

## Publicar EXE

```powershell
dotnet publish src\Unitec.DeviceService -c Release -r win-x64 --self-contained true -o dist
```

## Serviço Windows (recomendado)

Caminho oficial em produção: serviço `UnitecDeviceService` (start automático).
Use `scripts\install-device-service-startup.ps1` como administrador, ou:

```powershell
sc create "UnitecDeviceService" binPath= "C:\caminho\Unitec.DeviceService.exe" start= auto
sc start UnitecDeviceService
```

Não abra o EXE na mão (modo app). A API fica em `http://127.0.0.1:9330` sem ícone de tray.

## Integração com o ERP

O navegador do caixa (JS `erp-device-service.js`) chama `127.0.0.1:9330`.
Laravel gera o ESC/POS (mike42) e o JS envia Base64 ao Device Service.
Se o serviço estiver offline, o cupom NFC-e cai no fallback `window.print`.

### Teste de balança serial

`POST /api/scale/read` recebe a configuração exibida no Terminal:

```json
{
  "marca": "balUrano",
  "port": "COM3",
  "baudRate": 9600,
  "dataBits": 8,
  "parity": "None",
  "stopBits": "2",
  "handshake": "None"
}
```

O campo `marca` seleciona o parser:

| Marca | Protocolo | Serial típico |
|-------|-----------|---------------|
| `balUrano` (ou vazio) | Uran12 / Std01–05 | 9600 8N**2** (Uran12) |
| `balToledo` | **P05 / P05A** (PDV) | 9600 8N**1** |
| `balFilizola` | ENQ → STX+5 dígitos+ETX (caixa) | 4800 ou 9600 8N**1** |

**Urano:** solicita com ENQ (`0x05`) e tenta EOT (`0x04`) como fallback.
Std01–03 retornam kg; Std04/05 retornam gramas (convertidos para kg). Estados
instável / negativo / sobrecarga viram falha explicada.

**Toledo P05:** mesmo ENQ; resposta `STX` + 5 dígitos + `ETX`, onde os 5 dígitos
são 2 inteiros + 3 decimais (`01014` = 1,014 kg). P05A usa o mesmo frame e pode
enviar `IIIII` / `NNNNN` / `SSSSS` (instável / negativo / sobrecarga).

**Filizola (caixa):** mesmo ENQ e frame `STX` + 5 dígitos + `ETX` (`00423` = 0,423 kg).
Ajuste o baud no terminal conforme o modelo/emulador (comum 4800 ou 9600, 8N1).
Frames longos de peso+preço+total (CS/Pluris) ainda não são interpretados.

Em teste com com0com, configure o simulador em uma ponta do par (por exemplo,
`COM2`) e o ERP/Device Service na outra (`COM3`); habilite **Monitorar Requisição**
no simulador. A rota abre e fecha a COM a cada teste.
