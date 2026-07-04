<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'numero',
    'serie',
    'modelo',
    'data_emissao',
    'hora_emissao',
    'data_saida',
    'hora_saida',
    'cliente_id',
    'transportadora_id',
    'venda_id',
    'pdv_venda_id',
    'chave',
    'chave_nfe_referenciada',
    'protocolo',
    'cnf',
    'xml',
    'xml_cancelamento',
    'obs_fisco',
    'obs_contribuinte',
    'total',
    'subtotal',
    'desconto',
    'frete',
    'seguro',
    'despesas',
    'outros',
    'troco',
    'base_icms',
    'total_icms',
    'base_icms_st',
    'valor_icms_st',
    'base_ipi',
    'total_ipi',
    'base_icms_pis',
    'total_icms_pis',
    'base_icms_cofins',
    'total_icms_cofins',
    'total_desoneracao',
    'vfcp',
    'vfcp_uf_dest',
    'vicms_uf_dest',
    'vicms_uf_remet',
    'trib_mun',
    'trib_est',
    'trib_fed',
    'trib_imp',
    'total_itens',
    'status',
    'situacao',
    'finalidade',
    'movimento',
    'consumidor_final',
    'tipo_emissao',
    'cfop',
    'npedido',
    'tipo_frete',
    'especie',
    'marca',
    'nvol',
    'qvol',
    'peso_b',
    'peso_l',
    'placa',
    'uf_placa',
    'rntc',
    'motivo_contingencia',
    'ind_pag',
    'tp_pag',
    'forma_pgto',
    'meio_pgto',
])]
class Nfe extends Model
{
    protected $table = 'nfes';

    public const SITUACAO_ABERTA = '1';

    public const SITUACAO_TRANSMITIDA = '2';

    public const SITUACAO_CANCELADA = '3';

    public const SITUACAO_DUPLICIDADE = '4';

    public const SITUACAO_INUTILIZADA = '5';

    public const SITUACAO_DENEGADA = '6';

    public const SITUACAO_CONTINGENCIA = '7';

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_TRANSMITIDA = 'transmitida';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_DUPLICIDADE = 'duplicidade';

    public const STATUS_INUTILIZADA = 'inutilizada';

    public const STATUS_DENEGADA = 'denegada';

    public const STATUS_CONTINGENCIA = 'contingencia';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_TRANSMITIDA => 'Transmitida',
            self::STATUS_CANCELADA => 'Cancelada',
            self::STATUS_DUPLICIDADE => 'Duplicidade',
            self::STATUS_INUTILIZADA => 'Inutilizada',
            self::STATUS_DENEGADA => 'Denegada',
            self::STATUS_CONTINGENCIA => 'Contingência',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoToStatusMap(): array
    {
        return [
            self::SITUACAO_ABERTA => self::STATUS_ABERTA,
            self::SITUACAO_TRANSMITIDA => self::STATUS_TRANSMITIDA,
            self::SITUACAO_CANCELADA => self::STATUS_CANCELADA,
            self::SITUACAO_DUPLICIDADE => self::STATUS_DUPLICIDADE,
            self::SITUACAO_INUTILIZADA => self::STATUS_INUTILIZADA,
            self::SITUACAO_DENEGADA => self::STATUS_DENEGADA,
            self::SITUACAO_CONTINGENCIA => self::STATUS_CONTINGENCIA,
        ];
    }

    public static function statusToSituacao(string $status): string
    {
        $map = array_flip(self::situacaoToStatusMap());

        return $map[$status] ?? self::SITUACAO_ABERTA;
    }

    public static function nextNumero(?int $empresaId = null): string
    {
        if ($empresaId) {
            return (string) VendasParametro::forEmpresa($empresaId)->peekNumero();
        }

        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function syncStatusFromSituacao(): void
    {
        $this->status = self::situacaoToStatusMap()[$this->situacao] ?? self::STATUS_ABERTA;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NfeItem::class)->orderBy('item');
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(NfeFatura::class)->orderBy('numero');
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(NfeReferencia::class);
    }

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_saida' => 'date',
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'frete' => 'decimal:2',
            'seguro' => 'decimal:2',
            'despesas' => 'decimal:2',
            'outros' => 'decimal:2',
            'troco' => 'decimal:2',
            'base_icms' => 'decimal:2',
            'total_icms' => 'decimal:2',
            'base_icms_st' => 'decimal:2',
            'valor_icms_st' => 'decimal:2',
            'base_ipi' => 'decimal:2',
            'total_ipi' => 'decimal:2',
            'base_icms_pis' => 'decimal:2',
            'total_icms_pis' => 'decimal:2',
            'base_icms_cofins' => 'decimal:2',
            'total_icms_cofins' => 'decimal:2',
            'total_desoneracao' => 'decimal:2',
            'vfcp' => 'decimal:2',
            'vfcp_uf_dest' => 'decimal:2',
            'vicms_uf_dest' => 'decimal:2',
            'vicms_uf_remet' => 'decimal:2',
            'trib_mun' => 'decimal:2',
            'trib_est' => 'decimal:2',
            'trib_fed' => 'decimal:2',
            'trib_imp' => 'decimal:2',
            'total_itens' => 'decimal:4',
            'peso_b' => 'decimal:3',
            'peso_l' => 'decimal:3',
        ];
    }
}
