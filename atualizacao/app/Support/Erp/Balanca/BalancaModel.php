<?php

namespace App\Support\Erp\Balanca;

/**
 * Modelos de arquivo de carga de balança (paridade Delphi).
 */
final class BalancaModel
{
    public const FILIZOLA = 'modFilizola';

    public const TOLEDO = 'modToledo';

    public const URANO = 'modUrano';

    public const URANO_S = 'modUranoS';

    public const TOLEDO_MGV5 = 'modToledoMGV5';

    public const TOLEDO_MGV6 = 'modToledoMGV6';

    public const TOLEDO_MGV7 = 'modToledoMGV7';

    public const URANO_URF32 = 'modUranoURF32';

    public const DEFAULT = self::FILIZOLA;

    public const DEFAULT_DIRECTORY = 'C:\\UNITECNOLOGIA_WEB\\balanca';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::FILIZOLA => 'modFilizola',
            self::TOLEDO => 'modToledo',
            self::URANO => 'modUrano',
            self::URANO_S => 'modUranoS',
            self::TOLEDO_MGV5 => 'modToledoMGV5',
            self::TOLEDO_MGV6 => 'modToledoMGV6',
            self::TOLEDO_MGV7 => 'modToledoMGV7',
            self::URANO_URF32 => 'modUranoURF32',
        ];
    }

    public static function isValid(string $modelo): bool
    {
        return array_key_exists($modelo, self::options());
    }

    public static function normalize(?string $modelo): string
    {
        $modelo = trim((string) $modelo);

        return self::isValid($modelo) ? $modelo : self::DEFAULT;
    }

    /**
     * Nomes de arquivo gerados por modelo.
     *
     * @return list<string>
     */
    public static function filenames(string $modelo): array
    {
        return match (self::normalize($modelo)) {
            self::FILIZOLA => ['CADTXT.TXT', 'SETORTXT.TXT'],
            self::TOLEDO, self::TOLEDO_MGV5 => ['TXITENS.TXT'],
            self::TOLEDO_MGV6, self::TOLEDO_MGV7 => ['ITENSMGV.TXT', 'DEPTO.TXT', 'INFNUTRI.TXT'],
            self::URANO, self::URANO_S, self::URANO_URF32 => ['Produtos.txt'],
            default => ['CADTXT.TXT'],
        };
    }

    public static function formatLabel(string $modelo): string
    {
        return match (self::normalize($modelo)) {
            self::FILIZOLA => 'Filizola (CADTXT.TXT + SETORTXT.TXT)',
            self::TOLEDO => 'Toledo clássico (TXITENS.TXT)',
            self::TOLEDO_MGV5 => 'Toledo MGV5 (TXITENS.TXT)',
            self::TOLEDO_MGV6 => 'Toledo MGV6 (ITENSMGV + DEPTO + INFNUTRI)',
            self::TOLEDO_MGV7 => 'Toledo MGV7 (ITENSMGV + DEPTO + INFNUTRI)',
            self::URANO => 'Urano (Produtos.txt)',
            self::URANO_S => 'Urano S (Produtos.txt)',
            self::URANO_URF32 => 'Urano URF32 (Produtos.txt)',
            default => self::normalize($modelo),
        };
    }
}
