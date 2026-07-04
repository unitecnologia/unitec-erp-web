<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesPersonPhoto
{
    public ?string $fotoPreviewUrl = null;

    public function mountPersonPhoto(): void
    {
        $this->refreshFotoPreviewUrl();
    }

    public function getFotoPreviewUrl(): ?string
    {
        $path = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (blank($path)) {
            return null;
        }

        return asset('storage/' . $path);
    }

    public function capturePersonPhoto(string $base64): void
    {
        $base64 = preg_replace('#^data:image/(jpeg|jpg);base64,#i', '', $base64) ?? '';

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            Notification::make()
                ->title('Não foi possível processar a imagem.')
                ->danger()
                ->send();

            return;
        }

        $currentPath = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (filled($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        $filename = 'people-photos/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($filename, $binary);

        $this->data['foto_path'] = $filename;
        $this->form->fill([
            ...($this->data ?? []),
            'foto_path' => $filename,
        ]);

        $this->refreshFotoPreviewUrl();

        Notification::make()
            ->title('Foto capturada. Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function clearPersonPhoto(): void
    {
        $path = $this->data['foto_path'] ?? $this->form->getState()['foto_path'] ?? null;

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }

        $this->data['foto_path'] = null;
        $this->form->fill([
            ...($this->data ?? []),
            'foto_path' => null,
        ]);

        $this->refreshFotoPreviewUrl();
    }

    protected function refreshFotoPreviewUrl(): void
    {
        $this->fotoPreviewUrl = $this->getFotoPreviewUrl();
    }
}
