<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class RhAnexo extends Model
{
    public const CAT_DOCUMENTO = 'documento';

    public const CAT_EPI = 'epi';

    public const CAT_EXAME = 'exame';

    public const CAT_TREINAMENTO = 'treinamento';

    public const CAT_UNIFORME = 'uniforme';

    public const CAT_HOLERITE = 'holerite';

    public const CAT_OCORRENCIA = 'ocorrencia';

    public const CAT_OUTRO = 'outro';

    protected $table = 'rh_anexos';

    protected $fillable = [
        'anexavel_type',
        'anexavel_id',
        'categoria',
        'titulo',
        'caminho',
        'mime',
        'tamanho',
        'emitido_em',
        'valido_ate',
        'entregue_em',
        'observacao',
        'ativo',
    ];

    public function anexavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): ?string
    {
        if (! filled($this->caminho)) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->caminho);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeVencendoEm(Builder $query, int $dias = 30): Builder
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays($dias);

        return $query->ativos()
            ->whereNotNull('valido_ate')
            ->whereDate('valido_ate', '>=', $hoje)
            ->whereDate('valido_ate', '<=', $limite);
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->ativos()
            ->whereNotNull('valido_ate')
            ->whereDate('valido_ate', '<', Carbon::today());
    }

    protected function casts(): array
    {
        return [
            'emitido_em' => 'date',
            'valido_ate' => 'date',
            'entregue_em' => 'date',
            'ativo' => 'boolean',
            'tamanho' => 'integer',
        ];
    }
}
