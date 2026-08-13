<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class NfceFiscalCertificateResolver
{
    public static function resolve(Empresa $empresa, VendasParametro $parametros): Certificate
    {
        CaBundleResolver::setProjectRoot(base_path());

        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);
        $senha = $parametros->safeSenhaCertificado();

        if ($certPath === null || $senha === null) {
            throw new FiscalEngineException('Certificado digital ou senha não configurados.');
        }

        return CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);
    }

    public static function tpAmb(VendasParametro $parametros): int
    {
        return $parametros->ambiente === VendasParametro::AMBIENTE_PRODUCAO ? 1 : 2;
    }

    public static function ambienteNfce(VendasParametro $parametros): int
    {
        return self::tpAmb($parametros) === 1
            ? PdvVendaNfce::AMBIENTE_PRODUCAO
            : PdvVendaNfce::AMBIENTE_HOMOLOGACAO;
    }
}
