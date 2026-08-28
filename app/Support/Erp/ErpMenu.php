<?php

namespace App\Support\Erp;

use App\Filament\Pages\ImpressaoEtiquetasNovoPage;
use App\Filament\Pages\ConfigFiscaisPage;
use App\Filament\Pages\TabelaIcmsPage;
use App\Filament\Pages\ForcaVendasTelaVendaPage;
use App\Filament\Pages\PermissoesPage;
use App\Filament\Pages\TrocarEmpresaPage;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Filament\Resources\NfceResource;
use App\Filament\Resources\NfeResource;
use App\Filament\Pages\PdvPage;
use App\Filament\Resources\PromocaoResource;
use App\Filament\Pages\ZeraEstoqueNegativoPage;
use App\Filament\Pages\AnaliseComprasPage;
use App\Filament\Pages\OutrasSaidasMovimentoPage;
use App\Filament\Pages\OperacoesFiscaisPage;
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
use App\Support\Erp\ErpAccess;

class ErpMenu
{
    public static function mainMenus(): array
    {
        return static::filterMenusByPermission(static::allMenus());
    }

    /**
     * Árvore completa do menu (sem filtrar por permissão do usuário logado).
     * Usada na tela de permissões para configurar o acesso.
     *
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public static function allMenus(): array
    {
        return [
            ['label' => 'Acesso', 'items' => static::acessoItems()],
            ['label' => 'Pessoas', 'items' => static::pessoasItems()],
            ['label' => 'Estoque', 'items' => static::estoqueItems()],
            ['label' => 'Compras', 'items' => static::comprasItems()],
            ['label' => 'Vendas', 'items' => static::vendasItems()],
            ['label' => 'Logística', 'items' => static::logisticaItems()],
            ['label' => 'Financeiro', 'items' => static::financeiroItems()],
            ['label' => 'Fiscal', 'items' => static::fiscalItems()],
            ['label' => 'OS', 'items' => static::osItems()],
            ['label' => 'Relatórios', 'items' => static::relatoriosItems()],
            ['label' => 'Configurações', 'items' => static::configuracoesItems()],
            ['label' => 'Ajuda', 'items' => static::ajudaItems()],
        ];
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
            static::link('Permissões / Usuários', PermissoesPage::getUrl(), permission: 'acesso.permissoes.manage', icon: 'heroicon-o-shield-check', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Trocar Empresa', TrocarEmpresaPage::getUrl(), icon: 'heroicon-o-building-office-2', iconColor: 'erp-menu-bar__icon--blue'),
            static::sep(),
            static::action('Alterar Senha', 'alterar-senha', icon: 'heroicon-o-key', iconColor: 'erp-menu-bar__icon--amber'),
            static::action('Trocar de Usuário', 'trocar-usuario', icon: 'heroicon-o-users', iconColor: 'erp-menu-bar__icon--teal'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function pessoasItems(): array
    {
        return [
            static::link('Contatos', PersonResource::getUrl('index') . '?tipo=todos', 'F2', 'pessoas.access', icon: 'heroicon-o-user-group', iconColor: 'erp-menu-bar__icon--blue'),
            static::group('RH', static::rhItems()),
            static::sep(),
            static::link('Lista SPC/CCF', PersonResource::getUrl('index') . '?tipo=ccf_spc', permission: 'pessoas.access', icon: 'heroicon-o-exclamation-triangle', iconColor: 'erp-menu-bar__icon--orange'),
            static::link('Lista Aniversariantes', AniversarianteResource::getUrl('index'), permission: 'aniversariantes.access', icon: 'heroicon-o-cake', iconColor: 'erp-menu-bar__icon--purple'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function estoqueItems(): array
    {
        return [
            static::link('Produtos', ProductResource::getUrl('index'), permission: 'produtos.access', icon: 'heroicon-o-cube', iconColor: 'erp-menu-bar__icon--orange'),
            static::link('Grupo', GrupoResource::getUrl('index'), permission: 'grupos.access', icon: 'heroicon-o-rectangle-group', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Unidades', UnidadeResource::getUrl('index'), permission: 'unidades.access', icon: 'heroicon-o-scale', iconColor: 'erp-menu-bar__icon--blue'),
            static::link('Marcas', MarcaResource::getUrl('index'), permission: 'marcas.access', icon: 'heroicon-o-tag', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Impressão Etiquetas Novo', ImpressaoEtiquetasNovoPage::getUrl(), permission: 'etiquetas.access', icon: 'heroicon-o-printer', iconColor: 'erp-menu-bar__icon--green'),
            static::link('Impressão de Etiquetas', ImpressaoEtiquetaResource::getUrl('index'), permission: 'etiquetas.access', icon: 'heroicon-o-qr-code', iconColor: 'erp-menu-bar__icon--amber'),
            static::sep(),
            static::group('Movimentações e Ajuste', [
                static::link('Ajuste de Preço em Lote', AjustaPrecoResource::getUrl('index'), permission: 'ajusta_preco.access', icon: 'heroicon-o-currency-dollar', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Ajusta Estoque', AjusteEstoqueResource::getUrl('index'), permission: 'ajuste_estoque.access', icon: 'heroicon-o-arrows-up-down', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Ajuste Estoque Grupo', AjustaEstoqueGrupoResource::getUrl('index'), permission: 'ajuste_estoque.access', icon: 'heroicon-o-squares-2x2', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Zera Estoque Negativo', ZeraEstoqueNegativoPage::getUrl(), permission: 'ajuste_estoque.access', icon: 'heroicon-o-minus-circle', iconColor: 'erp-menu-bar__icon--red'),
                static::stub('Transferência de Estoque', icon: 'heroicon-o-arrows-right-left', iconColor: 'erp-menu-bar__icon--slate'),
                static::link('Outras Saídas/Movimento', OutrasSaidasMovimentoPage::getUrl(), permission: 'ajuste_estoque.access', icon: 'heroicon-o-arrow-right-circle', iconColor: 'erp-menu-bar__icon--orange'),
            ]),
            static::sep(),
            static::stub('Fabricar Produto', icon: 'heroicon-o-wrench-screwdriver', iconColor: 'erp-menu-bar__icon--slate'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function comprasItems(): array
    {
        return [
            static::link('Lista Compras', CompraResource::getUrl('index'), permission: 'compras.access', icon: 'heroicon-o-building-storefront', iconColor: 'erp-menu-bar__icon--blue'),
            static::link('Análise e Sugestão de Compra', AnaliseComprasPage::getUrl(), permission: 'compras.access', icon: 'heroicon-o-chart-bar', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Notas de Fornecedores', NotaFornecedorResource::getUrl('index'), permission: 'compras.access', icon: 'heroicon-o-document-arrow-down', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Devolução de Compra', DevolucaoCompraResource::getUrl('index'), permission: 'devolucoes_compra.access', icon: 'heroicon-o-arrow-uturn-left', iconColor: 'erp-menu-bar__icon--amber'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function vendasItems(): array
    {
        $items = [
            static::link('Orçamento', OrcamentoResource::getUrl('index'), permission: 'orcamentos.access', icon: 'heroicon-o-document-text', iconColor: 'erp-menu-bar__icon--indigo'),
        ];

        if (static::pdvHabilitado()) {
            $items[] = static::link('PDV', PdvPage::getUrl(), permission: 'pdv.access', icon: 'heroicon-o-calculator', iconColor: 'erp-menu-bar__icon--blue');
        }

        $items[] = static::link(
            'Promoções',
            PromocaoResource::getUrl('index'),
            permission: 'promocoes.access',
            icon: 'heroicon-o-tag',
            iconColor: 'erp-menu-bar__icon--amber',
        );

        return [
            ...$items,
            static::stub('Delivery', icon: 'heroicon-o-truck', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Restaurante', icon: 'heroicon-o-building-storefront', iconColor: 'erp-menu-bar__icon--slate'),
            static::link('Lista de Vendas', VendaResource::getUrl('index'), permission: 'vendas.access', icon: 'heroicon-o-shopping-bag', iconColor: 'erp-menu-bar__icon--red'),
            static::link('Tela de Venda', ForcaVendasTelaVendaPage::getUrl(), permission: 'vendas.access', icon: 'heroicon-o-device-phone-mobile', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Monitor de Vendas', ForcaVendasMonitorResource::getUrl('index'), permission: 'vendas.access', icon: 'heroicon-o-computer-desktop', iconColor: 'erp-menu-bar__icon--green'),
            static::link('Devolução de Venda', DevolucaoVendaResource::getUrl('index'), permission: 'devolucoes_venda.access', icon: 'heroicon-o-arrow-uturn-left', iconColor: 'erp-menu-bar__icon--amber'),
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
            static::link('Forma de Pagamentos', FormaPagamentoResource::getUrl('index'), permission: 'formas_pagamento.access', icon: 'heroicon-o-credit-card', iconColor: 'erp-menu-bar__icon--blue'),
            static::link('Plano de Contas', PlanoContaResource::getUrl('index'), permission: 'planos_contas.access', icon: 'heroicon-o-queue-list', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Contas', CaixaContaResource::getUrl('index'), permission: 'contas_caixa.access', icon: 'heroicon-o-building-library', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Contas a Pagar', ContaPagarResource::getUrl('index'), permission: 'contas_pagar.access', icon: 'heroicon-o-arrow-up-circle', iconColor: 'erp-menu-bar__icon--red'),
            static::link('Contas a Receber', ContaReceberResource::getUrl('index'), permission: 'contas_receber.access', icon: 'heroicon-o-arrow-down-circle', iconColor: 'erp-menu-bar__icon--green'),
            static::link('Livro Caixa', CaixaResource::getUrl('index'), permission: 'caixa.access', icon: 'heroicon-o-banknotes', iconColor: 'erp-menu-bar__icon--amber'),
            static::sep(),
            static::link('Impressão de Recibos', ReciboResource::getUrl('index'), permission: 'recibos.access', icon: 'heroicon-o-printer', iconColor: 'erp-menu-bar__icon--orange'),
            static::sep(),
            static::group('Boleto', [
                static::link('Configuração', BoletoConfigPage::getUrl(), permission: 'boletos.access', icon: 'heroicon-o-cog-6-tooth', iconColor: 'erp-menu-bar__icon--slate'),
                static::link('Remessa', BoletoRemessaResource::getUrl('index'), permission: 'boletos.access', icon: 'heroicon-o-arrow-up-tray', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Retorno', BoletoRetornoResource::getUrl('index'), permission: 'boletos.access', icon: 'heroicon-o-arrow-down-tray', iconColor: 'erp-menu-bar__icon--teal'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function fiscalItems(): array
    {
        return [
            static::link('NFC-e', NfceResource::getUrl('index'), permission: 'nfce.access', icon: 'heroicon-o-receipt-percent', iconColor: 'erp-menu-bar__icon--orange'),
            static::link('NF-e', NfeResource::getUrl('index'), permission: 'nfe.access', icon: 'heroicon-o-document-arrow-up', iconColor: 'erp-menu-bar__icon--indigo'),
            static::stub('CTe-OS', icon: 'heroicon-o-document', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('CTe', icon: 'heroicon-o-truck', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('MDFe', icon: 'heroicon-o-map', iconColor: 'erp-menu-bar__icon--slate'),
            static::sep(),
            static::link('CFOP', CfopResource::getUrl('index'), permission: 'cfops.access', icon: 'heroicon-o-hashtag', iconColor: 'erp-menu-bar__icon--blue'),
            static::link('CFOP - Operações fiscais', OperacoesFiscaisPage::getUrl(), permission: 'cfops.access', icon: 'heroicon-o-adjustments-horizontal', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Tabela ICMS', TabelaIcmsPage::getUrl(), permission: 'tabela_icms.access', icon: 'heroicon-o-table-cells', iconColor: 'erp-menu-bar__icon--green'),
            static::sep(),
            static::stub('Sped Fiscal', icon: 'heroicon-o-archive-box', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Sped Contribuições', icon: 'heroicon-o-archive-box', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Sintegra', icon: 'heroicon-o-document-duplicate', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Enviar Sped', icon: 'heroicon-o-paper-airplane', iconColor: 'erp-menu-bar__icon--slate'),
            static::sep(),
            static::stub('Inventário por CSOSN / CST', icon: 'heroicon-o-clipboard-document-list', iconColor: 'erp-menu-bar__icon--slate'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function logisticaItems(): array
    {
        return [
            static::link('Controle de Expedição', ExpedicaoResource::getUrl('index'), permission: 'logistica.access', icon: 'heroicon-o-clipboard-document-check', iconColor: 'erp-menu-bar__icon--blue'),
            static::sep(),
            static::group('Transportadora', [
                static::link('Motorista / Transportador', TransportadoraResource::getUrl('index'), permission: 'transportadoras.access', icon: 'heroicon-o-user', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Veículos', VeiculoResource::getUrl('index'), permission: 'veiculos.access', icon: 'heroicon-o-truck', iconColor: 'erp-menu-bar__icon--orange'),
                static::link('Tomador de Serviço', TomadorServicoResource::getUrl('index'), permission: 'tomadores_servico.access', icon: 'heroicon-o-briefcase', iconColor: 'erp-menu-bar__icon--indigo'),
                static::link('Destinatário', LogisticaDestinatarioResource::getUrl('index'), permission: 'logistica_destinatarios.access', icon: 'heroicon-o-map-pin', iconColor: 'erp-menu-bar__icon--red'),
                static::link('Remetente', LogisticaRemetenteResource::getUrl('index'), permission: 'logistica_remetentes.access', icon: 'heroicon-o-home', iconColor: 'erp-menu-bar__icon--green'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function osItems(): array
    {
        return [
            static::link('Ordem de Serviço', OrdemServicoResource::getUrl('index'), permission: 'ordens_servico.access', icon: 'heroicon-o-wrench-screwdriver', iconColor: 'erp-menu-bar__icon--amber'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function rhItems(): array
    {
        return [
            static::link('Painel RH', RhDashboardPage::getUrl(), permission: 'rh.dashboard.access', icon: 'heroicon-o-chart-pie', iconColor: 'erp-menu-bar__icon--blue'),
            static::sep(),
            static::link('Funcionários', RhFuncionarioResource::getUrl('index'), permission: 'rh.funcionarios.access', icon: 'heroicon-o-identification', iconColor: 'erp-menu-bar__icon--teal'),
            static::link('Contador', ContadorResource::getUrl('index'), permission: 'contadores.access', icon: 'heroicon-o-calculator', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Cargos', RhCargoResource::getUrl('index'), permission: 'rh.cargos.access', icon: 'heroicon-o-briefcase', iconColor: 'erp-menu-bar__icon--amber'),
            static::link('Departamentos', RhDepartamentoResource::getUrl('index'), permission: 'rh.departamentos.access', icon: 'heroicon-o-building-office', iconColor: 'erp-menu-bar__icon--green'),
            static::sep(),
            static::stub('Documentos', icon: 'heroicon-o-document-text', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('EPIs', icon: 'heroicon-o-shield-check', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Exames / ASO', icon: 'heroicon-o-heart', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Treinamentos', icon: 'heroicon-o-academic-cap', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Uniformes', icon: 'heroicon-o-sparkles', iconColor: 'erp-menu-bar__icon--slate'),
            static::sep(),
            static::stub('Holerites', icon: 'heroicon-o-banknotes', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Benefícios', icon: 'heroicon-o-gift', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Ocorrências', icon: 'heroicon-o-exclamation-circle', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Férias', icon: 'heroicon-o-sun', iconColor: 'erp-menu-bar__icon--slate'),
            static::stub('Escalas', icon: 'heroicon-o-calendar-days', iconColor: 'erp-menu-bar__icon--slate'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function relatoriosItems(): array
    {
        return [
            static::group('Produtos', [
                static::link('Curva ABC', route('erp.reports.tabular', ['slug' => 'curva-abc']), permission: 'produtos.print', icon: 'heroicon-o-chart-bar', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Histórico de Produtos', route('erp.reports.tabular', ['slug' => 'historico-produtos']), permission: 'produtos.print', icon: 'heroicon-o-clock', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Histórico de Compras', route('erp.reports.tabular', ['slug' => 'historico-compras']), permission: 'compras.print', icon: 'heroicon-o-clock', iconColor: 'erp-menu-bar__icon--indigo'),
                static::link('Histórico de Compras p/ Fornecedor', route('erp.reports.tabular', ['slug' => 'historico-compras-fornecedor']), permission: 'compras.print', icon: 'heroicon-o-building-storefront', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Produtos Lucratividade', route('erp.reports.tabular', ['slug' => 'produtos-lucratividade']), permission: 'produtos.print', icon: 'heroicon-o-currency-dollar', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Produtos menos lucrativos', route('erp.reports.tabular', ['slug' => 'produtos-menos-lucrativos']), permission: 'produtos.print', icon: 'heroicon-o-arrow-trending-down', iconColor: 'erp-menu-bar__icon--red'),
                static::link('Produtos mais lucrativos', route('erp.reports.tabular', ['slug' => 'produtos-mais-lucrativos']), permission: 'produtos.print', icon: 'heroicon-o-arrow-trending-up', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Produtos menos vendidos', route('erp.reports.tabular', ['slug' => 'produtos-menos-vendidos']), permission: 'produtos.print', icon: 'heroicon-o-arrow-trending-down', iconColor: 'erp-menu-bar__icon--orange'),
                static::link('Produtos mais vendidos', route('erp.reports.tabular', ['slug' => 'produtos-mais-vendidos']), permission: 'produtos.print', icon: 'heroicon-o-arrow-trending-up', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Relatório de Produtos com Preço Alterado', route('erp.reports.tabular', ['slug' => 'preco-alterado']), permission: 'produtos.print', icon: 'heroicon-o-tag', iconColor: 'erp-menu-bar__icon--purple'),
                static::link('Relatório de Estoque - Composição', route('erp.reports.tabular', ['slug' => 'estoque-composicao']), permission: 'produtos.print', icon: 'heroicon-o-cube', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Relatório de Estoque - Grade', route('erp.reports.tabular', ['slug' => 'estoque-grade']), permission: 'produtos.print', icon: 'heroicon-o-table-cells', iconColor: 'erp-menu-bar__icon--indigo'),
                static::link('Relatório de Estoque - Mínimo', route('erp.reports.tabular', ['slug' => 'estoque-minimo']), permission: 'produtos.print', icon: 'heroicon-o-exclamation-triangle', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Relatório de Estoque - Negativo', route('erp.reports.tabular', ['slug' => 'estoque-negativo']), permission: 'produtos.print', icon: 'heroicon-o-minus-circle', iconColor: 'erp-menu-bar__icon--red'),
                static::sep(),
                static::link('Listagem - Conferência de Estoque', route('erp.reports.tabular', ['slug' => 'conferencia-estoque']), permission: 'produtos.print', icon: 'heroicon-o-clipboard-document-check', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Listagem - Validade de Produtos', route('erp.reports.tabular', ['slug' => 'validade-produtos']), permission: 'produtos.print', icon: 'heroicon-o-calendar-days', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Listagem - Validade por Lote', route('erp.reports.tabular', ['slug' => 'validade-lotes']), permission: 'produtos.print', icon: 'heroicon-o-queue-list', iconColor: 'erp-menu-bar__icon--teal'),
            ], icon: 'heroicon-o-cube', iconColor: 'erp-menu-bar__icon--amber'),
            static::group('Vendas', [
                static::link('Histórico de Vendas', route('erp.reports.tabular', ['slug' => 'historico-vendas']), permission: 'vendas.print', icon: 'heroicon-o-clock', iconColor: 'erp-menu-bar__icon--red'),
                static::link('Histórico de Orçamentos', route('erp.reports.tabular', ['slug' => 'historico-orcamentos']), permission: 'vendas.print', icon: 'heroicon-o-document-text', iconColor: 'erp-menu-bar__icon--indigo'),
                static::link('Histórico de Vendas p/ Cliente', route('erp.reports.tabular', ['slug' => 'historico-vendas-cliente']), permission: 'vendas.print', icon: 'heroicon-o-user', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Histórico de Vendas p/ Vendedor', route('erp.reports.tabular', ['slug' => 'historico-vendas-vendedor']), permission: 'vendas.print', icon: 'heroicon-o-user-group', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Relatório de Vendas por PDV', route('erp.reports.tabular', ['slug' => 'vendas-pdv']), permission: 'vendas.print', icon: 'heroicon-o-calculator', iconColor: 'erp-menu-bar__icon--orange'),
                static::link('Relatório Vendas por Forma de Pagamento', route('erp.reports.tabular', ['slug' => 'vendas-forma-pagamento']), permission: 'vendas.print', icon: 'heroicon-o-credit-card', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Relatório de Vendas por Produtos - Geral', route('erp.reports.tabular', ['slug' => 'vendas-produtos-geral']), permission: 'vendas.print', icon: 'heroicon-o-cube', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Relatório de Vendas de Produtos - Clientes', route('erp.reports.tabular', ['slug' => 'vendas-produtos-clientes']), permission: 'vendas.print', icon: 'heroicon-o-users', iconColor: 'erp-menu-bar__icon--purple'),
                static::link('Relatório de Vendas de Produtos - Vendedores', route('erp.reports.tabular', ['slug' => 'vendas-produtos-vendedores']), permission: 'vendas.print', icon: 'heroicon-o-shopping-bag', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Relatório de Vendas Por CFOP/CSOSN', route('erp.reports.tabular', ['slug' => 'vendas-cfop-csosn']), permission: 'vendas.print', icon: 'heroicon-o-hashtag', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Relatório de Vendas de Produtos c/ Trib.Monofásica', route('erp.reports.tabular', ['slug' => 'vendas-produtos-monofasica']), permission: 'vendas.print', icon: 'heroicon-o-receipt-percent', iconColor: 'erp-menu-bar__icon--indigo'),
            ], icon: 'heroicon-o-shopping-cart', iconColor: 'erp-menu-bar__icon--red'),
            static::group('Financeiro', [
                static::link('Relatório Comissão de Operadores', route('erp.reports.comissao-vendedores'), permission: 'vendas.print', icon: 'heroicon-o-banknotes', iconColor: 'erp-menu-bar__icon--green'),
                static::link('Relatório de Contas a Receber', route('erp.reports.tabular', ['slug' => 'contas-receber']), permission: 'contas_receber.print', icon: 'heroicon-o-arrow-down-circle', iconColor: 'erp-menu-bar__icon--teal'),
                static::link('Relatório de Contas a Pagar', route('erp.reports.tabular', ['slug' => 'contas-pagar']), permission: 'contas_pagar.print', icon: 'heroicon-o-arrow-up-circle', iconColor: 'erp-menu-bar__icon--red'),
                static::link('Relatório Resumo Caixa', route('erp.reports.tabular', ['slug' => 'resumo-caixa']), permission: 'caixa.print', icon: 'heroicon-o-chart-pie', iconColor: 'erp-menu-bar__icon--blue'),
                static::link('Relatório de Movimento Caixa', route('erp.reports.tabular', ['slug' => 'movimento-caixa']), permission: 'caixa.print', icon: 'heroicon-o-arrows-right-left', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Relatório Balanço Financeiro', route('erp.reports.tabular', ['slug' => 'balanco-financeiro']), permission: 'caixa.print', icon: 'heroicon-o-scale', iconColor: 'erp-menu-bar__icon--indigo'),
                static::link('Relatório Resumo Financeiro p/ Conta', route('erp.reports.tabular', ['slug' => 'resumo-financeiro-conta']), permission: 'contas_caixa.print', icon: 'heroicon-o-building-library', iconColor: 'erp-menu-bar__icon--orange'),
                static::link('Relatório Financeiro - Cartão', route('erp.reports.contas-receber-cartoes'), permission: 'contas_receber.print', icon: 'heroicon-o-credit-card', iconColor: 'erp-menu-bar__icon--purple'),
                static::link('Relatório por Plano de Contas', route('erp.reports.tabular', ['slug' => 'plano-contas']), permission: 'planos_contas.print', icon: 'heroicon-o-queue-list', iconColor: 'erp-menu-bar__icon--green'),
            ], icon: 'heroicon-o-banknotes', iconColor: 'erp-menu-bar__icon--green'),
            static::group('Auditoria', [
                static::link('Log de Operações', ErpOperacaoLogResource::getUrl('index'), permission: 'vendas.access', icon: 'heroicon-o-clipboard-document-list', iconColor: 'erp-menu-bar__icon--slate'),
            ], icon: 'heroicon-o-clipboard-document-list', iconColor: 'erp-menu-bar__icon--slate'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function configuracoesItems(): array
    {
        return [
            static::link('Empresa', EmpresaResource::getUrl('index'), permission: 'empresa.access', icon: 'heroicon-o-building-office-2', iconColor: 'erp-menu-bar__icon--blue'),
            static::link('Terminais', TerminalResource::getUrl('index'), permission: 'terminais.access', icon: 'heroicon-o-computer-desktop', iconColor: 'erp-menu-bar__icon--teal'),
            static::stub('Mesas', icon: 'heroicon-o-table-cells', iconColor: 'erp-menu-bar__icon--slate'),
            static::link('Config. Fiscais', ConfigFiscaisPage::getUrl(), permission: 'config_fiscais.access', icon: 'heroicon-o-document-check', iconColor: 'erp-menu-bar__icon--indigo'),
            static::link('Balança', BalancaConfigPage::getUrl(), permission: 'balanca.access', icon: 'heroicon-o-scale', iconColor: 'erp-menu-bar__icon--amber'),
            static::link('Backup', BackupPage::getUrl(), permission: 'backup.access', icon: 'heroicon-o-circle-stack', iconColor: 'erp-menu-bar__icon--green'),
            static::sep(),
            static::group('Comandos', [
                static::link('Migra dados FB', MigraFirebirdPage::getUrl(), permission: 'migra_firebird.access', icon: 'heroicon-o-arrow-path', iconColor: 'erp-menu-bar__icon--orange'),
                static::link('Aquecer Sistema', url('/admin/comandos-sistema').'?foco=aquecer', permission: 'comandos.access', icon: 'heroicon-o-bolt', iconColor: 'erp-menu-bar__icon--amber'),
                static::link('Info do Sistema', url('/admin/comandos-sistema').'?foco=info', permission: 'comandos.access', icon: 'heroicon-o-information-circle', iconColor: 'erp-menu-bar__icon--blue'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function ajudaItems(): array
    {
        return [
            static::stub('Vídeos de Treinamento', icon: 'heroicon-o-play-circle', iconColor: 'erp-menu-bar__icon--slate'),
            static::link('Licença do Sistema', LicencaSistemaPage::getUrl(), icon: 'heroicon-o-shield-check', iconColor: 'erp-menu-bar__icon--indigo'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function link(string $label, string $url, ?string $shortcut = null, ?string $permission = null, ?string $icon = null, ?string $iconColor = null): array
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

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        if ($iconColor !== null) {
            $item['icon_color'] = $iconColor;
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
    protected static function stub(string $label, ?string $shortcut = null, ?string $icon = null, ?string $iconColor = null): array
    {
        $item = [
            'label' => $label,
            'pending' => true,
        ];

        if ($shortcut !== null) {
            $item['shortcut'] = $shortcut;
        }

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        if ($iconColor !== null) {
            $item['icon_color'] = $iconColor;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function action(string $label, string $action, ?string $shortcut = null, ?string $icon = null, ?string $iconColor = null): array
    {
        $item = [
            'label' => $label,
            'action' => $action,
        ];

        if ($shortcut !== null) {
            $item['shortcut'] = $shortcut;
        }

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        if ($iconColor !== null) {
            $item['icon_color'] = $iconColor;
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
    protected static function group(string $label, array $items, ?string $icon = null, ?string $iconColor = null): array
    {
        $group = [
            'label' => $label,
            'items' => $items,
        ];

        if ($icon !== null) {
            $group['icon'] = $icon;
        }

        if ($iconColor !== null) {
            $group['icon_color'] = $iconColor;
        }

        return $group;
    }
}
