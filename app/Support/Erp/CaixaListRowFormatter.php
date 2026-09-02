<?php

namespace App\Support\Erp;

use App\Models\CaixaLancamento;
use Illuminate\Support\Carbon;

final class CaixaListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(CaixaLancamento $record): array
    {
        $historico = mb_strtoupper(
            preg_replace('/^\[MANUAL\]\s*/iu', '', (string) ($record->historico ?? '')) ?? '',
            'UTF-8',
        );

        return [
            'codigo' => e((string) ($record->codigo ?? '—')),
            'emissao' => e($this->formatData($record->emissao)),
            'documento' => filled($record->documento) ? e((string) $record->documento) : '—',
            'historico' => e($historico !== '' ? $historico : '—'),
            'plano_contas' => filled($record->plano_contas) ? e((string) $record->plano_contas) : '—',
            'conta' => e($record->conta?->nome ?? '—'),
            'entrada' => $this->formatMoneyCell((float) ($record->entrada ?? 0)),
            'saida' => $this->formatMoneyCell((float) ($record->saida ?? 0)),
            'ver_itens' => $this->formatViewButton((int) $record->getKey()),
        ];
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

    private function formatMoneyCell(float $value): string
    {
        $valor = number_format($value, 2, ',', '.');

        return '<span class="erp-caixa-money">'
            . '<span class="erp-caixa-money__currency">R$</span>'
            . '<span class="erp-caixa-money__amount" title="R$ '.e($valor).'">'.e($valor).'</span>'
            . '</span>';
    }

    private function formatViewButton(int $lancamentoId): string
    {
        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-caixa-open-view\', { lancamentoId: '.$lancamentoId.' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-caixa-open-view\', { lancamentoId: '.$lancamentoId.' })"'
            . ' class="erp-caixa__eye-btn" title="Visualizar lançamento" aria-label="Visualizar lançamento">'
            . '<svg class="erp-caixa__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }
}
