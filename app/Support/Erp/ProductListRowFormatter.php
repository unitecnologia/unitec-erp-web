<?php

namespace App\Support\Erp;

use App\Models\Product;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Support\Carbon;

final class ProductListRowFormatter
{
    public function __construct(
        private readonly ProductEmpresaPrecoService $precoService,
        private readonly ProductEstoqueSaldoService $estoqueService,
    ) {}

    /**
     * @return array<string, string>
     */
    public function format(Product $record): array
    {
        $estoqueAtual = $this->resolveEstoqueEmpresaAtual($record);
        $reservado = (float) ($record->estoque_reservado_sum ?? 0);
        $disponivel = $estoqueAtual - $reservado;

        return [
            'codigo' => e((string) $record->codigo),
            'referencia' => filled($record->referencia) ? e((string) $record->referencia) : '—',
            'codigo_barras' => filled($record->codigo_barras) ? e((string) $record->codigo_barras) : '—',
            'descricao' => e((string) $record->descricao),
            'grupo' => e((string) ($record->grupo ?? '')),
            'preco_venda' => $this->formatPreco($record),
            'estoque' => number_format($estoqueAtual, 0, ',', '.'),
            'estoque_reservado' => number_format($reservado, 0, ',', '.'),
            'estoque_disponivel' => number_format($disponivel, 0, ',', '.'),
            'localizacao' => e((string) ($record->localizacao ?? '')),
            'validade' => $this->formatValidade($record),
            'lote' => e((string) ($record->lote ?? '')),
        ];
    }

    private function formatPreco(Product $record): string
    {
        $valor = number_format($this->precoService->resolvePrecoVenda($record), 2, ',', '.');

        return '<span class="erp-produtos-preco"><span class="erp-produtos-preco__rs">R$</span>'
            . '<span class="erp-produtos-preco__val">' . e($valor) . '</span></span>';
    }

    private function formatValidade(Product $record): string
    {
        if (! filled($record->validade)) {
            return '';
        }

        $date = Carbon::parse($record->validade)->format('d/m/Y');
        $status = $record->validadeStatus();
        $dias = $record->validadeDiasRestantes();

        if ($status === null || $dias === null) {
            return e($date);
        }

        $diasLabel = match (true) {
            $dias === 0 => 'vence hoje',
            $dias === 1 => 'falta 1 dia',
            $dias > 1 => 'faltam ' . $dias . ' dias',
            $dias === -1 => 'vencido há 1 dia',
            default => 'vencido há ' . abs($dias) . ' dias',
        };

        $title = e($diasLabel . ' — ' . $record->validadeStatusLabel());
        $vencidaClass = $status === 'vencido' ? ' is-vencida' : '';

        return '<span class="erp-prod-validade' . $vencidaClass . '" title="' . $title . '">'
            . e($date)
            . '<span class="erp-prod-validade__dot erp-prod-validade__dot--' . e($status) . '" aria-hidden="true"></span>'
            . '</span>';
    }

    private function resolveEstoqueEmpresaAtual(Product $record): float
    {
        if (isset($record->estoque_empresa_atual)) {
            return (float) $record->estoque_empresa_atual;
        }

        return $this->estoqueService->fisicoEmpresa((int) $record->id);
    }
}
