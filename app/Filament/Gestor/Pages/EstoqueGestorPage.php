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
        $this->saudeEstoque = $this->saudeEstoqueDaEmpresa($empresaId);
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

    /**
     * Mesma base da lista de críticos e do contador de estoque baixo.
     * Sem saldo por empresa, permanece o gauge do dashboard ERP.
     *
     * @return array<string, mixed>
     */
    private function saudeEstoqueDaEmpresa(int $empresaId): array
    {
        $saldos = app(ProductEstoqueSaldoService::class);
        if ($empresaId <= 0 || ! $saldos->suportaEstoquePorEmpresa($empresaId)) {
            return ErpDashboardGauges::saudeEstoqueGauge();
        }

        $expr = $saldos->sqlEstoqueEmpresaExpression($saldos->estoqueIdParaEmpresa($empresaId));
        $row = Product::query()
            ->where('ativo', true)
            ->selectRaw(
                'COUNT(*) as total,'.
                "COALESCE(SUM(CASE WHEN ({$expr}) <= 0 THEN 0 WHEN COALESCE(estoque_minimo, 0) > 0 AND ({$expr}) < estoque_minimo THEN 0 ELSE 1 END), 0) as ok,".
                "COALESCE(SUM(CASE WHEN COALESCE(estoque_minimo, 0) > 0 AND ({$expr}) < estoque_minimo THEN 1 ELSE 0 END), 0) as critico"
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        $ok = (int) ($row->ok ?? 0);
        $critico = (int) ($row->critico ?? 0);
        $percent = $total > 0 ? round(($ok / $total) * 100, 1) : 0.0;

        $tone = match (true) {
            $percent < 20 => 'red',
            $percent < 40 => 'orange',
            $percent < 60 => 'yellow',
            $percent < 80 => 'lime',
            default => 'green',
        };

        return [
            'label' => 'Saúde do Estoque',
            'percent' => $percent,
            'display_percent' => number_format($percent, 1, ',', '').'%',
            'meta_label' => 'Produtos: '.number_format($total, 0, ',', '.'),
            'stat_left_label' => 'OK',
            'stat_left' => number_format($ok, 0, ',', '.'),
            'stat_right_label' => 'Crítico',
            'stat_right' => number_format($critico, 0, ',', '.'),
            'stat_right_tone' => $critico > 0 ? 'orange' : '',
            'tone' => $tone,
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
