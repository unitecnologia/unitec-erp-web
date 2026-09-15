<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ordem_servico_id',
    'tipo',
    'empresa_id',
    'usuario_id',
    'atendente_id',
    'codigo_legado',
    'item',
    'caminho',
    'mime',
    'tamanho',
])]
class OrdemServicoImagem extends Model
{
    public const TIPO_FOTO = 'foto';

    public const TIPO_ASSINATURA = 'assinatura';

    protected $table = 'ordem_servico_imagens';

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
