<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\Terminal;
use Illuminate\Support\Facades\Storage;

final class PdvPedidoReportData
{
    public const TIPO_IMPRESSORA_PEDIDO_A4 = '0';

    public static function shouldUsePedidoA4(?Terminal $terminal): bool
    {
        return (string) ($terminal?->tipo_impressora ?? '1') === self::TIPO_IMPRESSORA_PEDIDO_A4;
    }

    public static function resolveTerminal(PdvVenda $venda): ?Terminal
    {
        $fromSessao = $venda->sessao?->terminal;

        if ($fromSessao !== null) {
            return $fromSessao;
        }

        return TerminalResolver::make()->current();
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        bool $autoPrint,
        int $copias,
    ): array {
        $dataVenda = $venda->fechado_em ?? $venda->created_at;

        return [
            'venda' => $venda,
            'empresa' => $empresa,
            'usuario' => $usuario,
            'numero' => self::formatNumero($venda->numero),
            'dataVenda' => $dataVenda?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'clienteNome' => mb_strtoupper(
                $venda->person?->nome_razao ?? 'CONSUMIDOR FINAL',
                'UTF-8',
            ),
            'vendedorNome' => mb_strtoupper(
                $venda->vendedor_nome
                    ?: ($venda->vendedor?->nome ?? 'LOJA'),
                'UTF-8',
            ),
            'meioPagamento' => mb_strtoupper(self::formatMeioPagamento($venda), 'UTF-8'),
            'empresaEndereco' => self::formatEmpresaEndereco($empresa),
            'empresaCidadeUf' => self::formatEmpresaCidadeUf($empresa),
            'declaracaoCidadeUf' => self::formatDeclaracaoCidadeUf($empresa),
            'declaracaoTexto' => self::formatDeclaracaoTexto($empresa, $dataVenda),
            'logoDataUri' => null,
            'logoUrl' => $empresa?->logoUrl(),
            'autoPrint' => $autoPrint,
            'copias' => max(1, min(3, $copias)),
            'printedAt' => now(),
        ];
    }

    public static function formatNumero(int|string|null $numero): string
    {
        if (blank($numero)) {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', (string) $numero);

        return $digits > 0 ? (string) $digits : (string) $numero;
    }

    public static function formatMeioPagamento(PdvVenda $venda): string
    {
        if ($venda->relationLoaded('pagamentos') && $venda->pagamentos->isNotEmpty()) {
            return $venda->pagamentos
                ->map(fn ($pagamento) => trim((string) $pagamento->forma))
                ->filter()
                ->unique()
                ->implode(' / ');
        }

        return filled($venda->forma_pagamento) ? (string) $venda->forma_pagamento : '—';
    }

    public static function formatEmpresaEndereco(?Empresa $empresa): string
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

    public static function formatEmpresaCidadeUf(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $partes = array_filter([
            filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : null,
            filled($empresa->uf) ? mb_strtoupper(trim($empresa->uf), 'UTF-8') : null,
        ]);

        return $partes !== [] ? implode(' - ', $partes) : '';
    }

    public static function formatDeclaracaoCidadeUf(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $cidade = filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : '';
        $uf = filled($empresa->uf) ? mb_strtoupper(trim($empresa->uf), 'UTF-8') : '';

        if ($cidade === '' && $uf === '') {
            return '';
        }

        if ($cidade !== '' && $uf !== '') {
            return $cidade . '-' . $uf;
        }

        return $cidade !== '' ? $cidade : $uf;
    }

    public static function formatDeclaracaoTexto(?Empresa $empresa, ?\DateTimeInterface $dataVenda): string
    {
        $cidadeUf = self::formatDeclaracaoCidadeUf($empresa);
        $data = $dataVenda?->format('d/m/Y') ?? now()->format('d/m/Y');

        $texto = 'Declaro que recebi os itens descritos acima';

        if ($cidadeUf !== '') {
            $texto .= ', ' . $cidadeUf;
        }

        return $texto . ', ' . $data;
    }

    public static function logoDataUri(?Empresa $empresa, int $maxBytes = 524288): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }

        $size = Storage::disk('public')->size($empresa->logo_path);

        if ($size <= 0 || $size > $maxBytes) {
            return null;
        }

        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
