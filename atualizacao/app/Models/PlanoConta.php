<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'descricao',
    'dc',
    'nivel',
    'codigo_plano',
    'pai_codigo',
    'conta_completa',
    'flag',
    'despesas',
    'compras',
    'entradas',
    'taxa_juros',
    'ativo',
])]
class PlanoConta extends Model
{
    protected $table = 'planos_contas';

    public function pagamentos(): HasMany
    {
        return $this->hasMany(ContaPagarPagamento::class, 'plano_conta_id');
    }

    public function dcLabel(): string
    {
        return match (strtoupper((string) $this->dc)) {
            'D' => 'Débito',
            'C' => 'Crédito',
            default => '—',
        };
    }

    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'nivel' => 'integer',
            'pai_codigo' => 'integer',
            'despesas' => 'boolean',
            'compras' => 'boolean',
            'entradas' => 'boolean',
            'taxa_juros' => 'decimal:4',
            'ativo' => 'boolean',
        ];
    }
}
