<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\WithFileUploads;

trait ManagesPersonPhoto
{
    use WithFileUploads;

    public $personFotoUpload = null;

    public ?string $fotoPreviewUrl = null;

    /** Caminho já gravado no banco. Arquivos novos ficam pendentes até o F5. */
    public ?string $personFotoOriginalPath = null;

    public function mountPersonPhoto(): void
    {
        $this->personFotoOriginalPath = filled($this->data['foto_path'] ?? null)
            ? (string) $this->data['foto_path']
            : null;
        $this->refreshFotoPreviewUrl();
    }

    public function getFotoPreviewUrl(): ?string
    {
        $path = array_key_exists('foto_path', $this->data ?? [])
            ? ($this->data['foto_path'] ?? null)
            : null;

        if (blank($path) && ! array_key_exists('foto_path', $this->data ?? []) && method_exists($this, 'form')) {
            try {
                $path = $this->form->getState()['foto_path'] ?? null;
            } catch (\Throwable) {
                $path = null;
            }
        }

        if (blank($path)) {
            return null;
        }

        $version = '0';

        try {
            $fullPath = Storage::disk('public')->path($path);
            if (is_file($fullPath)) {
                $version = (string) filemtime($fullPath);
            }
        } catch (\Throwable) {
            $version = substr(md5((string) $path), 0, 8);
        }

        return asset('storage/' . ltrim((string) $path, '/')) . '?v=' . $version;
    }

    public function updatedPersonFotoUpload(): void
    {
        try {
            $this->validate([
                'personFotoUpload' => 'nullable|image|mimes:jpg,jpeg|max:4096',
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->personFotoUpload = null;

            Notification::make()
                ->title('Foto não carregada')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Somente imagens .jpg ou .jpeg.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->personFotoUpload) {
            return;
        }

        $this->deleteUnsavedPersonPhoto();

        try {
            Storage::disk('public')->makeDirectory('people-photos');
            $storedPath = $this->personFotoUpload->store('people-photos', 'public');
        } catch (\Throwable $exception) {
            $this->personFotoUpload = null;
            report($exception);

            Notification::make()
                ->title('Foto não carregada')
                ->body('Não foi possível salvar o arquivo. Verifique o link public/storage.')
                ->danger()
                ->send();

            return;
        }

        $this->data['foto_path'] = $storedPath;
        $this->syncPersonFotoFormState($storedPath);
        $this->personFotoUpload = null;
        $this->refreshFotoPreviewUrl();

        Notification::make()
            ->title('Foto carregada. Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function capturePersonPhoto(string $base64): void
    {
        $base64 = preg_replace('#^data:image/(jpeg|jpg);base64,#i', '', $base64) ?? '';

        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            Notification::make()
                ->title('Não foi possível processar a imagem.')
                ->danger()
                ->send();

            return;
        }

        $this->deleteUnsavedPersonPhoto();

        try {
            Storage::disk('public')->makeDirectory('people-photos');
            $filename = 'people-photos/' . Str::uuid() . '.jpg';
            Storage::disk('public')->put($filename, $binary);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Foto não carregada')
                ->body('Não foi possível salvar o arquivo. Verifique o link public/storage.')
                ->danger()
                ->send();

            return;
        }

        $this->data['foto_path'] = $filename;
        $this->syncPersonFotoFormState($filename);
        $this->refreshFotoPreviewUrl();

        Notification::make()
            ->title('Foto capturada. Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function clearPersonPhoto(): void
    {
        $this->deleteUnsavedPersonPhoto();

        $this->data['foto_path'] = null;
        $this->syncPersonFotoFormState(null);
        $this->personFotoUpload = null;
        $this->refreshFotoPreviewUrl();
    }

    protected function syncPersonFotoFormState(?string $path): void
    {
        try {
            $this->form->fill([
                ...($this->data ?? []),
                'foto_path' => $path,
            ]);
        } catch (\Throwable) {
            // Form Schema pode não estar hidratado em alguns embeds; data[] já guarda o path.
        }
    }

    protected function refreshFotoPreviewUrl(): void
    {
        $this->fotoPreviewUrl = $this->getFotoPreviewUrl();
    }

    public function commitPersonPhotoAfterSave(): void
    {
        $saved = array_key_exists('foto_path', $this->data ?? [])
            ? ($this->data['foto_path'] ?? null)
            : ($this->record?->foto_path ?? null);
        $saved = filled($saved) ? (string) $saved : null;
        $original = filled($this->personFotoOriginalPath) ? (string) $this->personFotoOriginalPath : null;

        if ($original !== null && $original !== $saved) {
            Storage::disk('public')->delete($original);
        }

        $this->personFotoOriginalPath = $saved;
    }

    public function discardPendingPersonPhoto(): void
    {
        $this->deleteUnsavedPersonPhoto();
    }

    protected function deleteUnsavedPersonPhoto(): void
    {
        $current = $this->data['foto_path'] ?? null;

        if (filled($current) && (string) $current !== (string) $this->personFotoOriginalPath) {
            Storage::disk('public')->delete((string) $current);
        }
    }
}
