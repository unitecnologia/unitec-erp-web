<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\WithFileUploads;

trait ManagesEmpresaLogo
{
    use WithFileUploads;

    public $logoUpload = null;

    public ?string $logoPreviewUrl = null;

    public function mountEmpresaLogo(): void
    {
        $this->refreshLogoPreviewUrl();
    }

    public function getLogoPreviewUrl(): ?string
    {
        $path = $this->data['logo_path'] ?? $this->form->getState()['logo_path'] ?? null;

        if (blank($path)) {
            return null;
        }

        return asset('storage/' . $path);
    }

    public function updatedLogoUpload(): void
    {
        $this->validate([
            'logoUpload' => 'nullable|image|max:4096',
        ]);

        if (! $this->logoUpload) {
            return;
        }

        $currentPath = $this->data['logo_path'] ?? $this->form->getState()['logo_path'] ?? null;

        if (filled($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        $storedPath = $this->logoUpload->store('empresa-logos', 'public');

        $this->data['logo_path'] = $storedPath;
        $this->form->fill([
            ...($this->data ?? []),
            'logo_path' => $storedPath,
        ]);

        $this->logoUpload = null;
        $this->refreshLogoPreviewUrl();

        Notification::make()
            ->title('Logomarca carregada. Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function clearEmpresaLogo(): void
    {
        $path = $this->data['logo_path'] ?? $this->form->getState()['logo_path'] ?? null;

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }

        $this->data['logo_path'] = null;
        $this->form->fill([
            ...($this->data ?? []),
            'logo_path' => null,
        ]);

        $this->logoUpload = null;
        $this->refreshLogoPreviewUrl();
    }

    protected function refreshLogoPreviewUrl(): void
    {
        $this->logoPreviewUrl = $this->getLogoPreviewUrl();
    }
}
