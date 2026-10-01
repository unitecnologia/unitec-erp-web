# Pedido ao Portal do Contador (Unitec ERP)

**De:** Unitec ERP Web  
**Para:** time do Portal (`unitecnologiasc.com.br`)  
**Assunto:** Vínculo automático + melhorias de UI de documentos

---

## Texto para enviar (copiar)

> Precisamos de vínculo **automático** ERP → Portal:
>
> 1. Novo endpoint (ex. `POST /api/portal/vinculos/auto`) recebendo CNPJ da empresa + CNPJ do contador, autenticado com `Authorization: Bearer {ERP_AUTO_VINCULO_SECRET}` (segredo exclusivo; não reutilizar senha admin).
> 2. Se o contador existir e estiver ativo, retornar **já autorizado** com `token`, `empresaId` e `apiUrl` (mesmo formato das credenciais atuais).
> 3. A empresa deve aparecer **imediatamente** na carteira do contador; ele só busca pelo CNPJ do cliente — sem tela de “Autorizar solicitação” nesse fluxo.
> 4. Erro claro se o contador não estiver cadastrado no portal (`contador_nao_encontrado`).
> 5. Na listagem de documentos: usar `modelo` (55/65) para NFC-e vs NF-e e destacar contingência (`NF_CONTINGENCIA` / `contingencia`).
>
> O ERP Unitec já consome o vínculo atual e envia esses campos; falta o portal fechar o auto-vínculo e a UI.
>
> Spec completa: `docs/portal-contador-vinculo-api.md` (seção **Vínculo automático**).

---

## Experiência desejada

1. No ERP: vincula o contador (CNPJ do cadastro Contadores) e clica **Conectar**.
2. Portal recebe CNPJ da **empresa** + CNPJ do **contador** (com Bearer `ERP_AUTO_VINCULO_SECRET`).
3. Contador ativo no portal → **autoriza na hora** e devolve token.
4. Contador entra no portal, digita o CNPJ do cliente → **empresa já está na carteira**.

Sem código de autorização e sem clique em “Autorizar” nesse fluxo.

---

## Checklist

- [ ] `POST /api/portal/vinculos/auto`
- [ ] Auth Bearer com `ERP_AUTO_VINCULO_SECRET` (exclusivo; não senha admin)
- [ ] Resposta `201` com `status=authorized` + `credenciais` na hora
- [ ] Empresa na carteira do contador imediatamente
- [ ] Erro `contador_nao_encontrado` se CNPJ do contador não existir
- [ ] Manter `solicitar` + Autorizar como fallback
- [ ] UI: rotular NFC-e (`modelo=65`) vs NF-e (`modelo=55`)
- [ ] UI: badge Contingência (`NF_CONTINGENCIA` / `contingencia=true`)
