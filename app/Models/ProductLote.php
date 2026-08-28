<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLote extends Model
{
    protected $table = 'product_lotes';

    protected $fillable = [
        'product_id',
        'lote',
        'data_validade',
        'quantidade_atual',
    ];

    protected function casts(): array
    {
        return [
            'data_validade' => 'date',
            'quantidade_atual' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function diasRestantes(): ?int
    {
        if (! $this->data_validade) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data_validade->copy()->startOfDay(), false);
    }

    /**
     * @return 'ok'|'atencao'|'critico'|'vencido'
     */
    public function situacao(int $diasAlerta = 30): string
    {
        $dias = $this->diasRestantes();
        if ($dias === null) {
            return 'ok';
        }
        if ($dias < 0) {
            return 'vencido';
        }
        if ($dias <= 7) {
            return 'critico';
        }
        if ($dias <= $diasAlerta) {
            return 'atencao';
        }

        return 'ok';
    }

    public function situacaoLabel(int $diasAlerta = 30): string
    {
        return match ($this->situacao($diasAlerta)) {
            'vencido' => 'Vencido',
            'critico' => 'Crítico',
            'atencao' => 'Atenção',
            default => 'OK',
        };
    }
}
