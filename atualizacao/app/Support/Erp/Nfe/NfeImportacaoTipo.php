<?php

namespace App\Support\Erp\Nfe;

final class NfeImportacaoTipo
{
    public const ORCAMENTO = 'orcamento';

    public const VENDA = 'venda';

    public const DEV_COMPRA = 'dev_compra';

    public const DEV_VENDA = 'dev_venda';

    public const OS = 'os';

    public const PEDIDO_WEB = 'pedido_web';

    public const NFCE = 'nfce';

    /**
     * @return list<array{tipo: string, label: string, hotkey: string, implemented: bool}>
     */
    public static function menuItens(): array
    {
        return [
            ['tipo' => self::VENDA, 'label' => 'Pedido', 'hotkey' => 'F2', 'implemented' => true],
            ['tipo' => self::DEV_COMPRA, 'label' => 'Dev. Compra', 'hotkey' => 'F3', 'implemented' => true],
            ['tipo' => self::DEV_VENDA, 'label' => 'Dev. Venda', 'hotkey' => 'F4', 'implemented' => false],
            ['tipo' => self::OS, 'label' => 'O. S.', 'hotkey' => 'F5', 'implemented' => false],
            ['tipo' => self::NFCE, 'label' => 'NFCe', 'hotkey' => 'F6', 'implemented' => true],
        ];
    }

    public static function label(string $tipo): string
    {
        foreach (self::menuItens() as $item) {
            if ($item['tipo'] === $tipo) {
                return $item['label'];
            }
        }

        return $tipo;
    }

    public static function isImplemented(string $tipo): bool
    {
        foreach (self::menuItens() as $item) {
            if ($item['tipo'] === $tipo) {
                return $item['implemented'];
            }
        }

        return false;
    }

    public static function fromHotkey(string $key): ?string
    {
        $map = [
            'F2' => self::VENDA,
            'F3' => self::DEV_COMPRA,
            'F4' => self::DEV_VENDA,
            'F5' => self::OS,
            'F6' => self::NFCE,
        ];

        return $map[$key] ?? null;
    }
}
