<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComissaoPeriodo extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_FECHADA = 'fechada';

    public const STATUS_PAGA = 'paga';

    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'comissao_periodos';

    protected $fillable = [
        'empresa_id',
        'vendedor_id',
        'periodo_de',
        'periodo_ate',
        'status',
        'base_avista',
        'base_aprazo',
        'comissao_avista',
        'comissao_aprazo',
        'comissao_total',
        'percentual_av',
        'percentual_ap',
        'credor_person_id',
        'conta_pagar_id',
        'fechado_em',
        'fechado_por_user_id',
        'cancelado_em',
        'cancelado_por_user_id',
        'motivo_cancelamento',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_FECHADA => 'Fechada',
            self::STATUS_PAGA => 'Paga',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function credor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'credor_person_id');
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por_user_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_user_id');
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(ComissaoPeriodoVenda::class, 'comissao_periodo_id');
    }

    public function isAberta(): bool
    {
        return $this->status === self::STATUS_ABERTA;
    }

    public function isFechadaOuPaga(): bool
    {
        return in_array($this->status, [self::STATUS_FECHADA, self::STATUS_PAGA], true);
    }

    protected function casts(): array
    {
        return [
            'periodo_de' => 'date',
            'periodo_ate' => 'date',
            'base_avista' => 'decimal:2',
            'base_aprazo' => 'decimal:2',
            'comissao_avista' => 'decimal:2',
            'comissao_aprazo' => 'decimal:2',
            'comissao_total' => 'decimal:2',
            'percentual_av' => 'decimal:2',
            'percentual_ap' => 'decimal:2',
            'fechado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }
}
