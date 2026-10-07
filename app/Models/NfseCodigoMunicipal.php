<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['codigo', 'descricao', 'ativo'])]
class NfseCodigoMunicipal extends Model
{
    protected $table = 'nfse_codigos_municipais';

    /**
     * Mesma normalização do campo c_trib_mun do produto (só letras e números).
     */
    public static function normalizarCodigo(string $codigo): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($codigo))) ?? '';
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
