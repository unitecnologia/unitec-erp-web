<?php

namespace App\Support\Erp\Recibo;

use App\Filament\Resources\ReciboResource;
use App\Models\Empresa;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ReciboReportService
{
    public function resolveEmpresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Recibo $recibo, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->resolveEmpresa();
        $logoDataUri = $this->pdfImagesSupported() ? $this->logoDataUri($empresa) : null;
        $logoUrl = $logoDataUri === null && $this->pdfImagesSupported() ? $empresa?->logoUrl() : null;

        return [
            'recibo' => $recibo,
            'empresa' => $empresa,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $logoDataUri,
            'logoUrl' => $logoUrl,
            'extenso' => $recibo->ensureExtenso(),
            'printedAt' => now(),
            'closeUrl' => ReciboResource::getUrl('index'),
            'autoPrint' => false,
            'embedded' => false,
            'bobina' => false,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(Recibo $recibo, ?Empresa $empresa = null): array
    {
        $data = $this->buildViewData($recibo, $empresa);
        $directory = storage_path('app/temp/recibos');

        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'recibo-'.$recibo->id.'-'.uniqid('', true).'.pdf';
        $name = 'RECIBO.PDF';

        try {
            Pdf::loadView('reports.recibo-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->save($path);
        } catch (\Throwable $exception) {
            if ($this->shouldRetryPdfWithoutImages($exception, $data)) {
                $data['logoDataUri'] = null;
                $data['logoUrl'] = null;

                Pdf::loadView('reports.recibo-pdf', $data)
                    ->setPaper('a4', 'portrait')
                    ->save($path);
            } else {
                throw $exception;
            }
        }

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    public function defaultEmailSubject(int|string $codigo): string
    {
        return 'RECIBO N.'.$codigo;
    }

    public function defaultEmailMessage(int|string $codigo): string
    {
        return 'SEGUE EM ANEXO RECIBO N.'.$codigo;
    }

    protected function formatEmpresaEndereco(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $partes = array_filter([
            filled($empresa->endereco) ? mb_strtoupper(trim($empresa->endereco), 'UTF-8') : null,
            filled($empresa->numero) ? trim((string) $empresa->numero) : null,
            filled($empresa->bairro) ? mb_strtoupper(trim($empresa->bairro), 'UTF-8') : null,
            filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : null,
            filled($empresa->uf) ? mb_strtoupper(trim($empresa->uf), 'UTF-8') : null,
        ]);

        return $partes === [] ? '' : implode(', ', $partes);
    }

    protected function logoDataUri(?Empresa $empresa): ?string
    {
        if (! $this->pdfImagesSupported()) {
            return null;
        }

        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    protected function pdfImagesSupported(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function shouldRetryPdfWithoutImages(\Throwable $exception, array $data): bool
    {
        if (($data['logoDataUri'] ?? null) === null && ($data['logoUrl'] ?? null) === null) {
            return false;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'gd extension')
            || str_contains($message, 'gd ')
            || str_contains($message, 'image');
    }
}
