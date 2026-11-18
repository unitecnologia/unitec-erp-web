<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use App\Support\Fiscal\NfceContingenciaJustificativa;

/**
 * Texto do rodapé amarelo da listagem NFC-e (Contingência / Duplicidade / Denegado).
 */
final class NfceListAvisoRodape
{
    /**
     * @return array{titulo: string, motivo: string, ajuda: string}|null
     */
    public static function fromRecord(PdvVendaNfce $record, string $statusFilter): ?array
    {
        $numero = str_pad((string) $record->numero, 6, '0', STR_PAD_LEFT);

        if ($statusFilter === PdvVendaNfce::TAB_CONTINGENCIA
            && $record->status === PdvVendaNfce::STATUS_CONTINGENCIA) {
            $motivo = trim((string) ($record->motivo_contingencia ?? ''));
            if ($motivo === '') {
                $motivo = NfceContingenciaJustificativa::PADRAO;
            }

            return [
                'titulo' => 'NFC-e '.$numero.' em contingência',
                'motivo' => $motivo,
                'ajuda' => 'A nota foi emitida sem autorização da SEFAZ. Selecione-a e use F5 | Transmitir para regularizar. Se a SEFAZ estiver fora do ar, aguarde e tente de novo.',
            ];
        }

        if ($statusFilter === PdvVendaNfce::TAB_DUPLICIDADE
            && $record->status === PdvVendaNfce::STATUS_DUPLICIDADE) {
            $motivo = trim((string) ($record->motivo_rejeicao ?? ''));
            if ($motivo === '') {
                $motivo = 'Rejeição 539: este número já foi autorizado na SEFAZ com outra chave.';
            }

            $conflitoConfirmado = str_contains(mb_strtolower($motivo, 'UTF-8'), 'conflito confirmado');

            return [
                'titulo' => 'NFC-e '.$numero.' em duplicidade (539)',
                'motivo' => $motivo,
                'ajuda' => $conflitoConfirmado
                    ? 'Conflito confirmado: a SEFAZ já tem outra NFC-e com este número (conteúdo diferente desta venda). O F4 não pode restaurar. Use F5 | Transmitir — o sistema emite com número novo e não reutiliza este nas próximas notas.'
                    : 'O número pode já ter sido usado na SEFAZ com outra chave. Use F4 | Recuperar para tentar restaurar a nota autorizada. Se o conflito for confirmado como outra emissão, use F5 | Transmitir (novo número).',
            ];
        }

        if ($statusFilter === PdvVendaNfce::TAB_DENEGADO
            && in_array((string) $record->status, [
                PdvVendaNfce::STATUS_REJEITADA,
                PdvVendaNfce::STATUS_DENEGADA,
            ], true)) {
            $motivo = trim((string) ($record->motivo_rejeicao ?? ''));
            if ($motivo === '') {
                return null;
            }

            $cStat = $record->cStatDoMotivoRejeicao();
            $denegada = (string) $record->status === PdvVendaNfce::STATUS_DENEGADA;
            $titulo = $denegada
                ? 'NFC-e '.$numero.' com uso denegado'
                : 'NFC-e '.$numero.' rejeitada';
            if ($cStat !== null) {
                $titulo .= ' (cStat '.$cStat.')';
            }

            return [
                'titulo' => $titulo,
                'motivo' => $motivo,
                'ajuda' => $denegada
                    ? 'Uso denegado pela SEFAZ: o número foi consumido e não deve ser retransmitido. Regularize conforme orientação do contador.'
                    : 'Corrija o problema indicado pela SEFAZ e use F5 | Transmitir para reenviar. Em dúvida, consulte o contador.',
            ];
        }

        return null;
    }
}
