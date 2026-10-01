<?php

namespace App\Support\Fiscal;

use App\Models\FormaPagamento;

/**
 * Mapeia o campo Tipo do cadastro formas_pagamento → tPag (NF-e).
 *
 * A descrição/nome da forma é só exibição; o código fiscal vem exclusivamente do Tipo.
 * Aceita também chaves legadas gravadas em nfe.meio_pgto (dinheiro, cartao, pix…).
 *
 * TEF não é meio válido na NF-e (permanece só no PDV/NFC-e).
 */
final class FormaPagamentoTPagMap
{
    /** @var list<string> */
    public const TIPOS_NFE = [
        'dinheiro',
        'cheque',
        'cartao_credito',
        'cartao_debito',
        'crediario',
        'boleto',
        'deposito',
        'pix',
        'transferencia',
        'troca',
    ];

    public static function isTipoPermitidoNaNfe(?string $tipo): bool
    {
        $tipo = self::normalizeTipoAlias($tipo);

        return $tipo !== '' && in_array($tipo, self::TIPOS_NFE, true);
    }

    /**
     * Resolve tPag a partir do valor salvo em nfe.meio_pgto
     * (id de FormaPagamento ou chave/tipo legado).
     */
    public static function fromMeioPgto(?string $meioPgto): string
    {
        $meio = trim((string) $meioPgto);

        if ($meio !== '' && ctype_digit($meio)) {
            $forma = FormaPagamento::query()->find((int) $meio);

            if ($forma !== null) {
                return self::fromTipo($forma->tipo);
            }
        }

        return self::fromTipo($meio !== '' ? $meio : 'dinheiro');
    }

    /**
     * @param  string|null  $tipo  Valor de formas_pagamento.tipo ou alias legado (ex.: cartao).
     */
    public static function fromTipo(?string $tipo): string
    {
        $tipo = self::normalizeTipoAlias($tipo);

        // TEF e tipos fora da lista NF-e não geram tPag próprio (fallback legado 01).
        return match ($tipo) {
            'dinheiro' => '01',
            'cheque' => '02',
            'cartao_credito' => '03',
            'cartao_debito' => '04',
            'crediario' => '05',
            'boleto' => '15',
            'deposito' => '16',
            'pix' => '17',
            'transferencia' => '18',
            'troca' => '99',
            default => '01',
        };
    }

    private static function normalizeTipoAlias(?string $tipo): string
    {
        $tipo = mb_strtolower(trim((string) $tipo), 'UTF-8');

        return match ($tipo) {
            'cartao' => 'cartao_credito',
            'credito_loja' => 'crediario',
            default => $tipo,
        };
    }
}
