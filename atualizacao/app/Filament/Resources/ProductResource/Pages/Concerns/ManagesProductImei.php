<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductImei;
use Filament\Notifications\Notification;

trait ManagesProductImei
{
    /** @var array<int, array<string, mixed>> */
    public array $imeiRows = [];

    public ?int $selectedImeiIndex = null;

    protected function loadProductImeis(?Product $product = null): void
    {
        if (! $product) {
            $this->imeiRows = [];
            $this->selectedImeiIndex = null;

            return;
        }

        $this->imeiRows = $product->imeis()
            ->with('fornecedor:id,nome')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductImei $imei): array => [
                'id' => $imei->id,
                'imei' => $imei->imei,
                'fornecedor_id' => $imei->fornecedor_id,
                'fornecedor' => $imei->fornecedor?->nome ?? '',
                'ativo' => (bool) $imei->ativo,
            ])
            ->values()
            ->all();
    }

    public function selectImeiRow(int $index): void
    {
        if (isset($this->imeiRows[$index])) {
            $this->selectedImeiIndex = $index;
        }
    }

    public function addImeiRow(): void
    {
        $this->imeiRows[] = [
            'id' => null,
            'imei' => '',
            'fornecedor_id' => null,
            'fornecedor' => '',
            'ativo' => true,
        ];

        $this->selectedImeiIndex = count($this->imeiRows) - 1;
    }

    public function deleteImeiRow(): void
    {
        if ($this->selectedImeiIndex === null || ! isset($this->imeiRows[$this->selectedImeiIndex])) {
            Notification::make()
                ->title('Selecione uma linha de IMEI.')
                ->warning()
                ->send();

            return;
        }

        unset($this->imeiRows[$this->selectedImeiIndex]);
        $this->imeiRows = array_values($this->imeiRows);
        $this->selectedImeiIndex = null;
    }

    protected function syncProductImeis(Product $product): void
    {
        if (! ($product->usa_imei ?? false)) {
            $product->imeis()->delete();

            return;
        }

        $ids = [];

        foreach ($this->imeiRows as $row) {
            $imei = trim((string) ($row['imei'] ?? ''));

            if ($imei === '') {
                continue;
            }

            $attributes = [
                'imei' => $imei,
                'fornecedor_id' => filled($row['fornecedor_id'] ?? null) ? (int) $row['fornecedor_id'] : null,
                'ativo' => (bool) ($row['ativo'] ?? true),
            ];

            if (filled($row['id'] ?? null)) {
                ProductImei::query()->whereKey($row['id'])->update($attributes);
                $ids[] = (int) $row['id'];
            } else {
                $created = $product->imeis()->create($attributes);
                $ids[] = $created->id;
            }
        }

        $product->imeis()->whereNotIn('id', $ids)->delete();
    }
}
