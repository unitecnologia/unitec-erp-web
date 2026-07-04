<?php

namespace App\Support\Erp\Terminais;

final class TerminalFormOptions
{
    /**
     * @return list<string>
     */
    public static function modelosEscPos(): array
    {
        return ['ELGIN', 'BEMATECH', 'EPSON', 'DARUMA', 'SWEDA', 'TANCA'];
    }

    /**
     * @return list<string>
     */
    public static function portasImpressora(): array
    {
        return ['COM1', 'COM2', 'COM3', 'COM4', 'LPT1', 'USB', 'RAW:IMPRESSORA'];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposOperacaoPadrao(): array
    {
        return [
            'modo_hibrido' => 'Modo Híbrido',
            'nfce_contingencia' => 'NFCe - Contingência',
            'nfce_transmitir' => 'NFCe - Transmitir',
            'pedido_nao_fiscal' => 'Pedido Não Fiscal',
        ];
    }

    public static function normalizeTipoOperacaoPadrao(?string $value): string
    {
        $key = mb_strtolower(trim((string) $value), 'UTF-8');

        $aliases = [
            'nfce' => 'nfce_transmitir',
            'nfe' => 'pedido_nao_fiscal',
            'orcamento' => 'pedido_nao_fiscal',
            'ecf_fiscal_finalizar' => 'pedido_nao_fiscal',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return array_key_exists($key, self::tiposOperacaoPadrao()) ? $key : 'pedido_nao_fiscal';
    }

    /**
     * @return array<string, string>
     */
    public static function botoesOperacaoPadrao(): array
    {
        return [
            'exibe_f3' => 'Botão F3 — Contingência (NFCe)',
            'exibe_f4' => 'Botão F4 — Transmitir (NFCe)',
            'exibe_f5' => 'Botão F5 — Pedido',
            'exibe_f6' => 'Botão F6 — Finalizar',
        ];
    }
}
