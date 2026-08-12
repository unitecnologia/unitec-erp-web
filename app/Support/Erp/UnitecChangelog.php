<?php

namespace App\Support\Erp;

/**
 * Histórico de novidades exibido em Ajuda → Lista de Updates.
 * Novas entradas: adicionar no topo de releases().
 */
class UnitecChangelog
{
    /**
     * @return list<array{
     *     version: string,
     *     date: string,
     *     title: string,
     *     highlights: list<string>,
     *     news?: list<string>,
     *     fixes?: list<string>
     * }>
     */
    public static function releases(): array
    {
        return [
            [
                'version' => '6.4.1.89',
                'date' => '12/08/2026',
                'title' => 'Cadastro de cliente: cidade, CEP/IBGE e NF-e',
                'highlights' => [
                    'Busca de CEP preenche endereço e código IBGE do município.',
                    'Pesquisa de cidade em todo o Brasil; UF preenchida ao selecionar a cidade.',
                    'Totais NF-e editáveis (frete, seguro, outras, desconto) com Enter entre campos.',
                ],
                'fixes' => [
                    'Rejeição SEFAZ cStat 274 (município inexistente) em clientes novos sem IBGE.',
                    'Rejeição cStat 535 — frete/seguro/outras rateados nos itens do XML.',
                    'Campo UF espremido no cadastro; Enter na cidade avança para o e-mail.',
                ],
            ],
            [
                'version' => '6.4.1.87',
                'date' => '11/08/2026',
                'title' => 'E-mail SMTP: senha com maiúsculas e minúsculas',
                'highlights' => [
                    'Campos de e-mail (senha, usuário, host, API key) deixam de forçar maiúsculas.',
                ],
                'fixes' => [
                    'SMTP deixava de autenticar quando a senha tinha letras minúsculas (campo convertia tudo para maiúsculo).',
                ],
            ],
            [
                'version' => '6.4.1.86',
                'date' => '07/08/2026',
                'title' => 'Aquecimento automático do ERP na instalação e no boot',
                'highlights' => [
                    'Comando unitec:warm compila OPcache e visita telas do menu ao iniciar o servidor.',
                    'Novos clientes: ERP sobe com Windows e aquece em segundo plano (8765) — 1ª tela mais rápida.',
                    'Prefetch na tela Principal após login (telas do menu do usuário).',
                    'Botão Aquecer Sistema usa o warm completo; útil após Limpar cache.',
                ],
            ],
            [
                'version' => '6.4.1.85',
                'date' => '04/08/2026',
                'title' => 'Update: flock, logs detalhados e cadastro de cliente',
                'highlights' => [
                    'Lock de atualização com flock (só um processo por vez).',
                    'Log completo das etapas em storage/logs/erp-update-spawn.log (com stack trace em erro).',
                    'Cadastro de cliente: Enter seleciona o conteúdo; clique seleciona; cursor em mãozinha.',
                ],
                'fixes' => [
                    'Não restaura arquivos após migrate (evita código velho + banco novo).',
                    'Limpa modo manutenção se o processo de update morrer no meio.',
                ],
            ],
            [
                'version' => '6.4.1.84',
                'date' => '04/08/2026',
                'title' => 'Atualização: 1 ZIP + instalação mais segura',
                'highlights' => [
                    'Canal update publica só Unitec-ERP-Update.zip (com SHA256).',
                    'Download retomável, checagem de disco, modo manutenção e restore automático se falhar.',
                ],
                'fixes' => [
                    'Removidos pacote dual (full/delta) e uploads duplicados no GitHub.',
                ],
            ],
            [
                'version' => '6.4.1.83',
                'date' => '04/08/2026',
                'title' => 'Cliente: Enter nos campos (CPF/CNPJ → Tipo de Cont.)',
                'highlights' => [
                    'Cadastro de cliente: Enter navega do CPF/CNPJ até Tipo de Cont. (mesmo esquema da tela XML).',
                ],
            ],
            [
                'version' => '6.4.1.82',
                'date' => '04/08/2026',
                'title' => 'Produto: código só leitura + Enter nos campos',
                'highlights' => [
                    'Cadastro de produto: Enter navega da Descrição até o CEST (como na tela XML).',
                ],
                'fixes' => [
                    'Campo Código do produto fica só informativo — não permite editar.',
                ],
            ],
            [
                'version' => '6.4.1.81',
                'date' => '04/08/2026',
                'title' => 'Update: script de reinício desligado de vez',
                'fixes' => [
                    'O script pós-update não mata mais o PHP (mesmo se a versão antiga ainda agendar o reinício).',
                    'Evita trava em “Reiniciando servidor… liberando login”.',
                ],
            ],
            [
                'version' => '6.4.1.80',
                'date' => '04/08/2026',
                'title' => 'Painel: data da mensalidade correta',
                'fixes' => [
                    'Card de licença volta a usar o vencimento da mensalidade (pagamento), não a validade do contrato.',
                    'No login a data da mensalidade é gravada no cache — após update o painel não cai mais no 30/12.',
                ],
            ],
            [
                'version' => '6.4.1.79',
                'date' => '04/08/2026',
                'title' => 'Update sem reiniciar o PHP',
                'fixes' => [
                    'Após atualizar, o sistema NÃO mata mais o PHP — evita porta zumbi e login que não abre.',
                    'OPcache passa a validar arquivos novos sozinho; update → login, simples.',
                ],
            ],
            [
                'version' => '6.4.1.78',
                'date' => '04/08/2026',
                'title' => 'Update: login volta a abrir após reinício',
                'fixes' => [
                    'Após atualizar, o sistema esperava o servidor novo responder antes de abrir o login — evita porta 8765 zumbi (TCP ok, tela branca).',
                    'Reinício pós-update mata o PHP pela porta (taskkill em árvore) e só libera quando /admin/login responde.',
                ],
            ],
            [
                'version' => '6.4.1.77',
                'date' => '04/08/2026',
                'title' => 'Licença mais rápida e mensalidade no painel',
                'highlights' => [
                    'Card de licença no painel usa a data da mensalidade (pagamento), não só a validade do contrato.',
                    'Bloqueio pelo gerenciador mostra só aviso — sem pedir Pix.',
                ],
                'fixes' => [
                    'Cliques/menu lentos: poll das listas não re-renderiza à toa; navegação não consulta o portal a cada tela.',
                    'Consulta de faturas/mensalidade sai do caminho do login e do painel (cache em segundo plano).',
                ],
            ],
            [
                'version' => '6.4.1.76',
                'date' => '04/08/2026',
                'title' => 'Bloqueio de licença ativo em produção',
                'fixes' => [
                    'Validação remota de licença/pagamento passa a usar o portal Unitec por padrão — não depende mais de variável no .env.',
                    'Clientes em produção que não bloqueavam (mesmo com mensalidade em aberto) voltam a bloquear corretamente.',
                ],
            ],
            [
                'version' => '6.4.1.75',
                'date' => '04/08/2026',
                'title' => 'Listas em rede atualizam sozinhas',
                'highlights' => [
                    'Em rede, as listagens de Produtos, Vendas, Orçamentos e Pessoas atualizam sozinhas (~10s) quando outro terminal grava dados.',
                    'O botão Atualizar continua disponível quando quiser forçar na hora.',
                ],
                'fixes' => [
                    'NF-e: bloqueio de estoque negativo também na confirmação do lançamento (não só na transmissão).',
                    'NF-e: F4 cancela nota aberta (inutiliza na SEFAZ e limpa a tela).',
                    'NF-e: Enter navega pelos campos da grade de itens como no lançamento XML.',
                ],
            ],
            [
                'version' => '6.4.1.74',
                'date' => '03/08/2026',
                'title' => 'Abas NFC-e/NF-e com contraste normal',
                'fixes' => [
                    'Abas inativas da NFC-e/NF-e deixam de ficar apagadas (opacidade removida).',
                ],
            ],
            [
                'version' => '6.4.1.73',
                'date' => '03/08/2026',
                'title' => 'Correção: PHP zumbi após update',
                'fixes' => [
                    'Após atualizar, o PHP antigo podia continuar rodando e a tela ficava na versão velha.',
                    'Reinício do servidor agora encerra o processo certo (porta/pasta do ERP).',
                    'Finalização do update mais leve — não trava mais no “Finalizando”.',
                ],
            ],
            [
                'version' => '6.4.1.72',
                'date' => '03/08/2026',
                'title' => 'Update grava a versão de verdade (fim do loop na 66)',
                'fixes' => [
                    'Após aplicar o pacote, o cache era regenerado com a versão antiga (OPcache) e a tela ficava na 66.',
                    'config:cache no update agora roda sem OPcache; incluído “Reparar Versao Update.bat”.',
                ],
            ],
            [
                'version' => '6.4.1.71',
                'date' => '03/08/2026',
                'title' => 'Proteção: update/instalador não zera o banco',
                'highlights' => [
                    'Atualização só roda migrate incremental — nunca migrate:fresh / wipe.',
                    'Setup/reinstalação bloqueia apagar banco quando já existem dados.',
                ],
                'fixes' => [
                    'Corrigido risco do instalador recriar o banco do zero em cliente já em uso.',
                ],
            ],
            [
                'version' => '6.4.1.70',
                'date' => '03/08/2026',
                'title' => 'Update reinicia o servidor automaticamente',
                'highlights' => [
                    'Após instalar, o ERP reinicia o PHP para limpar OPcache e mostrar a versão/telas novas.',
                    'Atualização continua sempre FULL (sem delta).',
                ],
                'fixes' => [
                    'Corrigido caso em que o update “concluía” mas a tela continuava na versão antiga.',
                ],
            ],
            [
                'version' => '6.4.1.69',
                'date' => '03/08/2026',
                'title' => 'Atualização sempre completa (sem delta)',
                'highlights' => [
                    'Canal de update passa a publicar só pacote FULL — mais previsível.',
                    'Pacote incremental (delta) desativado para evitar versão/arquivos pela metade.',
                ],
                'fixes' => [
                    'Clientes não recebem mais update parcial que deixava telas e versão antigas.',
                ],
            ],
            [
                'version' => '6.4.1.68',
                'date' => '03/08/2026',
                'title' => 'Correção: versão ficava antiga após atualizar',
                'fixes' => [
                    'Após instalar update, o sistema deixava de mostrar a versão nova (cache OPcache/config).',
                    'A versão exibida passa a ler o arquivo no disco, não só o cache.',
                ],
            ],
            [
                'version' => '6.4.1.67',
                'date' => '03/08/2026',
                'title' => 'NF-e: envio unificado, DANFE e e-mail na Empresa',
                'highlights' => [
                    'E-mail SMTP/Brevo movido para Empresa → Parâmetros → E-mail.',
                    'NF-e: botão Enviar (F9) com e-mail e WhatsApp na mesma tela, com barra de progresso.',
                    'Listagem NF-e: Imprimir DANFE (F7); emissão mostra badge Homologação quando aplicável.',
                    'Instalador libera portas do firewall para estações na rede.',
                ],
                'fixes' => [
                    'DANFE: campos vazios não mostram mais &nbsp;; rodapé com Unitecnologia ERP, data/hora e usuário.',
                    'Qtde e Vlr. unit. na emissão deixam de se preencher sozinhos ao apagar.',
                    'WhatsApp de NF-e liberado nos parâmetros (antes bloqueava o envio).',
                ],
            ],
            [
                'version' => '6.4.1.66',
                'date' => '02/08/2026',
                'title' => 'Comandos do Sistema e menu mais confortável',
                'highlights' => [
                    'Configurações → Comandos: Limpar Cache, Aquecer Sistema e Info do Sistema.',
                    'Tela de comandos no padrão visual do Backup (modal profissional).',
                    'Faixa do mouse no submenu um pouco mais alta, com letra levemente maior.',
                ],
                'fixes' => [
                    'Corrigido erro 500 ao abrir telas quando a rota de Comandos ainda não estava no cache.',
                ],
            ],
            [
                'version' => '6.4.1.65',
                'date' => '02/08/2026',
                'title' => 'Telas mais rápidas em produção',
                'highlights' => [
                    'OPcache ativo no servidor embutido (artisan serve) — abertura de telas bem mais rápida.',
                    'Mais workers no PHP para o Filament não enfileirar pedidos ao abrir menus.',
                ],
                'fixes' => [
                    'Corrigido gargalo de performance em produção (OPcache CLI desligado e sem workers).',
                ],
            ],
            [
                'version' => '6.4.1.64',
                'date' => '02/08/2026',
                'title' => 'Lista de Updates sem espaço em branco',
                'fixes' => [
                    'Card aberto na Lista de Updates deixa de ocupar a tela toda com espaço vazio.',
                ],
            ],
            [
                'version' => '6.4.1.63',
                'date' => '02/08/2026',
                'title' => 'Lista de Updates compacta e correção do download',
                'highlights' => [
                    'Lista de Updates mais compacta (acordeão), sem ocupar a tela toda.',
                ],
                'fixes' => [
                    'Corrigido “Download incompleto” em pacotes delta pequenos (agora aceita ZIP válido de qualquer tamanho).',
                ],
            ],
            [
                'version' => '6.4.1.62',
                'date' => '02/08/2026',
                'title' => 'Lista de Updates no menu Ajuda',
                'highlights' => [
                    'Ajuda → Lista de Updates agora mostra melhorias, novidades e correções por versão.',
                    'Versão instalada destacada na tela de histórico.',
                ],
            ],
            [
                'version' => '6.4.1.61',
                'date' => '02/08/2026',
                'title' => 'Atualização mais rápida e segura',
                'highlights' => [
                    'Atualização incremental: o cliente pode receber só o que mudou (mais leve e rápido).',
                    'Se a versão local estiver atrasada, o sistema baixa automaticamente o pacote completo (seguro).',
                ],
                'news' => [
                    'Canal estável no GitHub com pacote leve + pacote full de fallback.',
                ],
            ],
            [
                'version' => '6.4.1.60',
                'date' => '02/08/2026',
                'title' => 'Cadastro de produtos e balança',
                'highlights' => [
                    'Marca, Grupo e Unidade com busca ao digitar (lista abre colada no campo).',
                    'No Grupo: marcar App e Balança direto na lista, com destaque verde quando ativo.',
                    'Unidade alinhada (sigla + descrição) e textos em caixa alta.',
                    'Removido o campo Estoque Inicial — estoque segue pelos movimentos do sistema.',
                ],
                'news' => [
                    'Regras de produto de balança: grupo marcado, PLU até 6 dígitos e validação de código de barras.',
                    'Exportação de balança usando o código do produto (PLU), sem depender de prefixo antigo.',
                ],
                'fixes' => [
                    'Seta dos selects de Marca/Grupo/Unidade aumentada para facilitar o clique.',
                    'Lista do Grupo não abre mais “lá embaixo” na tela.',
                ],
            ],
        ];
    }

    public static function currentVersion(): string
    {
        return ErpUpdateService::readInstalledVersion();
    }
}
