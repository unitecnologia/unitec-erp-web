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

    public const STATUS_CANCELADA = 'cancelada';

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
            self::TAB_DUPLICIDADE => ['duplicidade'],
            self::TAB_INUTILIZADOS => ['inutilizada'],
            self::TAB_GRAVADOS => [self::STATUS_PENDENTE],
            self::TAB_CONTINGENCIA => [self::STATUS_CONTINGENCIA],
            self::TAB_CANCELADOS => [self::STATUS_CANCELADA],
            self::TAB_DENEGADO => [self::STATUS_REJEITADA],
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

    public static function normalizeTabFilter(string $filter): string
    {
        return array_key_exists($filter, self::tabLabels()) ? $filter : self::TAB_TRANSMITIDOS;
    }
}
