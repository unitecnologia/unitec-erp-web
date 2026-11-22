<?php

namespace App\Support\Fiscal;

/**
 * Mapeia forma de pagamento → tPag da NFC-e (modelo 65).
 *
 * Diferença em relação à NF-e ({@see FormaPagamentoTPagMap}): PIX do PDV usa tPag=20
 * (PIX estático), não 17.
 */
final class FormaPagamentoNfceTPagMap
{
    /**
     * tPag a partir do Tipo do cadastro formas_pagamento.
     */
    public static function fromTipo(?string $tipo): string
    {
        $tipo = mb_strtolower(trim((string) $tipo), 'UTF-8');
        $tipo = match ($tipo) {
            'cartao' => 'cartao_credito',
            'credito_loja' => 'crediario',
            default => $tipo,
        };

        return match ($tipo) {
            'dinheiro' => '01',
            'cheque' => '02',
            'cartao_credito' => '03',
            'cartao_debito', 'tef' => '04',
            'crediario' => '05',
            'boleto' => '15',
            'deposito' => '16',
            // NFC-e PDV: PIX estático (QR/chave) — grupo card não exigido.
            'pix' => '20',
            'transferencia' => '18',
            'troca' => '99',
            'sem_pagamento' => '90',
            default => '01',
        };
    }

    /**
     * tPag a partir da descrição gravada no pagamento da venda (legado / PDV).
     *
     * @param  bool  $creditoCliente  Forma cadastrada como crédito do cliente (ou atalho PDV).
     */
    public static function fromDescricao(string $forma, bool $creditoCliente = false): string
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        if ($creditoCliente) {
            return '05';
        }

        return match (true) {
            str_contains($forma, 'DINHEIRO') => '01',
            str_contains($forma, 'CHEQUE') => '02',
            // PIX antes de crédito/débito para não confundir descrições compostas.
            str_contains($forma, 'PIX') => '20',
            str_contains($forma, 'BOLETO') => '15',
            str_contains($forma, 'DEPOSITO') || str_contains($forma, 'DEPÓSITO') => '16',
            str_contains($forma, 'TRANSFERENCIA') || str_contains($forma, 'TRANSFERÊNCIA') => '18',
            str_contains($forma, 'CREDIARIO')
                || str_contains($forma, 'CREDIÁRIO')
                || str_contains($forma, 'PRAZO')
                || str_contains($forma, 'FIADO') => '05',
            str_contains($forma, 'CREDITO') || str_contains($forma, 'CRÉDITO') => '03',
            str_contains($forma, 'DEBITO') || str_contains($forma, 'DÉBITO') => '04',
            str_contains($forma, 'TEF') => '04',
            default => '99',
        };
    }

    public static function isCartao(string $tPag): bool
    {
        return in_array($tPag, ['03', '04'], true);
    }

    public static function isEletronico(string $tPag): bool
    {
        return self::isCartao($tPag) || $tPag === '20';
    }
}
