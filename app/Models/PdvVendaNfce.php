<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaNfce extends Model
{
    public const STATUS_SIMULADA = 'simulada';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_AUTORIZADA = 'autorizada';

    public const STATUS_CONTINGENCIA = 'contingencia';

    public const STATUS_REJEITADA = 'rejeitada';

    /** Uso denegado (cStat 110/301/302/303): número consumido na SEFAZ; não retransmite nem inutiliza. */
    public const STATUS_DENEGADA = 'denegada';

    /** cStat de denegação de uso na autorização/consulta. */
    public const CSTAT_DENEGACAO = ['110', '301', '302', '303'];

    public const STATUS_CANCELADA = 'cancelada';

    /** Rejeição 539: número já autorizado na SEFAZ com outra chave, não localizada no ERP. */
    public const STATUS_DUPLICIDADE = 'duplicidade';

    /** Número inutilizado na SEFAZ (F3); o registro e o XML da tentativa permanecem. */
    public const STATUS_INUTILIZADA = 'inutilizada';

    /** Abas da tela NFC-e (espelho do Delphi). */
    public const TAB_TRANSMITIDOS = 'transmitidos';

    public const TAB_DUPLICIDADE = 'duplicidade';

    public const TAB_INUTILIZADOS = 'inutilizados';

    public const TAB_GRAVADOS = 'gravados';

    public const TAB_CONTINGENCIA = 'contingencia';

    public const TAB_CANCELADOS = 'cancelados';

    public const TAB_DENEGADO = 'denegado';

    public const AMBIENTE_PRODUCAO = 1;

    public const AMBIENTE_HOMOLOGACAO = 2;

    protected $table = 'pdv_venda_nfce';

    protected $fillable = [
        'pdv_venda_id',
        'empresa_id',
        'nfe_id',
        'operacao',
        'modelo',
        'serie',
        'numero',
        'cnf',
        'chave',
        'protocolo',
        'protocolo_cancelamento',
        'status',
        'ambiente',
        'tipo_emissao',
        'simulada',
        'qr_code_conteudo',
        'xml',
        'xml_cancelamento',
        'motivo_rejeicao',
        'motivo_contingencia',
        'autorizada_em',
        'cancelada_em',
    ];

    protected static function booted(): void
    {
        // Documento fiscal (chave/XML) nunca é excluído; só cupom simulado pode sair.
        static::deleting(function (self $nfce): void {
            $simulada = (bool) $nfce->getRawOriginal('simulada') || $nfce->getRawOriginal('status') === self::STATUS_SIMULADA;

            if (! $simulada && (filled($nfce->getRawOriginal('chave')) || filled($nfce->getRawOriginal('xml')))) {
                throw new \DomainException('NFC-e nº '.($nfce->numero ?: '—').' é documento fiscal e não pode ser excluída.');
            }
        });

        static::saved(function (self $nfce): void {
            if ($nfce->wasRecentlyCreated || $nfce->wasChanged(['status', 'numero', 'serie', 'pdv_venda_id'])) {
                try {
                    \App\Support\Erp\EstoqueMovimentacaoDocumento::sincronizarNfcePdvVenda($nfce);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'ambiente' => 'integer',
            'simulada' => 'boolean',
            'autorizada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function pdvVenda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }

    /**
     * @return array<int, string>
     */
    public static function statusesForTab(string $tab): array
    {
        return match ($tab) {
            self::TAB_TRANSMITIDOS => [self::STATUS_AUTORIZADA, self::STATUS_SIMULADA],
            self::TAB_DUPLICIDADE => [self::STATUS_DUPLICIDADE],
            self::TAB_INUTILIZADOS => [self::STATUS_INUTILIZADA],
            self::TAB_GRAVADOS => [self::STATUS_PENDENTE],
            self::TAB_CONTINGENCIA => [self::STATUS_CONTINGENCIA],
            self::TAB_CANCELADOS => [self::STATUS_CANCELADA],
            self::TAB_DENEGADO => [self::STATUS_REJEITADA, self::STATUS_DENEGADA],
            default => [self::STATUS_AUTORIZADA],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function tabLabels(): array
    {
        return [
            self::TAB_TRANSMITIDOS => 'Transmitidos',
            self::TAB_DUPLICIDADE => 'Duplicidade',
            self::TAB_INUTILIZADOS => 'Inutilizados',
            self::TAB_GRAVADOS => 'Gravados',
            self::TAB_CONTINGENCIA => 'Contingência',
            self::TAB_CANCELADOS => 'Cancelados',
            self::TAB_DENEGADO => 'Denegado',
        ];
    }

    /** Cupom simulado nunca foi à SEFAZ: só aparece em Transmitidos, nunca nas abas fiscais de pendência. */
    public static function excluirSimuladasForaTransmitidos(\Illuminate\Database\Eloquent\Builder $query, string $tab): void
    {
        if ($tab === self::TAB_TRANSMITIDOS) {
            return;
        }

        $query->where(fn ($q) => $q->where($q->getModel()->getTable().'.simulada', false)
            ->orWhereNull($q->getModel()->getTable().'.simulada'));
    }

    /** Situação exibida nas abas de pendência (Gravados, Duplicidade, Inutilizados, Denegado). */
    public function situacaoSefazLabel(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PENDENTE => 'Gravada — não confirmada na SEFAZ',
            self::STATUS_DUPLICIDADE => 'Duplicidade (539)',
            self::STATUS_INUTILIZADA => 'Número inutilizado',
            self::STATUS_DENEGADA => 'Uso denegado',
            self::STATUS_REJEITADA => 'Rejeitada',
            default => mb_convert_case((string) $this->status, MB_CASE_TITLE, 'UTF-8'),
        };
    }

    public static function normalizeTabFilter(string $filter): string
    {
        return array_key_exists($filter, self::tabLabels()) ? $filter : self::TAB_TRANSMITIDOS;
    }
}
