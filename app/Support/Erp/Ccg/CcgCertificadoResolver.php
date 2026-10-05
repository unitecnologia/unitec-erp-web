<?php

namespace App\Support\Erp\Ccg;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Abre o mesmo A1/PFX já gravado em vendas_parametros. Não copia certificado nem senha.
 */
final class CcgCertificadoResolver
{
    public static function resolve(Empresa $empresa): Certificate
    {
        $parametros = VendasParametro::query()
            ->where('empresa_id', $empresa->id)
            ->first();

        if (! $parametros instanceof VendasParametro) {
            throw self::ausente();
        }

        try {
            return NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        } catch (FiscalEngineException $exception) {
            $mensagem = mb_strtolower($exception->getMessage());

            if (
                str_contains($mensagem, 'não configurados')
                || str_contains($mensagem, 'nao configurados')
                || str_contains($mensagem, 'não encontrado')
                || str_contains($mensagem, 'nao encontrado')
            ) {
                throw self::ausente();
            }

            throw new CcgConsultaException(
                'Certificado digital inválido ou senha incorreta. Verifique o certificado A1 nas configurações fiscais.',
                CcgConsultaException::CERTIFICADO,
            );
        }
    }

    private static function ausente(): CcgConsultaException
    {
        return new CcgConsultaException(
            'Certificado digital A1 não configurado nesta empresa. A consulta GTIN usa o certificado das configurações fiscais.',
            CcgConsultaException::CERTIFICADO,
        );
    }
}
