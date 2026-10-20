<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpTimezone;

/**
 * Células da grade leve NFC-e (mesmo conteúdo visual da tabela Filament anterior).
 */
final class NfceListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(PdvVendaNfce $record, bool $showSituacao = false): array
    {
        $venda = $record->pdvVenda;

        $cpfRaw = filled($venda?->cpf_nota)
            ? (string) $venda->cpf_nota
            : (string) ($venda?->person?->cpf_cnpj ?? '');
        $cpf = $this->formatCpfCnpj($cpfRaw);

        $cliente = $this->clienteNome($venda);
        $chave = filled($record->chave) ? (string) $record->chave : '—';
        $protocolo = filled($record->protocolo) ? (string) $record->protocolo : '—';

        $descAcres = round((float) ($venda?->acrescimo ?? 0) - (float) ($venda?->desconto ?? 0), 2);
        $descAcresText = $descAcres == 0.0
            ? '0,00'
            : (($descAcres > 0 ? '+' : '-').number_format(abs($descAcres), 2, ',', '.'));
        $descAcresHtml = match (true) {
            $descAcres < 0 => '<span class="text-danger-600 dark:text-danger-400">'.e($descAcresText).'</span>',
            $descAcres > 0 => '<span class="text-success-600 dark:text-success-400">'.e($descAcresText).'</span>',
            default => e($descAcresText),
        };

        $total = number_format((float) ($venda?->total ?? 0), 2, ',', '.');
        $totalHtml = '<span class="erp-list-total-cell">'
            .'<span class="erp-list-total-cell__currency">R$</span>'
            .'<span class="erp-list-total-cell__amount" title="R$ '.e($total).'">'.e($total).'</span>'
            .'</span>';

        $pedido = $venda?->venda?->numero;
        $pedidoText = '—';
        if ($pedido !== null && $pedido !== '') {
            $trimmed = ltrim((string) $pedido, '0');
            $pedidoText = $trimmed !== '' ? $trimmed : '0';
        }

        $dav = $venda?->numero !== null
            ? str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT)
            : '—';

        $fechado = filled($venda?->fechado_em)
            ? ErpTimezone::toLocal($venda->fechado_em)->format('d/m/Y H:i')
            : '—';

        $vendedor = filled($venda?->vendedor?->nome)
            ? (string) $venda->vendedor->nome
            : (string) ($venda?->vendedor_nome ?: '—');

        $cells = [
            'serie' => e((string) ($record->serie ?? '—')),
            'numero' => e($record->numero !== null ? str_pad((string) $record->numero, 6, '0', STR_PAD_LEFT) : '—'),
            'fechado_em' => e($fechado),
            'chave' => '<span title="'.e($chave === '—' ? '' : $chave).'">'.e($chave).'</span>',
            'protocolo' => '<span title="'.e($protocolo === '—' ? '' : $protocolo).'">'.e($protocolo).'</span>',
            'cpf' => e($cpf),
            'cliente' => '<span title="'.e($cliente === '—' ? '' : $cliente).'">'.e($cliente).'</span>',
            'caixa' => e((string) ($venda?->sessao?->terminal?->nome ?: '—')),
            'vendedor' => e($vendedor !== '' ? $vendedor : '—'),
            'desc_acres' => $descAcresHtml,
            'total' => $totalHtml,
            'pedido' => e($pedidoText),
            'dav' => e($dav),
        ];

        if ($showSituacao) {
            $situacao = $record->situacaoSefazLabel()
                .(filled($record->motivo_rejeicao) ? ' — '.trim((string) $record->motivo_rejeicao) : '');
            $situacaoShort = mb_strlen($situacao, 'UTF-8') > 90
                ? mb_substr($situacao, 0, 90, 'UTF-8').'…'
                : $situacao;
            $danger = in_array((string) $record->status, [
                PdvVendaNfce::STATUS_REJEITADA,
                PdvVendaNfce::STATUS_DENEGADA,
                PdvVendaNfce::STATUS_DUPLICIDADE,
            ], true);
            $cells['situacao'] = $danger
                ? '<span class="text-danger-600 dark:text-danger-400" title="'.e(trim((string) ($record->motivo_rejeicao ?? ''))).'">'.e($situacaoShort).'</span>'
                : '<span title="'.e(trim((string) ($record->motivo_rejeicao ?? ''))).'">'.e($situacaoShort).'</span>';
        }

        return $cells;
    }

    private function clienteNome(mixed $venda): string
    {
        if ($venda === null) {
            return 'CONSUMIDOR FINAL';
        }

        $nota = trim((string) ($venda->nome_nota ?? ''));
        if ($nota !== '' && filled($venda->cpf_nota)) {
            return $nota;
        }

        // Cadastro genérico CF / sem destinatário: rótulo de apresentação (não altera o XML).
        return NfceConsumidorIdentificado::nome($venda->person) ?: 'CONSUMIDOR FINAL';
    }

    private function formatCpfCnpj(string $raw): string
    {
        if ($raw === '') {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        return $raw;
    }
}
