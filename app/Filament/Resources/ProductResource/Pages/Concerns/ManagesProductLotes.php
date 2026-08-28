<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductLote;
use App\Support\Erp\ProductLoteService;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

trait ManagesProductLotes
{
    /** @var list<array<string, mixed>> */
    public array $productLotesRows = [];

    public ?int $productLoteSelectedId = null;

    public bool $productLoteModalOpen = false;

    /** @var 'create'|'edit' */
    public string $productLoteModalMode = 'create';

    public string $productLoteDraftLote = '';

    public string $productLoteDraftValidade = '';

    public string $productLoteDraftQuantidade = '0,000';

    public function selectProductLote(?int $id): void
    {
        $this->productLoteSelectedId = $id && $id > 0 ? $id : null;
    }

    public function openProductLoteCreate(): void
    {
        if (! $this->canManageProductLotes()) {
            return;
        }

        $this->productLoteModalMode = 'create';
        $this->productLoteDraftLote = '';
        $this->productLoteDraftValidade = '';
        $this->productLoteDraftQuantidade = '0,000';
        $this->productLoteModalOpen = true;
    }

    public function openProductLoteEdit(): void
    {
        if (! $this->canManageProductLotes()) {
            return;
        }

        $id = (int) ($this->productLoteSelectedId ?? 0);
        if ($id <= 0) {
            Notification::make()
                ->title('Selecione um lote na grade para editar.')
                ->warning()
                ->send();

            return;
        }

        $lote = ProductLote::query()
            ->whereKey($id)
            ->where('product_id', (int) $this->record->id)
            ->first();

        if (! $lote) {
            Notification::make()
                ->title('Lote não encontrado.')
                ->danger()
                ->send();
            $this->productLoteSelectedId = null;
            $this->loadProductLotes($this->record);

            return;
        }

        $this->productLoteModalMode = 'edit';
        $this->productLoteDraftLote = (string) $lote->lote;
        $this->productLoteDraftValidade = $lote->data_validade?->format('Y-m-d') ?? '';
        $this->productLoteDraftQuantidade = number_format((float) $lote->quantidade_atual, 3, ',', '.');
        $this->productLoteModalOpen = true;
    }

    public function closeProductLoteModal(): void
    {
        $this->productLoteModalOpen = false;
        $this->productLoteDraftLote = '';
        $this->productLoteDraftValidade = '';
        $this->productLoteDraftQuantidade = '0,000';
    }

    public function saveProductLote(): void
    {
        if (! $this->canManageProductLotes()) {
            return;
        }

        $lote = mb_substr(trim($this->productLoteDraftLote), 0, 60);
        $validadeRaw = trim($this->productLoteDraftValidade);
        $validade = $this->parseProductLoteDate($validadeRaw);
        $quantidade = method_exists($this, 'parseBrDecimal')
            ? $this->parseBrDecimal($this->productLoteDraftQuantidade, 3)
            : (float) str_replace(['.', ','], ['', '.'], $this->productLoteDraftQuantidade);

        if ($lote === '') {
            Notification::make()->title('Informe o lote.')->warning()->send();

            return;
        }

        if ($validadeRaw === '' || ! $validade) {
            Notification::make()
                ->title('Informe a validade (dd/mm/aaaa).')
                ->warning()
                ->send();

            return;
        }

        if ($quantidade < 0) {
            Notification::make()
                ->title('A quantidade não pode ser negativa.')
                ->warning()
                ->send();

            return;
        }

        $product = $this->record;
        $editId = $this->productLoteModalMode === 'edit'
            ? (int) ($this->productLoteSelectedId ?? 0)
            : 0;

        $duplicado = ProductLote::query()
            ->where('product_id', $product->id)
            ->where('lote', $lote)
            ->whereDate('data_validade', $validade->toDateString())
            ->when($editId > 0, fn ($q) => $q->where('id', '!=', $editId))
            ->exists();

        if ($duplicado) {
            Notification::make()
                ->title('Já existe um lote com o mesmo número e validade.')
                ->warning()
                ->send();

            return;
        }

        if ($this->productLoteModalMode === 'edit') {
            $row = ProductLote::query()
                ->whereKey($editId)
                ->where('product_id', $product->id)
                ->first();

            if (! $row) {
                Notification::make()->title('Lote não encontrado.')->danger()->send();
                $this->closeProductLoteModal();
                $this->loadProductLotes($product);

                return;
            }

            $row->fill([
                'lote' => $lote,
                'data_validade' => $validade->toDateString(),
                'quantidade_atual' => $quantidade,
            ])->save();

            $this->productLoteSelectedId = (int) $row->id;
        } else {
            $row = ProductLote::query()->create([
                'product_id' => $product->id,
                'lote' => $lote,
                'data_validade' => $validade->toDateString(),
                'quantidade_atual' => $quantidade,
            ]);
            $this->productLoteSelectedId = (int) $row->id;
        }

        $product->refresh();
        app(ProductLoteService::class)->sincronizarEspelhoProduto($product);
        $product->refresh();

        if (isset($this->data) && is_array($this->data)) {
            $this->data['lote'] = $product->lote;
            $validade = $product->validade;
            $this->data['validade'] = $validade
                ? (is_string($validade) ? $validade : $validade->format('Y-m-d'))
                : null;
        }

        $foiEdicao = $this->productLoteModalMode === 'edit';
        $this->closeProductLoteModal();
        $this->loadProductLotes($product);

        Notification::make()
            ->title($foiEdicao ? 'Lote atualizado.' : 'Lote incluído.')
            ->success()
            ->send();
    }

    public function refreshProductLotes(): void
    {
        $this->loadProductLotes($this->record ?? null);
    }

    protected function canManageProductLotes(): bool
    {
        if (! Schema::hasTable('product_lotes')) {
            Notification::make()->title('Tabela de lotes indisponível.')->danger()->send();

            return false;
        }

        $product = $this->record instanceof Product ? $this->record : null;
        if (! $product?->id) {
            Notification::make()
                ->title('Grave o produto antes de incluir lotes.')
                ->warning()
                ->send();

            return false;
        }

        if (! (bool) ($this->data['controla_lote_validade'] ?? $product->controla_lote_validade ?? false)) {
            Notification::make()
                ->title('Marque Controla lote/validade nos parâmetros.')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    protected function loadProductLotes(?Product $product = null): void
    {
        $this->productLotesRows = [];

        if (! Schema::hasTable('product_lotes')) {
            return;
        }

        $product ??= $this->record instanceof Product ? $this->record : null;
        if (! $product?->id) {
            return;
        }

        if (! (bool) ($this->data['controla_lote_validade'] ?? $product->controla_lote_validade ?? false)) {
            return;
        }

        $this->productLotesRows = ProductLote::query()
            ->where('product_id', $product->id)
            ->orderBy('data_validade')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(static function (ProductLote $lote): array {
                $dias = $lote->diasRestantes();
                $sit = $lote->situacao(30);

                return [
                    'id' => (int) $lote->id,
                    'lote' => (string) $lote->lote,
                    'validade' => $lote->data_validade?->format('d/m/Y') ?? '—',
                    'dias' => $dias,
                    'estoque' => number_format((float) $lote->quantidade_atual, 3, ',', '.'),
                    'situacao' => $sit,
                    'situacao_label' => $lote->situacaoLabel(30),
                ];
            })
            ->all();

        if ($this->productLoteSelectedId) {
            $stillExists = collect($this->productLotesRows)
                ->contains(fn (array $row): bool => (int) $row['id'] === (int) $this->productLoteSelectedId);
            if (! $stillExists) {
                $this->productLoteSelectedId = null;
            }
        }
    }

    protected function parseProductLoteDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
                return Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
            }

            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
