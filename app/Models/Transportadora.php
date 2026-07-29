<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'tipo_pessoa',
    'cnpj_cpf',
    'rg_ie',
    'cep',
    'proprietario',
    'apelido',
    'whatsapp',
    'endereco',
    'numero',
    'bairro',
    'cidade',
    'codigo_municipio',
    'uf',
    'ativo',
])]
class Transportadora extends Model
{
    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function motoristas(): HasMany
    {
        return $this->hasMany(TransportadoraMotorista::class)->orderBy('ordem');
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
