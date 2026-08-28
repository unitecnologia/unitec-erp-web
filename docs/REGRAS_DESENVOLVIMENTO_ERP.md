# Regras de Desenvolvimento — Unitec ERP Web

**Documento oficial.** Referência obrigatória para qualquer alteração futura feita por desenvolvedores ou por IA.

| Campo | Valor |
|-------|--------|
| Produto | UNI SISTEMAS 3.0 / Unitec ERP Web |
| Escopo | Repositório `unitec-erp-web` — **esta máquina é só DEV** (porta **8000**) |
| Cliente | Porta **8765**, pasta **`C:\UNITECNOLOGIA_WEB` no PC do cliente** (padrão travado; não alterar sem pedido explícito). Nesta máquina de DEV essa pasta não existe. |
| Versão do app | Definida em `config/unitec.php` (`versao`) |

---

# 1. Visão geral do sistema

## 1.1 Stack principal

- **PHP ^8.3** + **Laravel ^13**
- **Filament ^4** + Livewire + Blade (UI administrativa)
- **Vite 8** + Tailwind 4 (assets)
- **Sanctum** (APIs autenticadas)
- Banco típico de produção: **MySQL/MariaDB** (SQLite pode existir em ambientes locais; Firebird só para **migração** de legado)

## 1.2 Painéis Filament

| Painel | Path | Função |
|--------|------|--------|
| Admin (ERP) | `/admin` | Operação completa; shell customizado (sem navegação Filament padrão) |
| Gestor | `/gestor` | Unitec Executivo (visão gerencial) |

## 1.3 Módulos principais (ERP)

Organizados pelo menu (`app/Support/Erp/ErpMenu.php`), entre outros:

- Acesso / usuários / permissões
- Pessoas
- Estoque (produtos, grupos, marcas, unidades, ajustes, etiquetas)
- Compras / notas de fornecedor / devoluções
- Vendas / orçamentos / PDV / devoluções
- Logística / expedição
- Financeiro (caixa, contas a pagar/receber, recibos, boletos)
- Fiscal (NF-e, NFC-e, CFOP, ICMS, config fiscais)
- Ordens de serviço
- Força de Vendas
- Vendas Internas
- RH
- Relatórios / Ajuda (licença, update, backup)

A lógica de negócio fica preferencialmente em `app/Support/*`. Telas Filament em `app/Filament/*`. Persistência em `app/Models/*`.

## 1.4 Pacotes internos (Composer path)

| Pacote | Caminho | Papel |
|--------|---------|--------|
| `unitec/fiscal-engine` | `packages/unitec-fiscal-engine` | Emissão/consulta NF-e e NFC-e, XML, certificado |
| `unitec/pdv-ui` | `packages/unitec-pdv-ui` | Defaults/UI do PDV |

Tratar esses pacotes como **contratos internos**: mudanças exigem o mesmo rigor do restante do ERP e testes do pacote quando existirem.

## 1.5 APIs (`routes/api.php`)

Prefixo típico: `/api/...`

| Prefixo | Consumidor |
|---------|------------|
| `api/v1/forca-vendas` | App Flutter Força de Vendas |
| `api/v1/vendas-internas` | App Flutter Vendas Internas |
| `api/v1/pdv` | Mini-PDV / carga e retorno offline |
| Webhooks | Mercado Livre, Mercado Pago |
| Hub Meli | Pairing OAuth para clientes sem domínio próprio |

## 1.6 Serviços auxiliares

| Serviço | Pasta / porta | Papel |
|---------|---------------|--------|
| Unitec Device Service | `services/unitec-device-service` (localhost **9330**) | Impressão ESC/POS / gaveta no PC do caixa |
| WhatsApp gateway | `services/erp-whatsapp-gateway` | Integração auxiliar Node |
| Scripts ops | `scripts/*.ps1` + `.bat` na raiz | Dev, update, tunnel, reparo, instalador |
| Apps móveis | Repos separados (ex.: `unitec-forca-vendas`); cópias locais em `apps/` são gitignored | Não misturar commit do ERP com o app sem pedido explícito |

## 1.7 UI

- Visual **moderno web** (Filament / CSS do projeto).
- Legado Delphi/Firebird é referência de **negócio**, nunca de estética.

---

# 2. Regras obrigatórias antes de alterar código

1. **Sempre analisar antes** — ler estrutura, fluxo e causa raiz; não “adivinhar” tabelas, rotas, campos ou funções.
2. **Nunca alterar banco** (schema, dados de produção, seeds destrutivos) **sem autorização explícita**.
3. **Nunca criar migration** sem **explicar o impacto** em clientes que já têm banco antigo.
4. **Nunca remover** arquivo, classe, rota ou coluna **sem comprovar** que está sem uso (busca no código + contexto de runtime).
5. **Sempre informar** quais **arquivos serão / foram alterados**.
6. **Sempre informar** o **motivo**, o **risco** e os **possíveis impactos**.
7. **Não inventar** funções, tabelas, rotas ou campos que não existem.
8. Se faltar informação, **perguntar** antes de programar.
9. Preferir **alterações pequenas e seguras**; preservar a arquitetura existente.
10. **Não publicar** pacote de update no GitHub sem pedido explícito; o ZIP do cliente deve sair **limpo** (sem sujeira de DEV).
11. Em commits Git do dia a dia: seguir a política do time (em geral, push/commit do app Android é repo separado; ERP só quando pedido explicitamente).

---

# 3. Componentes que NÃO podem ser quebrados

Qualquer mudança que toque estes eixos exige plano explícito, autorização e checklist de regressão.

## Fiscal

- **Pacote:** `packages/unitec-fiscal-engine`
- **Também sensível:** fluxos NF-e/NFC-e em `app/Support/Erp/Nfe`, `Nfce`, Resources Filament correspondentes, certificados e endpoints SEFAZ.
- **Regra:** não alterar builders/assinatura/XML “de passagem”; mudanças fiscais são de alto risco legal e operacional.

## PDV

- **API:** `api/v1/pdv` (carga, certificado, retorno)
- **Também sensível:** `app/Support/Pdv/*`, terminal ativo, espelho de venda na retaguarda, estoque/financeiro do PDV.
- **Regra:** não mudar contrato de payload sem versão nova e plano de migração dos caixas.

## Aplicativos

- **API:** `api/v1/forca-vendas`
- **API:** `api/v1/vendas-internas`
- **Regra:** aparelhos em campo dependem do contrato atual (device + Sanctum). Quebra = app parado na rua.

## Atualização

- **Canal:** GitHub Releases tag/canal `update`
- **Artefato:** `Unitec-ERP-Update.zip` (+ `.sha256`)
- **Scripts:** `scripts/criar-pacote-update.ps1`, `scripts/publicar-update-github.ps1`
- **Regra:** um ZIP FULL; não reativar dual-upload/delta sem decisão formal.

## Impressão

- **Caminho atual:** Unitec **Device Service** (`config/unitec.device_service`, JS `erp-device-service.js`)
- **Legado:** QZ Tray ainda pode existir em rotas/config — não remover sem inventário de clientes.
- **Regra:** não substituir o fluxo Device Service sem plano e teste em terminal real.

## Interface

- **Filament shell customizado** (menu `ErpMenu`, hooks em `AdminPanelProvider` / `GestorPanelProvider`, assets em `resources/views/filament/components/erp/*`, CSS tokens)
- **Regra:** não “voltar” para navegação Filament padrão nem copiar estética Delphi.

---

# 4. Regras de alteração

## Antes

- Explicar o **problema** (sintoma + causa raiz quando conhecida).
- Listar **arquivos envolvidos**.
- Mostrar **plano** (o que muda / o que não muda).
- Aguardar **autorização** quando a mudança for sensível (banco, API v1, update, fiscal, Enter em grades, ACL ampla).

## Durante

- Fazer a **alteração mínima** que resolve o problema.
- **Não** refatorar “já que estamos aqui”.
- **Não** alterar funcionalidades fora do escopo.
- **Não** criar arquivos novos sem autorização (salvo quando o pedido for explicitamente criar um artefato, como este documento).
- Evitar PowerShell `Set-Content` em PHP/Blade com acentos (corrompe UTF-8).

## Depois

- Informar **testes realizados** (telas, usuários, URLs).
- Informar **possíveis impactos** e regressões.
- Se a mudança for para clientes: update **somente** com pedido explícito e bump de versão combinado.

---

# 5. Banco de dados

1. **Nunca** alterar tabela/coluna/índice existente sem explicar impacto e obter autorização.
2. Toda **migration** deve considerar **clientes antigos** (bancos já migrados, dados reais, possíveis NULLs, charset, `defaultStringLength(191)`).
3. **Não assumir banco limpo** — o que passa em SQLite local vazio pode falhar no MySQL do cliente.
4. Firebird (`config/firebird.php`) é caminho de **importação/migração**, não o banco operacional do ERP web.
5. Evitar operações destrutivas (`drop`, truncate, rewrite em massa) em scripts “de correção” sem backup e autorização.

---

# 6. APIs

1. Manter **compatibilidade** com **`api/v1/*`** enquanto houver apps/terminais em campo.
2. **Não mudar** contrato existente (paths, campos obrigatórios, significados, códigos de erro esperados) sem versão nova.
3. Quando for incompatível: criar **`v2`** (ou novo endpoint aditivo) e plano de migração dos clientes.
4. Respeitar middlewares existentes (`forcavendas.device`, `vendasinternas.device`, `pdv.terminal.ativo`, Sanctum, throttles).
5. Rotas web de relatório/impressão devem respeitar **`erp.permission`** alinhado ao menu — não expor só com `auth` quando o módulo exige permissão.

---

# 7. JavaScript / Livewire / Filament

1. O painel admin usa **SPA Filament** (`->spa()`): remount/morph pode matar foco e “travar” Enter.
2. Cuidado com **eventos duplicados** (mesmo script incluído em `head-assets`, `shell-scripts` e form shell).
3. **Não alterar** o fluxo de **Enter em grades** sem teste manual no browser.

### Padrão obrigatório de Enter em grades editáveis

- JS dedicado com `keydown` em **capture** (ex.: `public/js/erp-compras-lanc-enter.js`).
- Gravação Livewire com `wire:keydown.enter.prevent="..."`.
- No JS: `preventDefault()` — **nunca** `stopImmediatePropagation` (senão o Livewire não grava).
- Commit PHP com `$this->skipRender()` quando necessário para não remountar a grade.
- Anti-autofill (`erp-no-browser-hints.js`): **não** abortar Enter só porque `readOnly === true`; remover `readonly` e seguir.

4. Preferir assets condicionais via `ErpPageAssets` / views de módulo — não carregar JS pesado em todas as páginas sem necessidade.
5. Cache bust: respeitar `ErpAssetVersion` / query `?v=`; validar no browser após mudança de JS.

---

# 8. Atualização dos clientes

### Padrão travado — não alterar sem autorização explícita

O fluxo canônico (ZIP único no canal `update` → `atualizacao/` no cliente → confirmação no login → `unitec:apply-atualizacao`) **não pode ser quebrado nem redesenhado** sem pedido explícito. No cliente a pasta de produção é **`C:\UNITECNOLOGIA_WEB`**.

### Não alterar sem decisão formal e autorização explícita

- Formato do **ZIP FULL** (`Unitec-ERP-Update.zip`) e assinatura `.sha256`
- URL/canal estável do release **`update`**
- **Preservação** no cliente de: **`.env`**, **`storage/`**, **`tools/`**
- Processo: download (Range) → validação → temp → backup → `artisan down` → copia → migrate → optimize → `up` / rollback de árvore se falhar
- Scripts oficiais de gerar/publicar pacote
- Caminho de produção no cliente: `C:\UNITECNOLOGIA_WEB`

### O que o pacote **não** deve incluir

- `.env`, `storage/`, `tools/`, `installer/`, `node_modules/` (e demais exclusões do processo oficial)

### Publicação

- Só gerar/publicar update quando o responsável pedir (“publique”, “suba o update”, etc.).
- Alterar código **não** implica publicar.
- O ZIP no canal `update` é o artefato de produção do cliente: validar exclusões e versão antes de publicar.

---

# 9. Processo obrigatório de trabalho

Toda alteração deve seguir:

```text
ANALISAR
   ↓
EXPLICAR
   ↓
AGUARDAR AUTORIZAÇÃO
   ↓
ALTERAR
   ↓
TESTAR
   ↓
DOCUMENTAR
```

| Etapa | Obrigatório |
|-------|-------------|
| **ANALISAR** | Causa raiz, arquivos, impacto em componentes da seção 3 |
| **EXPLICAR** | Problema, plano, arquivos, riscos |
| **AGUARDAR AUTORIZAÇÃO** | Especialmente: DB, API v1, update, fiscal, Enter, remoções |
| **ALTERAR** | Diff mínimo; sem refatoração paralela |
| **TESTAR** | Telas/APIs afetadas; usuário com e sem permissão quando for ACL |
| **DOCUMENTAR** | O que mudou, como testar, impactos; changelog/versão se for release |

### Checklist rápido antes do PR / entrega

- [ ] Escopo único e autorizado
- [ ] Nenhum componente da seção 3 quebrado sem plano
- [ ] Sem migration surpresa
- [ ] Sem remoção não comprovada
- [ ] Lista de arquivos + riscos informada
- [ ] Testes manuais (ou automatizados) descritos
- [ ] Update/produção **não** disparados sem pedido

---

## Referências internas úteis

- `docs/` — instalação no cliente, desenvolvimento local, tunnel, portal contador
- `.cursor/rules/` — UI moderna, portas, update, Enter em grades, Git do app Android
- `config/unitec.php` — versão, update URL, device service, licença
- `routes/web.php` / `routes/api.php` — superfície HTTP
- `app/Providers/Filament/*` — painéis e hooks de UI

---

*Fim do documento oficial de regras de desenvolvimento do Unitec ERP Web.*
