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
    'cliente_id',
    'vendedor_id',
    'orcamento_id',
    'venda_id',
    'total',
    'status',
    'situacao',
    'erro',
    'payload',
    'client_created_at',
    'received_at',
    'pago_at',
])]
class VendasInternasOrder extends Model
{
    public const STATUS_IMPORTADO = 'importado';

    public const STATUS_ERRO = 'erro';

    public const SITUACAO_AGUARDANDO = 'aguardando';

    public const SITUACAO_NO_CAIXA = 'no_caixa';

    public const SITUACAO_PAGO = 'pago';

    public const SITUACAO_CANCELADO = 'cancelado';

    protected $table = 'vendas_internas_orders';

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'payload' => 'array',
            'client_created_at' => 'datetime',
            'received_at' => 'datetime',
            'pago_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_AGUARDANDO => 'Aguardando PDV',
            self::SITUACAO_NO_CAIXA => 'No caixa',
            self::SITUACAO_PAGO => 'Pago',
            self::SITUACAO_CANCELADO => 'Cancelado',
        ];
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? 'Aguardando PDV';
    }

    public function clienteNome(): string
    {
        $payload = $this->payload ?? [];

        return $this->cliente?->nome_razao
            ?? ($payload['cliente_nome'] ?? null)
            ?? ($this->cliente_id ? '#'.$this->cliente_id : '—');
    }

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
