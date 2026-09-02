<?php

namespace App\Support\Erp;

use App\Models\Orcamento;
use Illuminate\Support\Carbon;

final class OrcamentoListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Orcamento $record): array
    {
        $status = (string) ($record->status ?? '');
        $statusLabel = Orcamento::statusLabels()[$status] ?? $status;
        $valor = number_format((float) ($record->total ?? 0), 2, ',', '.');

        return [
            'numero' => e($this->formatNumero($record->numero)),
            'data' => e($this->formatData($record->data)),
            'hora' => e($record->horaExibicao() ?? '—'),
            'cliente' => e($record->clienteDisplayNome() ?: '—'),
            'vendedor' => e($record->vendedor?->nome ?? '—'),
            'cidade' => e($record->clienteDisplayCidade() ?: '—'),
            'uf' => e($record->clienteDisplayUf() ?: '—'),
            'plataforma' => e($record->plataformaLabel()),
            'status' => '<span class="erp-orc-status erp-orc-status--' . e($status) . '">' . e($statusLabel) . '</span>',
            'total' => '<span class="erp-orc-total-cell"><span class="erp-orc-total-cell__currency">R$</span>'
                . '<span class="erp-orc-total-cell__amount" title="R$ ' . e($valor) . '">' . e($valor) . '</span></span>',
            'ver_itens' => $this->formatVerItensButton((int) $record->getKey()),
        ];
    }

    private function formatNumero(mixed $state): string
    {
        if (blank($state)) {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', (string) $state);

        return $digits > 0 ? (string) $digits : (string) $state;
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

    private function formatVerItensButton(int $orcamentoId): string
    {
        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-orcamento-open-view\', { orcamentoId: ' . $orcamentoId . ' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-orcamento-open-view\', { orcamentoId: ' . $orcamentoId . ' })"'
            . ' class="erp-orcamentos__eye-btn" title="Visualizar orçamento" aria-label="Visualizar orçamento">'
            . '<svg class="erp-orcamentos__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }
}
