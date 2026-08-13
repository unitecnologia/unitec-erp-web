<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Empresa;
use App\Models\Orcamento;

class OrcamentoBobinaBuilder
{
    /**
     * @return list<string>
     */
    public function buildLines(
        Orcamento $orcamento,
        ?Empresa $empresa,
        string $numero,
        string $statusLabel,
        string $empresaEndereco,
    ): array {
        $f = OrcamentoBobinaFormatter::class;
        $lines = [];

        foreach ($f::wrap(mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8')) as $line) {
            $lines[] = $f::center($line);
        }

        if (filled($empresa?->responsavel)) {
            foreach ($f::wrap(mb_strtoupper($empresa->responsavel, 'UTF-8')) as $line) {
                $lines[] = $f::center($line);
            }
        }

        if (filled($empresaEndereco)) {
            foreach ($f::wrap($empresaEndereco) as $line) {
                $lines[] = $f::center($line);
            }
        }

        $foneEmail = 'FONE: ' . ($empresa?->telefone ?: '') . '  EMAIL: ' . ($empresa?->email ?: '');

        foreach ($f::wrap($foneEmail) as $line) {
            $lines[] = $f::center($line);
        }

        $lines[] = $f::rule('=');
        $lines[] = $f::line('ORCAMENTO n ' . $numero, $statusLabel);
        $lines[] = $f::rule('-');
        $lines[] = 'DATA: ' . ($orcamento->data?->format('d/m/Y') ?? '—');
        $lines[] = 'VALIDADE: ' . (int) ($orcamento->validade_dias ?? 0) . ' dias';

        foreach ($f::wrap('CLIENTE: ' . mb_strtoupper($orcamento->cliente?->nome_razao ?? '—', 'UTF-8')) as $line) {
            $lines[] = $line;
        }

        foreach ($f::wrap('VENDEDOR: ' . mb_strtoupper($orcamento->vendedor?->nome ?? '—', 'UTF-8')) as $line) {
            $lines[] = $line;
        }

        $fpg = mb_strtoupper($orcamento->forma_pagamento ?? '', 'UTF-8');
        $lines[] = filled($fpg) ? 'FPG: ' . $fpg : 'FPG:';

        $lines[] = $f::rule('-');
        $lines[] = $f::line('IT', 'PRODUTO');
        $lines[] = $f::padLeft('PRECO', 10) . ' '
            . $f::padLeft('QTD', 6) . ' '
            . $f::padRight('UND', 3) . ' '
            . $f::padLeft('TOTAL', 10);

        if ($orcamento->itens->isEmpty()) {
            $lines[] = 'Nenhum item informado.';
        }

        foreach ($orcamento->itens as $item) {
            $quantidade = (float) $item->quantidade;
            $quantidadeLabel = fmod($quantidade, 1.0) === 0.0
                ? (string) (int) $quantidade
                : number_format($quantidade, 3, ',', '');
            $descricao = filled($item->descricao)
                ? $item->descricao
                : ($item->product?->descricao ?? '—');
            $descricao = mb_strtoupper($descricao, 'UTF-8');
            $unidade = mb_strtoupper($item->product?->unidade ?? 'UN', 'UTF-8');
            $itemNum = str_pad((string) $item->item, 2, '0', STR_PAD_LEFT);

            $descLines = $f::wrap($descricao);
            $lines[] = $itemNum . ' ' . ($descLines[0] ?? '');

            for ($index = 1, $count = count($descLines); $index < $count; $index++) {
                $lines[] = '   ' . $descLines[$index];
            }

            $detail = $f::padLeft($f::money((float) $item->preco_unitario), 10) . ' '
                . $f::padLeft($quantidadeLabel, 6) . ' '
                . $f::padRight($unidade, 3) . ' '
                . $f::padLeft($f::money((float) $item->total), 10);
            $lines[] = $detail;
        }

        $lines[] = $f::rule('-');
        $lines[] = $f::line('SubTotal>>>', $f::money((float) $orcamento->subtotal));
        $lines[] = $f::line('Desconto>>>', $f::money((float) $orcamento->desconto_valor));
        $lines[] = $f::line('Total>>>', $f::money((float) $orcamento->total));
        $lines[] = $f::rule('-');
        $lines[] = 'Observacoes:';

        foreach ($f::wrap((string) ($orcamento->observacoes ?: '')) as $line) {
            $lines[] = $line === '' ? '' : $line;
        }

        $lines[] = $f::rule('=');

        return $lines;
    }
}
