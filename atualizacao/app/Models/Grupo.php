<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'ativo', 'mostrar_no_app', 'balanca_marcado'])]
class Grupo extends Model
{
    public static function displayNome(?string $nome): string
    {
        $nome = trim((string) $nome);

        if ($nome === '') {
            return '';
        }

        return mb_strtoupper($nome, 'UTF-8');
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'mostrar_no_app' => 'boolean',
            'balanca_marcado' => 'boolean',
        ];
    }
}
