<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Person;
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
        $fornecedoresValidos = $this->fornecedoresExistentesDosImeis();
        $fornecedoresIgnorados = [];

        foreach ($this->imeiRows as $row) {
            $imei = trim((string) ($row['imei'] ?? ''));

            if ($imei === '') {
                continue;
            }

            $fornecedorId = filled($row['fornecedor_id'] ?? null) ? (int) $row['fornecedor_id'] : null;
            if ($fornecedorId !== null && $fornecedorId > 0 && ! isset($fornecedoresValidos[$fornecedorId])) {
                $fornecedoresIgnorados[] = $fornecedorId;
                $fornecedorId = null;
            }
            if ($fornecedorId !== null && $fornecedorId <= 0) {
                $fornecedorId = null;
            }

            $attributes = [
                'imei' => $imei,
                'fornecedor_id' => $fornecedorId,
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

        $ignorados = array_values(array_unique($fornecedoresIgnorados));
        if ($ignorados !== []) {
            Notification::make()
                ->title('IMEI gravado sem fornecedor.')
                ->body('Estes IDs de fornecedor não existem no cadastro e foram ignorados: '.implode(', ', $ignorados).'.')
                ->warning()
                ->send();
        }
    }

    /** @return array<int, true> */
    protected function fornecedoresExistentesDosImeis(): array
    {
        $ids = [];
        foreach ($this->imeiRows as $row) {
            if (! filled($row['fornecedor_id'] ?? null)) {
                continue;
            }
            $id = (int) $row['fornecedor_id'];
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        return Person::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }
}
