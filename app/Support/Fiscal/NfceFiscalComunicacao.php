<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class NfceFiscalComunicacao
{
    /**
     * Indica falha de infraestrutura (rede/SEFAZ indisponível), não rejeição fiscal com cStat.
     */
    public static function isIndisponivel(FiscalEngineException $exception): bool
    {
        if ($exception->sefazCodigo !== null && $exception->sefazCodigo !== '') {
            return false;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        $indicadores = [
            'falha na comunicação com a sefaz',
            'não foi possível iniciar a comunicação',
            'não foi possível resolver o endereço',
            'could not resolve host',
            'sefaz retornou http',
            'resposta vazia da sefaz',
            'resposta inválida da sefaz',
            'timed out',
            'timeout',
            'connection refused',
            'failed to connect',
            'connection reset',
            'network is unreachable',
            'falha ssl ao conectar',
        ];

        foreach ($indicadores as $indicador) {
            if (str_contains($message, $indicador)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Falha antes de a requisição chegar à SEFAZ (DNS, conexão recusada, timeout de conexão, SSL).
     * Timeout de operação, HTTP de erro ou resposta vazia/inválida NÃO entram: a nota pode ter sido
     * recebida e a situação é desconhecida até consultar a chave.
     */
    public static function naoEnviada(FiscalEngineException $exception): bool
    {
        if ($exception->sefazCodigo !== null && $exception->sefazCodigo !== '') {
            return false;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        foreach (['ssl_read', 'recv failure', 'connection reset', 'operation timed out'] as $aposEnvio) {
            if (str_contains($message, $aposEnvio)) {
                return false;
            }
        }

        foreach ([
            'não foi possível iniciar a comunicação',
            'não foi possível resolver o endereço',
            'could not resolve host',
            'resolving timed out',
            'connection timed out',
            'connection refused',
            'failed to connect',
            'network is unreachable',
            'falha ssl ao conectar',
        ] as $indicador) {
            if (str_contains($message, $indicador)) {
                return true;
            }
        }

        return false;
    }
}
