<?php

namespace App\Support\Fiscal;

use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class DistribuicaoDfeMensagens
{
    public const CSTAT_CONSUMO_INDEVIDO = '656';

    public const CSTAT_SEM_DOCUMENTOS = '138';

    public const CSTAT_COM_DOCUMENTOS = '137';

    public const CSTAT_NFE_INEXISTENTE = '217';

    public static function consumoIndevido(): string
    {
        return 'A SEFAZ bloqueou a consulta por consumo indevido. '
            . 'Quando não há novos documentos, é necessário aguardar 1 hora antes de consultar novamente.';
    }

    public static function semDocumentosNovos(): string
    {
        return 'Nenhum documento novo encontrado na Distribuição DF-e.';
    }

    public static function nfeInexistente(): string
    {
        return 'A NF-e não foi localizada na Distribuição DF-e para a chave informada. '
            . 'Verifique se a chave está correta, se a nota foi emitida contra o CNPJ da empresa '
            . 'e se o ambiente fiscal (homologação ou produção) está configurado corretamente.';
    }

    public static function formatarMotivo(string $cStat, string $motivoSefaz = ''): string
    {
        return match ($cStat) {
            self::CSTAT_CONSUMO_INDEVIDO => self::consumoIndevido(),
            self::CSTAT_SEM_DOCUMENTOS => self::semDocumentosNovos(),
            self::CSTAT_COM_DOCUMENTOS => 'Documentos localizados na Distribuição DF-e.',
            self::CSTAT_NFE_INEXISTENTE => self::nfeInexistente(),
            default => self::limparMotivoSefaz($motivoSefaz) ?: 'Retorno da SEFAZ.',
        };
    }

    /**
     * @return array{mensagem: string, codigo: ?string}
     */
    public static function mensagemOverlay(FiscalEngineException $exception): array
    {
        $bruta = trim($exception->getMessage());
        $codigo = self::extrairCodigoSefaz($bruta);
        $motivoSefaz = self::limparMensagemException($bruta);

        if ($codigo !== null) {
            return [
                'mensagem' => self::formatarMotivo($codigo, $motivoSefaz),
                'codigo' => $codigo,
            ];
        }

        return [
            'mensagem' => $motivoSefaz !== '' ? $motivoSefaz : $bruta,
            'codigo' => null,
        ];
    }

    public static function extrairCodigoSefaz(string $mensagem): ?string
    {
        if (preg_match('/\[cStat\s+(\d+)\]/iu', $mensagem, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function limparMensagemException(string $mensagem): string
    {
        $mensagem = (string) preg_replace('/\s*\[cStat\s+\d+\]\s*/iu', '', $mensagem);

        return self::limparMotivoSefaz($mensagem);
    }

    public static function isConsumoIndevidoException(FiscalEngineException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'consumo indevido')
            || str_contains($message, 'cstat 656')
            || str_contains($message, 'cstat656');
    }

    public static function limparMotivoSefaz(string $motivo): string
    {
        $motivo = trim($motivo);
        $motivo = (string) preg_replace('/^Rejei[cç][aã]o:\s*/iu', '', $motivo);
        $motivo = (string) preg_replace('/\s+/', ' ', $motivo);

        return trim($motivo);
    }

    public static function mensagemProximaTentativa(?Carbon $bloqueadoAte): string
    {
        if (! $bloqueadoAte instanceof Carbon) {
            return '';
        }

        $liberacao = ErpTimezone::toLocal($bloqueadoAte);

        if ($liberacao->isPast()) {
            return '';
        }

        $agora = ErpTimezone::toLocal();
        $minutos = max(1, (int) ceil($agora->floatDiffInMinutes($liberacao)));

        $hora = $liberacao->isToday()
            ? $liberacao->format('H:i')
            : $liberacao->format('d/m/Y H:i');

        return "Aguarde {$minutos} minuto(s). Próxima tentativa às {$hora}.";
    }
}
