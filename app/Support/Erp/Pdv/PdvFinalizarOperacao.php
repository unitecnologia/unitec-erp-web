<?php

namespace App\Support\Erp\Pdv;

use App\Models\Terminal;

final class PdvFinalizarOperacao
{
    public const PEDIDO = 'pedido';

    public const NFCE_CONTINGENCIA = 'nfce_contingencia';

    public const NFCE_TRANSMITIR = 'nfce_transmitir';

    public const FINALIZAR = 'finalizar';

    /**
     * Botões de fechamento conforme flags do terminal (exibe_f3 … exibe_f6).
     *
     * @return list<array{key: string, atalho: string, label: string, fiscal: bool, primary: bool}>
     */
    public static function botoes(?Terminal $terminal): array
    {
        if ($terminal === null) {
            return [self::botaoPedido('F10', 'Concluir', true)];
        }

        $botoes = [];

        if ($terminal->exibe_f3) {
            $botoes[] = [
                'key' => self::NFCE_CONTINGENCIA,
                'atalho' => 'F3',
                'label' => 'NFCe Contingência',
                'fiscal' => true,
                'primary' => false,
            ];
        }

        if ($terminal->exibe_f4) {
            $botoes[] = [
                'key' => self::NFCE_TRANSMITIR,
                'atalho' => 'F4',
                'label' => 'NFCe Online Transmitir',
                'fiscal' => true,
                'primary' => false,
            ];
        }

        if ($terminal->exibe_f5) {
            $botoes[] = [
                'key' => self::PEDIDO,
                'atalho' => 'F5',
                'label' => 'Pedido',
                'fiscal' => false,
                'primary' => false,
            ];
        }

        if ($terminal->exibe_f6) {
            $botoes[] = [
                'key' => self::FINALIZAR,
                'atalho' => 'F6',
                'label' => 'Finalizar',
                'fiscal' => true,
                'primary' => false,
            ];
        }

        if ($botoes === []) {
            return [self::botaoPedido('F10', 'Concluir', true)];
        }

        if (count($botoes) === 1) {
            $botoes[0]['primary'] = true;

            if ($botoes[0]['key'] === self::PEDIDO && $botoes[0]['atalho'] === 'F5') {
                $botoes[0]['atalho'] = 'F10';
                $botoes[0]['label'] = 'Concluir';
            }
        }

        return $botoes;
    }

    public static function operacaoUnica(?Terminal $terminal): ?string
    {
        $botoes = self::botoes($terminal);

        return count($botoes) === 1 ? $botoes[0]['key'] : null;
    }

    public static function operacaoPermitida(?Terminal $terminal, string $operacao): bool
    {
        foreach (self::botoes($terminal) as $botao) {
            if ($botao['key'] === $operacao) {
                return true;
            }
        }

        return false;
    }

    public static function isFiscal(string $operacao): bool
    {
        return match ($operacao) {
            self::NFCE_CONTINGENCIA, self::NFCE_TRANSMITIR, self::FINALIZAR => true,
            default => false,
        };
    }

    public static function solicitaConfirmacaoImpressao(string $operacao): bool
    {
        return in_array($operacao, [self::NFCE_TRANSMITIR, self::PEDIDO], true);
    }

    public static function mensagemStub(string $operacao): string
    {
        return match ($operacao) {
            self::NFCE_CONTINGENCIA => 'Emissão NFC-e em contingência em implementação no web.',
            self::NFCE_TRANSMITIR => 'Emissão e transmissão de NFC-e em implementação no web.',
            self::FINALIZAR => 'Finalização fiscal em implementação no web.',
            default => 'Operação fiscal em implementação no web.',
        };
    }

    public static function tituloStub(string $operacao): string
    {
        return match ($operacao) {
            self::NFCE_CONTINGENCIA => 'NFC-e — Contingência',
            self::NFCE_TRANSMITIR => 'NFC-e',
            self::FINALIZAR => 'Finalizar',
            default => 'NFC-e',
        };
    }

    /**
     * @return array{key: string, atalho: string, label: string, fiscal: bool, primary: bool}
     */
    private static function botaoPedido(string $atalho, string $label, bool $primary): array
    {
        return [
            'key' => self::PEDIDO,
            'atalho' => $atalho,
            'label' => $label,
            'fiscal' => false,
            'primary' => $primary,
        ];
    }
}
