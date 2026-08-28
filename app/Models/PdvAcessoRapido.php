<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvAcessoRapido extends Model
{
    protected $table = 'pdv_acesso_rapido';

    protected $fillable = [
        'terminal_id',
        'empresa_id',
        'slots_count',
        'itens',
    ];

    protected function casts(): array
    {
        return [
            'slots_count' => 'integer',
            'itens' => 'array',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return list<array{pos: int, product_id: int}>
     */
    public function itensNormalizados(): array
    {
        $out = [];

        foreach (is_array($this->itens) ? $this->itens : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pos = (int) ($row['pos'] ?? -1);
            $productId = (int) ($row['product_id'] ?? 0);

            if ($pos < 0 || $productId <= 0) {
                continue;
            }

            $out[] = [
                'pos' => $pos,
                'product_id' => $productId,
            ];
        }

        return $out;
    }
}
