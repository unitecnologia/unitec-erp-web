<?php

namespace App\Support\Erp\Os;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Support\Erp\Orcamento\OrcamentoDescontoService;

/**
 * Monta as linhas e o rateio do desconto geral de um orçamento
 * para a OS, sem gravar nem alterar o orçamento.
 */
final class OrcamentoOsImportacao
{
    public function __construct(
        private readonly OrcamentoDescontoService $descontos = new OrcamentoDescontoService(),
    ) {}

    /**
     * @return array{
     *     linhas: list<array{
     *         tipo: string,
     *         product_id: ?int,
     *         product_codigo: string,
     *         discriminacao: string,
     *         qtd: float,
     *         preco: float,
     *         desconto: float,
     *         acrescimo: float,
     *         total: float,
     *         foto: ?string
     *     }>,
     *     vl_desc_pecas: float,
     *     vl_desc_servicos: float
     * }
     */
    public function mapear(Orcamento $orcamento): array
    {
        $orcamento->loadMissing('itens.product');

        $linhas = [];
        $basePecas = 0.0;
        $baseServicos = 0.0;

        foreach ($orcamento->itens as $item) {
            $linha = $this->linha($item);
            $linhas[] = $linha;

            if ($linha['tipo'] === 'S') {
                $baseServicos += $linha['total'];
            } else {
                $basePecas += $linha['total'];
            }
        }

        $rateio = $this->ratearDescontoGeral(
            (float) ($orcamento->desconto_valor ?? 0),
            round($basePecas, 2),
            round($baseServicos, 2),
        );

        return [
            'linhas' => $linhas,
            'vl_desc_pecas' => $rateio['pecas'],
            'vl_desc_servicos' => $rateio['servicos'],
        ];
    }

    /**
     * Desconto geral proporcional à base líquida de cada grupo.
     * A soma das duas cotas é exatamente o desconto informado.
     * Não inclui desconto de linha.
     *
     * @return array{pecas: float, servicos: float}
     */
    public function ratearDescontoGeral(float $descontoGeral, float $basePecas, float $baseServicos): array
    {
        $descontoGeral = round(max(0, $descontoGeral), 2);
        $basePecas = round(max(0, $basePecas), 2);
        $baseServicos = round(max(0, $baseServicos), 2);
        $soma = round($basePecas + $baseServicos, 2);

        if ($descontoGeral <= 0 || $soma <= 0) {
            return ['pecas' => 0.0, 'servicos' => 0.0];
        }

        if ($baseServicos <= 0) {
            return ['pecas' => $descontoGeral, 'servicos' => 0.0];
        }

        if ($basePecas <= 0) {
            return ['pecas' => 0.0, 'servicos' => $descontoGeral];
        }

        $pecas = round($descontoGeral * ($basePecas / $soma), 2);

        return [
            'pecas' => $pecas,
            'servicos' => round($descontoGeral - $pecas, 2),
        ];
    }

    /**
     * @return array{
     *     tipo: string,
     *     product_id: ?int,
     *     product_codigo: string,
     *     discriminacao: string,
     *     qtd: float,
     *     preco: float,
     *     desconto: float,
     *     acrescimo: float,
     *     total: float,
     *     foto: ?string
     * }
     */
    private function linha(OrcamentoItem $item): array
    {
        $product = $item->product;
        $servico = (bool) ($product?->is_servico ?? false);
        $partes = $this->descontos->partesDaLinha($item);
        $descricao = trim((string) ($item->descricao ?: $product?->descricao ?: ''));

        return [
            'tipo' => $servico ? 'S' : 'P',
            'product_id' => $product ? (int) $product->id : ((int) $item->product_id > 0 ? (int) $item->product_id : null),
            'product_codigo' => (string) ($product?->codigo ?? ''),
            'discriminacao' => $descricao,
            'qtd' => (float) $item->quantidade,
            'preco' => (float) $item->preco_unitario,
            'desconto' => $partes['desconto'],
            'acrescimo' => $partes['acrescimo'],
            'total' => $partes['total'],
            'foto' => $servico ? null : $product?->fotoUrl(),
        ];
    }
};
