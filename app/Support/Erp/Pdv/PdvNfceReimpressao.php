<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
use App\Support\Fiscal\NfceContingenciaConsistencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reimpressão de DANFE NFC-e no PDV: só documento já gravado e imprimível.
 * Não emite, não retransmite e não consome numeração.
 */
final class PdvNfceReimpressao
{
    /**
     * Statuses que podem gerar DANFE de reimpressão (autorizada ou contingência consistente).
     *
     * @return list<string>
     */
    public static function statusesImprimiveis(): array
    {
        return [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_CONTINGENCIA,
        ];
    }

    public static function podeReimprimir(?PdvVendaNfce $nfce): bool
    {
        if ($nfce === null || $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return false;
        }

        $status = (string) $nfce->status;

        // Autorizada: exige chave/protocolo (NfceImpressaoFiscal).
        if ($status === PdvVendaNfce::STATUS_AUTORIZADA) {
            return NfceImpressaoFiscal::motivoBloqueio($nfce) === null;
        }

        // Contingência: só documento consistente (DANFE = XML que será transmitido).
        // Pendente/rejeitada nunca entram como reimpressão de documento autorizado.
        if ($status === PdvVendaNfce::STATUS_CONTINGENCIA) {
            return NfceContingenciaConsistencia::motivo($nfce) === null;
        }

        return false;
    }

    public static function motivoIndisponivel(?PdvVendaNfce $nfce): string
    {
        if ($nfce === null || $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return 'Não há NFC-e disponível para reimpressão.';
        }

        $numero = 'NFC-e nº '.($nfce->numero ?: '—');
        $status = (string) $nfce->status;

        if ($status === PdvVendaNfce::STATUS_AUTORIZADA) {
            return NfceImpressaoFiscal::motivoBloqueio($nfce)
                ?? 'Não há NFC-e disponível para reimpressão.';
        }

        if ($status === PdvVendaNfce::STATUS_CONTINGENCIA) {
            $motivo = NfceContingenciaConsistencia::motivo($nfce);

            return $motivo === null
                ? 'Não há NFC-e disponível para reimpressão.'
                : $numero.' em contingência inconsistente: '.$motivo.'.';
        }

        return match ($status) {
            PdvVendaNfce::STATUS_PENDENTE => $numero.' pendente na SEFAZ: não há DANFE autorizado para reimprimir.',
            PdvVendaNfce::STATUS_REJEITADA => $numero.' rejeitada pela SEFAZ: não é documento fiscal válido.',
            default => 'Não há NFC-e disponível para reimpressão.',
        };
    }

    /**
     * Vendas do caixa/empresa com NFC-e imprimível (mais recente primeiro).
     *
     * @return Builder<PdvVenda>
     */
    public static function queryVendasImprimiveis(int $sessaoId, int $empresaId): Builder
    {
        return PdvVenda::query()
            ->with('nfce')
            ->where('pdv_caixa_sessao_id', $sessaoId)
            ->where('situacao', '!=', 'C')
            ->where('fiscal', true)
            ->whereHas('nfce', function (Builder $q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)
                    ->whereIn('status', self::statusesImprimiveis())
                    ->where(function (Builder $sim): void {
                        $sim->where('simulada', false)->orWhereNull('simulada');
                    });
            })
            ->orderByDesc('numero');
    }

    /**
     * @return list<array{venda_id:int,numero:string,nfce_numero:string,status:string,total:string,vendedor:string}>
     */
    public static function listarParaModal(int $sessaoId, int $empresaId, ?string $term = null, int $limit = 50): array
    {
        if ($sessaoId <= 0 || $empresaId <= 0) {
            return [];
        }

        $query = self::queryVendasImprimiveis($sessaoId, $empresaId);
        $term = trim((string) $term);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('vendedor_nome', 'like', $like);

                if (is_numeric($term)) {
                    $q->orWhere('numero', (int) $term)
                        ->orWhereHas('nfce', fn (Builder $nfce) => $nfce->where('numero', (int) $term));
                }
            });
        }

        /** @var Collection<int, PdvVenda> $vendas */
        $vendas = $query->limit(max(1, min(100, $limit)))->get();

        return $vendas
            ->filter(fn (PdvVenda $venda): bool => self::podeReimprimir($venda->nfce))
            ->values()
            ->map(function (PdvVenda $venda): array {
                $nfce = $venda->nfce;

                return [
                    'venda_id' => (int) $venda->id,
                    'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
                    'nfce_numero' => str_pad((string) ($nfce?->numero ?? ''), 6, '0', STR_PAD_LEFT),
                    'status' => (string) ($nfce?->status ?? ''),
                    'total' => ErpMoney::formatBr($venda->total),
                    'vendedor' => mb_strtoupper((string) ($venda->vendedor_nome ?: '—'), 'UTF-8'),
                ];
            })
            ->all();
    }

    public static function ultimaVendaImprimivel(int $sessaoId, int $empresaId): ?PdvVenda
    {
        $rows = self::listarParaModal($sessaoId, $empresaId, null, 1);
        $vendaId = (int) ($rows[0]['venda_id'] ?? 0);

        if ($vendaId <= 0) {
            return null;
        }

        return PdvVenda::query()->with('nfce')->find($vendaId);
    }
}
