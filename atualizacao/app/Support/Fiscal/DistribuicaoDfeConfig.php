<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Illuminate\Support\Carbon;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\ChaveAcesso;

final class DistribuicaoDfeConfig
{
    public static function podeConsultar(VendasParametro $parametros, Empresa $empresa): bool
    {
        if (blank($empresa->cnpj)) {
            return false;
        }

        if (NfeFiscalConfig::certificadoAbsolutePath($parametros) === null) {
            return false;
        }

        return $parametros->hasStoredSenhaCertificado();
    }

    public static function validarConsulta(VendasParametro $parametros, Empresa $empresa): void
    {
        self::validarCertificado($parametros, $empresa);
        self::validarBloqueioSefaz($parametros);
    }

    public static function validarCertificado(VendasParametro $parametros, Empresa $empresa): void
    {
        if (! self::podeConsultar($parametros, $empresa)) {
            throw new FiscalEngineException(
                'Consulta de Distribuição DF-e não configurada. Informe certificado digital e senha nas configurações fiscais.',
            );
        }

        $cnpj = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ da empresa inválido para consulta na Distribuição DF-e.');
        }
    }

    public static function validarBloqueioSefaz(VendasParametro $parametros): void
    {
        $bloqueadoAte = $parametros->dfe_bloqueado_ate;

        if (! $bloqueadoAte instanceof Carbon) {
            return;
        }

        if (ErpTimezone::toLocal($bloqueadoAte)->isPast()) {
            self::limparBloqueioSefaz($parametros);

            return;
        }

        $proximaTentativa = DistribuicaoDfeMensagens::mensagemProximaTentativa($bloqueadoAte);

        throw new FiscalEngineException(
            DistribuicaoDfeMensagens::consumoIndevido()
                . ($proximaTentativa !== '' ? ' ' . $proximaTentativa : ''),
        );
    }

    public static function registrarBloqueioConsumoIndevido(VendasParametro $parametros): void
    {
        $parametros->update([
            'dfe_bloqueado_ate' => ErpTimezone::toLocal()->addHour(),
        ]);
    }

    public static function limparBloqueioSefaz(VendasParametro $parametros): void
    {
        if ($parametros->dfe_bloqueado_ate === null) {
            return;
        }

        $parametros->update([
            'dfe_bloqueado_ate' => null,
        ]);
    }

    public static function cUfAutor(Empresa $empresa, VendasParametro $parametros): string
    {
        $uf = strtoupper((string) ($parametros->uf ?: $empresa->uf ?: 'SC'));

        return ChaveAcesso::cUfFromSigla($uf);
    }

    public static function ultimoNsu(VendasParametro $parametros): string
    {
        $nsu = trim((string) ($parametros->dfe_ultimo_nsu ?? ''));

        return \Unitec\FiscalEngine\Nfe\DfeDistribuidor::normalizarNsu($nsu);
    }
}
