<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Empresa;
use App\Models\Orcamento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class OrcamentoReportService
{
    public function loadOrcamento(Orcamento $orcamento): Orcamento
    {
        return $orcamento->load([
            'cliente',
            'vendedor',
            'itens' => fn ($query) => $query->orderBy('item')->with('product'),
        ]);
    }

    public function resolveEmpresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Orcamento $orcamento, ?Empresa $empresa = null): array
    {
        $orcamento = $this->loadOrcamento($orcamento);
        $empresa ??= $this->resolveEmpresa();

        $numero = $this->formatNumero($orcamento->numero);
        $statusLabel = mb_strtoupper(Orcamento::statusLabels()[$orcamento->status] ?? $orcamento->status, 'UTF-8');
        $logoDataUri = $this->pdfImagesSupported() ? $this->logoDataUri($empresa) : null;
        $logoUrl = $logoDataUri === null && $this->pdfImagesSupported() ? $empresa?->logoUrl() : null;

        return [
            'orcamento' => $orcamento,
            'empresa' => $empresa,
            'numero' => $numero,
            'statusLabel' => $statusLabel,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $logoDataUri,
            'logoUrl' => $logoUrl,
            'autoPrint' => false,
            'embedded' => false,
            'printedAt' => now(),
            'bobina' => false,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(Orcamento $orcamento, ?Empresa $empresa = null): array
    {
        $data = $this->buildViewData($orcamento, $empresa);
        $directory = storage_path('app/temp/orcamentos');

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'orcamento-' . $orcamento->id . '-' . uniqid('', true) . '.pdf';
        $name = 'ORCAMENTO.PDF';

        try {
            Pdf::loadView('reports.orcamento-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->save($path);
        } catch (\Throwable $exception) {
            if ($this->shouldRetryPdfWithoutImages($exception, $data)) {
                $data['logoDataUri'] = null;
                $data['logoUrl'] = null;

                Pdf::loadView('reports.orcamento-pdf', $data)
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

    public function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', $numero);

        return $digits > 0 ? (string) $digits : $numero;
    }

    public function defaultEmailSubject(string $numero): string
    {
        return 'ORCAMENTO N.' . $numero;
    }

    public function defaultEmailMessage(string $numero): string
    {
        return 'SEGUE EM ANEXO ORCAMENTO N.' . $numero;
    }

    public function defaultWhatsAppMessage(string $numero): string
    {
        return 'Segue orçamento nº ' . $numero . '.';
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
        ]);

        if ($partes === []) {
            return '';
        }

        $endereco = array_shift($partes);

        if ($partes !== []) {
            $endereco .= ', ' . implode(' - ', $partes);
        }

        return 'END: ' . $endereco;
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

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
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
