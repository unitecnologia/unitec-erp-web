<?php

namespace App\Support\Erp\Nfe;

use App\Filament\Resources\NfeResource;
use App\Models\Nfe;

/**
 * Células da grade leve NF-e (mesmo conteúdo visual da tabela Filament anterior).
 */
final class NfeListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Nfe $record): array
    {
        $numero = $record->numero !== null && $record->numero !== ''
            ? (string) $record->numero
            : '—';

        $dataEmissao = filled($record->data_emissao)
            ? $record->data_emissao->format('d/m/Y')
            : '—';

        $dataSaida = filled($record->data_saida)
            ? $record->data_saida->format('d/m/Y')
            : '—';

        $cliente = filled($record->cliente?->nome_razao)
            ? (string) $record->cliente->nome_razao
            : '—';

        $chave = filled($record->chave) ? (string) $record->chave : '—';
        $protocolo = filled($record->protocolo) ? (string) $record->protocolo : '—';
        $finalidade = Nfe::finalidadeLabel($record->finalidade);

        $status = (string) ($record->status ?? '');
        $statusLabel = Nfe::statusLabels()[$status] ?? $status;
        $statusHtml = '<span class="erp-nfe__status-chip erp-nfe__status-chip--'.e($status).'">'
            .e($statusLabel)
            .'</span>';

        $valor = number_format((float) ($record->total ?? 0), 2, ',', '.');
        $totalHtml = '<span class="erp-list-total-cell">'
            .'<span class="erp-list-total-cell__currency">R$</span>'
            .'<span class="erp-list-total-cell__amount" title="R$ '.e($valor).'">'.e($valor).'</span>'
            .'</span>';

        return [
            'numero' => e($numero),
            'npedido' => NfeResource::formatOsDav($record),
            'data_emissao' => e($dataEmissao),
            'data_saida' => e($dataSaida),
            'cliente' => '<span title="'.e($cliente === '—' ? '' : $cliente).'">'.e($cliente).'</span>',
            'chave' => '<span title="'.e($chave === '—' ? '' : $chave).'">'.e($chave).'</span>',
            'protocolo' => '<span title="'.e($protocolo === '—' ? '' : $protocolo).'">'.e($protocolo).'</span>',
            'finalidade' => e($finalidade !== '' ? $finalidade : 'NORMAL'),
            'status' => $statusHtml,
            'total' => $totalHtml,
        ];
    }
}
