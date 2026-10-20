<?php

namespace App\Support\Erp\Carga;

use App\Models\Carga;
use Illuminate\Support\Carbon;

/**
 * Células da grade leve Carga / Romaneio (mesmo conteúdo visual da tabela Filament anterior).
 */
final class CargaListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Carga $record): array
    {
        $numero = trim((string) ($record->numero ?? ''));
        $numeroText = $numero !== '' ? $numero : '—';

        $data = $this->formatData($record->data);

        $motorista = trim((string) ($record->motorista?->proprietario ?? ''));
        $entregador = trim((string) ($record->entregador?->name ?? ''));

        $placa = trim((string) ($record->veiculo?->placa ?? ''));
        $descricao = trim((string) ($record->veiculo?->descricao ?? ''));
        $veiculo = $placa === ''
            ? '—'
            : ($descricao !== '' ? "{$placa} — {$descricao}" : $placa);

        $qtd = (int) ($record->pedidos_count ?? 0);
        $valor = (float) ($record->pedidos_sum_total ?? 0);
        $valorFmt = number_format($valor, 2, ',', '.');

        $status = (string) ($record->status ?? '');
        $statusLabel = Carga::statusLabels()[$status] ?? $status;
        $statusColor = match ($status) {
            Carga::STATUS_ABERTA => 'info',
            Carga::STATUS_FECHADA => 'success',
            Carga::STATUS_CANCELADA => 'danger',
            default => 'gray',
        };

        return [
            'numero' => e($numeroText),
            'data' => e($data),
            'motorista' => '<span class="fi-font-bold" title="'.e($motorista === '' ? '' : $motorista).'">'
                .e($motorista !== '' ? $motorista : '—').'</span>',
            'entregador' => '<span title="'.e($entregador === '' ? '' : $entregador).'">'
                .e($entregador !== '' ? $entregador : '—').'</span>',
            'veiculo' => '<span title="'.e($veiculo === '—' ? '' : $veiculo).'">'.e($veiculo).'</span>',
            'pedidos_count' => e((string) $qtd),
            'valor_total' => '<span class="erp-list-total-cell">'
                .'<span class="erp-list-total-cell__currency">R$</span>'
                .'<span class="erp-list-total-cell__amount" title="R$ '.e($valorFmt).'">'.e($valorFmt).'</span>'
                .'</span>',
            'status' => '<span class="fi-badge fi-size-sm fi-color-'.$statusColor.'">'
                .'<span class="fi-badge-label">'.e($statusLabel).'</span></span>',
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
}
