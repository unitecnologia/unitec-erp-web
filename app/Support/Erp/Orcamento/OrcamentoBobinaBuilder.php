<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Empresa;
use App\Models\Orcamento;

class OrcamentoBobinaBuilder
{
    /**
     * @param  array{subtotal_bruto?: float, descontos?: float, total?: float}  $totais
     * @return list<string>
     */
    public function buildLines(
        Orcamento $orcamento,
        ?Empresa $empresa,
        string $numero,
        string $statusLabel,
        string $empresaEndereco,
        array $totais = [],
    ): array {
        $f = OrcamentoBobinaFormatter::class;
        $report = app(OrcamentoReportService::class);
        $lines = [];

        if ($totais === []) {
            $totais = $report->totaisImpressao($orcamento);
        }

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
        $lines[] = $f::padLeft('PRECO', 9) . ' '
            . $f::padLeft('QTD', 6) . ' '
            . $f::padRight('UND', 3) . ' '
            . $f::padLeft('DESC', 9) . ' '
            . $f::padLeft('TOTAL', 9);

        if ($orcamento->itens->isEmpty()) {
            $lines[] = 'Nenhum item informado.';
        }

        foreach ($orcamento->itens as $item) {
            $linha = $report->linhaImpressao($item);
            $descricao = mb_strtoupper($linha['produto'], 'UTF-8');
            $itemNum = str_pad((string) $item->item, 2, '0', STR_PAD_LEFT);

            $descLines = $f::wrap($descricao);
            $lines[] = $itemNum . ' ' . ($descLines[0] ?? '');

            for ($index = 1, $count = count($descLines); $index < $count; $index++) {
                $lines[] = '   ' . $descLines[$index];
            }

            $quantidade = $linha['quantidade'];
            $quantidadeLabel = fmod($quantidade, 1.0) === 0.0
                ? (string) (int) $quantidade
                : number_format($quantidade, 3, ',', '');

            $lines[] = $f::padLeft($f::money($linha['valor_unitario']), 9) . ' '
                . $f::padLeft($quantidadeLabel, 6) . ' '
                . $f::padRight($linha['unidade'], 3) . ' '
                . $f::padLeft($f::money($linha['desconto']), 9) . ' '
                . $f::padLeft($f::money($linha['subtotal']), 9);
        }

        $lines[] = $f::rule('-');
        $lines[] = $f::line('Subtotal bruto', $f::money((float) ($totais['subtotal_bruto'] ?? 0)));
        $lines[] = $f::line('Descontos', $f::money((float) ($totais['descontos'] ?? 0)));
        $lines[] = $f::line('Total', $f::money((float) ($totais['total'] ?? $orcamento->total)));
        $lines[] = $f::rule('-');
        $lines[] = 'Observacoes:';

        foreach ($f::wrap((string) ($orcamento->observacoes ?: '')) as $line) {
            $lines[] = $line === '' ? '' : $line;
        }

        $lines[] = $f::rule('=');

        return $lines;
    }
}
