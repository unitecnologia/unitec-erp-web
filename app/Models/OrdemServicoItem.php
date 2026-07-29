<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ordem_servico_id', 'codigo_legado', 'funcionario_id', 'product_id', 'usuario_id', 'empresa_id',
    'tipo', 'situacao', 'discriminacao',
    'data_inicio', 'hora_inicio', 'data_termino', 'hora_termino',
    'qtd', 'preco', 'total',
    'cor', 'tamanho', 'detalhe', 'nome', 'numero', 'grade_legado',
])]
class OrdemServicoItem extends Model
{
    protected $table = 'ordem_servico_itens';

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'funcionario_id');
    }

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_termino' => 'date',
            'qtd' => 'decimal:3',
            'preco' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }
}
