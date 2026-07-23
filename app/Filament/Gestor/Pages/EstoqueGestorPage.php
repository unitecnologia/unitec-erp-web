<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Models\Product;
use App\Support\Gestor\GestorExecutivoService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class EstoqueGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'estoque';

    protected static ?string $title = 'Estoque';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.estoque';

    /** @var array<string, mixed> */
    public array $snapshot = [];

    /** @var list<array{id: int, codigo: string, descricao: string, estoque: float, minimo: float}> */
    public array $criticos = [];

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->snapshot = app(GestorExecutivoService::class)->snapshot();
        $this->criticos = Product::query()
            ->estoqueCritico()
            ->orderBy('descricao')
            ->limit(30)
            ->get(['id', 'codigo', 'descricao', 'estoque', 'estoque_minimo'])
            ->map(fn (Product $p): array => [
                'id' => (int) $p->id,
                'codigo' => (string) ($p->codigo ?? ''),
                'descricao' => (string) ($p->descricao ?? ''),
                'estoque' => round((float) $p->estoque, 3),
                'minimo' => round((float) $p->estoque_minimo, 3),
            ])
            ->all();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
