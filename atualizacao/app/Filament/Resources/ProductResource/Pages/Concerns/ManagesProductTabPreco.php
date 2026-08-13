<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use Filament\Notifications\Notification;

trait ManagesProductTabPreco
{
    /** @var array<int, array<string, mixed>> */
    public array $priceTableRows = [];

    /** @var array<int, array{id: int, codigo: string, descricao: string}> */
    public array $priceTableOptions = [];

    public ?int $selectedPriceTableRowIndex = null;

    public ?int $tabPrecoSelectedTableId = null;

    public string $tabPrecoValor = '0,00';

    public string $tabPrecoFator = '0';

    protected function loadPriceTableOptions(): void
    {
        $this->priceTableOptions = PriceTable::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PriceTable $table): array => [
                'id' => $table->id,
                'codigo' => $table->codigo,
                'descricao' => $table->descricao,
            ])
            ->values()
            ->all();
    }

    protected function loadProductPriceTableItems(?Product $product = null): void
    {
        $this->loadPriceTableOptions();

        if (! $product) {
            $this->priceTableRows = [];
            $this->selectedPriceTableRowIndex = null;
            $this->tabPrecoSelectedTableId = $this->priceTableOptions[0]['id'] ?? null;

            return;
        }

        $this->priceTableRows = $product->priceTableItems()
            ->with('priceTable:id,codigo,descricao')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductPriceTableItem $item): array => [
                'id' => $item->id,
                'price_table_id' => $item->price_table_id,
                'tabela' => trim(($item->priceTable?->codigo ?? '') . ' - ' . ($item->priceTable?->descricao ?? '')),
                'valor' => $this->formatBrDecimal($item->valor, 2),
                'fator' => $this->formatBrDecimal($item->fator, 3),
            ])
            ->values()
            ->all();

        $this->tabPrecoSelectedTableId = $this->priceTableOptions[0]['id'] ?? null;
    }

    public function selectPriceTableRow(int $index): void
    {
        if (! isset($this->priceTableRows[$index])) {
            return;
        }

        $this->selectedPriceTableRowIndex = $index;
        $row = $this->priceTableRows[$index];
        $this->tabPrecoSelectedTableId = (int) ($row['price_table_id'] ?? 0);
        $this->tabPrecoValor = (string) ($row['valor'] ?? '0,00');
        $this->tabPrecoFator = (string) ($row['fator'] ?? '0');
    }

    public function startPriceTableItem(): void
    {
        $this->selectedPriceTableRowIndex = null;
        $this->tabPrecoValor = '0,00';
        $this->tabPrecoFator = '0';
        $this->tabPrecoSelectedTableId = $this->priceTableOptions[0]['id'] ?? null;
    }

    public function savePriceTableItem(): void
    {
        if (! $this->tabPrecoSelectedTableId) {
            Notification::make()
                ->title('Selecione uma tabela de preço.')
                ->warning()
                ->send();

            return;
        }

        $table = collect($this->priceTableOptions)->firstWhere('id', $this->tabPrecoSelectedTableId);

        if (! $table) {
            Notification::make()
                ->title('Tabela de preço inválida.')
                ->warning()
                ->send();

            return;
        }

        $payload = [
            'id' => null,
            'price_table_id' => $this->tabPrecoSelectedTableId,
            'tabela' => trim($table['codigo'] . ' - ' . $table['descricao']),
            'valor' => $this->formatBrDecimal($this->parseBrDecimal($this->tabPrecoValor, 2), 2),
            'fator' => $this->formatBrDecimal($this->parseBrDecimal($this->tabPrecoFator, 3), 3),
        ];

        if ($this->selectedPriceTableRowIndex !== null && isset($this->priceTableRows[$this->selectedPriceTableRowIndex])) {
            $payload['id'] = $this->priceTableRows[$this->selectedPriceTableRowIndex]['id'] ?? null;
            $this->priceTableRows[$this->selectedPriceTableRowIndex] = $payload;
        } else {
            $existingIndex = collect($this->priceTableRows)
                ->search(fn (array $row): bool => (int) ($row['price_table_id'] ?? 0) === (int) $this->tabPrecoSelectedTableId);

            if ($existingIndex !== false) {
                $payload['id'] = $this->priceTableRows[$existingIndex]['id'] ?? null;
                $this->priceTableRows[$existingIndex] = $payload;
                $this->selectedPriceTableRowIndex = $existingIndex;
            } else {
                $this->priceTableRows[] = $payload;
                $this->selectedPriceTableRowIndex = count($this->priceTableRows) - 1;
            }
        }

        Notification::make()
            ->title('Item de tabela de preço atualizado.')
            ->body('Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function deletePriceTableItem(): void
    {
        if ($this->selectedPriceTableRowIndex === null || ! isset($this->priceTableRows[$this->selectedPriceTableRowIndex])) {
            Notification::make()
                ->title('Selecione um item da tabela de preço.')
                ->warning()
                ->send();

            return;
        }

        unset($this->priceTableRows[$this->selectedPriceTableRowIndex]);
        $this->priceTableRows = array_values($this->priceTableRows);
        $this->startPriceTableItem();
    }

    protected function syncProductPriceTableItems(Product $product): void
    {
        if (! ($product->usa_tab_preco ?? false)) {
            $product->priceTableItems()->delete();

            return;
        }

        $ids = [];

        foreach ($this->priceTableRows as $row) {
            $tableId = (int) ($row['price_table_id'] ?? 0);

            if ($tableId <= 0) {
                continue;
            }

            $attributes = [
                'price_table_id' => $tableId,
                'valor' => $this->parseBrDecimal($row['valor'] ?? 0, 2),
                'fator' => $this->parseBrDecimal($row['fator'] ?? 0, 3),
            ];

            if (filled($row['id'] ?? null)) {
                ProductPriceTableItem::query()->whereKey($row['id'])->update($attributes);
                $ids[] = (int) $row['id'];
            } else {
                $created = $product->priceTableItems()->create($attributes);
                $ids[] = $created->id;
            }
        }

        $product->priceTableItems()->whereNotIn('id', $ids)->delete();
    }
}
