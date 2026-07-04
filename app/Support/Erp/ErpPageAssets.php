<?php

namespace App\Support\Erp;

class ErpPageAssets
{
    /** @var array<string, list<string>> */
    private const LIST_MODULE_STYLES = [
        'products' => ['css/erp-produtos.css'],
        'people' => ['css/erp-pessoas.css'],
        'empresas' => ['css/erp-empresas.css'],
        'vendedores' => ['css/erp-vendedores.css', 'css/erp-form-ui.css'],
        'entregadores' => ['css/erp-entregadores.css'],
        'contadores' => ['css/erp-contadores.css', 'css/erp-form-ui.css'],
        'terminais' => ['css/erp-terminais.css'],
        'aniversariantes' => ['css/erp-aniversariantes.css'],
        'compras' => ['css/erp-compras.css'],
        'vendas' => ['css/erp-vendas.css'],
        'orcamentos' => ['css/erp-orcamentos.css', 'css/erp-form-ui.css', 'css/erp-orcamentos-form.css'],
        'caixa' => ['css/erp-caixa.css'],
        'contas-receber' => ['css/erp-receber.css'],
        'contas-pagar' => ['css/erp-pagar.css'],
        'formas-pagamento' => ['css/erp-estoque-menus.css'],
        'contas-caixa' => ['css/erp-estoque-menus.css'],
        'grupos' => ['css/erp-estoque-menus.css'],
        'marcas' => ['css/erp-estoque-menus.css'],
        'unidades' => ['css/erp-estoque-menus.css'],
        'ajustes-estoque' => ['css/erp-estoque-menus.css'],
        'ajusta-estoque-grupo' => ['css/erp-estoque-menus.css'],
        'ajusta-precos' => ['css/erp-estoque-menus.css'],
        'impressao-etiquetas' => ['css/erp-estoque-menus.css'],
        'impressao-etiquetas-novo' => ['css/erp-estoque-menus.css'],
        'zera-estoque-negativo' => ['css/erp-estoque-menus.css'],
        'nfce' => ['css/erp-nfe.css'],
        'nfe' => ['css/erp-nfe.css'],
        'config-fiscais' => ['css/erp-config-fiscais.css'],
        'forca-vendas-pedidos' => ['css/erp-forca-vendas.css'],
        'forca-vendas-aparelhos' => ['css/erp-forca-vendas.css'],
        'forca-vendas-monitor' => ['css/erp-nfe.css', 'css/erp-forca-vendas.css', 'css/erp-fv-monitor.css'],
        'pdv' => ['css/erp-pdv.css'],
        'usuarios' => ['css/erp-acesso.css', 'css/erp-form-ui.css'],
        'permissoes' => ['css/erp-acesso.css'],
    ];

    /** @var array<string, list<string>> */
    private const CARDEX_MODULE_STYLES = [
        'products' => ['css/erp-produtos-form.css', 'css/erp-produtos.css'],
    ];

    /** @var array<string, list<string>> */
    private const FORM_MODULE_STYLES = [
        'products' => ['css/erp-produtos-form.css'],
        'people' => ['css/erp-pessoas-form.css'],
        'empresas' => ['css/erp-empresas-form.css'],
        'terminais' => ['css/erp-terminais-form.css', 'css/erp-terminais.css'],
        'orcamentos' => ['css/erp-orcamentos-form.css'],
        'config-fiscais' => ['css/erp-config-fiscais.css'],
    ];

    /**
     * CSS base compartilhado (grade + listagem ou formulário).
     *
     * @return list<string>
     */
    public static function coreStylesheets(): array
    {
        if (! self::shouldLoadAuthenticatedAssets()) {
            return [];
        }

        $styles = match (self::routeKind()) {
            'form' => ['css/erp-form-ui.css'],
            'cardex' => ['css/erp-form-ui.css'],
            'pdv' => [],
            'list' => ['css/erp-grid.css', 'css/erp-list-ui.css'],
            'dashboard' => ['css/erp-home.css'],
            default => [],
        };

        return $styles;
    }

    /**
     * CSS específico do módulo da rota atual (um arquivo na maioria das telas).
     *
     * @return list<string>
     */
    public static function moduleStylesheets(): array
    {
        if (! self::shouldLoadAuthenticatedAssets()) {
            return [];
        }

        $segment = self::resourceSegment();

        if ($segment === null) {
            return [];
        }

        return match (self::routeKind()) {
            'form' => self::FORM_MODULE_STYLES[$segment] ?? [],
            'cardex' => self::CARDEX_MODULE_STYLES[$segment] ?? [],
            'pdv' => self::LIST_MODULE_STYLES['pdv'],
            'list' => self::LIST_MODULE_STYLES[$segment] ?? [],
            default => [],
        };
    }

    public static function shouldLoadAuthenticatedAssets(): bool
    {
        if (! filament()->auth()->check()) {
            return false;
        }

        $path = request()->path();

        return $path === 'admin' || str_starts_with($path, 'admin/');
    }

    public static function resourceSegment(): ?string
    {
        $path = trim(request()->path(), '/');

        if (! str_starts_with($path, 'admin')) {
            return null;
        }

        $suffix = trim(substr($path, strlen('admin')), '/');

        if ($suffix === '') {
            return null;
        }

        return explode('/', $suffix)[0] ?: null;
    }

    public static function routeKind(): string
    {
        $path = request()->path();

        if ($path === 'admin/pdv' || str_starts_with($path, 'admin/pdv/')) {
            return 'pdv';
        }

        if ($path === 'admin') {
            return 'dashboard';
        }

        if (str_ends_with($path, '/create') || preg_match('#/edit$#', $path)) {
            return 'form';
        }

        if (preg_match('#/cardex$#', $path)) {
            return 'cardex';
        }

        $segment = self::resourceSegment();

        if ($segment === null) {
            return 'other';
        }

        $parts = explode('/', trim(substr($path, strlen('admin/')), '/'));

        if (count($parts) === 1) {
            if (in_array($segment, ['terminais', 'config-fiscais'], true)) {
                return 'form';
            }

            return 'list';
        }

        return 'other';
    }
}
