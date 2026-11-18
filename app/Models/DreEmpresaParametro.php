<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'exibir_tributos',
    'calcular_estimativa_simples',
    'aliquota_simples_pct',
])]
class DreEmpresaParametro extends Model
{
    protected $table = 'dre_empresa_parametros';

    protected function casts(): array
    {
        return [
            'exibir_tributos' => 'boolean',
            'calcular_estimativa_simples' => 'boolean',
            'aliquota_simples_pct' => 'decimal:4',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
