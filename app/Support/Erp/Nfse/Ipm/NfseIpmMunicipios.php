<?php

namespace App\Support\Erp\Nfse\Ipm;

/**
 * Endpoints IPM informados oficialmente pelo suporte. Outros municípios IPM usam a URL digitada.
 */
final class NfseIpmMunicipios
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        '4101804' => 'https://araucaria.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe',
        '4203204' => 'https://camboriu.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe',
    ];

    /**
     * Dados que o portal IPM imprime no DANFSe do município (código SIAFI/TOM, nomes e textos fixos).
     *
     * @var array<string, array{siafi: string, nome: string, secretaria: string, texto_simples: string, vencimento_iss_dia: int}>
     */
    private const DANFSE = [
        '4101804' => [
            'siafi' => '7435',
            'nome' => 'Araucária',
            'secretaria' => 'SECRETARIA MUNICIPAL DE FINANÇAS',
            'texto_simples' => 'Contribuinte enquadrado como Simples - Homologado de ISS ou ISS em regime estimado/fixo',
            'vencimento_iss_dia' => 20,
        ],
    ];

    public static function endpoint(mixed $codigoIbge): ?string
    {
        $codigo = preg_replace('/\D/', '', (string) $codigoIbge) ?? '';

        return self::ENDPOINTS[$codigo] ?? null;
    }

    /**
     * @return array{siafi: string, nome: string, secretaria: string, texto_simples: string, vencimento_iss_dia: int}|null
     */
    public static function danfse(mixed $codigoIbge): ?array
    {
        $codigo = preg_replace('/\D/', '', (string) $codigoIbge) ?? '';

        return self::DANFSE[$codigo] ?? null;
    }
}
