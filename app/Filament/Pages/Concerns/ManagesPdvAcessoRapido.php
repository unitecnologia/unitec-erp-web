<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvAcessoRapido;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvAcessoRapido
{
    public const ACESSO_RAPIDO_SLOTS_MIN = 12;

    public const ACESSO_RAPIDO_SLOTS_MAX = 60;

    public const ACESSO_RAPIDO_SLOTS_STEP = 6;

    public const ACESSO_RAPIDO_SLOTS_DEFAULT = 30;

    public bool $acessoRapidoEditando = false;

    public int $acessoRapidoSlotsCount = self::ACESSO_RAPIDO_SLOTS_DEFAULT;

    /**
     * Tiles indexados 0..n-1; null = vazio.
     *
     * @var array<int, array{product_id: int, codigo: string, descricao: string, preco: string}|null>
     */
    public array $acessoRapidoTiles = [];

    public string $acessoRapidoBusca = '';

    /** @var list<array{product_id: int, codigo: string, descricao: string, preco: string}> */
    public array $acessoRapidoBuscaResults = [];

    public ?int $acessoRapidoSlotAlvo = null;

    protected function prepareAcessoRapidoOnOpen(): void
    {
        $this->acessoRapidoEditando = false;
        $this->acessoRapidoBusca = '';
        $this->acessoRapidoBuscaResults = [];
        $this->acessoRapidoSlotAlvo = null;
        $this->loadAcessoRapidoFromDb();
    }

    protected function loadAcessoRapidoFromDb(): void
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());
        $config = null;

        if ($terminal?->id) {
            $config = PdvAcessoRapido::query()->where('terminal_id', $terminal->id)->first();
        }

        $slots = (int) ($config?->slots_count ?: self::ACESSO_RAPIDO_SLOTS_DEFAULT);
        $slots = $this->clampAcessoRapidoSlots($slots);

        $byPos = [];

        foreach ($config?->itensNormalizados() ?? [] as $row) {
            $byPos[(int) $row['pos']] = (int) $row['product_id'];
        }

        $productIds = array_values(array_unique(array_filter($byPos)));
        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $tiles = [];

        for ($i = 0; $i < $slots; $i++) {
            $productId = $byPos[$i] ?? null;
            $product = $productId ? $products->get($productId) : null;

            if (! $product || ! $product->ativo) {
                $tiles[$i] = null;

                continue;
            }

            $tiles[$i] = $this->mapProductToAcessoRapidoTile($product);
        }

        $this->acessoRapidoSlotsCount = $slots;
        $this->acessoRapidoTiles = $tiles;
    }

    protected function saveAcessoRapidoToDb(): void
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());

        if (! $terminal?->id) {
            Notification::make()
                ->title('Terminal não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $itens = [];

        foreach ($this->acessoRapidoTiles as $pos => $tile) {
            if (! is_array($tile) || (int) ($tile['product_id'] ?? 0) <= 0) {
                continue;
            }

            $itens[] = [
                'pos' => (int) $pos,
                'product_id' => (int) $tile['product_id'],
            ];
        }

        PdvAcessoRapido::query()->updateOrCreate(
            ['terminal_id' => $terminal->id],
            [
                'empresa_id' => $this->resolveEmpresaId() ?? Auth::user()?->empresa_id,
                'slots_count' => $this->clampAcessoRapidoSlots($this->acessoRapidoSlotsCount),
                'itens' => $itens,
            ],
        );
    }

    /**
     * @return array{product_id: int, codigo: string, descricao: string, preco: string}
     */
    protected function mapProductToAcessoRapidoTile(Product $product): array
    {
        $preco = $this->pdvPriceService()->resolvePrecoVenda($product, 1.0);

        return [
            'product_id' => (int) $product->id,
            'codigo' => (string) ($product->codigo ?? ''),
            'descricao' => mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
            'preco' => ErpMoney::formatBr($preco),
        ];
    }

    protected function clampAcessoRapidoSlots(int $slots): int
    {
        $slots = max(self::ACESSO_RAPIDO_SLOTS_MIN, min(self::ACESSO_RAPIDO_SLOTS_MAX, $slots));
        $mod = $slots % self::ACESSO_RAPIDO_SLOTS_STEP;

        if ($mod !== 0) {
            $slots -= $mod;
        }

        return max(self::ACESSO_RAPIDO_SLOTS_MIN, $slots);
    }

    public function toggleAcessoRapidoEditar(): void
    {
        if ($this->acessoRapidoEditando) {
            $this->saveAcessoRapidoToDb();
            $this->acessoRapidoEditando = false;
            $this->acessoRapidoBusca = '';
            $this->acessoRapidoBuscaResults = [];
            $this->acessoRapidoSlotAlvo = null;

            Notification::make()
                ->title('Atalhos salvos.')
                ->success()
                ->send();

            return;
        }

        $this->acessoRapidoEditando = true;
    }

    public function aumentarAcessoRapidoSlots(): void
    {
        if (! $this->acessoRapidoEditando) {
            return;
        }

        $novo = $this->clampAcessoRapidoSlots($this->acessoRapidoSlotsCount + self::ACESSO_RAPIDO_SLOTS_STEP);

        if ($novo === $this->acessoRapidoSlotsCount) {
            return;
        }

        for ($i = $this->acessoRapidoSlotsCount; $i < $novo; $i++) {
            $this->acessoRapidoTiles[$i] = null;
        }

        $this->acessoRapidoSlotsCount = $novo;
    }

    public function diminuirAcessoRapidoSlots(): void
    {
        if (! $this->acessoRapidoEditando) {
            return;
        }

        $novo = $this->clampAcessoRapidoSlots($this->acessoRapidoSlotsCount - self::ACESSO_RAPIDO_SLOTS_STEP);

        if ($novo >= $this->acessoRapidoSlotsCount) {
            return;
        }

        for ($i = $novo; $i < $this->acessoRapidoSlotsCount; $i++) {
            if (is_array($this->acessoRapidoTiles[$i] ?? null)) {
                Notification::make()
                    ->title('Esvazie os últimos atalhos antes de reduzir.')
                    ->body('Remova os produtos das últimas posições e tente novamente.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->acessoRapidoTiles = array_slice($this->acessoRapidoTiles, 0, $novo, true);
        $this->acessoRapidoSlotsCount = $novo;
    }

    public function removerAcessoRapidoSlot(int $pos): void
    {
        if (! $this->acessoRapidoEditando || ! array_key_exists($pos, $this->acessoRapidoTiles)) {
            return;
        }

        $this->acessoRapidoTiles[$pos] = null;
    }

    public function moverAcessoRapidoSlot(int $pos, int $delta): void
    {
        if (! $this->acessoRapidoEditando) {
            return;
        }

        $destino = $pos + $delta;

        if (! array_key_exists($pos, $this->acessoRapidoTiles) || ! array_key_exists($destino, $this->acessoRapidoTiles)) {
            return;
        }

        $tmp = $this->acessoRapidoTiles[$destino];
        $this->acessoRapidoTiles[$destino] = $this->acessoRapidoTiles[$pos];
        $this->acessoRapidoTiles[$pos] = $tmp;
    }

    public function selecionarSlotAcessoRapido(int $pos): void
    {
        if (! $this->acessoRapidoEditando || $pos < 0 || $pos >= $this->acessoRapidoSlotsCount) {
            return;
        }

        $this->acessoRapidoSlotAlvo = $pos;
    }

    public function updatedAcessoRapidoBusca(string $value): void
    {
        $term = mb_strtoupper(trim($value), 'UTF-8');

        if ($this->acessoRapidoBusca !== $term) {
            $this->acessoRapidoBusca = $term;
        }

        if (! $this->acessoRapidoEditando || mb_strlen($term) < 1) {
            $this->acessoRapidoBuscaResults = [];

            return;
        }

        $rows = $this->queryProductsForPdv($term);
        $out = [];

        foreach (array_slice($rows, 0, 12) as $row) {
            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $out[] = $this->mapProductToAcessoRapidoTile($product);
        }

        $this->acessoRapidoBuscaResults = $out;
    }

    public function atribuirProdutoAcessoRapido(int $productId): void
    {
        if (! $this->acessoRapidoEditando || $productId <= 0) {
            return;
        }

        $product = Product::query()->find($productId);

        if (! $product || ! $product->ativo) {
            Notification::make()
                ->title('Produto inválido.')
                ->warning()
                ->send();

            return;
        }

        $pos = $this->acessoRapidoSlotAlvo;

        if ($pos === null || ! array_key_exists($pos, $this->acessoRapidoTiles)) {
            $pos = null;

            foreach ($this->acessoRapidoTiles as $i => $tile) {
                if ($tile === null) {
                    $pos = (int) $i;

                    break;
                }
            }
        }

        if ($pos === null) {
            Notification::make()
                ->title('Sem slot vazio.')
                ->body('Selecione um quadrado ou aumente a quantidade de atalhos.')
                ->warning()
                ->send();

            return;
        }

        $this->acessoRapidoTiles[$pos] = $this->mapProductToAcessoRapidoTile($product);
        $this->acessoRapidoSlotAlvo = null;
        $this->acessoRapidoBusca = '';
        $this->acessoRapidoBuscaResults = [];
    }

    public function lancarAcessoRapido(int $pos): void
    {
        if ($this->acessoRapidoEditando) {
            $this->selecionarSlotAcessoRapido($pos);

            return;
        }

        if (! $this->caixaAberto) {
            Notification::make()
                ->title('Caixa fechado.')
                ->warning()
                ->send();

            return;
        }

        $tile = $this->acessoRapidoTiles[$pos] ?? null;

        if (! is_array($tile)) {
            return;
        }

        $product = Product::query()->find((int) ($tile['product_id'] ?? 0));

        if (! $product || ! $product->ativo) {
            Notification::make()
                ->title('Produto indisponível.')
                ->warning()
                ->send();

            return;
        }

        $this->closePdvModal();

        $codigo = trim((string) ($product->codigo_barras ?: $product->codigo ?: ''));
        $preco = $this->pdvPriceService()->resolvePrecoVenda($product, 1.0);

        $this->pdvSearch = $codigo;
        $this->pdvSearchResults = [[
            'product_id' => (int) $product->id,
            'codigo' => $codigo !== '' ? $codigo : (string) ($product->codigo ?? ''),
            'descricao' => mb_strtoupper((string) $product->descricao, 'UTF-8'),
            'preco' => $preco,
            'estoque' => (float) $product->estoque,
            'unidade' => $product->unidade ?: 'UN',
            'localizacao' => filled($product->localizacao)
                ? mb_strtoupper((string) $product->localizacao, 'UTF-8')
                : '',
            'preco_variavel' => (bool) $product->preco_variavel,
            'produto_pesado' => (bool) $product->produto_pesado,
        ]];
        $this->selectedSearchIndex = 0;
        $this->pdvPendingLaunchProductId = (int) $product->id;
        $this->proceedAfterProductSelected($product);
    }

    public function fecharAcessoRapido(): void
    {
        if ($this->acessoRapidoEditando) {
            $this->saveAcessoRapidoToDb();
            $this->acessoRapidoEditando = false;
        }

        $this->acessoRapidoBusca = '';
        $this->acessoRapidoBuscaResults = [];
        $this->acessoRapidoSlotAlvo = null;
        $this->closePdvModal();
    }
}
