<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'origem',
    'order_uuid',
    'order_id',
    'conta_receber_id',
    'venda_id',
    'provedor',
    'txid',
    'provider_ref',
    'valor',
    'status',
    'qr_copia_cola',
    'qr_imagem_base64',
    'payer_email',
    'expira_em',
    'pago_em',
    'raw',
])]
class PixCobranca extends Model
{
    public const ORIGEM_PEDIDO = 'pedido';

    public const ORIGEM_TITULO = 'titulo';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PAGO = 'pago';

    public const STATUS_EXPIRADO = 'expirado';

    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'pix_cobrancas';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'expira_em' => 'datetime',
            'pago_em' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class, 'conta_receber_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function isPago(): bool
    {
        return $this->status === self::STATUS_PAGO;
    }

    public function isPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function isExpirada(): bool
    {
        return $this->expira_em !== null
            && $this->status === self::STATUS_PENDENTE
            && $this->expira_em->isPast();
    }
}
