<?php

namespace App\Support\Erp\Os;

use App\Filament\Resources\OrdemServicoResource;

/**
 * Retorno à lista de OS após emissão fiscal (NFS-e / NF-e das peças) iniciada pela própria OS.
 * Só a entrada pela OS anexa o parâmetro; quem abre pelo módulo fiscal não é afetado.
 */
final class OrdemServicoRetorno
{
    public const PARAM = 'retorno';

    public const VALOR = 'os';

    public const PARAM_SELECIONADA = 'os_sel';

    public static function anexar(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').self::PARAM.'='.self::VALOR;
    }

    /** Ler apenas no mount(): em requisições Livewire posteriores a query string não é a da página. */
    public static function solicitadoNaRequisicao(): bool
    {
        return request()->query(self::PARAM) === self::VALOR;
    }

    public static function urlLista(int $osId): string
    {
        return OrdemServicoResource::getUrl('index').'?'.self::PARAM_SELECIONADA.'='.$osId;
    }

    public static function osSelecionadaNaRequisicao(): ?int
    {
        $id = (int) request()->query(self::PARAM_SELECIONADA, 0);

        return $id > 0 ? $id : null;
    }
}
