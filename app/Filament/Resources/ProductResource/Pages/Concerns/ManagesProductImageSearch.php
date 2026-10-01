<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Support\Erp\ProductImageSearchDownloader;
use App\Support\Erp\ProductImageSearchService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

trait ManagesProductImageSearch
{
    public bool $productImageSearchOpen = false;

    public string $productImageSearchQuery = '';

    /** @var list<array{thumbnail: string, image_url: string, title: string}> */
    public array $productImageSearchResults = [];

    public ?string $productImageSearchMessage = null;

    public ?int $productImageSearchConfirmIndex = null;

    public function openProductImageSearch(): void
    {
        $this->productImageSearchQuery = trim((string) ($this->data['descricao'] ?? ''));
        $this->clearProductImageSearchResults();
        $this->productImageSearchOpen = true;
    }

    public function closeProductImageSearch(): void
    {
        $this->productImageSearchOpen = false;
        $this->productImageSearchQuery = '';
        $this->clearProductImageSearchResults();
    }

    public function searchProductImagesOnline(): void
    {
        $this->productImageSearchConfirmIndex = null;
        $this->productImageSearchResults = [];

        $found = app(ProductImageSearchService::class)->search($this->productImageSearchQuery);

        $this->productImageSearchResults = $found['results'];
        $this->productImageSearchMessage = $found['message'];
    }

    public function askProductImageSearchUse(int $index): void
    {
        if (! isset($this->productImageSearchResults[$index])) {
            return;
        }

        $this->productImageSearchConfirmIndex = $index;
    }

    public function cancelProductImageSearchUse(): void
    {
        $this->productImageSearchConfirmIndex = null;
    }

    public function confirmProductImageSearchUse(): void
    {
        $index = $this->productImageSearchConfirmIndex;
        $item = is_int($index) ? ($this->productImageSearchResults[$index] ?? null) : null;

        if (! is_array($item) || ! filled($item['image_url'] ?? null)) {
            $this->productImageSearchMessage = 'Não foi possível baixar a imagem.';
            $this->productImageSearchConfirmIndex = null;

            return;
        }

        $download = app(ProductImageSearchDownloader::class)->download((string) $item['image_url']);
        $storedPath = $download['path'] ?? null;

        if (! filled($storedPath)) {
            $this->productImageSearchMessage = $download['message'] ?? 'Não foi possível baixar a imagem.';
            $this->productImageSearchConfirmIndex = null;

            return;
        }

        $applied = $this->applySearchedProductPhoto((string) $storedPath);

        if (! $applied) {
            $this->productImageSearchMessage = 'Não foi possível usar esta imagem. A foto atual foi mantida.';
            $this->productImageSearchConfirmIndex = null;

            return;
        }

        $message = $this->isEditingProduct()
            ? 'Foto gravada com sucesso.'
            : 'Foto carregada. Salve o produto com F5 para concluir o cadastro.';

        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->closeProductImageSearch();
    }

    private function applySearchedProductPhoto(string $storedPath): bool
    {
        $previous = $this->data['foto_path'] ?? null;

        $this->pendingProductFotoUrl = null;
        $this->data['foto_path'] = $storedPath;
        $this->productFotoUpload = null;
        $this->refreshProductFotoPreviewUrl();

        try {
            $this->persistProductFotoToRecord();
        } catch (\Throwable $exception) {
            report($exception);
            $this->data['foto_path'] = $previous;
            $this->refreshProductFotoPreviewUrl();
            Storage::disk('public')->delete($storedPath);

            return false;
        }

        if (filled($previous) && $previous !== $storedPath) {
            Storage::disk('public')->delete($previous);
        }

        return true;
    }

    private function clearProductImageSearchResults(): void
    {
        $this->productImageSearchResults = [];
        $this->productImageSearchMessage = null;
        $this->productImageSearchConfirmIndex = null;
    }
}
