<?php

namespace App\Support\Erp\Fabricacao;

/**
 * Unidades tratadas como inteiras nesta etapa (sem conversão KG/G etc.).
 */
final class FabricacaoUnidadeRules
{
    private const EPS = 0.0005;

    /** @var list<string> */
    private const INTEIRAS = [
        'UN', 'PC', 'DZ', 'PAR', 'CX', 'KIT', 'CJ', 'FD', 'PCT', 'RL', 'SC',
    ];

    public static function exigeInteiro(?string $unidade): bool
    {
        $u = strtoupper(trim((string) $unidade));

        if ($u === '') {
            return true;
        }

        return in_array($u, self::INTEIRAS, true);
    }

    public static function isQuantidadeInteira(float $quantidade): bool
    {
        return abs($quantidade - round($quantidade)) <= self::EPS;
    }

    /**
     * @throws FabricacaoException
     */
    public static function assertQuantidadeCompativel(float $quantidade, ?string $unidade, string $rotulo): void
    {
        if (! self::exigeInteiro($unidade)) {
            return;
        }

        if (! self::isQuantidadeInteira($quantidade)) {
            throw new FabricacaoException(
                $rotulo.' exige quantidade inteira na unidade '
                .strtoupper(trim((string) ($unidade ?: 'UN')))
                .' (informado: '.number_format($quantidade, 3, ',', '.')
                .'). Não há arredondamento automático.'
            );
        }
    }
}
