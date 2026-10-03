<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Empresa;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
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
     * Totais só para impressão/preview. Não grava e não altera o orçamento.
     *
     * Descontos do rodapé = diferença entre o bruto das linhas (qtd × preço)
     * e `orcamentos.total`. Assim o desconto de item e o geral entram uma vez,
     * mesmo quando o rateio copiou `desconto_valor` em `orcamento_itens.desconto`.
     *
     * @return array{subtotal_bruto: float, descontos: float, total: float, qtd_total: float}
     */
    public function totaisImpressao(Orcamento $orcamento): array
    {
        $bruto = 0.0;
        $qtdTotal = 0.0;

        foreach ($orcamento->itens as $item) {
            $qtdTotal += (float) $item->quantidade;
            $bruto += round((float) $item->quantidade * (float) $item->preco_unitario, 2);
        }

        $bruto = round($bruto, 2);
        $total = round((float) $orcamento->total, 2);

        return [
            'subtotal_bruto' => $bruto,
            'descontos' => round(max(0, $bruto - $total), 2),
            'total' => $total,
            'qtd_total' => $qtdTotal,
        ];
    }

    /**
     * Linha só para exibição. Não grava.
     *
     * Usa `orcamento_itens.desconto`. Quando esse valor já está no `total` da
     * linha (desconto do item), a conta é preço × qtd − desconto = total.
     * Quando o rateio do desconto geral só sobrescreveu `desconto` e não
     * reduziu `total`, o líquido da linha desconta essa parcela uma vez.
     * O rodapé continua em `totaisImpressao()` (bruto − `orcamentos.total`),
     * para não somar o geral de novo.
     *
     * @return array{codigo: string, produto: string, unidade: string, quantidade: float, valor_unitario: float, desconto: float, subtotal: float}
     */
    public function linhaImpressao(OrcamentoItem $item): array
    {
        $quantidade = (float) $item->quantidade;
        $valorUnitario = round((float) $item->preco_unitario, 2);
        $bruto = round($quantidade * $valorUnitario, 2);
        $descontoGravado = round(max(0, (float) $item->desconto), 2);
        $totalGravado = round(max(0, (float) $item->total), 2);
        $descontoJaNoTotal = abs(round($bruto - $descontoGravado, 2) - $totalGravado) <= 0.02;

        if ($descontoJaNoTotal) {
            $subtotal = $totalGravado;
            $desconto = $descontoGravado;
        } else {
            $subtotal = round(max(0, $totalGravado - $descontoGravado), 2);
            $desconto = round(max(0, $bruto - $subtotal), 2);
        }

        $codigo = $item->product?->codigo;
        $codigo = filled($codigo) ? (string) $codigo : '—';
        $produto = filled($item->descricao)
            ? (string) $item->descricao
            : (string) ($item->product?->descricao ?? '—');

        return [
            'codigo' => $codigo,
            'produto' => $produto,
            'unidade' => mb_strtoupper((string) ($item->product?->unidade ?: 'UN'), 'UTF-8'),
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'desconto' => $desconto,
            'subtotal' => $subtotal,
        ];
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function formatQuantidade(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return number_format($value, 2, ',', '.');
        }

        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public function statusImpressaoKey(Orcamento $orcamento): string
    {
        return match ($orcamento->status) {
            Orcamento::STATUS_FECHADO => 'confirmado',
            Orcamento::STATUS_CANCELADO => 'cancelado',
            Orcamento::STATUS_IMPORTADO => 'faturado',
            default => 'pendente',
        };
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
        $user = Auth::user();

        return [
            'orcamento' => $orcamento,
            'empresa' => $empresa,
            'numero' => $numero,
            'statusLabel' => $statusLabel,
            'statusKey' => $this->statusImpressaoKey($orcamento),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'empresaCidadeUf' => $this->formatEmpresaCidadeUf($empresa),
            'logoDataUri' => $logoDataUri,
            'logoUrl' => $logoUrl,
            'totais' => $this->totaisImpressao($orcamento),
            'autoPrint' => false,
            'embedded' => false,
            'printedAt' => now(),
            'printedBy' => (string) ($user?->name ?: $user?->email ?: 'USUARIO'),
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

    protected function formatEmpresaCidadeUf(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $cidade = filled($empresa->cidade)
            ? mb_strtoupper(trim((string) $empresa->cidade), 'UTF-8')
            : '';
        $uf = filled($empresa->uf)
            ? mb_strtoupper(trim((string) $empresa->uf), 'UTF-8')
            : '';

        if ($cidade === '' && $uf === '') {
            return '';
        }

        if ($cidade !== '' && $uf !== '') {
            return $cidade . ' / ' . $uf;
        }

        return $cidade !== '' ? $cidade : $uf;
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
