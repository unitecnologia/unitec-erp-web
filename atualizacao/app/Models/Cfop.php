<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'codigo',
    'descricao',
    'tipo',
    'operacao',
    'movimenta_estoque',
    'ativo',
])]
class Cfop extends Model
{
    protected $table = 'cfops';

    public const TIPO_ENTRADA = 'E';

    public const TIPO_SAIDA = 'S';

    public const OPERACAO_INTERNA = 'I';

    public const OPERACAO_EXTERNA = 'E';

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_ENTRADA => 'E - ENTRADA',
            self::TIPO_SAIDA => 'S - SAIDA',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function operacaoLabels(): array
    {
        return [
            self::OPERACAO_INTERNA => 'DENTRO DO ESTADO',
            self::OPERACAO_EXTERNA => 'FORA DO ESTADO',
        ];
    }

    public function tipoLabel(): string
    {
        return static::tipoLabels()[$this->tipo] ?? (string) $this->tipo;
    }

    public function operacaoLabel(): string
    {
        return static::operacaoLabels()[$this->operacao] ?? (string) $this->operacao;
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeInativos(Builder $query): Builder
    {
        return $query->where('ativo', false);
    }

    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'movimenta_estoque' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
