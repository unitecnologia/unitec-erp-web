<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'device_uuid',
    'user_id',
    'empresa_id',
    'tipo',
    'cliente_id',
    'vendedor_id',
    'orcamento_id',
    'venda_id',
    'total',
    'latitude',
    'longitude',
    'status',
    'situacao',
    'identificacao',
    'erro',
    'payload',
    'client_created_at',
    'received_at',
    'confirmed_at',
    'faturado_at',
    'canceled_at',
])]
class ForcaVendasOrder extends Model
{
    public const TIPO_PEDIDO = 'pedido';

    public const TIPO_ORCAMENTO = 'orcamento';

    public const STATUS_IMPORTADO = 'importado';

    public const STATUS_ERRO = 'erro';

    public const SITUACAO_PENDENTE = 'pendente';

    public const SITUACAO_CONFIRMADO = 'confirmado';

    public const SITUACAO_FATURADO = 'faturado';

    public const SITUACAO_CANCELADO = 'cancelado';

    protected $table = 'forca_vendas_orders';

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'payload' => 'array',
            'client_created_at' => 'datetime',
            'received_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'faturado_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_PENDENTE => 'Pendente',
            self::SITUACAO_CONFIRMADO => 'Confirmado',
            self::SITUACAO_FATURADO => 'Faturado',
            self::SITUACAO_CANCELADO => 'Cancelado',
        ];
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? 'Pendente';
    }

    /**
     * Cor (paleta Filament) usada no badge/linha da situação.
     */
    public function situacaoColor(): string
    {
        return match ($this->situacao) {
            self::SITUACAO_CONFIRMADO => 'info',
            self::SITUACAO_FATURADO => 'success',
            self::SITUACAO_CANCELADO => 'danger',
            default => 'warning',
        };
    }

    public function clienteNome(): string
    {
        $payload = $this->payload ?? [];

        return $this->cliente?->nome_razao
            ?? ($payload['cliente_nome'] ?? null)
            ?? ($this->cliente_id ? '#' . $this->cliente_id : '—');
    }

    /**
     * Data/hora em que a venda foi registrada no app (não a sincronização).
     */
    public function dataAberturaAt(): ?\Illuminate\Support\Carbon
    {
        return $this->client_created_at ?? $this->received_at;
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }
}
