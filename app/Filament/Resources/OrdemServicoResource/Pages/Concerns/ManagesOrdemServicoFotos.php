<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Support\UnitecOs\OrdemServicoImagemService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\WithFileUploads;

trait ManagesOrdemServicoFotos
{
    use WithFileUploads;

    /** @var list<array{id: int, url: string, em: string}> */
    public array $osFotos = [];

    public $osFotoUpload = null;

    protected function resetOsFotosState(): void
    {
        $this->osFotos = [];
        $this->osFotoUpload = null;
    }

    protected function refreshOsFotosFromOrdem(OrdemServico $ordem): void
    {
        if (! $ordem->relationLoaded('imagens')) {
            $ordem->load('imagens');
        }

        $this->osFotos = $ordem->imagens
            ->filter(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_FOTO)
            ->sortBy([
                ['item', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (OrdemServicoImagem $img): ?array => $this->mapOsFotoRow($img))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return ?array{id: int, url: string, em: string}
     */
    protected function mapOsFotoRow(OrdemServicoImagem $img): ?array
    {
        $path = trim((string) $img->caminho);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return [
            'id' => (int) $img->id,
            'url' => Storage::disk('public')->url($path),
            'em' => optional($img->created_at)?->format('d/m/Y H:i') ?: '—',
        ];
    }

    public function updatedOsFotoUpload(): void
    {
        if ($this->osReadOnly()) {
            $this->osFotoUpload = null;

            return;
        }

        if (! $this->isEditingOs()) {
            $this->osFotoUpload = null;
            Notification::make()
                ->title('Salve a OS antes de anexar fotos.')
                ->body('Use Gravar (F5) para registrar a ordem; depois inclua as imagens aqui ou pelo app.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->validate([
                'osFotoUpload' => 'required|image|max:12288',
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->osFotoUpload = null;
            Notification::make()
                ->title('Foto não carregada')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Use uma imagem válida (até 12 MB).')
                ->danger()
                ->send();

            return;
        }

        if (! $this->osFotoUpload) {
            return;
        }

        /** @var OrdemServico $ordem */
        $ordem = $this->record;

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $this->osFotoUpload;

        try {
            app(OrdemServicoImagemService::class)->storeFoto($ordem, Auth::user(), $file);
        } catch (\Throwable $exception) {
            $this->osFotoUpload = null;
            report($exception);
            Notification::make()
                ->title('Não foi possível anexar a foto.')
                ->body('Verifique o link public/storage e tente novamente.')
                ->danger()
                ->send();

            return;
        }

        $this->osFotoUpload = null;
        $this->refreshOsFotosFromOrdem($ordem->fresh('imagens'));

        Notification::make()
            ->title('Foto anexada.')
            ->success()
            ->send();
    }

    public function excluirOsFoto(int $fotoId): void
    {
        if ($this->osReadOnly() || ! $this->isEditingOs()) {
            return;
        }

        /** @var OrdemServico $ordem */
        $ordem = $this->record;

        $img = $ordem->imagens()
            ->whereKey($fotoId)
            ->where('tipo', OrdemServicoImagem::TIPO_FOTO)
            ->first();

        if (! $img instanceof OrdemServicoImagem) {
            return;
        }

        try {
            app(OrdemServicoImagemService::class)->deleteFileAndRow($img);
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Não foi possível remover a foto.')
                ->danger()
                ->send();

            return;
        }

        $this->refreshOsFotosFromOrdem($ordem->fresh('imagens'));

        Notification::make()
            ->title('Foto removida.')
            ->success()
            ->send();
    }
}
