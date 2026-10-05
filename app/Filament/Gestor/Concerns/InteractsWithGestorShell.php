<?php

namespace App\Filament\Gestor\Concerns;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Gestor\GestorExecutivoService;
use App\Support\Inventario\InventarioAcesso;
use Illuminate\Support\Facades\Auth;

trait InteractsWithGestorShell
{
    public string $gestorTema = 'light';

    public ?int $empresaGestorId = null;

    public function mountGestorShell(): void
    {
        $tema = (string) (request()->cookie('gestor_tema') ?? session('gestor_tema', 'light'));
        $this->gestorTema = in_array($tema, ['light', 'dark'], true) ? $tema : 'light';
        $this->empresaGestorId = $this->empresaIdAtiva();
    }

    public function toggleTema(): void
    {
        $this->gestorTema = $this->gestorTema === 'dark' ? 'light' : 'dark';
        session(['gestor_tema' => $this->gestorTema]);
        cookie()->queue(cookie('gestor_tema', $this->gestorTema, 60 * 24 * 365));
    }

    /**
     * @return array<int, string>
     */
    public function empresasGestor(): array
    {
        $user = Auth::user();
        if ($user === null) {
            return [];
        }

        $ids = $user->accessibleEmpresaIds();
        if ($ids === []) {
            return [];
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn ($nome, $id): array => [(int) $id => (string) $nome])
            ->all();
    }

    public function empresaIdAtiva(): int
    {
        return app(GestorExecutivoService::class)->empresaId();
    }

    public function updatedEmpresaGestorId(mixed $value): void
    {
        $this->switchEmpresaGestor((int) $value);
    }

    public function switchEmpresaGestor(int $empresaId): void
    {
        $user = Auth::user();
        if ($user === null || $empresaId <= 0) {
            return;
        }

        if (! ErpContext::userCanAccessEmpresa($empresaId, $user)) {
            $this->empresaGestorId = $this->empresaIdAtiva();

            return;
        }

        if ($empresaId === $this->empresaIdAtiva()) {
            $this->empresaGestorId = $empresaId;

            return;
        }

        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();
        $this->empresaGestorId = $empresaId;

        $this->redirect(static::getUrl(panel: 'gestor'));
    }

    public function logoutGestor(): void
    {
        Auth::logout();
        session()->forget('erp_empresa_id');
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(filament()->getLoginUrl());
    }

    public function podeAbrirInventario(): bool
    {
        $user = Auth::user();

        return $user instanceof User && InventarioAcesso::podeEntrar($user);
    }

    public function usuarioNome(): string
    {
        return (string) (Auth::user()?->name ?? '');
    }

    public function empresaNome(): string
    {
        $id = $this->empresaIdAtiva();

        if ($id <= 0) {
            return '';
        }

        return (string) (Empresa::query()->whereKey($id)->value('nome') ?? '');
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
                'visible' => static::podeVerFinanceiroGestor(),
            ],
            [
                'key' => 'vendas',
                'label' => 'Vendas',
                'icon' => 'vendas',
                'url' => \App\Filament\Gestor\Pages\VendasGestorPage::getUrl(panel: 'gestor'),
                'active' => $current === \App\Filament\Gestor\Pages\VendasGestorPage::class,
                'visible' => static::podeVerVendasGestor(),
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

        if (! $user->podeAcessarApp(\App\Models\User::APP_GESTAO)) {
            return false;
        }

        return ErpAccess::can($user, 'produtos.access')
            || ErpAccess::can($user, 'ajusta_preco.access')
            || ErpAccess::can($user, 'ajuste_estoque.access')
            || (bool) $user->is_admin;
    }

    public static function podeVerFinanceiroGestor(): bool
    {
        $user = Auth::user();

        if ($user === null || ! static::canAccessGestor()) {
            return false;
        }

        return ErpAccess::can($user, 'contas_receber.access')
            || ErpAccess::can($user, 'contas_pagar.access')
            || ErpAccess::can($user, 'caixa.access')
            || ErpAccess::can($user, 'contas_caixa.access');
    }

    public static function podeVerVendasGestor(): bool
    {
        $user = Auth::user();

        if ($user === null || ! static::canAccessGestor()) {
            return false;
        }

        return ErpAccess::can($user, 'vendas.access');
    }

    public static function podeVerAprovacoesGestor(): bool
    {
        $user = Auth::user();

        if ($user === null || ! static::canAccessGestor()) {
            return false;
        }

        return ErpAccess::can($user, 'forca_vendas.access')
            || ErpAccess::can($user, 'vendas.access');
    }

    public function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
