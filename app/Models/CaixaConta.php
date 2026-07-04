<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nome',
    'tipo',
    'situacao',
    'ativo',
    'ultimo_usuario_id',
])]
class CaixaConta extends Model
{
    public const TIPO_BANCO = 'BANCO';

    public const TIPO_CAIXA = 'CAIXA';

    public const TIPO_SUBCAIXA = 'SUBCAIXA';

    public const TIPO_COFRE = 'COFRE';

    public const SITUACAO_ABERTO = 'aberto';

    public const SITUACAO_FECHADO = 'fechado';

    protected $table = 'caixa_contas';

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_BANCO => 'BANCO',
            self::TIPO_CAIXA => 'CAIXA',
            self::TIPO_SUBCAIXA => 'SUBCAIXA',
            self::TIPO_COFRE => 'COFRE',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tiposValidos(): array
    {
        return array_keys(self::tipoLabels());
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_ABERTO => 'ABERTO',
            self::SITUACAO_FECHADO => 'FECHADO',
        ];
    }

    public function tipoLabel(): string
    {
        return self::tipoLabels()[$this->tipo] ?? mb_strtoupper((string) $this->tipo, 'UTF-8');
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? mb_strtoupper((string) $this->situacao, 'UTF-8');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(CaixaLancamento::class);
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_usuario_id');
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
