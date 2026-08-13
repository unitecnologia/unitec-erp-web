<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Str;

trait ManagesProductDuplicateCheck
{
    public bool $duplicateConfirmOpen = false;

    public ?int $duplicateExistingProductId = null;

    public ?string $duplicateMatchReason = null;

    public function closeDuplicateConfirmModal(): void
    {
        $this->duplicateConfirmOpen = false;
        $this->duplicateExistingProductId = null;
        $this->duplicateMatchReason = null;
    }

    public function cancelDuplicateConfirmModal(): void
    {
        $this->closeDuplicateConfirmModal();
    }

    public function confirmEditExistingProduct(): void
    {
        if (! $this->duplicateExistingProductId) {
            return;
        }

        $url = ProductResource::getUrl('edit', ['record' => $this->duplicateExistingProductId]);

        if ($this->embedsInPdv) {
            $url .= '?pdv=1';
        }

        $this->closeDuplicateConfirmModal();

        $this->redirect($url);
    }

    public function handleDuplicateEscape(): void
    {
        if ($this->duplicateConfirmOpen) {
            $this->cancelDuplicateConfirmModal();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getDuplicateConfirmViewStateProperty(): array
    {
        if (! $this->duplicateConfirmOpen || ! $this->duplicateExistingProductId) {
            return [];
        }

        $product = Product::query()->find($this->duplicateExistingProductId);

        if (! $product) {
            return [];
        }

        return [
            'codigo' => $product->codigo,
            'descricao' => $product->descricao,
            'codigo_barras' => $product->codigo_barras,
            'matchLabel' => match ($this->duplicateMatchReason) {
                'codigo_barras' => 'Código de barras já cadastrado.',
                'descricao' => 'Descrição já cadastrada.',
                default => 'Produto já cadastrado.',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function promptDuplicateConfirmIfNeeded(array $data): bool
    {
        $existing = $this->findExistingProductForDuplicateCheck($data);

        if (! $existing) {
            return false;
        }

        $this->openDuplicateConfirmModal($existing, $this->resolveDuplicateMatchReason($data, $existing));

        return true;
    }

    protected function openDuplicateConfirmModal(Product $product, string $reason): void
    {
        $this->duplicateConfirmOpen = true;
        $this->duplicateExistingProductId = $product->getKey();
        $this->duplicateMatchReason = $reason;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function findExistingProductForDuplicateCheck(array $data): ?Product
    {
        $barcode = preg_replace('/\D/', '', (string) ($data['codigo_barras'] ?? ''));

        if (strlen($barcode) >= 8) {
            $byBarcode = Product::query()->where('codigo_barras', $barcode)->first();

            if ($byBarcode) {
                return $byBarcode;
            }
        }

        $descricao = Str::upper(trim((string) ($data['descricao'] ?? '')));

        if ($descricao !== '') {
            return Product::query()->where('descricao', $descricao)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveDuplicateMatchReason(array $data, Product $existing): string
    {
        $barcode = preg_replace('/\D/', '', (string) ($data['codigo_barras'] ?? ''));

        if (
            strlen($barcode) >= 8
            && filled($existing->codigo_barras)
            && $barcode === preg_replace('/\D/', '', (string) $existing->codigo_barras)
        ) {
            return 'codigo_barras';
        }

        return 'descricao';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureUniqueProductCodigo(array $data): array
    {
        $codigo = trim((string) ($data['codigo'] ?? ''));

        if ($codigo === '' || Product::query()->where('codigo', $codigo)->exists()) {
            $data['codigo'] = Product::nextCodigo();
        }

        return $data;
    }

}
