<?php

namespace App\Support\Erp\Pdv;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvNfceFiscalMensagens
{
    public const CSTAT_PRAZO_CANCELAMENTO = '501';

    /**
     * @return array{titulo: string, corpo: string|null, modal: bool}
     */
    public static function resolver(FiscalEngineException $exception): array
    {
        $codigo = $exception->sefazCodigo ?? self::extrairCStatDaMensagem($exception->getMessage());

        return match ($codigo) {
            self::CSTAT_PRAZO_CANCELAMENTO, '220' => [
                'titulo' => 'Não é possível cancelar esta NFC-e',
                'corpo' => "O prazo legal de 30 minutos para o cancelamento direto em Santa Catarina já expirou.\n\n"
                    . "O que fazer agora?\n\n"
                    . 'Para regularizar esta operação de forma legal perante a SEFAZ-SC, você deve emitir uma '
                    . 'Nota Fiscal de Entrada (Estorno de Venda) utilizando o modelo 55. '
                    . 'Consulte sua contabilidade para mais detalhes.',
                'modal' => true,
            ],
            '539' => [
                'titulo' => 'Rejeição: duplicidade de NFC-e (número já usado na SEFAZ)',
                'corpo' => 'Este número/série já foi autorizado anteriormente com outra chave. '
                    . 'O sistema avançou a numeração — tente finalizar a venda novamente.',
                'modal' => false,
            ],
            default => [
                'titulo' => self::tituloAmigavel($exception),
                'corpo' => null,
                'modal' => false,
            ],
        };
    }

    private static function tituloAmigavel(FiscalEngineException $exception): string
    {
        $mensagem = trim($exception->getMessage());

        if ($mensagem === '') {
            return 'Não foi possível concluir a operação fiscal.';
        }

        return preg_replace('/\s*\[cStat\s+\d+\]\s*$/', '', $mensagem) ?? $mensagem;
    }

    private static function extrairCStatDaMensagem(string $message): ?string
    {
        if (preg_match('/\[cStat\s+(\d+)\]/', $message, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
