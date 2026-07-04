<?php

namespace App\Support\Erp;

use App\Filament\Pages\ImpressaoEtiquetasNovoPage;
use App\Filament\Pages\ConfigFiscaisPage;
use App\Filament\Pages\ForcaVendasAppPage;
use App\Filament\Pages\PermissoesPage;
use App\Filament\Resources\ForcaVendasDeviceResource;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Filament\Resources\ForcaVendasPedidoResource;
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
use App\Filament\Resources\CompraResource;
use App\Filament\Resources\ContaPagarResource;
use App\Filament\Resources\ContaReceberResource;
use App\Filament\Resources\ContadorResource;
use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\TerminalResource;
use App\Filament\Resources\EntregadorResource;
use App\Filament\Resources\FormaPagamentoResource;
use App\Filament\Resources\GrupoResource;
use App\Filament\Resources\ImpressaoEtiquetaResource;
use App\Filament\Resources\MarcaResource;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UnidadeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VendaResource;
use App\Filament\Resources\VendasInternasDeviceResource;

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
            static::stub('Alterar Senha'),
            static::stub('Trocar de Usuário'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function pessoasItems(): array
    {
        return [
            static::link('Contatos', PersonResource::getUrl('index') . '?tipo=todos', 'F2', 'pessoas.access'),
            static::link('Colaboradores', VendedorResource::getUrl('index'), permission: 'vendedores.access'),
            static::link('Entregador', EntregadorResource::getUrl('index'), permission: 'entregadores.access'),
            static::link('Contador', ContadorResource::getUrl('index'), permission: 'contadores.access'),
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
            static::link('Ajusta Preço', AjustaPrecoResource::getUrl('index'), permission: 'ajusta_preco.access'),
            static::link('Ajusta Estoque', AjusteEstoqueResource::getUrl('index'), permission: 'ajuste_estoque.access'),
            static::link('Ajuste Estoque Grupo', AjustaEstoqueGrupoResource::getUrl('index'), permission: 'ajuste_estoque.access'),
            static::stub('Ajusta Saldo de Estoque'),
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
            static::stub('Notas de Fornecedores'),
            static::stub('Devolução de Compra'),
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
            static::link('Monitor de Vendas', ForcaVendasMonitorResource::getUrl('index'), permission: 'vendas.access'),
            static::stub('Devolução de Venda'),
        ];
    }

    protected static function pdvHabilitado(): bool
    {
        return PdvConfig::make()->usarPdvRetaguarda();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function financeiroItems(): array
    {
        return [
            static::link('Forma de Pagamentos', FormaPagamentoResource::getUrl('index'), permission: 'formas_pagamento.access'),
            static::stub('Tabela de Preço'),
            static::stub('Plano de Contas'),
            static::link('Contas', CaixaContaResource::getUrl('index'), permission: 'contas_caixa.access'),
            static::link('Contas a Pagar', ContaPagarResource::getUrl('index'), permission: 'contas_pagar.access'),
            static::link('Contas a Receber', ContaReceberResource::getUrl('index'), permission: 'contas_receber.access'),
            static::stub('Ficha de Clientes'),
            static::link('Livro Caixa', CaixaResource::getUrl('index'), permission: 'caixa.access'),
            static::stub('Transferência de Contas'),
            static::sep(),
            static::stub('Impressão de Recibo'),
            static::sep(),
            static::group('Boleto', [
                static::stub('Configuração'),
                static::stub('Remessa'),
                static::stub('Retorno'),
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
            static::stub('Lote XML NFC-e'),
            static::stub('Lote XML NF-e (Compra)'),
            static::stub('Lote XML NF-e (Venda)'),
            static::stub('CFOP'),
            static::stub('IBPT'),
            static::stub('Tabela ICMS'),
            static::sep(),
            static::stub('Sped Fiscal'),
            static::stub('Sped Contribuições'),
            static::stub('Sintegra'),
            static::stub('Enviar Sped'),
            static::sep(),
            static::stub('Inventário por CSOSN / CST'),
            static::group('Transportadora', [
                static::stub('Motorista / Transportador'),
                static::stub('Veículos'),
                static::stub('Tomador de Serviço'),
                static::stub('Destinatário'),
                static::stub('Remetente'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function osItems(): array
    {
        return [
            static::stub('Ordem de Serviço'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function forcaVendaItems(): array
    {
        return [
            static::link('Orçamentos recebidos', ForcaVendasPedidoResource::getUrl('index'), permission: 'forca_vendas.access'),
            static::link('Aparelhos (autorizar)', ForcaVendasDeviceResource::getUrl('index'), permission: 'forca_vendas.access'),
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
                static::stub('Curva ABC'),
                static::stub('Histórico de Produtos'),
                static::stub('Histórico de Compras'),
                static::stub('Histórico de Compras p/ Fornecedor'),
                static::stub('Produtos Lucratividade'),
                static::stub('Produtos menos lucrativos'),
                static::stub('Produtos mais lucrativos'),
                static::stub('Produtos menos vendidos'),
                static::stub('Produtos mais vendidos'),
                static::stub('Relatório de Produtos com Preço Alterado'),
                static::stub('Relatório de Estoque - Composição'),
                static::stub('Relatório de Estoque - Grade'),
                static::stub('Relatório de Estoque - Mínimo'),
                static::stub('Relatório de Estoque - Negativo'),
                static::sep(),
                static::stub('Listagem - Conferência de Estoque'),
            ]),
            static::group('Vendas', [
                static::stub('Histórico de Vendas'),
                static::stub('Histórico de Orçamentos'),
                static::stub('Histórico de Vendas p/ Cliente'),
                static::stub('Histórico de Vendas p/ Vendedor'),
                static::stub('Relatório de Vendas por PDV'),
                static::stub('Relatório Comissão de Vendedores'),
                static::stub('Relatório Vendas por Forma de Pagamento'),
                static::stub('Relatório de Vendas por Produtos - Geral'),
                static::stub('Relatório de Vendas de Produtos - Clientes'),
                static::stub('Relatório de Vendas de Produtos - Vendedores'),
                static::stub('Relatório de Vendas Por CFOP/CSOSN'),
                static::stub('Relatório de Vendas de Produtos c/ Trib.Monofásica'),
            ]),
            static::group('Financeiro', [
                static::link('Relatório Comissão de Vendedores', route('erp.reports.comissao-vendedores'), permission: 'vendas.print'),
                static::stub('Relatório de Contas a Receber'),
                static::stub('Relatório de Contas a Pagar'),
                static::stub('Relatório Resumo Caixa'),
                static::stub('Relatório de Movimento Caixa'),
                static::stub('Relatório Balanço Financeiro'),
                static::stub('Relatório Resumo Financeiro p/ Conta'),
                static::stub('Relatório Financeiro - Cartão'),
                static::stub('Relatório por Plano de Contas'),
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
            static::stub('Balança'),
            static::stub('SoftHouse'),
            static::stub('Backup'),
            static::stub('Abrir WhatsApp'),
            static::sep(),
            static::group('Comandos', [
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
            static::stub('Licença do Sistema'),
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
     * @return array<string, string>
     */
    protected static function stub(string $label, ?string $shortcut = null): array
    {
        $item = ['label' => $label];

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
