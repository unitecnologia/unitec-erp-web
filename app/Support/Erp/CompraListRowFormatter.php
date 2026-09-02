<?php

namespace App\Support\Erp;

use App\Models\Compra;
use Illuminate\Support\Carbon;

final class CompraListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Compra $record): array
    {
        $devolvida = (bool) ($record->has_devolucao_finalizada ?? false);
        $status = $devolvida ? 'devolvida' : (string) ($record->status ?? '');
        $statusLabel = $devolvida
            ? 'Devolvida'
            : (Compra::statusLabels()[$status] ?? $status);

        $valor = number_format((float) ($record->total ?? 0), 2, ',', '.');

        return [
            'numero' => e($this->formatNumero($record->numero)),
            'data_emissao' => e($this->formatData($record->data_emissao)),
            'data_entrada' => e($this->formatData($record->data_entrada)),
            'numero_nota' => filled($record->numero_nota) ? e((string) $record->numero_nota) : '—',
            'fornecedor' => e($record->fornecedor?->nome_razao ?? '—'),
            'chave_nfe' => filled($record->chave_nfe) ? e((string) $record->chave_nfe) : '—',
            'status' => '<span class="erp-compras__status-chip erp-compras__status-chip--' . e($status) . '">' . e($statusLabel) . '</span>',
            'total' => '<span class="erp-compras-total-cell"><span class="erp-compras-total-cell__currency">R$</span>'
                . '<span class="erp-compras-total-cell__amount" title="R$ ' . e($valor) . '">' . e($valor) . '</span></span>',
            'ver_itens' => $this->formatVerItensButton((int) $record->getKey()),
        ];
    }

    private function formatNumero(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        $trimmed = ltrim((string) $state, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    private function formatData(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function formatVerItensButton(int $compraId): string
    {
        return '<button type="button"'
            . ' wire:click.stop="$dispatch(\'erp-compra-open-lancamento\', { compraId: ' . $compraId . ' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-compra-open-lancamento\', { compraId: ' . $compraId . ' })"'
            . ' class="erp-compras__eye-btn" title="Lançamento de compras" aria-label="Lançamento de compras">'
            . '<svg class="erp-compras__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></button>';
    }
}
