<?php

namespace App\Support\Erp\Nfse;

use Throwable;

/**
 * Classifica o GET /eventos/{tipo}/{seq} antes de um novo POST de cancelamento.
 * Só libera novo POST quando o evento está ausente na SEFIN.
 */
final class NfseSefinCancelamentoConsulta
{
    public const REGISTRADO = 'registrado';

    public const AUSENTE = 'ausente';

    public const ERRO_COMUNICACAO = 'erro_comunicacao';

    /**
     * @return array{
     *     status: self::REGISTRADO|self::AUSENTE|self::ERRO_COMUNICACAO,
     *     resposta: ?NfseSefinEventoResposta,
     *     motivo: ?string
     * }
     */
    public static function classificar(int $http, string $body): array
    {
        try {
            $resposta = NfseSefinEventoResposta::interpretar($http, $body);

            if ($resposta->registrado) {
                return [
                    'status' => self::REGISTRADO,
                    'resposta' => $resposta,
                    'motivo' => null,
                ];
            }

            return [
                'status' => self::AUSENTE,
                'resposta' => $resposta,
                'motivo' => $resposta->mensagemErros() !== ''
                    ? $resposta->mensagemErros()
                    : 'Evento de cancelamento não encontrado na SEFIN.',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => self::ERRO_COMUNICACAO,
                'resposta' => null,
                'motivo' => $exception->getMessage(),
            ];
        }
    }

    public static function podeEnviarNovoPost(string $status): bool
    {
        return $status === self::AUSENTE;
    }
}
