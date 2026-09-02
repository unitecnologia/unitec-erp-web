<?php

namespace App\Support\Erp;

use App\Models\Entrega;
use App\Models\Venda;
use Illuminate\Support\Carbon;

final class VendaListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Venda $record): array
    {
        $status = (string) ($record->status ?? '');
        $statusLabel = Venda::statusLabels()[$status] ?? $status;

        $plataforma = $record->plataformaEfetiva();
        $plataformaLabel = e($record->plataformaLabel());
        $plataformaClass = match ($plataforma) {
            Venda::PLATAFORMA_PDV => 'info',
            Venda::PLATAFORMA_MOBILE => 'warning',
            default => 'gray',
        };

        $entregaStatus = $record->entrega?->status;
        $entregaLabel = $entregaStatus
            ? (Entrega::statusLabels()[$entregaStatus] ?? ucfirst(str_replace('_', ' ', (string) $entregaStatus)))
            : '—';

        $nfce = $record->pdvVenda?->nfce;
        $nfceLabel = '—';

        if ($nfce !== null && $nfce->numero !== null) {
            $serie = ltrim((string) ($nfce->serie ?? '1'), '0') ?: '1';
            $numero = str_pad((string) $nfce->numero, 6, '0', STR_PAD_LEFT);
            $nfceLabel = e($serie . ' / ' . $numero);
        }

        $pdvNumero = $record->pdvVenda?->numero;
        $pdvLabel = $pdvNumero !== null
            ? e(str_pad((string) $pdvNumero, 6, '0', STR_PAD_LEFT))
            : '—';

        $valor = number_format((float) ($record->total ?? 0), 2, ',', '.');

        return [
            'numero' => e($this->formatNumero($record->numero)),
            'data' => e($this->formatData($record->data)),
            'hora_abertura' => e($this->formatHora($record->hora_abertura)),
            'hora' => e($this->formatHora($record->hora)),
            'cliente' => e((string) ($record->cliente?->nome_razao ?? '—')),
            'vendedor' => e($record->vendedorNome()),
            'plataforma' => '<span class="fi-badge fi-color-' . e($plataformaClass) . '">' . $plataformaLabel . '</span>',
            'forma_pagamento' => filled($record->forma_pagamento) ? e((string) $record->forma_pagamento) : '—',
            'total' => '<span class="erp-vendas-total-cell"><span class="erp-vendas-total-cell__currency">R$</span>'
                . '<span class="erp-vendas-total-cell__amount" title="R$ ' . e($valor) . '">' . e($valor) . '</span></span>',
            'status' => '<span class="erp-vendas-status erp-vendas-status--' . e($status) . '">' . e($statusLabel) . '</span>',
            'entrega' => e($entregaLabel),
            'tipo' => e(Venda::tipoLabels()[(string) $record->tipo] ?? (string) $record->tipo),
            'pdv_numero' => $pdvLabel,
            'nfce' => $nfceLabel,
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

    private function formatHora(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        if ($state instanceof \DateTimeInterface) {
            return $state->format('H:i:s');
        }

        try {
            return Carbon::parse((string) $state)->format('H:i:s');
        } catch (\Throwable) {
            $raw = (string) $state;

            return strlen($raw) >= 8 ? substr($raw, 0, 8) : $raw;
        }
    }

    private function formatVerItensButton(int $vendaId): string
    {
        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-venda-open-itens\', { vendaId: ' . $vendaId . ' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-venda-open-itens\', { vendaId: ' . $vendaId . ' })"'
            . ' class="erp-vendas__eye-btn" title="Ver itens da venda" aria-label="Ver itens da venda">'
            . '<svg class="erp-vendas__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }
}
