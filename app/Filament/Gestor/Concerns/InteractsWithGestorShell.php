<?php

namespace App\Filament\Gestor\Concerns;

use App\Support\Erp\ErpAccess;
use App\Support\Gestor\GestorExecutivoService;
use Illuminate\Support\Facades\Auth;

trait InteractsWithGestorShell
{
    public string $gestorTema = 'light';

    public function mountGestorShell(): void
    {
        $tema = (string) (request()->cookie('gestor_tema') ?? session('gestor_tema', 'light'));
        $this->gestorTema = in_array($tema, ['light', 'dark'], true) ? $tema : 'light';
    }

    public function toggleTema(): void
    {
        $this->gestorTema = $this->gestorTema === 'dark' ? 'light' : 'dark';
        session(['gestor_tema' => $this->gestorTema]);
        cookie()->queue(cookie('gestor_tema', $this->gestorTema, 60 * 24 * 365));
    }

    public function logoutGestor(): void
    {
        Auth::logout();
        session()->forget('erp_empresa_id');
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(filament()->getLoginUrl());
    }

    public function usuarioNome(): string
    {
        return (string) (Auth::user()?->name ?? '');
    }

    public function empresaNome(): string
    {
        $id = app(GestorExecutivoService::class)->empresaId();

        if ($id <= 0) {
            return '';
        }

        return (string) (\App\Models\Empresa::query()->whereKey($id)->value('nome') ?? '');
    }

    /**
     * @return list<array{key: string, label: string, icon: string, url: string, active: bool}>
     */
    public function bottomNav(): array
    {
        $current = static::class;

        return [
            [
                'key' => 'home',
                'label' => 'Início',
                'icon' => 'home',
                'url' => \App\Filament\Gestor\Pages\DashboardGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\DashboardGestorPage::class,
            ],
            [
                'key' => 'fin',
                'label' => 'Financeiro',
                'icon' => 'fin',
                'url' => \App\Filament\Gestor\Pages\FinanceiroGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\FinanceiroGestorPage::class,
            ],
            [
                'key' => 'vendas',
                'label' => 'Vendas',
                'icon' => 'vendas',
                'url' => \App\Filament\Gestor\Pages\VendasGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\VendasGestorPage::class,
            ],
            [
                'key' => 'estoque',
                'label' => 'Estoque',
                'icon' => 'estoque',
                'url' => \App\Filament\Gestor\Pages\EstoqueGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\EstoqueGestorPage::class
                    || $current === \App\Filament\Gestor\Pages\ProdutosGestorPage::class,
            ],
            [
                'key' => 'mais',
                'label' => 'Mais',
                'icon' => 'mais',
                'url' => \App\Filament\Gestor\Pages\MaisGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\MaisGestorPage::class
                    || $current === \App\Filament\Gestor\Pages\AprovacoesGestorPage::class,
            ],
        ];
    }

    public static function canAccessGestor(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return ErpAccess::can($user, 'produtos.access')
            || ErpAccess::can($user, 'ajusta_preco.access')
            || ErpAccess::can($user, 'ajuste_estoque.access')
            || (bool) $user->is_admin
            || (bool) $user->is_supervisor;
    }

    public function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
