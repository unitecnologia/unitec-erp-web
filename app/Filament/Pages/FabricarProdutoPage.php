<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Fabricacao\FabricacaoException;
use App\Support\Erp\Fabricacao\FabricacaoService;
use App\Support\Erp\Os\OsProdutoBusca;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Throwable;

class FabricarProdutoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $title = '';

    protected static ?string $slug = 'fabricar-produto';

    protected static bool $shouldRegisterNavigation = false;

    public string $produtoSearch = '';

    public ?int $productId = null;

    public string $quantidade = '1,000';

    public string $idempotenciaToken = '';

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array<int, array<string, mixed>> */
    public array $produtoSuggestions = [];

    public bool $suggestionsOpen = false;

    public bool $confirmando = false;

    protected bool $muteSearchHook = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ajuste_estoque.access')
            && ErpAccess::currentCan('ajuste_estoque.create');
    }

    public function mount(): void
    {
        ErpScreen::set('Fabricar Produto');
        $this->renovarIdempotenciaToken();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-fabricar-produto-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.fabricar-produto.screen'),
            ]);
    }

    public function updatedProdutoSearch(): void
    {
        if ($this->muteSearchHook) {
            return;
        }

        $term = mb_strtoupper(trim($this->produtoSearch), 'UTF-8');
        if ($term !== $this->produtoSearch) {
            $this->produtoSearch = $term;
        }

        $this->productId = null;
        $this->preview = null;
        $this->refreshSuggestions();
    }

    public function updatedQuantidade(): void
    {
        if ($this->productId) {
            $this->recalcularPreview();
        }
    }

    public function openSuggestions(): void
    {
        $this->refreshSuggestions();
    }

    public function closeSuggestions(): void
    {
        $this->produtoSuggestions = [];
        $this->suggestionsOpen = false;
    }

    public function selectProduto(int $productId): void
    {
        $product = Product::query()
            ->whereKey($productId)
            ->first(['id', 'codigo', 'descricao', 'unidade']);

        if (! $product) {
            return;
        }

        $this->muteSearchHook = true;
        try {
            $this->productId = (int) $product->id;
            $this->produtoSearch = trim((string) ($product->codigo.' — '.$product->descricao));
        } finally {
            $this->muteSearchHook = false;
        }

        $this->closeSuggestions();
        $this->renovarIdempotenciaToken();
        $this->recalcularPreview();
    }

    public function confirmarFabricacao(): void
    {
        if ($this->confirmando) {
            return;
        }

        $this->confirmando = true;

        try {
            if (! $this->productId) {
                throw new FabricacaoException('Selecione o produto a fabricar.');
            }

            $qtd = BrDecimal::parse($this->quantidade ?: '0', 3);
            $service = app(FabricacaoService::class);

            $fab = $service->confirmar([
                'product_id' => (int) $this->productId,
                'quantidade' => $qtd,
                'idempotencia_token' => $this->idempotenciaToken !== ''
                    ? $this->idempotenciaToken
                    : (string) Str::uuid(),
                'estoque_id' => (int) ($this->preview['estoque_id'] ?? 0) ?: null,
                'empresa_id' => (int) ($this->preview['empresa_id'] ?? 0) ?: null,
            ]);

            Notification::make()
                ->title('Fabricação '.$fab->numero.' concluída.')
                ->body(
                    'Produzido: '.number_format((float) $fab->quantidade, 3, ',', '.')
                    .' | Custo total R$ '.number_format((float) $fab->custo_total, 2, ',', '.')
                )
                ->success()
                ->send();

            $this->limparAposSucesso();
        } catch (AuthorizationException $e) {
            Notification::make()->title('Acesso negado')->body($e->getMessage())->danger()->send();
        } catch (FabricacaoException $e) {
            Notification::make()->title('Fabricação bloqueada')->body($e->getMessage())->warning()->send();
            $this->recalcularPreview();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erro ao fabricar')
                ->body('A operação foi cancelada (rollback). '.$e->getMessage())
                ->danger()
                ->send();
            $this->recalcularPreview();
        } finally {
            $this->confirmando = false;
        }
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    protected function recalcularPreview(): void
    {
        if (! $this->productId) {
            $this->preview = null;

            return;
        }

        try {
            $qtd = BrDecimal::parse($this->quantidade ?: '0', 3);
            $this->preview = app(FabricacaoService::class)->preview((int) $this->productId, $qtd);
        } catch (AuthorizationException $e) {
            $this->preview = null;
            Notification::make()->title('Acesso negado')->body($e->getMessage())->danger()->send();
        } catch (FabricacaoException $e) {
            $this->preview = [
                'bloqueios' => [$e->getMessage()],
                'ingredientes' => [],
                'pode_confirmar' => false,
                'custo_total' => 0,
                'custo_unitario' => 0,
                'quantidade' => BrDecimal::parse($this->quantidade ?: '0', 3),
            ];
        }
    }

    protected function refreshSuggestions(): void
    {
        $term = trim($this->produtoSearch);
        if (mb_strlen($term) < 1) {
            $this->produtoSuggestions = [];
            $this->suggestionsOpen = false;

            return;
        }

        $query = Product::query()
            ->where('ativo', true)
            ->where('is_servico', false)
            ->where('is_composicao', false)
            ->whereHas('receita', fn ($q) => $q->where('ativo', true))
            ->select(['id', 'codigo', 'descricao', 'unidade']);

        OsProdutoBusca::aplicar($query, $term);

        $this->produtoSuggestions = $query
            ->limit(12)
            ->get()
            ->map(fn (Product $p): array => [
                'id' => (int) $p->id,
                'codigo' => (string) ($p->codigo ?? ''),
                'descricao' => (string) ($p->descricao ?? ''),
                'unidade' => (string) ($p->unidade ?? ''),
            ])
            ->all();

        $this->suggestionsOpen = $this->produtoSuggestions !== [];
    }

    protected function renovarIdempotenciaToken(): void
    {
        $this->idempotenciaToken = (string) Str::uuid();
    }

    protected function limparAposSucesso(): void
    {
        $this->productId = null;
        $this->produtoSearch = '';
        $this->quantidade = '1,000';
        $this->preview = null;
        $this->closeSuggestions();
        $this->renovarIdempotenciaToken();
        $this->dispatch('erp-masks-refresh');
    }
}
