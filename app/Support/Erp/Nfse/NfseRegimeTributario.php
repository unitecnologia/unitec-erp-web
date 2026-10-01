<?php

namespace App\Support\Erp\Nfse;

final class NfseRegimeTributario
{
    /**
     * Situação perante o Simples Nacional, a partir de empresas.regime_tributario.
     * mei = 2, simples = 3, demais = 1. Não é campo próprio.
     */
    public static function opSimpNac(?string $regimeTributario): string
    {
        return match (strtolower(trim((string) $regimeTributario))) {
            'mei' => '2',
            'simples' => '3',
            default => '1',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function regimesEspeciais(): array
    {
        return [
            '0' => 'Nenhum',
            '1' => 'Ato Cooperado',
            '2' => 'Estimativa',
            '3' => 'Microempresa Municipal',
            '4' => 'Notário/Registrador',
            '5' => 'Profissional Autônomo',
            '6' => 'Sociedade de Profissionais',
            '9' => 'Outros',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function regimesApuracaoSimples(): array
    {
        return [
            '1' => 'Federais e municipal pelo Simples',
            '2' => 'Federais pelo Simples e ISS por fora',
            '3' => 'Federais e municipal por fora do Simples',
        ];
    }
}
