<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use Filament\Notifications\Notification;
use App\Support\Erp\ProductPhotoDownloader;
use App\Support\Erp\ProductPhotoUrl;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\WithFileUploads;

trait ManagesProductPhoto
{
    use WithFileUploads;

    public $productFotoUpload = null;

    public ?string $productFotoPreviewUrl = null;

    public ?string $pendingProductFotoUrl = null;

    protected bool $productPhotoDownloadFailureNotified = false;

    public function mountProductPhoto(): void
    {
        $this->refreshProductFotoPreviewUrl();
    }

    public function getProductFotoPreviewUrl(): ?string
    {
        if (filled($this->pendingProductFotoUrl)) {
            return $this->pendingProductFotoUrl;
        }

        $path = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (blank($path)) {
            return null;
        }

        return ProductPhotoUrl::forPath($path);
    }

    public function setPendingProductFotoFromUrl(?string $url): void
    {
        $this->pendingProductFotoUrl = filled($url) ? $url : null;
        $this->refreshProductFotoPreviewUrl();
    }

    public function updatedProductFotoUpload(): void
    {
        try {
            $this->validate([
                'productFotoUpload' => 'nullable|image|max:4096',
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->productFotoUpload = null;

            Notification::make()
                ->title('Foto não carregada')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Arquivo de imagem inválido.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->productFotoUpload) {
            return;
        }

        $currentPath = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (filled($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        try {
            $storedPath = $this->productFotoUpload->store('products-photos', 'public');
        } catch (\Throwable $exception) {
            $this->productFotoUpload = null;

            report($exception);

            Notification::make()
                ->title('Foto não carregada')
                ->body('Não foi possível salvar o arquivo. Verifique permissões da pasta storage.')
                ->danger()
                ->send();

            return;
        }

        $this->pendingProductFotoUrl = null;
        $this->data['foto_path'] = $storedPath;
        $this->form->fill([
            ...($this->data ?? []),
            'foto_path' => $storedPath,
        ]);

        $this->productFotoUpload = null;
        $this->refreshProductFotoPreviewUrl();
        $this->persistProductFotoToRecord();

        $message = $this->isEditingProduct()
            ? 'Foto gravada com sucesso.'
            : 'Foto carregada. Salve o produto com F5 para concluir o cadastro.';

        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    protected function persistProductFotoToRecord(): void
    {
        if (! $this->isEditingProduct() || ! $this->record?->exists) {
            return;
        }

        $path = $this->data['foto_path'] ?? null;

        if ($path === $this->record->foto_path) {
            return;
        }

        $this->record->forceFill(['foto_path' => $path])->save();
    }

    public function clearProductPhoto(): void
    {
        $path = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }

        $this->pendingProductFotoUrl = null;
        $this->data['foto_path'] = null;
        $this->form->fill([
            ...($this->data ?? []),
            'foto_path' => null,
        ]);

        $this->productFotoUpload = null;
        $this->refreshProductFotoPreviewUrl();
        $this->persistProductFotoToRecord();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function persistProductPhotoForSave(array $data): array
    {
        if (! filled($this->pendingProductFotoUrl)) {
            return $data;
        }

        $currentPath = $data['foto_path'] ?? null;
        $download = app(ProductPhotoDownloader::class)->download($this->pendingProductFotoUrl);

        if (filled($download['path'] ?? null)) {
            $storedPath = $download['path'];

            if (filled($currentPath) && $currentPath !== $storedPath) {
                Storage::disk('public')->delete($currentPath);
            }

            $data['foto_path'] = $storedPath;
            $this->data['foto_path'] = $storedPath;
            $this->form->fill([
                ...($this->data ?? []),
                'foto_path' => $storedPath,
            ]);
            $this->pendingProductFotoUrl = null;
            $this->persistProductFotoToRecord();
            $this->refreshProductFotoPreviewUrl();

            return $data;
        }

        if (! $this->productPhotoDownloadFailureNotified) {
            Notification::make()
                ->title('Foto não gravada')
                ->body($download['message'] ?? 'Não foi possível baixar a imagem da consulta. Os demais dados serão salvos.')
                ->warning()
                ->send();

            $this->productPhotoDownloadFailureNotified = true;
        }

        $this->refreshProductFotoPreviewUrl();

        return $data;
    }

    protected function refreshProductFotoPreviewUrl(): void
    {
        $this->productFotoPreviewUrl = $this->getProductFotoPreviewUrl();
    }
}
