<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'codigo_legado',
    'data_entrada',
    'data_emissao',
    'numero',
    'chave',
    'cnpj',
    'nome',
    'nsu',
    'total',
    'xml',
    'status',
    'compra_id',
])]
class NotaFornecedor extends Model
{
    protected $table = 'notas_fornecedores';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_GEROU_COMPRAS = 'gerou_compras';

    public const STATUS_ACEITA = 'aceita';

    public const STATUS_DESCONHECIDA = 'desconhecida';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDENTE => 'Pendentes',
            self::STATUS_GEROU_COMPRAS => 'Gerou Compras',
            self::STATUS_ACEITA => 'Aceitas',
            self::STATUS_DESCONHECIDA => 'Desconhecidas',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusKeys(): array
    {
        return [
            self::STATUS_PENDENTE,
            self::STATUS_GEROU_COMPRAS,
            self::STATUS_ACEITA,
            self::STATUS_DESCONHECIDA,
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    protected function casts(): array
    {
        return [
            'codigo_legado' => 'integer',
            'data_entrada' => 'date',
            'data_emissao' => 'date',
            'total' => 'decimal:2',
        ];
    }
}
