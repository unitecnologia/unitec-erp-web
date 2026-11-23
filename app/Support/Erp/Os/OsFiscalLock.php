<?php

namespace App\Support\Erp\Os;

use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\PdvVendaNfce;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trava fiscal leve da OS: qualquer documento válido (ou transmissão em curso)
 * bloqueia alteração/reabertura. Consultas via EXISTS; sem auto-liberar a OS.
 */
final class OsFiscalLock
{
    public const TTL_SEGUNDOS = 300;

    public const ESPERA_SEGUNDOS = 5;

    /** Alinhado a NfseTransmitirService / IPM (reservarEnvio). */
    public const TRANSMISSAO_JANELA_SEGUNDOS = 90;

    public const MENSAGEM_OCUPADA = 'OS em operação fiscal por outro processo. Aguarde e tente novamente.';

    /**
     * NFS-e nesses status ainda não existe na prefeitura (ou foi cancelada/rejeitada).
     * Transmissão em curso (transmitindo_em) bloqueia mesmo com status aberta.
     *
     * @var list<string>
     */
    public const NFSE_LIBERADA = [Nfse::STATUS_ABERTA, Nfse::STATUS_CANCELADA, Nfse::STATUS_REJEITADA];

    /**
     * @var list<string>
     */
    public const NFE_EMITIDA = [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA, Nfe::STATUS_DUPLICIDADE];

    /**
     * @var list<string>
     */
    public const NFCE_BLOQUEIO = [
        PdvVendaNfce::STATUS_AUTORIZADA,
        PdvVendaNfce::STATUS_CONTINGENCIA,
        PdvVendaNfce::STATUS_DUPLICIDADE,
        PdvVendaNfce::STATUS_PENDENTE,
        PdvVendaNfce::STATUS_REJEITADA,
        PdvVendaNfce::STATUS_DENEGADA,
    ];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws DomainException
     */
    public static function executar(int $osId, callable $callback, int $espera = self::ESPERA_SEGUNDOS): mixed
    {
        if ($osId <= 0) {
            return $callback();
        }

        $lock = Cache::lock(self::cacheKey($osId), self::TTL_SEGUNDOS);

        try {
            $lock->block(max(0, $espera));
        } catch (LockTimeoutException) {
            throw new DomainException(self::MENSAGEM_OCUPADA);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $osIds
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws DomainException
     */
    public static function executarParaOsIds(array $osIds, callable $callback, int $espera = self::ESPERA_SEGUNDOS): mixed
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $osIds),
            static fn (int $id): bool => $id > 0,
        )));

        sort($ids);

        if ($ids === []) {
            return $callback();
        }

        $locks = [];

        try {
            foreach ($ids as $id) {
                $lock = Cache::lock(self::cacheKey($id), self::TTL_SEGUNDOS);

                try {
                    $lock->block(max(0, $espera));
                } catch (LockTimeoutException) {
                    throw new DomainException(self::MENSAGEM_OCUPADA);
                }

                $locks[] = $lock;
            }

            return $callback();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    public static function cacheKey(int $osId): string
    {
        return 'erp:fiscal:os:'.$osId;
    }

    /**
     * Motivo para bloquear alteração da OS (gravação, API, transporte, etc.).
     * Não libera a OS automaticamente após cancelar notas — só deixa de bloquear quando não houver doc válido.
     */
    public static function motivoBloqueioAlteracao(int|OrdemServico $os): ?string
    {
        $osId = $os instanceof OrdemServico ? (int) $os->id : (int) $os;

        if ($osId <= 0) {
            return null;
        }

        $modelo = $os instanceof OrdemServico ? $os : null;

        return self::motivoBloqueioDocumentos($osId, 'alterar', $modelo);
    }

    /**
     * @throws DomainException
     */
    public static function assertPodeAlterar(int|OrdemServico $os): void
    {
        $motivo = self::motivoBloqueioAlteracao($os);

        if ($motivo !== null) {
            throw new DomainException($motivo);
        }
    }

    /**
     * Documentos fiscais / transmissão que impedem reabrir, cancelar ou alterar a OS.
     */
    public static function motivoBloqueioDocumentos(int|OrdemServico $os, string $acao = 'alterar', ?OrdemServico $modelo = null): ?string
    {
        $osId = $os instanceof OrdemServico ? (int) $os->id : (int) $os;
        $modelo ??= $os instanceof OrdemServico ? $os : null;

        if ($osId <= 0) {
            return null;
        }

        if (self::existeNfseTransmitindo($osId)) {
            return 'Esta OS tem NFS-e em transmissão. Aguarde o retorno da prefeitura antes de '.$acao.' a OS.';
        }

        if (self::existeNfseBloqueante($osId)) {
            $nfse = self::nfseBloqueante($osId);
            $numero = $nfse !== null
                ? trim((string) ($nfse->numero_nfse ?: $nfse->numero_dps))
                : '';

            return 'Esta OS tem NFS-e emitida'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Cancele a NFS-e antes de '.$acao.' a OS.';
        }

        $nfe = self::nfeEmitida($osId, $modelo);

        if ($nfe instanceof Nfe) {
            $numero = trim((string) ($nfe->numero ?? ''));

            return 'Esta OS tem NF-e emitida'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Cancele a NF-e antes de '.$acao.' a OS.';
        }

        if (self::existeNfceBloqueante($osId)) {
            $nfce = self::nfceBloqueante($osId);
            $numero = $nfce !== null ? trim((string) ($nfce->numero ?? '')) : '';

            return 'Esta OS tem NFC-e emitida'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Cancele a NFC-e antes de '.$acao.' a OS.';
        }

        return null;
    }

    public static function existeDocumentoBloqueante(int $osId): bool
    {
        return self::motivoBloqueioDocumentos($osId) !== null;
    }

    public static function existeNfseTransmitindo(int $osId): bool
    {
        if ($osId <= 0 || ! self::temTabela('nfses')) {
            return false;
        }

        $limite = now()->subSeconds(self::TRANSMISSAO_JANELA_SEGUNDOS);

        return Nfse::query()
            ->daOrdemServico($osId)
            ->whereNotNull('transmitindo_em')
            ->where('transmitindo_em', '>', $limite)
            ->exists();
    }

    public static function existeNfseBloqueante(int $osId): bool
    {
        if ($osId <= 0 || ! self::temTabela('nfses')) {
            return false;
        }

        return Nfse::query()
            ->daOrdemServico($osId)
            ->whereNotIn('status', self::NFSE_LIBERADA)
            ->exists();
    }

    public static function nfseBloqueante(int $osId): ?Nfse
    {
        if ($osId <= 0 || ! self::temTabela('nfses')) {
            return null;
        }

        return Nfse::query()
            ->daOrdemServico($osId)
            ->whereNotIn('status', self::NFSE_LIBERADA)
            ->orderByDesc('id')
            ->first();
    }

    public static function existeNfceBloqueante(int $osId): bool
    {
        if ($osId <= 0 || ! self::temTabela('pdv_venda_nfce')) {
            return false;
        }

        if (! self::temColuna('pdv_venda_nfce', 'ordem_servico_id')) {
            return false;
        }

        return PdvVendaNfce::query()
            ->where('ordem_servico_id', $osId)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->whereIn('status', self::NFCE_BLOQUEIO)
            ->exists();
    }

    public static function nfceBloqueante(int $osId): ?PdvVendaNfce
    {
        if ($osId <= 0 || ! self::temTabela('pdv_venda_nfce') || ! self::temColuna('pdv_venda_nfce', 'ordem_servico_id')) {
            return null;
        }

        return PdvVendaNfce::query()
            ->where('ordem_servico_id', $osId)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->whereIn('status', self::NFCE_BLOQUEIO)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Prefere ordem_servico_id; fallback legado por npedido + obs (registros antigos).
     */
    public static function nfeEmitida(int|OrdemServico $os, ?OrdemServico $modelo = null): ?Nfe
    {
        $osId = $os instanceof OrdemServico ? (int) $os->id : (int) $os;
        $modelo ??= $os instanceof OrdemServico ? $os : null;

        if ($osId <= 0 || ! self::temTabela('nfes')) {
            return null;
        }

        if (self::temColuna('nfes', 'ordem_servico_id')) {
            $porVinculo = Nfe::query()
                ->where('ordem_servico_id', $osId)
                ->whereIn('status', self::NFE_EMITIDA)
                ->orderByDesc('id')
                ->first();

            if ($porVinculo instanceof Nfe) {
                return $porVinculo;
            }
        }

        if ($modelo === null) {
            $modelo = OrdemServico::query()->select(['id', 'numero', 'empresa_id', 'cliente_id'])->find($osId);
        }

        if (! $modelo instanceof OrdemServico) {
            return null;
        }

        return self::nfeEmitidaLegado($modelo);
    }

    /**
     * Registros antigos sem ordem_servico_id (NfeOrdemServicoService grava npedido + obs).
     */
    public static function nfeEmitidaLegado(OrdemServico $os): ?Nfe
    {
        $numero = ltrim(preg_replace('/\D/', '', (string) $os->numero) ?? '', '0');

        if ($numero === '') {
            return null;
        }

        $query = Nfe::query()
            ->whereIn('status', self::NFE_EMITIDA)
            ->whereIn('npedido', [$numero, 'OS-'.$numero, 'OS '.$numero])
            ->where('obs_contribuinte', 'like', '%ORIGINADA DA OS%')
            ->when($os->empresa_id, fn (Builder $q) => $q->where('empresa_id', (int) $os->empresa_id))
            ->when($os->cliente_id, fn (Builder $q) => $q->where('cliente_id', (int) $os->cliente_id));

        if (self::temColuna('nfes', 'ordem_servico_id')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('ordem_servico_id')->orWhere('ordem_servico_id', 0);
            });
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * IDs de OS ligados à NFS-e (cabeçalho + itens) para trava concorrente na transmissão.
     *
     * @return list<int>
     */
    public static function idsOsDaNfse(Nfse $nfse): array
    {
        $ids = [];

        if ((int) ($nfse->ordem_servico_id ?? 0) > 0) {
            $ids[] = (int) $nfse->ordem_servico_id;
        }

        if (self::temTabela('nfse_itens') && self::temColuna('nfse_itens', 'ordem_servico_id')) {
            $dosItens = DB::table('nfse_itens')
                ->where('nfse_id', (int) $nfse->id)
                ->whereNotNull('ordem_servico_id')
                ->where('ordem_servico_id', '>', 0)
                ->distinct()
                ->pluck('ordem_servico_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $dosItens);
        }

        return array_values(array_unique($ids));
    }

    private static function temTabela(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasTable($table);
    }

    private static function temColuna(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table.'.'.$column;

        return $cache[$key] ??= Schema::hasColumn($table, $column);
    }
}
