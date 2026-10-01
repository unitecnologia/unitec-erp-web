<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'carga_id',
    'pedido_id',
    'entregador_user_id',
    'app_local_uuid',
    'status',
    'motivo_nao_entrega',
    'observacao',
    'foto_path',
    'assinatura_path',
    'concluida_em',
])]
class CargaEntrega extends Model
{
    public const STATUS_ENTREGUE = 'entregue';

    public const STATUS_PARCIAL = 'parcial';

    public const STATUS_NAO_ENTREGUE = 'nao_entregue';

    public const MOTIVO_CLIENTE_FECHADO = 'cliente_fechado';

    public const MOTIVO_CLIENTE_AUSENTE = 'cliente_ausente';

    public const MOTIVO_RECUSOU = 'recusou_mercadoria';

    public const MOTIVO_ENDERECO = 'endereco_nao_localizado';

    public const MOTIVO_PROBLEMA = 'problema_no_pedido';

    public const MOTIVO_OUTRO = 'outro';

    /**
     * @return array<string, string>
     */
    public static function motivosNaoEntrega(): array
    {
        return [
            self::MOTIVO_CLIENTE_FECHADO => 'Cliente fechado',
            self::MOTIVO_CLIENTE_AUSENTE => 'Cliente ausente',
            self::MOTIVO_RECUSOU => 'Recusou mercadoria',
            self::MOTIVO_ENDERECO => 'Endereço não localizado',
            self::MOTIVO_PROBLEMA => 'Problema no pedido',
            self::MOTIVO_OUTRO => 'Outro',
        ];
    }

    protected $table = 'carga_entregas';

    protected function casts(): array
    {
        return [
            'concluida_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'pedido_id');
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    public function itens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargaEntregaItem::class, 'carga_entrega_id');
    }
}
