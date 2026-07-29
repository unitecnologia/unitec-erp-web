<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'ativo', 'mostrar_no_app'])]
class Grupo extends Model
{
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'mostrar_no_app' => 'boolean',
        ];
    }
}
