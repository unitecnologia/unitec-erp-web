<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Empresa;
use App\Models\Person;
use Illuminate\Support\Facades\Auth;

class CompraDanfeReportService
{
    public function loadCompra(Compra $compra): Compra
    {
        return $compra->load([
            'fornecedor',
            'itens' => fn ($query) => $query->orderBy('id')->with('product'),
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
    public function buildViewData(Compra $compra, ?Empresa $empresa = null): array
    {
        $compra = $this->loadCompra($compra);
        $empresa ??= $this->resolveEmpresa();
        $fornecedor = $compra->fornecedor;
        $nfe = $this->extractNfeKeyParts($compra->chave_nfe);

        $subtotalProdutos = (float) $compra->itens->sum('total');
        $totalNota = (float) ($compra->total > 0 ? $compra->total : $subtotalProdutos);

        return [
            'compra' => $compra,
            'empresa' => $empresa,
            'fornecedor' => $fornecedor,
            'emitente' => $this->buildPartyBlock($fornecedor, true),
            'destinatario' => $this->buildPartyBlock($empresa, false),
            'chave' => $this->onlyDigits($compra->chave_nfe),
            'chaveFormatada' => $this->formatChave($compra->chave_nfe),
            'barcodeDataUri' => $this->barcodeDataUri($compra->chave_nfe),
            'numeroNota' => $this->formatNumeroNota($compra->numero_nota),
            'serie' => str_pad($nfe['serie'], 3, '0', STR_PAD_LEFT),
            'modelo' => str_pad($nfe['modelo'], 2, '0', STR_PAD_LEFT),
            'tipoOperacao' => '0',
            'tipoOperacaoLabel' => 'ENTRADA',
            'naturezaOperacao' => 'COMPRA PARA COMERCIALIZACAO',
            'protocolo' => '',
            'dataEmissao' => $compra->data_emissao?->format('d/m/Y') ?? '',
            'dataEntrada' => $compra->data_entrada?->format('d/m/Y') ?? '',
            'horaEntrada' => '',
            'itens' => $this->buildItens($compra),
            'totais' => $this->buildTotais($subtotalProdutos, $totalNota),
            'informacoesComplementares' => $this->buildInformacoesComplementares($compra, $empresa),
            'printedAt' => now(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildPartyBlock(Person|Empresa|null $party, bool $isPerson): array
    {
        if (! $party) {
            return [
                'nome' => '',
                'endereco' => '',
                'municipio' => '',
                'uf' => '',
                'telefone' => '',
                'ie' => '',
                'im' => '',
                'cnpj' => '',
            ];
        }

        if ($isPerson && $party instanceof Person) {
            return [
                'nome' => mb_strtoupper((string) $party->nome_razao, 'UTF-8'),
                'endereco' => $this->formatEndereco($party->endereco, $party->numero, $party->bairro, $party->cep),
                'municipio' => mb_strtoupper((string) ($party->cidade_nome ?? ''), 'UTF-8'),
                'uf' => (string) ($party->uf ?? ''),
                'telefone' => (string) ($party->fone1 ?: $party->celular1 ?: ''),
                'ie' => (string) ($party->rg_ie ?? ''),
                'im' => '',
                'cnpj' => $this->formatCpfCnpj($party->cpf_cnpj),
            ];
        }

        /** @var Empresa $party */
        return [
            'nome' => mb_strtoupper((string) ($party->razao_social ?: $party->nome ?: $party->fantasia), 'UTF-8'),
            'endereco' => $this->formatEndereco($party->endereco, $party->numero, $party->bairro, $party->cep),
            'municipio' => mb_strtoupper((string) ($party->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($party->uf ?? ''),
            'telefone' => (string) ($party->telefone ?? ''),
            'ie' => (string) ($party->ie ?? ''),
            'im' => (string) ($party->im ?? ''),
            'cnpj' => $this->formatCpfCnpj($party->cnpj),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildItens(Compra $compra): array
    {
        $rows = [];

        foreach ($compra->itens as $index => $item) {
            $rows[] = $this->buildItemRow($item, $index + 1);
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    protected function buildItemRow(CompraItem $item, int $index): array
    {
        $product = $item->product;
        $codigo = $product?->codigo;
        $codigoFormatado = '—';

        if ($codigo !== null && $codigo !== '') {
            $trimmed = ltrim((string) $codigo, '0');
            $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
        }

        $quantidade = (float) $item->quantidade;
        $valorUnitario = (float) $item->valor_unitario;
        $total = (float) $item->total;
        $baseIcms = $total;
        $aliqIcms = (float) ($product?->aliq_icms ?? 0);
        $valorIcms = $aliqIcms > 0 ? round($baseIcms * ($aliqIcms / 100), 2) : 0.0;

        return [
            'item' => (string) $index,
            'codigo' => $codigoFormatado,
            'descricao' => $product?->descricao ?? '—',
            'ncm' => (string) ($product?->ncm ?? ''),
            'cst' => (string) ($product?->cst_entrada ?: $product?->cst_icms ?: ''),
            'cfop' => (string) ($product?->cfop_externo ?: $product?->cfop_interno ?: ''),
            'un' => (string) ($product?->unidade ?: 'UN'),
            'quant' => number_format($quantidade, 4, ',', '.'),
            'valor_unit' => number_format($valorUnitario, 4, ',', '.'),
            'valor_total' => number_format($total, 2, ',', '.'),
            'desconto' => '0,00',
            'base_icms' => number_format($baseIcms, 2, ',', '.'),
            'valor_icms' => number_format($valorIcms, 2, ',', '.'),
            'valor_ipi' => '0,00',
            'aliq_icms' => number_format($aliqIcms, 2, ',', '.'),
            'aliq_ipi' => '0,00',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildTotais(float $subtotalProdutos, float $totalNota): array
    {
        $zero = '0,00';

        return [
            'base_icms' => number_format($subtotalProdutos, 2, ',', '.'),
            'valor_icms' => $zero,
            'base_icms_st' => $zero,
            'valor_icms_st' => $zero,
            'total_produtos' => number_format($subtotalProdutos, 2, ',', '.'),
            'frete' => $zero,
            'seguro' => $zero,
            'desconto' => $zero,
            'outras' => $zero,
            'total_ipi' => $zero,
            'total_nota' => number_format($totalNota, 2, ',', '.'),
        ];
    }

    protected function buildInformacoesComplementares(Compra $compra, ?Empresa $empresa): string
    {
        $partes = array_filter([
            filled($compra->chave_nfe) ? 'CHAVE NF-e: ' . $this->onlyDigits($compra->chave_nfe) : null,
            $empresa ? 'DESTINATARIO: ' . mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome), 'UTF-8') : null,
            'DOCUMENTO AUXILIAR GERADO PELO UNITECH ERP WEB.',
        ]);

        return implode("\n", $partes);
    }

    protected function formatEndereco(?string $endereco, ?string $numero, ?string $bairro, ?string $cep): string
    {
        $partes = array_filter([
            filled($endereco) ? mb_strtoupper(trim($endereco), 'UTF-8') : null,
            filled($numero) ? 'Nº ' . trim((string) $numero) : null,
            filled($bairro) ? mb_strtoupper(trim($bairro), 'UTF-8') : null,
            filled($cep) ? 'CEP ' . $this->formatCep($cep) : null,
        ]);

        return implode(' - ', $partes);
    }

    protected function formatCep(?string $cep): string
    {
        $digits = preg_replace('/\D/', '', (string) $cep) ?? '';

        if (strlen($digits) !== 8) {
            return (string) $cep;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }

    public function formatCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }

    public function formatChave(?string $chave): string
    {
        $digits = $this->onlyDigits($chave);

        if ($digits === '') {
            return '';
        }

        return trim(chunk_split($digits, 4, ' '));
    }

    public function formatNumeroNota(?string $numero): string
    {
        $digits = preg_replace('/\D/', '', (string) $numero) ?? '';

        if ($digits === '') {
            return '';
        }

        $padded = str_pad($digits, 9, '0', STR_PAD_LEFT);

        return substr($padded, 0, 3) . '.' . substr($padded, 3, 3) . '.' . substr($padded, 6, 3);
    }

    /**
     * @return array{modelo: string, serie: string}
     */
    public function extractNfeKeyParts(?string $chave): array
    {
        $digits = $this->onlyDigits($chave);

        if (strlen($digits) !== 44) {
            return [
                'modelo' => '55',
                'serie' => '1',
            ];
        }

        $modelo = ltrim(substr($digits, 20, 2), '0');
        $serie = ltrim(substr($digits, 22, 3), '0');

        return [
            'modelo' => $modelo !== '' ? $modelo : '0',
            'serie' => $serie !== '' ? $serie : '0',
        ];
    }

    public function barcodeDataUri(?string $chave): ?string
    {
        $digits = $this->onlyDigits($chave);

        if (strlen($digits) !== 44) {
            return null;
        }

        $svg = $this->buildBarcodeSvg($digits);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    protected function buildBarcodeSvg(string $digits): string
    {
        $x = 2;
        $elements = ['<rect x="0" y="0" width="2" height="48" fill="#000"/>'];

        foreach (str_split($digits) as $digit) {
            $n = (int) $digit;
            $bar = ($n % 2 === 0) ? 2 : 3;
            $space = ($n % 3 === 0) ? 2 : 1;
            $elements[] = sprintf('<rect x="%d" y="0" width="%d" height="48" fill="#000"/>', $x, $bar);
            $x += $bar + $space;
        }

        $elements[] = sprintf('<rect x="%d" y="0" width="2" height="48" fill="#000"/>', $x);
        $width = $x + 2;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="48" viewBox="0 0 ' . $width . ' 48">'
            . implode('', $elements)
            . '</svg>';
    }

    protected function onlyDigits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
