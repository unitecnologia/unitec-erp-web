<?php

namespace App\Support\Erp;

use App\Filament\Pages\ImpressaoEtiquetasNovoPage;
use App\Filament\Pages\ConfigFiscaisPage;
use App\Filament\Pages\TabelaIcmsPage;
use App\Filament\Pages\ForcaVendasAppPage;
use App\Filament\Pages\ForcaVendasTelaVendaPage;
use App\Filament\Pages\RotasVendedoresPage;
use App\Filament\Pages\PermissoesPage;
use App\Filament\Resources\ForcaVendasDeviceResource;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Filament\Resources\NfceResource;
use App\Filament\Resources\NfeResource;
use App\Filament\Pages\PdvPage;
use App\Filament\Pages\ZeraEstoqueNegativoPage;
use App\Support\Erp\Pdv\PdvConfig;
use App\Filament\Resources\AjustaEstoqueGrupoResource;
use App\Filament\Resources\AjustaPrecoResource;
use App\Filament\Resources\AjusteEstoqueResource;
use App\Filament\Resources\AniversarianteResource;
use App\Filament\Resources\CaixaContaResource;
use App\Filament\Resources\CaixaResource;
use App\Filament\Resources\CfopResource;
use App\Filament\Resources\ReciboResource;
use App\Filament\Resources\CompraResource;
use App\Filament\Resources\ContaPagarResource;
use App\Filament\Resources\ContaReceberResource;
use App\Filament\Resources\ContadorResource;
use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\ErpOperacaoLogResource;
use App\Filament\Resources\TerminalResource;
use App\Filament\Resources\FormaPagamentoResource;
use App\Filament\Pages\RhDashboardPage;
use App\Filament\Pages\BackupPage;
use App\Filament\Pages\LicencaSistemaPage;
use App\Filament\Pages\BalancaConfigPage;
use App\Filament\Resources\RhCargoResource;
use App\Filament\Resources\RhDepartamentoResource;
use App\Filament\Resources\RhFuncionarioResource;
use App\Filament\Pages\BoletoConfigPage;
use App\Filament\Pages\MigraFirebirdPage;
use App\Filament\Resources\BoletoRemessaResource;
use App\Filament\Resources\BoletoRetornoResource;
use App\Filament\Resources\ExpedicaoResource;
use App\Filament\Resources\LogisticaDestinatarioResource;
use App\Filament\Resources\LogisticaRemetenteResource;
use App\Filament\Resources\NotaFornecedorResource;
use App\Filament\Resources\PlanoContaResource;
use App\Filament\Resources\GrupoResource;
use App\Filament\Resources\ImpressaoEtiquetaResource;
use App\Filament\Resources\MarcaResource;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\TomadorServicoResource;
use App\Filament\Resources\TransportadoraResource;
use App\Filament\Resources\UnidadeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VeiculoResource;
use App\Filament\Resources\DevolucaoCompraResource;
use App\Filament\Resources\DevolucaoVendaResource;
use App\Filament\Resources\VendaResource;
use App\Filament\Resources\VendasInternasDeviceResource;
use App\Filament\Resources\VendedorResource;

use App\Support\Erp\ErpAccess;

class ErpMenu
{
    public static function mainMenus(): array
    {
        $menus = [
            ['label' => 'Acesso', 'items' => static::acessoItems()],
            ['label' => 'Pessoas', 'items' => static::pessoasItems()],
            ['label' => 'Estoque', 'items' => static::estoqueItems()],
            ['label' => 'Compras', 'items' => static::comprasItems()],
            ['label' => 'Vendas', 'items' => static::vendasItems()],
            ['label' => 'Logística', 'items' => static::logisticaItems()],
            ['label' => 'Financeiro', 'items' => static::financeiroItems()],
            ['label' => 'Fiscal', 'items' => static::fiscalItems()],
            ['label' => 'OS', 'items' => static::osItems()],
            ['label' => 'Força de Venda', 'items' => static::forcaVendaItems()],
            ['label' => 'Vendas Internas', 'items' => static::vendasInternasItems()],
            ['label' => 'Relatórios', 'items' => static::relatoriosItems()],
            ['label' => 'Configurações', 'items' => static::configuracoesItems()],
            ['label' => 'Ajuda', 'items' => static::ajudaItems()],
        ];

        return static::filterMenusByPermission($menus);
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, color: string, image: string, url?: string, disabled?: bool, logout?: bool}>
     */
    public static function shortcuts(): array
    {
        $shortcuts = [
            static::shortcut('pessoas', 'Pessoas', 'heroicon-o-user-group', 'blue', [
                'url' => PersonResource::getUrl('index'),
                'permission' => 'pessoas.access',
            ]),
            static::shortcut('produtos', 'Produtos', 'heroicon-o-shopping-cart', 'orange', [
                'url' => ProductResource::getUrl('index'),
                'permission' => 'produtos.access',
            ]),
            static::shortcut('compras', 'Compras', 'heroicon-o-building-storefront', 'teal', [
                'url' => CompraResource::getUrl('index'),
                'permission' => 'compras.access',
            ]),
            static::shortcut('vendas', 'Vendas', 'heroicon-o-shopping-bag', 'red', [
                'url' => VendaResource::getUrl('index'),
                'permission' => 'vendas.access',
            ]),
            static::shortcut('orcamento', 'Orçamento', 'heroicon-o-document-text', 'indigo', [
                'url' => OrcamentoResource::getUrl('index'),
                'permission' => 'orcamentos.access',
            ]),
            static::shortcut('caixa', 'Caixa', 'heroicon-o-banknotes', 'green', [
                'url' => CaixaResource::getUrl('index'),
                'permission' => 'caixa.access',
            ]),
        ];

        if (static::pdvHabilitado()) {
            $shortcuts[] = static::shortcut('pdv', 'PDV', 'heroicon-o-calculator', 'blue', [
                'url' => PdvPage::getUrl(),
                'permission' => 'pdv.access',
            ]);
        }

        $shortcuts = [
            ...$shortcuts,
            static::shortcut('fv-monitor', 'Monitor', 'heroicon-o-computer-desktop', 'teal', [
                'url' => ForcaVendasMonitorResource::getUrl('index'),
                'permission' => 'vendas.access',
            ]),
            static::shortcut('nfce', 'NFCe', 'heroicon-o-receipt-percent', 'orange', [
                'url' => NfceResource::getUrl('index'),
                'permission' => 'nfce.access',
            ]),
            static::shortcut('nfe', 'NFe', 'heroicon-o-document-arrow-up', 'indigo', [
                'url' => NfeResource::getUrl('index'),
                'permission' => 'nfe.access',
            ]),
            static::shortcut('receber', 'A Receber', 'heroicon-o-arrow-down-circle', 'green', [
                'url' => ContaReceberResource::getUrl('index'),
                'permission' => 'contas_receber.access',
            ]),
            static::shortcut('pagar', 'A Pagar', 'heroicon-o-arrow-up-circle', 'red', [
                'url' => ContaPagarResource::getUrl('index'),
                'permission' => 'contas_pagar.access',
            ]),
            static::shortcut('sair', 'Sair', 'heroicon-o-arrow-right-on-rectangle', 'slate', ['logout' => true]),
        ];

        return static::filterShortcutsByPermission($shortcuts);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function shortcut(string $key, string $label, string $icon, string $color, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'image' => "img/erp/shortcuts/{$key}.png",
        ], $extra);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function acessoItems(): array
    {
        return [
            static::link('Usuários', UserResource::getUrl('index'), permission: 'acesso.usuarios.access'),
            static::link('Permissões', PermissoesPage::getUrl(), permission: 'acesso.permissoes.manage'),
            static::sep(),
            static::action('Alterar Senha', 'alterar-senha'),
            static::action('Trocar de Usuário', 'trocar-usuario'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function pessoasItems(): array
    {
        return [
            static::link('Contatos', PersonResource::getUrl('index') . '?tipo=todos', 'F2', 'pessoas.access'),
            static::link('Operadores', VendedorResource::getUrl('index'), permission: 'vendedores.access'),
            static::group('RH', static::rhItems()),
            static::sep(),
            static::link('Lista SPC/CCF', PersonResource::getUrl('index') . '?tipo=ccf_spc', permission: 'pessoas.access'),
            static::link('Lista Aniversariantes', AniversarianteResource::getUrl('index'), permission: 'aniversariantes.access'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function estoqueItems(): array
    {
        return [
            static::link('Produtos', ProductResource::getUrl('index'), permission: 'produtos.access'),
            static::link('Grupo', GrupoResource::getUrl('index'), permission: 'grupos.access'),
            static::link('Unidades', UnidadeResource::getUrl('index'), permission: 'unidades.access'),
            static::link('Marcas', MarcaResource::getUrl('index'), permission: 'marcas.access'),
            static::link('Impressão Etiquetas Novo', ImpressaoEtiquetasNovoPage::getUrl(), permission: 'etiquetas.access'),
            static::link('Impressão de Etiquetas', ImpressaoEtiquetaResource::getUrl('index'), permission: 'etiquetas.access'),
            static::sep(),
            static::link('Ajuste de Preço em Lote', AjustaPrecoResource::getUrl('index'), permission: 'ajusta_preco.access'),
            static::link('Ajusta Estoque', AjusteEstoqueResource::getUrl('index'), permission: 'ajuste_estoque.access'),
            static::link('Ajuste Estoque Grupo', AjustaEstoqueGrupoResource::getUrl('index'), permission: 'ajuste_estoque.access'),
            static::link('Zera Estoque Negativo', ZeraEstoqueNegativoPage::getUrl(), permission: 'ajuste_estoque.access'),
            static::sep(),
            static::stub('Fabricar Produto'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function comprasItems(): array
    {
        return [
            static::link('Lista Compras', CompraResource::getUrl('index'), permission: 'compras.access'),
            static::link('Notas de Fornecedores', NotaFornecedorResource::getUrl('index'), permission: 'compras.access'),
            static::link('Devolução de Compra', DevolucaoCompraResource::getUrl('index'), permission: 'devolucoes_compra.access'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function vendasItems(): array
    {
        $items = [
            static::link('Orçamento', OrcamentoResource::getUrl('index'), permission: 'orcamentos.access'),
        ];

        if (static::pdvHabilitado()) {
            $items[] = static::link('PDV', PdvPage::getUrl(), permission: 'pdv.access');
        }

        return [
            ...$items,
            static::stub('Delivery'),
            static::stub('Restaurante'),
            static::link('Lista de Vendas', VendaResource::getUrl('index'), permission: 'vendas.access'),
            static::link('Tela de Venda', ForcaVendasTelaVendaPage::getUrl(), permission: 'vendas.access'),
            static::link('Monitor de Vendas', ForcaVendasMonitorResource::getUrl('index'), permission: 'vendas.access'),
            static::link('Devolução de Venda', DevolucaoVendaResource::getUrl('index'), permission: 'devolucoes_venda.access'),
        ];
    }

    protected static function pdvHabilitado(): bool
    {
        // Flag param_geral_usar_pdv_retaguarda foi removida; PDV no menu (acesso via permissão).
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function financeiroItems(): array
    {
        return [
            static::link('Forma de Pagamentos', FormaPagamentoResource::getUrl('index'), permission: 'formas_pagamento.access'),
            static::link('Plano de Contas', PlanoContaResource::getUrl('index'), permission: 'planos_contas.access'),
            static::link('Contas', CaixaContaResource::getUrl('index'), permission: 'contas_caixa.access'),
            static::link('Contas a Pagar', ContaPagarResource::getUrl('index'), permission: 'contas_pagar.access'),
            static::link('Contas a Receber', ContaReceberResource::getUrl('index'), permission: 'contas_receber.access'),
            static::link('Livro Caixa', CaixaResource::getUrl('index'), permission: 'caixa.access'),
            static::sep(),
            static::link('Impressão de Recibos', ReciboResource::getUrl('index'), permission: 'recibos.access'),
            static::sep(),
            static::group('Boleto', [
                static::link('Configuração', BoletoConfigPage::getUrl(), permission: 'boletos.access'),
                static::link('Remessa', BoletoRemessaResource::getUrl('index'), permission: 'boletos.access'),
                static::link('Retorno', BoletoRetornoResource::getUrl('index'), permission: 'boletos.access'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function fiscalItems(): array
    {
        return [
            static::link('NFC-e', NfceResource::getUrl('index'), permission: 'nfce.access'),
            static::link('NF-e', NfeResource::getUrl('index'), permission: 'nfe.access'),
            static::stub('CTe-OS'),
            static::stub('CTe'),
            static::stub('MDFe'),
            static::sep(),
            static::link('CFOP', CfopResource::getUrl('index'), permission: 'cfops.access'),
            static::link('Tabela ICMS', TabelaIcmsPage::getUrl(), permission: 'tabela_icms.access'),
            static::sep(),
            static::stub('Sped Fiscal'),
            static::stub('Sped Contribuições'),
            static::stub('Sintegra'),
            static::stub('Enviar Sped'),
            static::sep(),
            static::stub('Inventário por CSOSN / CST'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function logisticaItems(): array
    {
        return [
            static::link('Controle de Expedição', ExpedicaoResource::getUrl('index'), permission: 'logistica.access'),
            static::sep(),
            static::group('Transportadora', [
                static::link('Motorista / Transportador', TransportadoraResource::getUrl('index'), permission: 'transportadoras.access'),
                static::link('Veículos', VeiculoResource::getUrl('index'), permission: 'veiculos.access'),
                static::link('Tomador de Serviço', TomadorServicoResource::getUrl('index'), permission: 'tomadores_servico.access'),
                static::link('Destinatário', LogisticaDestinatarioResource::getUrl('index'), permission: 'logistica_destinatarios.access'),
                static::link('Remetente', LogisticaRemetenteResource::getUrl('index'), permission: 'logistica_remetentes.access'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function osItems(): array
    {
        return [
            static::link('Ordem de Serviço', OrdemServicoResource::getUrl('index'), permission: 'ordens_servico.access'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function rhItems(): array
    {
        return [
            static::link('Painel RH', RhDashboardPage::getUrl(), permission: 'rh.dashboard.access'),
            static::sep(),
            static::link('Funcionários', RhFuncionarioResource::getUrl('index'), permission: 'rh.funcionarios.access'),
            static::link('Contador', ContadorResource::getUrl('index'), permission: 'contadores.access'),
            static::link('Cargos', RhCargoResource::getUrl('index'), permission: 'rh.cargos.access'),
            static::link('Departamentos', RhDepartamentoResource::getUrl('index'), permission: 'rh.departamentos.access'),
            static::sep(),
            static::stub('Documentos'),
            static::stub('EPIs'),
            static::stub('Exames / ASO'),
            static::stub('Treinamentos'),
            static::stub('Uniformes'),
            static::sep(),
            static::stub('Holerites'),
            static::stub('Benefícios'),
            static::stub('Ocorrências'),
            static::stub('Férias'),
            static::stub('Escalas'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function forcaVendaItems(): array
    {
        return [
            static::link('Aparelhos (autorizar)', ForcaVendasDeviceResource::getUrl('index'), permission: 'forca_vendas.access'),
            static::link('Rotas de Vendedores', RotasVendedoresPage::getUrl(), permission: 'forca_vendas.access'),
            static::sep(),
            static::link('App / Como conectar', ForcaVendasAppPage::getUrl(), permission: 'forca_vendas.config'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function vendasInternasItems(): array
    {
        return [
            static::link('Aparelhos (autorizar)', VendasInternasDeviceResource::getUrl('index'), permission: 'vendas_internas.access'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function relatoriosItems(): array
    {
        return [
            static::group('Produtos', [
                static::link('Curva ABC', route('erp.reports.tabular', ['slug' => 'curva-abc']), permission: 'produtos.print'),
                static::link('Histórico de Produtos', route('erp.reports.tabular', ['slug' => 'historico-produtos']), permission: 'produtos.print'),
                static::link('Histórico de Compras', route('erp.reports.tabular', ['slug' => 'historico-compras']), permission: 'compras.print'),
                static::link('Histórico de Compras p/ Fornecedor', route('erp.reports.tabular', ['slug' => 'historico-compras-fornecedor']), permission: 'compras.print'),
                static::link('Produtos Lucratividade', route('erp.reports.tabular', ['slug' => 'produtos-lucratividade']), permission: 'produtos.print'),
                static::link('Produtos menos lucrativos', route('erp.reports.tabular', ['slug' => 'produtos-menos-lucrativos']), permission: 'produtos.print'),
                static::link('Produtos mais lucrativos', route('erp.reports.tabular', ['slug' => 'produtos-mais-lucrativos']), permission: 'produtos.print'),
                static::link('Produtos menos vendidos', route('erp.reports.tabular', ['slug' => 'produtos-menos-vendidos']), permission: 'produtos.print'),
                static::link('Produtos mais vendidos', route('erp.reports.tabular', ['slug' => 'produtos-mais-vendidos']), permission: 'produtos.print'),
                static::link('Relatório de Produtos com Preço Alterado', route('erp.reports.tabular', ['slug' => 'preco-alterado']), permission: 'produtos.print'),
                static::link('Relatório de Estoque - Composição', route('erp.reports.tabular', ['slug' => 'estoque-composicao']), permission: 'produtos.print'),
                static::link('Relatório de Estoque - Grade', route('erp.reports.tabular', ['slug' => 'estoque-grade']), permission: 'produtos.print'),
                static::link('Relatório de Estoque - Mínimo', route('erp.reports.tabular', ['slug' => 'estoque-minimo']), permission: 'produtos.print'),
                static::link('Relatório de Estoque - Negativo', route('erp.reports.tabular', ['slug' => 'estoque-negativo']), permission: 'produtos.print'),
                static::sep(),
                static::link('Listagem - Conferência de Estoque', route('erp.reports.tabular', ['slug' => 'conferencia-estoque']), permission: 'produtos.print'),
            ]),
            static::group('Vendas', [
                static::link('Histórico de Vendas', route('erp.reports.tabular', ['slug' => 'historico-vendas']), permission: 'vendas.print'),
                static::link('Histórico de Orçamentos', route('erp.reports.tabular', ['slug' => 'historico-orcamentos']), permission: 'vendas.print'),
                static::link('Histórico de Vendas p/ Cliente', route('erp.reports.tabular', ['slug' => 'historico-vendas-cliente']), permission: 'vendas.print'),
                static::link('Histórico de Vendas p/ Vendedor', route('erp.reports.tabular', ['slug' => 'historico-vendas-vendedor']), permission: 'vendas.print'),
                static::link('Relatório de Vendas por PDV', route('erp.reports.tabular', ['slug' => 'vendas-pdv']), permission: 'vendas.print'),
                static::link('Relatório Vendas por Forma de Pagamento', route('erp.reports.tabular', ['slug' => 'vendas-forma-pagamento']), permission: 'vendas.print'),
                static::link('Relatório de Vendas por Produtos - Geral', route('erp.reports.tabular', ['slug' => 'vendas-produtos-geral']), permission: 'vendas.print'),
                static::link('Relatório de Vendas de Produtos - Clientes', route('erp.reports.tabular', ['slug' => 'vendas-produtos-clientes']), permission: 'vendas.print'),
                static::link('Relatório de Vendas de Produtos - Vendedores', route('erp.reports.tabular', ['slug' => 'vendas-produtos-vendedores']), permission: 'vendas.print'),
                static::link('Relatório de Vendas Por CFOP/CSOSN', route('erp.reports.tabular', ['slug' => 'vendas-cfop-csosn']), permission: 'vendas.print'),
                static::link('Relatório de Vendas de Produtos c/ Trib.Monofásica', route('erp.reports.tabular', ['slug' => 'vendas-produtos-monofasica']), permission: 'vendas.print'),
            ]),
            static::group('Financeiro', [
                static::link('Relatório Comissão de Operadores', route('erp.reports.comissao-vendedores'), permission: 'vendas.print'),
                static::link('Relatório de Contas a Receber', route('erp.reports.tabular', ['slug' => 'contas-receber']), permission: 'contas_receber.print'),
                static::link('Relatório de Contas a Pagar', route('erp.reports.tabular', ['slug' => 'contas-pagar']), permission: 'contas_pagar.print'),
                static::link('Relatório Resumo Caixa', route('erp.reports.tabular', ['slug' => 'resumo-caixa']), permission: 'caixa.print'),
                static::link('Relatório de Movimento Caixa', route('erp.reports.tabular', ['slug' => 'movimento-caixa']), permission: 'caixa.print'),
                static::link('Relatório Balanço Financeiro', route('erp.reports.tabular', ['slug' => 'balanco-financeiro']), permission: 'caixa.print'),
                static::link('Relatório Resumo Financeiro p/ Conta', route('erp.reports.tabular', ['slug' => 'resumo-financeiro-conta']), permission: 'contas_caixa.print'),
                static::link('Relatório Financeiro - Cartão', route('erp.reports.contas-receber-cartoes'), permission: 'contas_receber.print'),
                static::link('Relatório por Plano de Contas', route('erp.reports.tabular', ['slug' => 'plano-contas']), permission: 'planos_contas.print'),
            ]),
            static::group('Auditoria', [
                static::link('Log de Operações', ErpOperacaoLogResource::getUrl('index'), permission: 'vendas.access'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function configuracoesItems(): array
    {
        return [
            static::link('Empresa', EmpresaResource::getUrl('index'), permission: 'empresa.access'),
            static::link('Terminais', TerminalResource::getUrl('index'), permission: 'terminais.access'),
            static::stub('Mesas'),
            static::link('Config. Fiscais', ConfigFiscaisPage::getUrl(), permission: 'config_fiscais.access'),
            static::link('Balança', BalancaConfigPage::getUrl(), permission: 'balanca.access'),
            static::link('Backup', BackupPage::getUrl(), permission: 'backup.access'),
            static::sep(),
            static::group('Comandos', [
                static::link('Migra dados FB', MigraFirebirdPage::getUrl(), permission: 'migra_firebird.access'),
                static::stub('Ajusta Menu'),
                static::stub('Ajusta Campos'),
                static::stub('Atualiza Tabelas e Campos'),
                static::stub('Execute Script'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function ajudaItems(): array
    {
        return [
            static::stub('Vídeos de Treinamento'),
            static::stub('Lista de Updates'),
            static::link('Licença do Sistema', LicencaSistemaPage::getUrl()),
            static::sep(),
            static::action('Atualizar Sistema', 'system-update'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function link(string $label, string $url, ?string $shortcut = null, ?string $permission = null): array
    {
        $item = [
            'label' => $label,
            'url' => $url,
        ];

        if ($shortcut !== null) {
            $item['shortcut'] = $shortcut;
        }

        if ($permission !== null) {
            $item['permission'] = $permission;
        }

        return $item;
    }

    /**
     * @param  array<int, array{label: string, items: array<int, array<string, mixed>>}>  $menus
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    protected static function filterMenusByPermission(array $menus): array
    {
        $filtered = [];

        foreach ($menus as $menu) {
            $items = static::filterItemsByPermission($menu['items'] ?? []);

            if ($items === []) {
                continue;
            }

            $filtered[] = [
                'label' => $menu['label'],
                'items' => $items,
            ];
        }

        return $filtered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected static function filterItemsByPermission(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'separator') {
                if ($filtered !== [] && ! (($filtered[array_key_last($filtered)]['type'] ?? null) === 'separator')) {
                    $filtered[] = $item;
                }

                continue;
            }

            if (isset($item['items']) && is_array($item['items'])) {
                $children = static::filterItemsByPermission($item['items']);

                if ($children === []) {
                    continue;
                }

                $item['items'] = $children;
                $filtered[] = $item;

                continue;
            }

            $permission = $item['permission'] ?? null;

            if ($permission !== null && ! ErpAccess::currentCan($permission)) {
                continue;
            }

            $filtered[] = $item;
        }

        while ($filtered !== [] && (($filtered[array_key_last($filtered)]['type'] ?? null) === 'separator')) {
            array_pop($filtered);
        }

        return $filtered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $shortcuts
     * @return array<int, array<string, mixed>>
     */
    protected static function filterShortcutsByPermission(array $shortcuts): array
    {
        return array_values(array_filter($shortcuts, function (array $shortcut): bool {
            if (($shortcut['logout'] ?? false) === true) {
                return true;
            }

            $permission = $shortcut['permission'] ?? null;

            if ($permission === null) {
                return true;
            }

            return ErpAccess::currentCan($permission);
        }));
    }

    /**
     * Item de menu ainda não implementado — exibido como "Em breve", sem toast.
     *
     * @return array{label: string, pending: true, shortcut?: string}
     */
    protected static function stub(string $label, ?string $shortcut = null): array
    {
        $item = [
            'label' => $label,
            'pending' => true,
        ];

        if ($shortcut !== null) {
            $item['shortcut'] = $shortcut;
        }

        return $item;
    }

    /**
     * @return array<string, string>
     */
    protected static function action(string $label, string $action, ?string $shortcut = null): array
    {
        $item = [
            'label' => $label,
            'action' => $action,
        ];

        if ($shortcut !== null) {
            $item['shortcut'] = $shortcut;
        }

        return $item;
    }

    /**
     * @return array<string, string>
     */
    protected static function sep(): array
    {
        return ['type' => 'separator'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected static function group(string $label, array $items): array
    {
        return [
            'label' => $label,
            'items' => $items,
        ];
    }
}
