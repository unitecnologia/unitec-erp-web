<?php

namespace App\Models;

use App\Support\Erp\CargaNumeroService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'numero',
    'data',
    'motorista_id',
    'entregador_user_id',
    'veiculo_id',
    'status',
    'observacao',
])]
class Carga extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_FECHADA = 'fechada';

    public const STATUS_CANCELADA = 'cancelada';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_FECHADA => 'Fechada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public static function nextNumero(int $empresaId): string
    {
        // Somente preview do modal — não reserva / não avança contador.
        return (string) (app(CargaNumeroService::class)->maxNumeroExistente($empresaId) + 1);
    }

    public function isAberta(): bool
    {
        return $this->status === self::STATUS_ABERTA;
    }

    public function isFechada(): bool
    {
        return $this->status === self::STATUS_FECHADA;
    }

    public function isCancelada(): bool
    {
        return $this->status === self::STATUS_CANCELADA;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'motorista_id');
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function cargaPedidos(): HasMany
    {
        return $this->hasMany(CargaPedido::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(CargaEntrega::class);
    }

    public function pedidos(): BelongsToMany
    {
        return $this->belongsToMany(Venda::class, 'carga_pedidos', 'carga_id', 'pedido_id')
            ->withTimestamps();
    }

    /**
     * Pedidos já vinculados a cargas abertas ou fechadas (não disponíveis).
     *
     * @return Builder<Venda>
     */
    public static function pedidosOcupadosQuery(?int $exceptCargaId = null): Builder
    {
        return Venda::query()
            ->whereIn('id', function ($query) use ($exceptCargaId): void {
                $query->select('carga_pedidos.pedido_id')
                    ->from('carga_pedidos')
                    ->join('cargas', 'cargas.id', '=', 'carga_pedidos.carga_id')
                    ->whereIn('cargas.status', [self::STATUS_ABERTA, self::STATUS_FECHADA]);

                if ($exceptCargaId) {
                    $query->where('cargas.id', '!=', $exceptCargaId);
                }
            });
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }
}
