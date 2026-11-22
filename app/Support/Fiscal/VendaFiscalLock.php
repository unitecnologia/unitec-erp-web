<?php

namespace App\Support\Fiscal;

use App\Models\PdvVendaNfce;
use App\Models\Venda;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Trava fiscal comum por venda_id: NFC-e (regularização) e NF-e não podem ser emitidas
 * ao mesmo tempo para a mesma venda. É um lock de cache (sem transação aberta durante a SEFAZ).
 */
final class VendaFiscalLock
{
    public const TTL_SEGUNDOS = 300;

    public const ESPERA_SEGUNDOS = 5;

    public const MENSAGEM_OCUPADA = 'Venda em emissão fiscal (NFC-e/NF-e) por outro processo. Aguarde e tente novamente.';

    public const MENSAGEM_NFCE_BLOQUEIA_NFE = 'A venda vinculada já possui NFC-e autorizada/contingência. Cancele a NFC-e antes de emitir NF-e.';

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws FiscalEngineException quando a venda está travada por outra emissão
     */
    public static function executar(int $vendaId, callable $callback, int $espera = self::ESPERA_SEGUNDOS): mixed
    {
        $lock = Cache::lock('erp:fiscal:venda:'.$vendaId, self::TTL_SEGUNDOS);

        try {
            $lock->block(max(0, $espera));
        } catch (LockTimeoutException) {
            throw new FiscalEngineException(self::MENSAGEM_OCUPADA);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /** NFC-e real (não simulada) autorizada ou em contingência vinculada à venda. */
    public static function vendaTemNfceValida(int $vendaId): bool
    {
        return DB::table('pdv_venda_nfce as nf')
            ->join('pdv_vendas as pv', 'pv.id', '=', 'nf.pdv_venda_id')
            ->where('pv.venda_id', $vendaId)
            ->whereIn('nf.status', [PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_CONTINGENCIA])
            ->where(function ($q): void {
                $q->where('nf.simulada', false)->orWhereNull('nf.simulada');
            })
            ->exists();
    }

    /**
     * Motivo para bloquear NF-e a partir do registro NFC-e já carregado (sem query).
     * Autorizada/contingência reais bloqueiam; rejeitada/cancelada/simulada não.
     * Não usa o número NC como prova — só status (+ flag simulada).
     */
    public static function motivoBloqueioNfePorNfce(?PdvVendaNfce $nfce): ?string
    {
        if ($nfce === null) {
            return null;
        }

        if ((bool) $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return null;
        }

        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_CONTINGENCIA => self::MENSAGEM_NFCE_BLOQUEIA_NFE,
            default => null,
        };
    }

    /**
     * Preferência: relação venda.pdvVenda.nfce já eager-loaded (Monitor).
     * Fallback: consulta vendaTemNfceValida (ação direta / serviço).
     */
    public static function motivoBloqueioNfePorVenda(Venda $venda): ?string
    {
        if ($venda->relationLoaded('pdvVenda')) {
            $pdv = $venda->pdvVenda;

            if ($pdv === null) {
                return null;
            }

            if ($pdv->relationLoaded('nfce')) {
                return self::motivoBloqueioNfePorNfce($pdv->nfce);
            }
        }

        $vendaId = (int) $venda->id;

        return $vendaId > 0 && self::vendaTemNfceValida($vendaId)
            ? self::MENSAGEM_NFCE_BLOQUEIA_NFE
            : null;
    }
}
