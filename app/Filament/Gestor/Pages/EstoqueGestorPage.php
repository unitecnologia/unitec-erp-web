<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Models\Product;
use App\Support\Erp\Dashboard\ErpDashboardGauges;
use App\Support\Erp\ProductEstoqueSaldoService;
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

    /** @var array<string, mixed> */
    public array $saudeEstoque = [];

    /** @var list<array{id: int, codigo: string, descricao: string, estoque: float, minimo: float}> */
    public array $criticos = [];

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $empresaId = app(GestorExecutivoService::class)->empresaId();
        // Calcula o gauge uma vez; o snapshot reutiliza o memo de saudeEstoque.
        $this->saudeEstoque = ErpDashboardGauges::saudeEstoqueGauge();
        $this->snapshot = app(GestorExecutivoService::class)->snapshot($empresaId);
        $this->criticos = $this->listarCriticos($empresaId);
    }

    /**
     * @return list<array{id: int, codigo: string, descricao: string, estoque: float, minimo: float}>
     */
    private function listarCriticos(int $empresaId): array
    {
        $saldos = app(ProductEstoqueSaldoService::class);

        if ($empresaId > 0 && $saldos->suportaEstoquePorEmpresa($empresaId)) {
            $expr = $saldos->sqlEstoqueEmpresaExpression($saldos->estoqueIdParaEmpresa($empresaId));
            $products = $saldos->tabelaProductsSql();

            return Product::query()
                ->select(['id', 'codigo', 'descricao', 'estoque_minimo'])
                ->selectRaw("{$expr} as estoque_loja")
                ->where('ativo', true)
                ->where('estoque_minimo', '>', 0)
                ->whereRaw("{$expr} < {$products}.estoque_minimo")
                ->orderBy('descricao')
                ->limit(30)
                ->get()
                ->map(fn (Product $p): array => [
                    'id' => (int) $p->id,
                    'codigo' => (string) ($p->codigo ?? ''),
                    'descricao' => (string) ($p->descricao ?? ''),
                    'estoque' => round((float) ($p->estoque_loja ?? 0), 3),
                    'minimo' => round((float) $p->estoque_minimo, 3),
                ])
                ->all();
        }

        return Product::query()
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
