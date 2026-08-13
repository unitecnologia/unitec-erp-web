<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero',
    'venda_id',
    'cliente_id',
    'cliente_nome',
    'cliente_telefone',
    'endereco_cep',
    'endereco_logradouro',
    'endereco_numero',
    'endereco_complemento',
    'endereco_bairro',
    'endereco_cidade',
    'endereco_uf',
    'endereco_completo',
    'status',
    'origem',
    'usuario_expedicao_id',
    'observacoes',
    'entregador_id',
    'logistica_carga_id',
    'finalizado_em',
    'expedido_em',
    'tipo_saida',
    'transportadora_id',
    'qtd_volumes',
    'peso_calculado_kg',
    'romaneio_retirada_emitido_em',
])]
class Entrega extends Model
{
    public const TIPO_SAIDA_ENTREGA = 'entrega';

    public const TIPO_SAIDA_RETIRADA = 'retirada';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_EM_EXPEDICAO = 'em_expedicao';

    public const STATUS_EXPEDIDO = 'expedido';

    public const STATUS_CANCELADO = 'cancelado';

    public const ORIGEM_PDV = 'pdv';

    public const ORIGEM_MONITOR = 'monitor';

    public const ORIGEM_VI = 'vendas_internas';

    public const ORIGEM_ERP = 'erp';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_EM_EXPEDICAO => 'Em expedição',
            self::STATUS_EXPEDIDO => 'Expedido',
            self::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusControleFiltro(string $filtro): array
    {
        return match ($filtro) {
            'pendentes' => [self::STATUS_PENDENTE, self::STATUS_EM_EXPEDICAO],
            'expedidos' => [self::STATUS_EXPEDIDO],
            default => [
                self::STATUS_PENDENTE,
                self::STATUS_EM_EXPEDICAO,
                self::STATUS_EXPEDIDO,
            ],
        };
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? mb_strtoupper((string) $this->status, 'UTF-8');
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function usuarioExpedicao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_expedicao_id');
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(Entregador::class);
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(LogisticaCarga::class, 'logistica_carga_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'transportadora_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EntregaItem::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EntregaEvento::class)->orderByDesc('created_at');
    }

    public function estaCompleta(): bool
    {
        $this->loadMissing('itens');

        if ($this->itens->isEmpty()) {
            return true;
        }

        return $this->itens->every(
            fn (EntregaItem $item): bool => (float) $item->quantidade_expedida >= (float) $item->quantidade_pedida
        );
    }

    protected function casts(): array
    {
        return [
            'finalizado_em' => 'datetime',
            'expedido_em' => 'datetime',
            'qtd_volumes' => 'integer',
            'peso_calculado_kg' => 'decimal:3',
            'romaneio_retirada_emitido_em' => 'datetime',
        ];
    }
}
