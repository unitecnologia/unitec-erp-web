<?php

namespace App\Support\Erp\Os;

use App\Models\OrdemServico;
use App\Models\RhFuncionario;
use App\Support\Erp\WhatsApp\WhatsAppPhone;

/**
 * WhatsApp do técnico responsável da OS (Cadastro de Funcionário → Contato).
 */
final class OrdemServicoTecnicoWhatsApp
{
    public static function display(OrdemServico $ordem): string
    {
        $raw = self::raw($ordem);

        return $raw !== '' ? WhatsAppPhone::formatDisplay($raw) : '';
    }

    public static function raw(OrdemServico $ordem): string
    {
        $atendenteId = (int) ($ordem->atendente_id ?: 0);

        if ($atendenteId <= 0) {
            return '';
        }

        if ($ordem->relationLoaded('atendente') && $ordem->atendente?->relationLoaded('rhFuncionario')) {
            return trim((string) ($ordem->atendente->rhFuncionario?->whatsapp ?? ''));
        }

        return trim((string) (
            RhFuncionario::query()
                ->where('vendedor_id', $atendenteId)
                ->value('whatsapp') ?? ''
        ));
    }
}
